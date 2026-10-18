<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class GuruController extends Controller
{
    public function dashboard()
    {
        $guru = Auth::user();

        // Ambil data subjects beserta jumlah exams langsung dari query (menghindari error unserialize Eloquent Collection)
        $subjects = Subject::select('id', 'title', 'code', 'icon')
            ->withCount(['exams'])
            ->get();

        // ✅ Batasi kolom: hanya kolom yang dipakai untuk filter grade & tampil di view
        $students = \App\Models\User::where('role', 'murid')
            ->select('id', 'name', 'grade_level')
            ->get();

        // ✅ Eager loading spesifik: batasi kolom di setiap relasi
        // - subject: hanya id, title, code
        // - questions: hanya id & exam_id (untuk count)
        // - attempts: hanya kolom yang dibutuhkan + user (id, name, grade_level)
        $exams = Exam::with([
                'subject:id,title,code',
                'questions:id,exam_id',
                'attempts' => fn ($q) => $q
                    ->select('id', 'exam_id', 'user_id', 'score', 'correct_answers', 'total_questions', 'time_taken_seconds', 'status', 'completed_at', 'created_at')
                    ->with('user:id,name,grade_level'),
            ])
            ->select('id', 'subject_id', 'title', 'duration_minutes', 'passing_score', 'grade_level', 'status', 'allow_repeat', 'created_at')
            ->withCount(['attempts'])
            ->get()
            ->map(function ($exam) use ($students) {
                // Ambil 1 attempt terbaru per user (tanpa duplikasi)
                $attemptsByUser = $exam->attempts->sortByDesc('created_at')->unique('user_id');
                $completedUserIds = $attemptsByUser->pluck('user_id')->toArray();

                // Filter murid sesuai grade_level ujian
                $targetStudents = $students->filter(function ($student) use ($exam) {
                    if (!$exam->grade_level || $exam->grade_level === 'ALL') {
                        return true;
                    }
                    return $student->grade_level === $exam->grade_level;
                });

                $exam->completed_students = $attemptsByUser->values();
                $exam->pending_students = $targetStudents->reject(function ($student) use ($completedUserIds) {
                    return in_array($student->id, $completedUserIds);
                })->values();

                return $exam;
            });

        // ✅ Eager load dengan kolom spesifik, batasi 15 record terakhir
        $attempts = ExamAttempt::with([
                'user:id,name',
                'exam:id,title,subject_id',
                'exam.subject:id,title,code',
            ])
            ->select('id', 'user_id', 'exam_id', 'score', 'correct_answers', 'total_questions', 'status', 'completed_at', 'created_at')
            ->latest()
            ->take(15)
            ->get();

        // ✅ Cache statistik agregat 2 menit (data ini berubah saat siswa submit)
        $totalQuestions = Cache::driver('file')->remember('total_questions_count', 120, fn () => Question::count());
        $totalAttempts  = Cache::driver('file')->remember('total_attempts_count', 120, fn () => ExamAttempt::count());
        $averageScore   = Cache::driver('file')->remember('average_score', 120, fn () => round(ExamAttempt::avg('score') ?? 0, 1));

        return view('guru.dashboard', compact(
            'subjects', 'exams', 'attempts', 'students',
            'totalQuestions', 'totalAttempts', 'averageScore'
        ));
    }

    public function storeExam(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'passing_score' => 'required|integer|min:0|max:100',
            'grade_level' => 'required|in:SD Kelas 4,SD Kelas 5,SMP Kelas 7,SMP Kelas 8',
        ]);

        $exam = Exam::create([
            'subject_id'       => $request->subject_id,
            'title'            => $request->title,
            'duration_minutes' => $request->duration_minutes,
            'passing_score'    => $request->passing_score,
            'grade_level'      => $request->grade_level,
            'allow_repeat'     => $request->boolean('allow_repeat'),
            'created_by'       => Auth::id() ?? 1,
            'status'           => 'active',
        ]);

        // ✅ Invalidasi cache yang berkaitan dengan exam baru
        Cache::driver('file')->forget('subjects_with_exam_count');
        Cache::driver('file')->forget('admin_exams_list');

        return redirect()->route('guru.dashboard')->with('success', 'Paket Ujian berhasil ditambahkan!');
    }

    public function storeQuestion(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'type' => 'nullable|in:multiple_choice,matching,ordering,true_false_table,multiple_select',
            'question_text' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:500',
            'explanation' => 'nullable|string',
            'cognitive_level' => 'nullable|in:C1,C2,C3,C4',
            'bobot' => 'nullable|integer|min:1|max:100',
        ]);

        $type = $request->input('type', 'multiple_choice');

        $data = [
            'exam_id'         => $request->exam_id,
            'materi'          => $request->materi,
            'indikator'       => $request->indikator,
            'cognitive_level' => $request->cognitive_level,
            'bobot'           => $request->input('bobot', 1),
            'type'            => $type,
            'question_text'   => $request->question_text,
            'explanation'     => $request->explanation,
        ];

        // Handle question image upload (max 500kb)
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('questions', 'public');
        }

        if ($type === 'matching') {
            $data['pair_data'] = array_values(array_filter($request->input('pair_data', []), function ($pair) {
                return !empty($pair['left']) && !empty($pair['right']);
            }));
        } elseif ($type === 'ordering') {
            $data['sequence_data'] = array_values(array_filter($request->input('sequence_data', []), function ($item) {
                return !empty(trim($item));
            }));
        } elseif ($type === 'true_false_table') {
            // pair_data: [{"statement": "...", "answer": "benar|salah"}, ...]
            $data['pair_data'] = array_values(array_filter($request->input('tft_data', []), function ($row) {
                return !empty(trim($row['statement'] ?? ''));
            }));
        } elseif ($type === 'multiple_select') {
            // Pilihan ganda kompleks: options reuse option_a/b/c/d
            $data['option_a'] = $request->option_a;
            $data['option_b'] = $request->option_b;
            $data['option_c'] = $request->option_c;
            $data['option_d'] = ($request->input('option_count') == '3' || empty(trim($request->option_d ?? ''))) ? null : $request->option_d;

            $validOpts = !empty($data['option_d']) ? ['a','b','c','d'] : ['a','b','c'];
            $correctOptions = array_values(array_filter(
                array_map('strtolower', (array) $request->input('correct_options', [])),
                fn($v) => in_array($v, $validOpts)
            ));
            sort($correctOptions);
            $data['sequence_data'] = $correctOptions;
        } else {
            $data['option_a'] = $request->option_a;
            $data['option_b'] = $request->option_b;
            $data['option_c'] = $request->option_c;
            $data['option_d'] = ($request->input('option_count') == '3' || empty(trim($request->option_d ?? ''))) ? null : $request->option_d;

            $correctOpt = strtolower($request->correct_option ?? 'a');
            if (empty($data['option_d']) && $correctOpt === 'd') {
                $correctOpt = 'a';
            }
            $data['correct_option'] = $correctOpt;

            // Build wrong_answer_data from per-option error/diagnosis/treatment fields
            $activeOpts = !empty($data['option_d']) ? ['a', 'b', 'c', 'd'] : ['a', 'b', 'c'];
            $wrongAnswerData = [];
            foreach ($activeOpts as $opt) {
                if ($opt === $correctOpt) continue;
                $ep = $request->input("error_pattern_{$opt}");
                $diag = $request->input("diagnosis_{$opt}");
                $treat = $request->input("treatment_{$opt}");
                if (!empty($ep) || !empty($diag) || !empty($treat)) {
                    $wrongAnswerData[$opt] = [
                        'error_pattern' => $ep,
                        'diagnosis'     => $diag,
                        'treatment'     => $treat,
                    ];
                }
            }
            $data['wrong_answer_data'] = !empty($wrongAnswerData) ? $wrongAnswerData : null;
        }

        Question::create($data);

        // Update total questions count on Exam
        $exam = Exam::find($request->exam_id);
        $exam->update(['total_questions' => $exam->questions()->count()]);

        // ✅ Invalidasi cache statistik soal
        Cache::driver('file')->forget('total_questions_count');

        return redirect()->route('guru.dashboard')->with('success', 'Soal & Pembahasan berhasil ditambahkan!');
    }

    public function updateQuestion(Request $request, Question $question)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'type' => 'nullable|in:multiple_choice,matching,ordering,true_false_table,multiple_select',
            'question_text' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:500',
            'explanation' => 'nullable|string',
            'cognitive_level' => 'nullable|in:C1,C2,C3,C4',
            'bobot' => 'nullable|integer|min:1|max:100',
        ]);

        $type = $request->input('type', $question->type ?: 'multiple_choice');
        $oldExamId = $question->exam_id;

        $data = [
            'exam_id'         => $request->exam_id,
            'materi'          => $request->materi,
            'indikator'       => $request->indikator,
            'cognitive_level' => $request->cognitive_level,
            'bobot'           => $request->input('bobot', 1),
            'type'            => $type,
            'question_text'   => $request->question_text,
            'explanation'     => $request->explanation,
        ];

        // Handle image replacement or removal
        if ($request->boolean('remove_image')) {
            if ($question->image && Storage::disk('public')->exists($question->image)) {
                Storage::disk('public')->delete($question->image);
            }
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            if ($question->image && Storage::disk('public')->exists($question->image)) {
                Storage::disk('public')->delete($question->image);
            }
            $data['image'] = $request->file('image')->store('questions', 'public');
        }

        if ($type === 'matching') {
            $data['pair_data'] = array_values(array_filter($request->input('pair_data', []), function ($pair) {
                return !empty($pair['left']) && !empty($pair['right']);
            }));
            $data['sequence_data']     = null;
            $data['wrong_answer_data'] = null;
        } elseif ($type === 'ordering') {
            $data['sequence_data'] = array_values(array_filter($request->input('sequence_data', []), function ($item) {
                return !empty(trim($item));
            }));
            $data['pair_data']         = null;
            $data['wrong_answer_data'] = null;
        } elseif ($type === 'true_false_table') {
            // pair_data: [{"statement": "...", "answer": "benar|salah"}, ...]
            $data['pair_data'] = array_values(array_filter($request->input('tft_data', []), function ($row) {
                return !empty(trim($row['statement'] ?? ''));
            }));
            $data['sequence_data']     = null;
            $data['wrong_answer_data'] = null;
        } elseif ($type === 'multiple_select') {
            $data['option_a'] = $request->option_a;
            $data['option_b'] = $request->option_b;
            $data['option_c'] = $request->option_c;
            $data['option_d'] = ($request->input('option_count') == '3' || empty(trim($request->option_d ?? ''))) ? null : $request->option_d;

            $validOpts = !empty($data['option_d']) ? ['a','b','c','d'] : ['a','b','c'];
            $correctOptions = array_values(array_filter(
                array_map('strtolower', (array) $request->input('correct_options', [])),
                fn($v) => in_array($v, $validOpts)
            ));
            sort($correctOptions);
            $data['sequence_data']     = $correctOptions;
            $data['pair_data']         = null;
            $data['wrong_answer_data'] = null;
        } else {
            $data['option_a'] = $request->option_a;
            $data['option_b'] = $request->option_b;
            $data['option_c'] = $request->option_c;
            $data['option_d'] = ($request->input('option_count') == '3' || empty(trim($request->option_d ?? ''))) ? null : $request->option_d;

            $correctOpt = strtolower($request->correct_option ?? 'a');
            if (empty($data['option_d']) && $correctOpt === 'd') {
                $correctOpt = 'a';
            }
            $data['correct_option']    = $correctOpt;
            $data['pair_data']         = null;
            $data['sequence_data']     = null;

            // Build wrong_answer_data
            $activeOpts = !empty($data['option_d']) ? ['a', 'b', 'c', 'd'] : ['a', 'b', 'c'];
            $wrongAnswerData = [];
            foreach ($activeOpts as $opt) {
                if ($opt === $correctOpt) continue;
                $ep = $request->input("error_pattern_{$opt}");
                $diag = $request->input("diagnosis_{$opt}");
                $treat = $request->input("treatment_{$opt}");
                if (!empty($ep) || !empty($diag) || !empty($treat)) {
                    $wrongAnswerData[$opt] = [
                        'error_pattern' => $ep,
                        'diagnosis'     => $diag,
                        'treatment'     => $treat,
                    ];
                }
            }
            $data['wrong_answer_data'] = !empty($wrongAnswerData) ? $wrongAnswerData : null;
        }

        $question->update($data);

        if ($oldExamId != $request->exam_id) {
            $oldExam = Exam::find($oldExamId);
            if ($oldExam) {
                $oldExam->update(['total_questions' => $oldExam->questions()->count()]);
            }
            $newExam = Exam::find($request->exam_id);
            if ($newExam) {
                $newExam->update(['total_questions' => $newExam->questions()->count()]);
            }
        }

        return redirect()->route('guru.dashboard')->with('success', 'Soal & Pembahasan berhasil diperbarui!');
    }

    public function destroyQuestion(Question $question)
    {
        $exam = $question->exam;
        if ($question->image && Storage::disk('public')->exists($question->image)) {
            Storage::disk('public')->delete($question->image);
        }
        $question->delete();
        if ($exam) {
            $exam->update(['total_questions' => $exam->questions()->count()]);
        }

        return redirect()->route('guru.dashboard')->with('success', 'Soal berhasil dihapus.');
    }

    public function destroyExam(Exam $exam)
    {
        $exam->delete();
        return redirect()->route('guru.dashboard')->with('success', 'Paket Ujian berhasil dihapus.');
    }

    public function importExamFromPdf(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'passing_score' => 'required|integer|min:0|max:100',
            'grade_level' => 'required|in:SD Kelas 4,SD Kelas 5,SMP Kelas 7,SMP Kelas 8',
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $pdfFile = $request->file('pdf_file');

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfFile->getPathname());
            $text = $pdf->getText();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membaca file PDF: ' . $e->getMessage());
        }

        $questionsData = $this->parsePdfTextToQuestions($text);

        if (empty($questionsData)) {
            return redirect()->back()->with('error', 'Tidak dapat mengenali format soal pada file PDF. Pastikan file PDF memuat teks soal bertipe pilihan ganda (misal: 1. Pertanyaan... A. Opsi A B. Opsi B ... Kunci: A).');
        }

        $exam = Exam::create([
            'subject_id' => $request->subject_id,
            'title' => $request->title,
            'duration_minutes' => $request->duration_minutes,
            'passing_score' => $request->passing_score,
            'grade_level' => $request->grade_level,
            'allow_repeat' => $request->boolean('allow_repeat'),
            'total_questions' => count($questionsData),
            'created_by' => Auth::id() ?? 1,
            'status' => 'active',
        ]);

        foreach ($questionsData as $q) {
            Question::create([
                'exam_id' => $exam->id,
                'materi' => $q['materi'] ?? null,
                'indikator' => $q['indikator'] ?? null,
                'cognitive_level' => $q['cognitive_level'] ?? null,
                'bobot' => $q['bobot'] ?? 1,
                'type' => $q['type'] ?? 'multiple_choice',
                'question_text' => $q['question_text'],
                'option_a' => $q['option_a'],
                'option_b' => $q['option_b'],
                'option_c' => $q['option_c'],
                'option_d' => $q['option_d'],
                'correct_option' => $q['correct_option'],
                'explanation' => $q['explanation'],
                'pair_data' => $q['pair_data'] ?? null,
                'sequence_data' => $q['sequence_data'] ?? null,
                'wrong_answer_data' => $q['wrong_answer_data'] ?? null,
            ]);
        }

        Cache::driver('file')->forget('total_questions_count');

        return redirect()->route('guru.dashboard')->with('success', 'Paket Ujian berhasil dibuat dari file PDF! (' . count($questionsData) . ' soal terimpor)');
    }

    public function downloadTemplate()
    {
        $wordHtml = "<html xmlns:o='urn:schemas-microsoft-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>\n" .
            "<head><meta charset='utf-8'><title>Template & Panduan Naskah Bank Soal</title>\n" .
            "<style>\n" .
            "body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #1e293b; margin: 25px; }\n" .
            "h2 { color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 4px; margin-top: 20px; }\n" .
            "h3 { color: #0369a1; margin-top: 15px; }\n" .
            ".info-box { background: #f0fdf4; border: 1px solid #86efac; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 10pt; }\n" .
            ".code { font-family: 'Courier New', monospace; background: #f1f5f9; padding: 2px 4px; }\n" .
            "</style></head>\n" .
            "<body>\n" .
            "<h2>PANDUAN & TEMPLATE NASKAH BANK SOAL</h2>\n" .
            "<div class='info-box'>\n" .
            "<b>💡 Petunjuk Penggunaan:</b><br/>\n" .
            "1. Sistem mendukung 5 model soal: Pilihan Ganda (multiple_choice), Menjodohkan (matching), Susun Urutan (ordering), Tabel Benar/Salah (true_false_table), dan Pilihan Ganda Kompleks (multiple_select).<br/>\n" .
            "2. Anda dapat menambahkan <b>Gambar pada Soal</b> dengan ukuran maksimal <b>500 KB</b> (Format: JPG, PNG, WebP) langsung melalui dashboard portal guru.<br/>\n" .
            "3. Simpan file ini dan export ke format <b>PDF</b> untuk melakukan import otomatis ke paket ujian.\n" .
            "</div>\n" .
            "<hr/>\n" .
            "<h3>CONTOH 1: Pilihan Ganda Biasa (1 Jawaban Benar)</h3>\n" .
            "1. Hasil dari 15 + 25 x 2 adalah...<br/>\n" .
            "Materi: Operasi Hitung Campuran<br/>\n" .
            "Indikator: Siswa dapat menghitung perkalian dan penjumlahan<br/>\n" .
            "Level: C3<br/>\n" .
            "Bobot: 1<br/>\n" .
            "Tipe: multiple_choice<br/>\n" .
            "a. 80<br/>\n" .
            "b. 65<br/>\n" .
            "c. 55<br/>\n" .
            "d. 70<br/>\n" .
            "Kunci: B<br/>\n" .
            "Error A: Penjumlahan didahulukan daripada perkalian<br/>\n" .
            "Diagnosis A: Siswa belum paham prioritas operasi hitung<br/>\n" .
            "Treatment A: Latihan hirarki operasi hitung (KABATAKU)<br/>\n" .
            "Pembahasan: Dahulukan perkalian 25 x 2 = 50. Lalu 15 + 50 = 65.<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 2: Menjodohkan / Pasangan (Matching)</h3>\n" .
            "2. Jodohkan nama bangun datar di sebelah kiri dengan rumus luasnya di sebelah kanan:<br/>\n" .
            "Materi: Geometri Bangun Datar<br/>\n" .
            "Indikator: Menjodohkan rumus bangun datar<br/>\n" .
            "Level: C2<br/>\n" .
            "Bobot: 2<br/>\n" .
            "Tipe: matching<br/>\n" .
            "Persegi => s x s<br/>\n" .
            "Persegi Panjang => p x l<br/>\n" .
            "Segitiga => 1/2 x a x t<br/>\n" .
            "Lingkaran => π x r²<br/>\n" .
            "Pembahasan: Pasangan rumus luas bangun datar sesuai definisi geometri.<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 3: Susun Urutan (Ordering)</h3>\n" .
            "3. Urutkan tahapan metamorfosis kupu-kupu secara kronologis dari awal hingga akhir:<br/>\n" .
            "Materi: Daur Hidup Hewan<br/>\n" .
            "Indikator: Mengurutkan daur hidup kupu-kupu<br/>\n" .
            "Level: C2<br/>\n" .
            "Bobot: 2<br/>\n" .
            "Tipe: ordering<br/>\n" .
            "1. Telur<br/>\n" .
            "2. Ulat (Larva)<br/>\n" .
            "3. Kepompong (Pupa)<br/>\n" .
            "4. Kupu-kupu Dewasa (Imago)<br/>\n" .
            "Pembahasan: Daur hidup dimulai dari telur, menetas menjadi ulat, membungkus diri menjadi pupa/kepompong, lalu menjadi kupu-kupu dewasa.<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 4: Tabel Benar / Salah (True/False Table)</h3>\n" .
            "4. Berdasarkan situasi tersebut, tentukan Benar atau Salah untuk setiap pernyataan berikut:<br/>\n" .
            "Materi: Aritmatika Sosial<br/>\n" .
            "Indikator: Menilai kebenaran pernyataan matematis<br/>\n" .
            "Level: C3<br/>\n" .
            "Bobot: 2<br/>\n" .
            "Tipe: true_false_table<br/>\n" .
            "A. Sisa pekerjaan setelah hari ke-10 setara dengan 800 hari kerja 1 orang. => Benar<br/>\n" .
            "B. Selama 10 hari pertama proyek mengalami keterlambatan 5 orang. => Salah<br/>\n" .
            "C. Manajer cukup mempekerjakan kembali 15 orang tanpa pekerja tambahan. => Salah<br/>\n" .
            "Pembahasan: Analisis perhitungan beban hari kerja menunjukkan pernyataan A benar, sedangkan B dan C tidak tepat.<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 5: Pilihan Ganda Kompleks (Jawaban Benar Lebih Dari Satu)</h3>\n" .
            "5. Manakah dari bilangan berikut yang merupakan bilangan prima kurang dari 20? (Pilih semua yang benar)<br/>\n" .
            "Materi: Bilangan Prima<br/>\n" .
            "Indikator: Mengidentifikasi bilangan prima<br/>\n" .
            "Level: C2<br/>\n" .
            "Bobot: 2<br/>\n" .
            "Tipe: multiple_select<br/>\n" .
            "a. 2<br/>\n" .
            "b. 9<br/>\n" .
            "c. 13<br/>\n" .
            "d. 17<br/>\n" .
            "Kunci: A, C, D<br/>\n" .
            "Pembahasan: 2, 13, dan 17 hanya memiliki 2 faktor (1 dan dirinya sendiri), sedangkan 9 memiliki 3 faktor (1, 3, 9) sehingga bukan prima.<br/>\n" .
            "</body></html>";

        return response($wordHtml, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Template_BankSoal_PDF.doc"',
        ]);
    }

    public function parsePdfTextToQuestions(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Extract Global Answer Key table if present (e.g. "KUNCI JAWABAN: 1. A 2. B 3. C")
        $globalKeys = [];
        // Fixed regex: explicitly require JAWABAN or DAFTAR or ANSWER KEY to avoid matching individual "Kunci: A"
        if (preg_match('/(?:KUNCI JAWABAN|DAFTAR KUNCI|ANSWER KEY)\s*:?\s*\n?(.*)/is', $text, $mGlobal, PREG_OFFSET_CAPTURE)) {
            $globalSection = $mGlobal[1][0];
            if (preg_match_all('/(?:Soal\s*)?(\d+)[\.\)]\s*:?\s*\(?\s*([a-d])\s*\)?/i', $globalSection, $mMatches, PREG_SET_ORDER)) {
                foreach ($mMatches as $m) {
                    $globalKeys[(int)$m[1]] = strtolower($m[2]);
                }
            }
            // Strip global key section from main text so it isn't parsed as question blocks
            $text = substr($text, 0, $mGlobal[0][1]);
        }

        // Split text line by line to locate question headers
        $lines = explode("\n", $text);
        $blocks = [];
        $currentBlock = [];
        $questionNumber = 0;
        $questionNumbers = [];
        $lastQuestionNumber = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // Reset question counter if a new section starts (e.g., "BAGIAN 2:", "BAGIAN A:")
            if (preg_match('/^(?:BAGIAN|SECTION|BAG)[\s:]*[A-Z0-9]+/i', $trimmed)) {
                $lastQuestionNumber = 0;
                if (!empty($currentBlock)) {
                    $currentBlock[] = $trimmed;
                }
                continue;
            }

            // Check if line starts with question number: e.g. "1.", "2)", "Soal 1:"
            if (preg_match('/^(?:Soal\s*)?(\d+)[\.\)]\s+(.*)/i', $trimmed, $m)) {
                $qNum = (int)$m[1];
                $isExplicitSoal = preg_match('/^Soal\s+\d+/i', $trimmed);

                // Start a new block if this looks like a new question (incrementing sequence, or explicitly "Soal X")
                if ($isExplicitSoal || $lastQuestionNumber === 0 || ($qNum > $lastQuestionNumber && $qNum <= $lastQuestionNumber + 10)) {
                    if (!empty($currentBlock)) {
                        $blocks[] = implode("\n", $currentBlock);
                        $currentBlock = [];
                    }
                    $questionNumber = $qNum;
                    $lastQuestionNumber = $qNum;
                    $questionNumbers[] = $questionNumber;
                    $currentBlock[] = $m[2];
                    continue;
                }
            }

            if (!empty($currentBlock)) {
                $currentBlock[] = $trimmed;
            }
        }

        if (!empty($currentBlock)) {
            $blocks[] = implode("\n", $currentBlock);
        }

        $questions = [];
        foreach ($blocks as $idx => $block) {
            $qNum = $questionNumbers[$idx] ?? ($idx + 1);
            $gKey = $globalKeys[$qNum] ?? null;
            $parsed = $this->formatParsedQuestionBlock($block, $gKey);
            if ($parsed) {
                $questions[] = $parsed;
            }
        }

        // Fallback if blocks split didn't capture questions
        if (empty($questions)) {
            $pattern = '/(?:\n+|\A)(?:Soal\s*)?(\d+)[\.\)]\s*/i';
            $splitBlocks = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            if (count($splitBlocks) >= 2) {
                for ($i = 0; $i < count($splitBlocks); $i++) {
                    $item = trim($splitBlocks[$i]);
                    if (is_numeric($item)) {
                        $qNum = (int)$item;
                        $content = $splitBlocks[$i + 1] ?? '';
                        $i++;
                        $gKey = $globalKeys[$qNum] ?? null;
                        $parsed = $this->formatParsedQuestionBlock($content, $gKey);
                        if ($parsed) {
                            $questions[] = $parsed;
                        }
                    }
                }
            }
        }

        return $questions;
    }

    private function formatParsedQuestionBlock(string $block, ?string $globalKey = null): ?array
    {
        $block = trim($block);
        if (empty($block)) {
            return null;
        }

        // 1. Extract Explanation / Pembahasan
        $explanation = null;
        if (preg_match('/(?:Pembahasan|Penjelasan|Explanation)\s*:\s*(.+)/is', $block, $mExp, PREG_OFFSET_CAPTURE)) {
            $explanation = trim($mExp[1][0]);
            $block = trim(substr($block, 0, $mExp[0][1]));
        }

        // 2. Extract new metadata using regex replacements to remove them from block
        $materi = null;
        if (preg_match('/Materi\s*:\s*(.+?)(?=\n|$)/i', $block, $m)) {
            $materi = trim($m[1]);
            $block = str_ireplace($m[0], '', $block);
        }

        $indikator = null;
        if (preg_match('/Indikator\s*:\s*(.+?)(?=\n|$)/i', $block, $m)) {
            $indikator = trim($m[1]);
            $block = str_ireplace($m[0], '', $block);
        }

        $cognitive_level = null;
        if (preg_match('/Level\s*:\s*(C[1-4])(?=\n|$)/i', $block, $m)) {
            $cognitive_level = strtoupper(trim($m[1]));
            $block = str_ireplace($m[0], '', $block);
        }

        $bobot = 1;
        if (preg_match('/Bobot\s*:\s*(\d+)(?=\n|$)/i', $block, $m)) {
            $bobot = (int)$m[1];
            $block = str_ireplace($m[0], '', $block);
        }

        $type = 'multiple_choice';
        if (preg_match('/Tipe\s*:\s*(multiple_choice|matching|ordering)(?=\n|$)/i', $block, $m)) {
            $type = strtolower(trim($m[1]));
            $block = str_ireplace($m[0], '', $block);
        }

        $wrongAnswerData = [];
        foreach(['A', 'B', 'C', 'D'] as $opt) {
            if (preg_match('/Error\s+'.$opt.'\s*:\s*(.+?)(?=\n|$)/i', $block, $m)) {
                $wrongAnswerData[strtolower($opt)]['error_pattern'] = trim($m[1]);
                $block = str_ireplace($m[0], '', $block);
            }
            if (preg_match('/Diagnosis\s+'.$opt.'\s*:\s*(.+?)(?=\n|$)/i', $block, $m)) {
                $wrongAnswerData[strtolower($opt)]['diagnosis'] = trim($m[1]);
                $block = str_ireplace($m[0], '', $block);
            }
            if (preg_match('/Treatment\s+'.$opt.'\s*:\s*(.+?)(?=\n|$)/i', $block, $m)) {
                $wrongAnswerData[strtolower($opt)]['treatment'] = trim($m[1]);
                $block = str_ireplace($m[0], '', $block);
            }
        }

        $pairData = [];
        $sequenceData = [];
        
        $block = trim($block);
        
        // 3. Answer Key
        $correctOpt = $globalKey;
        $answerText = null;
        $hasKeyFromPdf = !empty($globalKey);

        if (!$correctOpt) {
            if (preg_match('/(?:Kunci\s*Jawaban|Kunci|Jawaban\s*Benar|Jawaban|Jwb|Key|Ans)\s*:?\s*(?:Benar\s*)?\n?\s*(?:\(?|\[?)\s*([a-d])\s*(?:\)?|\]?)(?:\.|\s|\n|$)/i', $block, $mKey, PREG_OFFSET_CAPTURE)) {
                $correctOpt = strtolower($mKey[1][0]);
                $hasKeyFromPdf = true;
                $block = trim(substr($block, 0, $mKey[0][1]));
            } elseif (preg_match('/(?:Kunci\s*Jawaban|Kunci|Jawaban\s*Benar|Jawaban|Jwb|Key|Ans)\s*:\s*(.+)/i', $block, $mVal, PREG_OFFSET_CAPTURE)) {
                $answerText = trim($mVal[1][0]);
                $block = trim(substr($block, 0, $mVal[0][1]));
            }
        }

        // 4. Extract Questions, Options, Matches, Sequences
        $optA = null; $optB = null; $optC = null; $optD = null;
        $questionText = $block;

        if ($type === 'matching') {
            // Find lines with "="
            $lines = explode("\n", $block);
            $qLines = [];
            foreach($lines as $line) {
                if (preg_match('/^(.+?)\s*=\s*(.+)$/', trim($line), $m)) {
                    $pairData[] = [
                        'left' => trim(preg_replace('/^[a-d][\.\)]\s*/i', '', trim($m[1]))),
                        'right' => trim($m[2])
                    ];
                } else {
                    $qLines[] = $line;
                }
            }
            $questionText = implode("\n", $qLines);
        } elseif ($type === 'ordering') {
            // Find lines starting with numbers
            $lines = explode("\n", $block);
            $qLines = [];
            foreach($lines as $line) {
                if (preg_match('/^\d+[\.\)]\s*(.+)$/', trim($line), $m)) {
                    $sequenceData[] = trim($m[1]);
                } else {
                    $qLines[] = $line;
                }
            }
            $questionText = implode("\n", $qLines);
        } else {
            // Standard Multiple Choice Extraction
            $patternA = '/(?:^|\n|\s)a[\.\)]\s*/i';
            $patternB = '/(?:^|\n|\s)b[\.\)]\s*/i';
            $patternC = '/(?:^|\n|\s)c[\.\)]\s*/i';
            $patternD = '/(?:^|\n|\s)d[\.\)]\s*/i';

            $posA = preg_match($patternA, $block, $mA, PREG_OFFSET_CAPTURE) ? $mA[0][1] : -1;
            $posB = preg_match($patternB, $block, $mB, PREG_OFFSET_CAPTURE) ? $mB[0][1] : -1;
            $posC = preg_match($patternC, $block, $mC, PREG_OFFSET_CAPTURE) ? $mC[0][1] : -1;
            $posD = preg_match($patternD, $block, $mD, PREG_OFFSET_CAPTURE) ? $mD[0][1] : -1;

            if ($posA !== -1 && $posB !== -1 && $posC !== -1 && $posD !== -1) {
                $questionText = trim(substr($block, 0, $posA));

                $rawA = substr($block, $posA, $posB - $posA);
                $rawB = substr($block, $posB, $posC - $posB);
                $rawC = substr($block, $posC, $posD - $posC);
                $rawD = substr($block, $posD);

                $optA = trim(preg_replace('/^(?:\n|\s)*a[\.\)]\s*/i', '', $rawA));
                $optB = trim(preg_replace('/^(?:\n|\s)*b[\.\)]\s*/i', '', $rawB));
                $optC = trim(preg_replace('/^(?:\n|\s)*c[\.\)]\s*/i', '', $rawC));
                $optD = trim(preg_replace('/^(?:\n|\s)*d[\.\)]\s*/i', '', $rawD));
            } else {
                // Fallback for unstructured lines
                $lines = array_values(array_filter(array_map('trim', explode("\n", $block))));
                if (count($lines) >= 2) {
                    $questionText = $lines[0];
                    $optA = $lines[1] ?? 'Pilihan A';
                    $optB = $lines[2] ?? 'Pilihan B';
                    $optC = $lines[3] ?? 'Pilihan C';
                    $optD = $lines[4] ?? 'Pilihan D';
                }
            }
        }

        // Clean up question text header lines if any
        $linesQ = explode("\n", $questionText);
        $cleanLinesQ = [];
        foreach ($linesQ as $lQ) {
            $trimLQ = trim($lQ);
            if (preg_match('/^(?:SOAL-SOAL|SD|SMP|SMA|KELAS|TKA)/i', $trimLQ)) {
                continue;
            }
            $cleanLinesQ[] = $trimLQ;
        }
        $questionText = implode("\n", $cleanLinesQ);

        // Match answerText to option if letter was not given (e.g. Jawaban: 5.000)
        if (!$correctOpt && $answerText && $type === 'multiple_choice') {
            $cleanAns = strtolower(trim($answerText));
            if ($optA && strtolower(trim($optA)) === $cleanAns) { $correctOpt = 'a'; $hasKeyFromPdf = true; }
            elseif ($optB && strtolower(trim($optB)) === $cleanAns) { $correctOpt = 'b'; $hasKeyFromPdf = true; }
            elseif ($optC && strtolower(trim($optC)) === $cleanAns) { $correctOpt = 'c'; $hasKeyFromPdf = true; }
            elseif ($optD && strtolower(trim($optD)) === $cleanAns) { $correctOpt = 'd'; $hasKeyFromPdf = true; }
        }

        $isDefaultKey = false;
        if ($type === 'multiple_choice') {
            if (!in_array($correctOpt, ['a', 'b', 'c', 'd'])) {
                $correctOpt = 'a';
                $isDefaultKey = true;
            }

            if ($isDefaultKey && !$hasKeyFromPdf) {
                $note = "[⚠️ KUNCI DEFAULT: Tidak ada kunci jawaban pada file PDF]";
                $explanation = $explanation ? $explanation . "\n" . $note : $note;
            }
        }

        return [
            'question_text' => trim($questionText) ?: 'Soal Ujian',
            'materi' => $materi,
            'indikator' => $indikator,
            'cognitive_level' => $cognitive_level,
            'bobot' => $bobot,
            'type' => $type,
            'option_a' => $type === 'multiple_choice' ? ($optA ?: 'Pilihan A') : null,
            'option_b' => $type === 'multiple_choice' ? ($optB ?: 'Pilihan B') : null,
            'option_c' => $type === 'multiple_choice' ? ($optC ?: 'Pilihan C') : null,
            'option_d' => $type === 'multiple_choice' ? ($optD ?: 'Pilihan D') : null,
            'correct_option' => $type === 'multiple_choice' ? $correctOpt : null,
            'explanation' => $explanation ?: null,
            'pair_data' => $type === 'matching' ? (empty($pairData) ? null : $pairData) : null,
            'sequence_data' => $type === 'ordering' ? (empty($sequenceData) ? null : $sequenceData) : null,
            'wrong_answer_data' => $type === 'multiple_choice' ? (empty($wrongAnswerData) ? null : $wrongAnswerData) : null,
        ];
    }

    /**
     * Export student results for a specific exam to Excel (.xls) or CSV
     */
    public function exportExamResults(Request $request, Exam $exam)
    {
        $exam->load(['subject', 'creator', 'attempts.user']);
        $attempts = $exam->attempts()->with('user')->orderByDesc('score')->get();

        $format = $request->query('format', 'xls');
        $filenameSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $exam->title);
        $dateStr = date('Y-m-d_His');

        if ($format === 'csv') {
            $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
            $csv .= "REKAP HASIL UJIAN SISWA\n";
            $csv .= "Paket Ujian:," . '"' . str_replace('"', '""', $exam->title) . '"' . "\n";
            $csv .= "Mata Pelajaran:," . '"' . ($exam->subject->title ?? '-') . '"' . "\n";
            $csv .= "Jenjang:," . '"' . ($exam->grade_level ?: 'Semua') . '"' . "\n";
            $csv .= "Tanggal Export:," . date('d/m/Y H:i') . "\n\n";

            $csv .= "No,Nama Siswa,Username,Email,Jenjang,Total Soal,Jawaban Benar,Jawaban Salah,Nilai Akhir (0-100),Durasi Pengerjaan,Status,Tanggal Selesai\n";
            foreach ($attempts as $idx => $att) {
                $timeMin = floor($att->time_taken_seconds / 60);
                $timeSec = $att->time_taken_seconds % 60;
                $duration = "{$timeMin}m {$timeSec}s";
                $wrong = max(0, $att->total_questions - $att->correct_answers);
                $status = $att->score >= 75 ? 'Tuntas' : 'Perlu Bimbingan';
                $completedDate = $att->created_at ? $att->created_at->format('d/m/Y H:i') : '-';

                $csv .= ($idx + 1) . ',';
                $csv .= '"' . str_replace('"', '""', $att->user->name ?? 'Siswa') . '",';
                $csv .= '"' . ($att->user->username ?? '-') . '",';
                $csv .= '"' . ($att->user->email ?? '-') . '",';
                $csv .= '"' . ($att->user->grade_level ?? '-') . '",';
                $csv .= $att->total_questions . ',';
                $csv .= $att->correct_answers . ',';
                $csv .= $wrong . ',';
                $csv .= $att->score . ',';
                $csv .= '"' . $duration . '",';
                $csv .= '"' . $status . '",';
                $csv .= '"' . $completedDate . '"' . "\n";
            }

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"Hasil_{$filenameSafe}_{$dateStr}.csv\"",
            ]);
        }

        // Formatted Excel Table (.xls)
        $avgScore = $attempts->count() > 0 ? round($attempts->avg('score'), 1) : 0;
        $maxScore = $attempts->count() > 0 ? $attempts->max('score') : 0;
        $minScore = $attempts->count() > 0 ? $attempts->min('score') : 0;
        $passCount = $attempts->where('score', '>=', 75)->count();

        $html = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>\n" .
            "<head><meta charset='utf-8'><style>\n" .
            "body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #1e293b; }\n" .
            "table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }\n" .
            ".header-title { font-size: 16pt; font-weight: bold; color: #1e3a8a; padding: 10px 0; }\n" .
            ".meta-table td { padding: 4px 8px; border: none; font-size: 10.5pt; }\n" .
            ".data-table th { background-color: #2563eb; color: #ffffff; font-weight: bold; padding: 10px 8px; border: 1px solid #1d4ed8; text-align: center; }\n" .
            ".data-table td { padding: 8px; border: 1px solid #cbd5e1; text-align: center; }\n" .
            ".data-table td.text-left { text-align: left; }\n" .
            ".status-pass { background-color: #dcfce7; color: #15803d; font-weight: bold; }\n" .
            ".status-fail { background-color: #fee2e2; color: #b91c1c; font-weight: bold; }\n" .
            ".summary-row td { background-color: #f1f5f9; font-weight: bold; border-top: 2px solid #64748b; }\n" .
            "</style></head><body>\n";

        $html .= "<div class='header-title'>REKAPITULASI HASIL UJIAN SISWA</div>\n";
        $html .= "<table class='meta-table'>\n";
        $html .= "<tr><td width='160'><b>Paket Ujian</b></td><td>: " . htmlspecialchars($exam->title) . "</td></tr>\n";
        $html .= "<tr><td><b>Mata Pelajaran</b></td><td>: " . htmlspecialchars($exam->subject->title ?? '-') . " (" . ($exam->subject->code ?? '-') . ")</td></tr>\n";
        $html .= "<tr><td><b>Jenjang / Kelas</b></td><td>: " . htmlspecialchars($exam->grade_level ?: 'Semua Jenjang') . "</td></tr>\n";
        $html .= "<tr><td><b>Total Soal</b></td><td>: " . $exam->questions()->count() . " Soal</td></tr>\n";
        $html .= "<tr><td><b>Jumlah Peserta</b></td><td>: " . $attempts->count() . " Siswa Mengerjakan (" . $passCount . " Tuntas / Lulus KKM)</td></tr>\n";
        $html .= "<tr><td><b>Tanggal Export</b></td><td>: " . date('d F Y - H:i') . " WIB</td></tr>\n";
        $html .= "</table>\n<br/>\n";

        $html .= "<table class='data-table' border='1'>\n<thead><tr>\n" .
            "<th>No</th>\n" .
            "<th>Nama Lengkap Siswa</th>\n" .
            "<th>Username / NISN</th>\n" .
            "<th>Email</th>\n" .
            "<th>Jenjang</th>\n" .
            "<th>Jumlah Soal</th>\n" .
            "<th>Jawaban Benar</th>\n" .
            "<th>Jawaban Salah</th>\n" .
            "<th>Nilai Akhir (Skala 100)</th>\n" .
            "<th>Durasi Pengerjaan</th>\n" .
            "<th>Status</th>\n" .
            "<th>Waktu Selesai</th>\n" .
            "</tr></thead>\n<tbody>\n";

        foreach ($attempts as $idx => $att) {
            $timeMin = floor($att->time_taken_seconds / 60);
            $timeSec = $att->time_taken_seconds % 60;
            $duration = "{$timeMin}m {$timeSec}s";
            $wrong = max(0, $att->total_questions - $att->correct_answers);
            $isPass = $att->score >= 75;
            $statusClass = $isPass ? 'status-pass' : 'status-fail';
            $statusText = $isPass ? 'TUNTAS' : 'REMEDIAL';
            $completedDate = $att->created_at ? $att->created_at->format('d/m/Y H:i') : '-';

            $html .= "<tr>\n" .
                "<td>" . ($idx + 1) . "</td>\n" .
                "<td class='text-left'><b>" . htmlspecialchars($att->user->name ?? 'Siswa') . "</b></td>\n" .
                "<td>" . htmlspecialchars($att->user->username ?? '-') . "</td>\n" .
                "<td class='text-left'>" . htmlspecialchars($att->user->email ?? '-') . "</td>\n" .
                "<td>" . htmlspecialchars($att->user->grade_level ?? '-') . "</td>\n" .
                "<td>" . $att->total_questions . "</td>\n" .
                "<td style='color:#15803d; font-weight:bold;'>" . $att->correct_answers . "</td>\n" .
                "<td style='color:#b91c1c;'>" . $wrong . "</td>\n" .
                "<td style='font-size:12pt; font-weight:bold; color:#1e3a8a;'>" . $att->score . "</td>\n" .
                "<td>" . $duration . "</td>\n" .
                "<td class='{$statusClass}'>" . $statusText . "</td>\n" .
                "<td>" . $completedDate . "</td>\n" .
                "</tr>\n";
        }

        if ($attempts->count() === 0) {
            $html .= "<tr><td colspan='12' style='padding:20px; color:#64748b; font-style:italic;'>Belum ada siswa yang mengerjakan paket ujian ini.</td></tr>\n";
        } else {
            $html .= "<tr class='summary-row'>\n" .
                "<td colspan='5' class='text-left'><b>STATISTIK KELAS</b></td>\n" .
                "<td colspan='3'><b>Rata-rata: " . $avgScore . "</b></td>\n" .
                "<td colspan='2'><b>Tertinggi: " . $maxScore . " | Terendah: " . $minScore . "</b></td>\n" .
                "<td colspan='2'><b>Kelulusan: " . round(($passCount / max(1, $attempts->count())) * 100) . "%</b></td>\n" .
                "</tr>\n";
        }

        $html .= "</tbody></table></body></html>";

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"Hasil_{$filenameSafe}_{$dateStr}.xls\"",
        ]);
    }

    /**
     * Export master gradebook of all students across all exams
     */
    public function exportAllResults(Request $request)
    {
        $attempts = ExamAttempt::with(['user', 'exam.subject'])
            ->orderByDesc('created_at')
            ->get();

        $format = $request->query('format', 'xls');
        $dateStr = date('Y-m-d_His');

        if ($format === 'csv') {
            $csv = "\xEF\xBB\xBF";
            $csv .= "MASTER REKAP SEMUA NILAI SISWA\n";
            $csv .= "Tanggal Export:," . date('d/m/Y H:i') . "\n\n";
            $csv .= "No,Nama Siswa,Username,Jenjang,Paket Ujian,Mata Pelajaran,Benar,Total Soal,Nilai Akhir,Durasi,Status,Tanggal Selesai\n";

            foreach ($attempts as $idx => $att) {
                $timeMin = floor($att->time_taken_seconds / 60);
                $timeSec = $att->time_taken_seconds % 60;
                $duration = "{$timeMin}m {$timeSec}s";
                $status = $att->score >= 75 ? 'Tuntas' : 'Perlu Bimbingan';
                $completedDate = $att->created_at ? $att->created_at->format('d/m/Y H:i') : '-';

                $csv .= ($idx + 1) . ',';
                $csv .= '"' . str_replace('"', '""', $att->user->name ?? 'Siswa') . '",';
                $csv .= '"' . ($att->user->username ?? '-') . '",';
                $csv .= '"' . ($att->user->grade_level ?? '-') . '",';
                $csv .= '"' . str_replace('"', '""', $att->exam->title ?? '-') . '",';
                $csv .= '"' . ($att->exam->subject->title ?? '-') . '",';
                $csv .= $att->correct_answers . ',';
                $csv .= $att->total_questions . ',';
                $csv .= $att->score . ',';
                $csv .= '"' . $duration . '",';
                $csv .= '"' . $status . '",';
                $csv .= '"' . $completedDate . '"' . "\n";
            }

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"Master_Rekap_Nilai_{$dateStr}.csv\"",
            ]);
        }

        $html = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>\n" .
            "<head><meta charset='utf-8'><style>\n" .
            "body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #1e293b; }\n" .
            "table { border-collapse: collapse; width: 100%; }\n" .
            "th { background-color: #059669; color: #ffffff; font-weight: bold; padding: 10px 8px; border: 1px solid #047857; text-align: center; }\n" .
            "td { padding: 8px; border: 1px solid #cbd5e1; text-align: center; }\n" .
            ".text-left { text-align: left; }\n" .
            ".status-pass { background-color: #dcfce7; color: #15803d; font-weight: bold; }\n" .
            ".status-fail { background-color: #fee2e2; color: #b91c1c; font-weight: bold; }\n" .
            "</style></head><body>\n" .
            "<h2>MASTER REKAPITULASI NILAI SEMUA UJIAN SISWA</h2>\n" .
            "<p>Tanggal Export: " . date('d F Y H:i') . " | Total Pengerjaan: " . $attempts->count() . " Kali</p>\n" .
            "<table border='1'>\n<thead><tr>\n" .
            "<th>No</th>\n" .
            "<th>Nama Siswa</th>\n" .
            "<th>Username</th>\n" .
            "<th>Jenjang</th>\n" .
            "<th>Paket Ujian</th>\n" .
            "<th>Mata Pelajaran</th>\n" .
            "<th>Benar / Total</th>\n" .
            "<th>Nilai (0-100)</th>\n" .
            "<th>Durasi</th>\n" .
            "<th>Status</th>\n" .
            "<th>Waktu Selesai</th>\n" .
            "</tr></thead>\n<tbody>\n";

        foreach ($attempts as $idx => $att) {
            $timeMin = floor($att->time_taken_seconds / 60);
            $timeSec = $att->time_taken_seconds % 60;
            $duration = "{$timeMin}m {$timeSec}s";
            $isPass = $att->score >= 75;
            $statusClass = $isPass ? 'status-pass' : 'status-fail';
            $statusText = $isPass ? 'TUNTAS' : 'REMEDIAL';
            $completedDate = $att->created_at ? $att->created_at->format('d/m/Y H:i') : '-';

            $html .= "<tr>\n" .
                "<td>" . ($idx + 1) . "</td>\n" .
                "<td class='text-left'><b>" . htmlspecialchars($att->user->name ?? 'Siswa') . "</b></td>\n" .
                "<td>" . htmlspecialchars($att->user->username ?? '-') . "</td>\n" .
                "<td>" . htmlspecialchars($att->user->grade_level ?? '-') . "</td>\n" .
                "<td class='text-left'>" . htmlspecialchars($att->exam->title ?? '-') . "</td>\n" .
                "<td>" . htmlspecialchars($att->exam->subject->title ?? '-') . "</td>\n" .
                "<td>" . $att->correct_answers . " / " . $att->total_questions . "</td>\n" .
                "<td style='font-weight:bold; font-size:12pt; color:#047857;'>" . $att->score . "</td>\n" .
                "<td>" . $duration . "</td>\n" .
                "<td class='{$statusClass}'>" . $statusText . "</td>\n" .
                "<td>" . $completedDate . "</td>\n" .
                "</tr>\n";
        }

        $html .= "</tbody></table></body></html>";

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"Master_Rekap_Nilai_{$dateStr}.xls\"",
        ]);
    }
}
