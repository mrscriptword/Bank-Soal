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

        // Seluruh murid terdaftar di sistem untuk evaluasi target ujian
        $allStudents = \App\Models\User::where('role', 'murid')
            ->select('id', 'name', 'username', 'grade_level')
            ->withCount(['examAttempts'])
            ->get();

        $guruGrade = trim((string)($guru->grade_level ?? ''));

        // Helper untuk mencocokkan jenjang kelas murid dengan target
        $matchGrade = function (?string $targetGrade, ?string $studentGrade) {
            if (empty($targetGrade) || strtoupper($targetGrade) === 'ALL') {
                return true;
            }
            if (empty($studentGrade)) {
                return false;
            }
            $tg = strtolower(trim($targetGrade));
            $sg = strtolower(trim($studentGrade));
            if ($tg === $sg) {
                return true;
            }
            if (str_starts_with($sg, $tg) || str_starts_with($tg, $sg)) {
                return true;
            }
            if (preg_match('/kelas\s*(\d+)/i', $tg, $mt) && preg_match('/kelas\s*(\d+)/i', $sg, $ms)) {
                $sameSchoolLevel = true;
                if (str_contains($tg, 'sd') && !str_contains($sg, 'sd')) $sameSchoolLevel = false;
                if (str_contains($tg, 'smp') && !str_contains($sg, 'smp')) $sameSchoolLevel = false;
                if (str_contains($tg, 'sma') && !str_contains($sg, 'sma')) $sameSchoolLevel = false;
                if ($sameSchoolLevel && $mt[1] === $ms[1]) {
                    return true;
                }
            }
            return false;
        };

        // Filter murid untuk kartu "Status Siswa Terdaftar" khusus berdasarkan jenjang kelas yang diampu guru
        $students = $allStudents->filter(function ($student) use ($guruGrade, $matchGrade) {
            return $matchGrade($guruGrade, $student->grade_level);
        })->values();

        // ✅ Eager loading spesifik: batasi kolom di setiap relasi
        // - subject: hanya id, title, code
        // - questions: muat seluruh kolom (question_text, options, metadata) untuk ditampilkan di daftar & modal edit
        // - attempts: hanya kolom yang dibutuhkan + user (id, name, username, grade_level)
        $exams = Exam::with([
                'subject:id,title,code',
                'questions',
                'attempts' => fn ($q) => $q
                    ->select('id', 'exam_id', 'user_id', 'score', 'correct_answers', 'total_questions', 'time_taken_seconds', 'status', 'started_at', 'completed_at', 'created_at')
                    ->with('user:id,name,username,grade_level'),
            ])
            ->select('id', 'subject_id', 'title', 'duration_minutes', 'passing_score', 'grade_level', 'status', 'allow_repeat', 'created_at')
            ->withCount(['attempts'])
            ->get()
            ->map(function ($exam) use ($allStudents, $matchGrade) {
                // Ambil 1 attempt terbaru per user (tanpa duplikasi)
                $attemptsByUser = $exam->attempts->sortByDesc('created_at')->unique('user_id');

                // Siswa yang sudah menyelesaikan ujian
                $completedAttempts = $attemptsByUser->filter(function ($att) {
                    return $att->status === 'completed' || !empty($att->completed_at);
                })->values();

                // Siswa yang sedang mengerjakan ujian
                $inProgressAttempts = $attemptsByUser->filter(function ($att) {
                    return $att->status === 'in_progress' && empty($att->completed_at);
                })->values();

                $activeOrDoneUserIds = $attemptsByUser->pluck('user_id')->toArray();

                // Filter murid sesuai grade_level ujian
                $targetStudents = $allStudents->filter(function ($student) use ($exam, $matchGrade) {
                    return $matchGrade($exam->grade_level, $student->grade_level);
                });

                // Siswa yang belum mulai
                $pendingStudents = $targetStudents->reject(function ($student) use ($activeOrDoneUserIds) {
                    return in_array($student->id, $activeOrDoneUserIds);
                })->values();

                $exam->completed_students   = $completedAttempts;
                $exam->in_progress_students = $inProgressAttempts;
                $exam->pending_students     = $pendingStudents;

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
            'grade_level' => 'required|in:SD Kelas 4,SD Kelas 5,SD Kelas 6,SMP Kelas 7,SMP Kelas 8,SMP Kelas 9',
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
            'grade_level' => 'required|in:SD Kelas 4,SD Kelas 5,SD Kelas 6,SMP Kelas 7,SMP Kelas 8,SMP Kelas 9',
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
            "<head><meta charset='utf-8'><title>Template Naskah Bank Soal</title>\n" .
            "<style>\n" .
            "body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #1e293b; margin: 25px; }\n" .
            "h2 { color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 4px; margin-top: 20px; }\n" .
            "h3 { color: #0369a1; margin-top: 15px; }\n" .
            ".info-box { background: #f0fdf4; border: 1px solid #86efac; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 10pt; }\n" .
            ".alert-box { background: #fff1f2; border: 1px solid #fecdd3; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 10pt; color: #9f1239; }\n" .
            "</style></head>\n" .
            "<body>\n" .
            "<h2>PANDUAN & TEMPLATE NASKAH BANK SOAL</h2>\n" .
            "<div class='alert-box'>\n" .
            "<b>⚠️ CATATAN PENTING:</b><br/>\n" .
            "• Sistem sangat cerdas & fleksibel: <b>TIDAK WAJIB</b> mencantumkan Pembahasan, Materi, Indikator, ataupun Level.<br/>\n" .
            "• Soal biasa (nomor soal, pertanyaan, pilihan A-D, dan Kunci) akan langsung terbaca dan terimpor dengan rapi.<br/>\n" .
            "• <b>Hapus atau ganti</b> teks panduan dan contoh di bawah ini dengan naskah soal Anda sendiri sebelum menyimpan/mengekspor ke format <b>PDF</b>.\n" .
            "</div>\n" .
            "<div class='info-box'>\n" .
            "<b>💡 Petunjuk Format:</b><br/>\n" .
            "• Penomoran soal: <code>1. Pertanyaan...</code> atau <code>1) Pertanyaan...</code><br/>\n" .
            "• Pilihan ganda: <code>a. Pilihan 1</code> atau <code>A. Pilihan 1</code> (bisa 3 opsi A-C atau 4 opsi A-D)<br/>\n" .
            "• Kunci jawaban per soal: <code>Kunci: B</code> atau <code>Jawaban: B</code> (opsional jika ditaruh di akhir naskah)<br/>\n" .
            "• Pembahasan (Opsional): <code>Pembahasan: Penjelasan cara penyelesaian...</code><br/>\n" .
            "• Anda juga dapat melampirkan <b>Gambar</b> pada setiap soal melalui portal guru setelah soal terimpor.\n" .
            "</div>\n" .
            "<hr/>\n" .
            "<h3>CONTOH 1: Pilihan Ganda Sederhana (Format Paling Umum)</h3>\n" .
            "1. Hasil dari 15 + 25 x 2 adalah...<br/>\n" .
            "a. 80<br/>\n" .
            "b. 65<br/>\n" .
            "c. 55<br/>\n" .
            "d. 70<br/>\n" .
            "Kunci: B<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 2: Pilihan Ganda Lengkap dengan Pembahasan & Metadata (Opsional)</h3>\n" .
            "2. Nilai dari ³√64 adalah …<br/>\n" .
            "Materi: Akar Pangkat Tiga<br/>\n" .
            "Indikator: Siswa dapat menentukan nilai akar pangkat tiga dari bilangan kubik<br/>\n" .
            "Level: C2<br/>\n" .
            "Bobot: 1<br/>\n" .
            "Tipe: multiple_choice<br/>\n" .
            "a. 4<br/>\n" .
            "b. 8<br/>\n" .
            "c. 12<br/>\n" .
            "d. 16<br/>\n" .
            "Kunci: A<br/>\n" .
            "Pembahasan: ³√64 = 4 karena 4 × 4 × 4 = 64.<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 3: Menjodohkan / Pasangan (Matching)</h3>\n" .
            "3. Pasangkan bangun datar berikut dengan rumus luasnya:<br/>\n" .
            "Tipe: matching<br/>\n" .
            "Persegi => s x s<br/>\n" .
            "Persegi Panjang => p x l<br/>\n" .
            "Segitiga => 1/2 x a x t<br/>\n" .
            "Lingkaran => π x r²<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 4: Susun Urutan Kronologis (Ordering)</h3>\n" .
            "4. Urutkan tahapan metamorfosis kupu-kupu dari awal:<br/>\n" .
            "Tipe: ordering<br/>\n" .
            "1. Telur<br/>\n" .
            "2. Ulat (Larva)<br/>\n" .
            "3. Kepompong (Pupa)<br/>\n" .
            "4. Kupu-kupu Dewasa (Imago)<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 5: Tabel Pernyataan Benar / Salah (True/False Table)</h3>\n" .
            "5. Tentukan Benar atau Salah untuk setiap pernyataan berikut:<br/>\n" .
            "Tipe: true_false_table<br/>\n" .
            "A. 15 adalah bilangan prima. => Salah<br/>\n" .
            "B. 17 adalah bilangan prima. => Benar<br/>\n" .
            "C. 2 adalah satu-satunya bilangan prima genap. => Benar<br/>\n" .
            "<br/>\n" .
            "<h3>CONTOH 6: Pilihan Ganda Kompleks (Jawaban Benar Lebih Dari Satu)</h3>\n" .
            "6. Manakah dari bilangan berikut yang merupakan kelipatan dari 6? (Pilih semua yang benar)<br/>\n" .
            "Tipe: multiple_select<br/>\n" .
            "a. 12<br/>\n" .
            "b. 15<br/>\n" .
            "c. 24<br/>\n" .
            "d. 30<br/>\n" .
            "Kunci: A, C, D<br/>\n" .
            "</body></html>";

        return response($wordHtml, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Template_BankSoal_PDF.doc"',
        ]);
    }

    public function parsePdfTextToQuestions(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = str_replace(["\xc2\xa0", "&nbsp;"], ' ', $text);

        // 1. Ekstrak Tabel Kunci Jawaban Global di akhir/awal dokumen (misal: "KUNCI JAWABAN: 1. A 2. B 3. C")
        $globalKeys = [];
        if (preg_match('/(?:KUNCI\s*JAWABAN|DAFTAR\s*KUNCI|ANSWER\s*KEY)\s*:?\s*\n?(.*)/is', $text, $mGlobal, PREG_OFFSET_CAPTURE)) {
            $globalSection = $mGlobal[1][0];
            if (preg_match_all('/(?:Soal\s*)?(\d+)[\.\)]\s*:?\s*\(?\s*([a-eA-E])\s*\)?/i', $globalSection, $mMatches, PREG_SET_ORDER)) {
                foreach ($mMatches as $m) {
                    $globalKeys[(int)$m[1]] = strtolower($m[2]);
                }
            }
            $text = substr($text, 0, $mGlobal[0][1]);
        }

        $lines = explode("\n", $text);
        $blocks = [];
        $currentBlock = [];
        $questionNumbers = [];
        $lastQuestionNumber = 0;
        $insideRealQuestion = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($insideRealQuestion && !empty($currentBlock)) {
                    $currentBlock[] = '';
                }
                continue;
            }

            // Bersihkan header / footer halaman dokumen PDF
            if (preg_match('/^(?:Halaman|Page|Hal\.)\s*\d+/i', $trimmed)) continue;
            if (preg_match('/^---\s*\d+\s*---$/', $trimmed)) continue;
            if (preg_match('/^(?:DOKUMEN\s+NEGARA|SANGAT\s+RAHASIA|BANK\s+SOAL|UJIAN\s+SEKOLAH)/i', $trimmed)) continue;

            // Reset counter nomor jika ada seksi / paket baru
            if (preg_match('/^(?:BAGIAN|SECTION|PAKET|ROMBEL)[\s:]*[A-Z0-9]+/i', $trimmed)) {
                $lastQuestionNumber = 0;
                continue;
            }

            // Deteksi baris awal nomor soal: misal "1. ", "2) ", "Soal 1: ", "1 . ", dll.
            if (preg_match('/^(?:Soal\s*(?:No\.?|Nomor)?\s*|No\.?\s*)?(\d+)[\.\:\)](?:\s+(.*)|\s*$)/i', $trimmed, $m)) {
                $qNum = (int)$m[1];
                $rest = isset($m[2]) ? trim($m[2]) : '';

                $isNextQuestion = false;
                if ($lastQuestionNumber === 0) {
                    // Soal pertama di dokumen (mulai dari nomor 1 sampai 5)
                    if ($qNum >= 1 && $qNum <= 5) {
                        // Jangan terkecoh teks petunjuk template (misal: "1. Sistem mendukung 5 model soal...")
                        if (!preg_match('/^(?:Sistem\s+mendukung|Petunjuk|Gunakan|Anda\s+dapat|Simpan\s+file|Format|Template)/i', $rest)) {
                            $isNextQuestion = true;
                        }
                    }
                } else {
                    // Progresi nomor soal yang valid (misal: 1 -> 2, atau lompat 1-5 jika guru menghapus nomor)
                    // PENTING: Jika $qNum <= $lastQuestionNumber (misal (1), (2) sub-pernyataan di dalam soal 16), itu BUKAN soal baru!
                    if ($qNum > $lastQuestionNumber && $qNum <= $lastQuestionNumber + 5) {
                        $isNextQuestion = true;
                    }
                }

                if ($isNextQuestion) {
                    if (!empty($currentBlock)) {
                        $blocks[] = implode("\n", $currentBlock);
                        $currentBlock = [];
                    }
                    $lastQuestionNumber = $qNum;
                    $questionNumbers[] = $qNum;
                    $insideRealQuestion = true;
                    if (!empty($rest)) {
                        $currentBlock[] = $rest;
                    }
                    continue;
                }
            }

            if ($insideRealQuestion) {
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
            if ($parsed && !empty($parsed['question_text'])) {
                $questions[] = $parsed;
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

        // Normalisasi spasi dan baris
        $block = str_replace(["\r\n", "\r"], "\n", $block);
        $block = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $block);
        $block = str_replace(["\xc2\xa0", "&nbsp;"], ' ', $block);

        $lines = explode("\n", $block);

        $questionLines = [];
        $explanationLines = [];
        $materiLines = [];
        $indikatorLines = [];
        $cognitiveLevel = 'C2';
        $bobot = 1;
        $type = null;
        $correctKey = $globalKey;
        $options = ['a' => null, 'b' => null, 'c' => null, 'd' => null];
        $wrongAnswerData = [];
        $pairData = [];
        $sequenceData = [];

        $currentSection = 'question';
        $currentOpt = null;
        $currentDiagnosticOpt = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($currentSection === 'question' && !empty($questionLines)) {
                    $questionLines[] = '';
                }
                continue;
            }

            // 1. Pembahasan / Penjelasan (OPSIONAL - Tidak wajib ada)
            if (preg_match('/^(?:Pembahasan|Penjelasan|Keterangan|Explanation|Solusi)\s*:\s*(.*)$/i', $trimmed, $m)) {
                $currentSection = 'explanation';
                if (!empty($m[1])) $explanationLines[] = trim($m[1]);
                continue;
            }

            if ($currentSection === 'explanation') {
                $explanationLines[] = $trimmed;
                continue;
            }

            // 2. Kunci Jawaban (OPSIONAL - Jika tidak ada, default otomatis)
            if (preg_match('/^(?:Kunci\s*Jawaban|Kunci|Jawaban\s*Benar|Jawaban|Jwb|Key|Ans)\s*:?\s*(?:Benar\s*)?(.*)$/i', $trimmed, $m)) {
                $rawKey = trim($m[1]);
                if (preg_match_all('/[a-eA-E]/', $rawKey, $km)) {
                    $letters = array_unique(array_map('strtolower', $km[0]));
                    if (count($letters) > 1) {
                        $correctKey = implode(',', $letters);
                        if (!$type) $type = 'multiple_select';
                    } elseif (count($letters) === 1) {
                        $correctKey = $letters[0];
                    }
                } else {
                    $correctKey = $rawKey;
                }
                $currentSection = 'after_key';
                continue;
            }

            // 3. Metadata Diagnostik (Error, Diagnosis, Treatment) (OPSIONAL)
            if (preg_match('/^Error\s+([A-Da-d])\s*:\s*(.*)$/i', $trimmed, $m)) {
                $currentSection = 'error';
                $currentDiagnosticOpt = strtolower($m[1]);
                $wrongAnswerData[$currentDiagnosticOpt]['error_pattern'] = trim($m[2]);
                continue;
            }
            if (preg_match('/^Diagnosis\s+([A-Da-d])\s*:\s*(.*)$/i', $trimmed, $m)) {
                $currentSection = 'diagnosis';
                $currentDiagnosticOpt = strtolower($m[1]);
                $wrongAnswerData[$currentDiagnosticOpt]['diagnosis'] = trim($m[2]);
                continue;
            }
            if (preg_match('/^Treatment\s+([A-Da-d])\s*:\s*(.*)$/i', $trimmed, $m)) {
                $currentSection = 'treatment';
                $currentDiagnosticOpt = strtolower($m[1]);
                $wrongAnswerData[$currentDiagnosticOpt]['treatment'] = trim($m[2]);
                continue;
            }

            if ($currentSection === 'error' && $currentDiagnosticOpt) {
                $wrongAnswerData[$currentDiagnosticOpt]['error_pattern'] .= ' ' . $trimmed;
                continue;
            }
            if ($currentSection === 'diagnosis' && $currentDiagnosticOpt) {
                $wrongAnswerData[$currentDiagnosticOpt]['diagnosis'] .= ' ' . $trimmed;
                continue;
            }
            if ($currentSection === 'treatment' && $currentDiagnosticOpt) {
                $wrongAnswerData[$currentDiagnosticOpt]['treatment'] .= ' ' . $trimmed;
                continue;
            }

            // 4. Metadata Soal (Materi, Indikator, Level, Bobot, Tipe) (SEMUANYA OPSIONAL)
            if (preg_match('/^Materi\s*:\s*(.*)$/i', $trimmed, $m)) {
                $currentSection = 'materi';
                if (!empty($m[1])) $materiLines[] = trim($m[1]);
                continue;
            }
            if (preg_match('/^Indikator(?:\s*Soal)?\s*:\s*(.*)$/i', $trimmed, $m)) {
                $currentSection = 'indikator';
                if (!empty($m[1])) $indikatorLines[] = trim($m[1]);
                continue;
            }
            if ($currentSection === 'materi') {
                if (!preg_match('/^(?:Indikator|Level|Bobot|Tipe|[a-eA-E][\.\)])/i', $trimmed)) {
                    $materiLines[] = $trimmed;
                    continue;
                }
            }
            if ($currentSection === 'indikator') {
                if (!preg_match('/^(?:Materi|Level|Bobot|Tipe|[a-eA-E][\.\)])/i', $trimmed)) {
                    $indikatorLines[] = $trimmed;
                    continue;
                }
            }

            if (preg_match('/^(?:Level|Cognitive Level|Tingkat)\s*:\s*(C[1-6]|Mudah|Sedang|Sukar|Sulit)\b/i', $trimmed, $m)) {
                $cognitiveLevel = strtoupper(trim($m[1]));
                $currentSection = 'metadata';
                continue;
            }

            if (preg_match('/^(?:Bobot|Poin|Skor)\s*:\s*(\d+)/i', $trimmed, $m)) {
                $bobot = (int)$m[1];
                $currentSection = 'metadata';
                continue;
            }

            if (preg_match('/^(?:Tipe|Type)\s*:\s*(multiple_choice|matching|ordering|true_false_table|multiple_select|pilihan_ganda|menjodohkan|urutan|benar_salah|kompleks)\b/i', $trimmed, $m)) {
                $rawType = strtolower(trim($m[1]));
                if (in_array($rawType, ['multiple_choice', 'pilihan_ganda'])) $type = 'multiple_choice';
                elseif (in_array($rawType, ['matching', 'menjodohkan'])) $type = 'matching';
                elseif (in_array($rawType, ['ordering', 'urutan'])) $type = 'ordering';
                elseif (in_array($rawType, ['true_false_table', 'benar_salah'])) $type = 'true_false_table';
                elseif (in_array($rawType, ['multiple_select', 'kompleks'])) $type = 'multiple_select';
                $currentSection = 'metadata';
                continue;
            }

            // 5. Deteksi Baris True/False Table (Benar/Salah)
            if (preg_match('/^(?:[a-zA-Z0-9][\.\)]\s*)?(.+?)\s*(?:=>|➔|->|=)\s*(Benar|Salah|True|False)\b/i', $trimmed, $tm)) {
                if (!$type) $type = 'true_false_table';
                $pairData[] = [
                    'statement' => trim($tm[1]),
                    'answer' => (strtolower($tm[2]) === 'benar' || strtolower($tm[2]) === 'true') ? 'benar' : 'salah',
                ];
                $currentSection = 'tft_row';
                continue;
            }

            // 6. Deteksi Baris Menjodohkan (Matching)
            if (($type === 'matching' || str_contains($trimmed, '=>') || str_contains($trimmed, '➔')) && preg_match('/^(.+?)\s*(?:=>|➔|->|=)\s*(.+)$/', $trimmed, $mm)) {
                if (!$type) $type = 'matching';
                $pairData[] = [
                    'left' => trim(preg_replace('/^[a-eA-E0-9][\.\)]\s*/', '', $mm[1])),
                    'right' => trim($mm[2]),
                ];
                $currentSection = 'matching_row';
                continue;
            }

            // 7. Deteksi Baris Susun Urutan (Ordering)
            if ($type === 'ordering' && preg_match('/^\d+[\.\)]\s*(.+)$/', $trimmed, $om)) {
                $sequenceData[] = trim($om[1]);
                $currentSection = 'ordering_row';
                continue;
            }

            // 8. Deteksi Pilihan Ganda (A., B., C., D. atau a., b., c., d. atau (A), (B))
            if (preg_match('/^(?:\(?([a-eA-E])[\.\)]|\(([a-eA-E])\))[ \t]+(.*)$/u', $trimmed, $om)) {
                $optLetter = strtolower(!empty($om[1]) ? $om[1] : $om[2]);
                $optVal = trim($om[3]);

                // Deteksi jika beberapa opsi ada dalam SATU baris sekaligus (misal: "A. 112   B. 109   C. 107   D. 105")
                $inlinePattern = '/(?:\s{2,}|\s+)(?:\(?([b-eB-E])[\.\)]|\(([b-eB-E])\))[ \t]+/u';
                if (preg_match_all($inlinePattern, $trimmed, $inlineMatches, PREG_OFFSET_CAPTURE)) {
                    $lastLetter = $optLetter;
                    $lastOffset = 0;

                    foreach ($inlineMatches[0] as $idx => $mBlock) {
                        $matchOffset = $mBlock[1];
                        $letter = strtolower(!empty($inlineMatches[1][$idx][0]) ? $inlineMatches[1][$idx][0] : $inlineMatches[2][$idx][0]);
                        
                        if ($idx === 0) {
                            preg_match('/^(?:\(?([a-eA-E])[\.\)]|\(([a-eA-E])\))[ \t]+/u', $trimmed, $firstMarker);
                            $start = strlen($firstMarker[0]);
                            $val = trim(substr($trimmed, $start, $matchOffset - $start));
                        } else {
                            $val = trim(substr($trimmed, $lastOffset, $matchOffset - $lastOffset));
                        }
                        if (array_key_exists($lastLetter, $options)) {
                            $options[$lastLetter] = $val;
                        }

                        $lastLetter = $letter;
                        $lastOffset = $matchOffset + strlen($mBlock[0]);
                    }
                    if (array_key_exists($lastLetter, $options)) {
                        $options[$lastLetter] = trim(substr($trimmed, $lastOffset));
                    }
                    $currentSection = 'options';
                    $currentOpt = $lastLetter;
                    continue;
                }

                // Opsi baris tunggal
                if (array_key_exists($optLetter, $options)) {
                    $options[$optLetter] = $optVal;
                }
                $currentSection = 'option_' . $optLetter;
                $currentOpt = $optLetter;
                continue;
            }

            // Jika baris ini merupakan kelanjutan teks opsi pilihan ganda
            if (str_starts_with($currentSection, 'option_') && $currentOpt) {
                if (array_key_exists($currentOpt, $options)) {
                    $options[$currentOpt] .= ' ' . $trimmed;
                }
                continue;
            }

            // Baris lainnya adalah teks pertanyaan (soal)
            $questionLines[] = $trimmed;
        }

        // Tentukan tipe otomatis jika belum diset
        if (!$type) {
            if (!empty($pairData)) {
                $type = isset($pairData[0]['statement']) ? 'true_false_table' : 'matching';
            } elseif (!empty($sequenceData)) {
                $type = 'ordering';
            } else {
                $type = 'multiple_choice';
            }
        }

        // Rakit teks soal dan bersihkan nomor soal di depan
        $questionText = trim(implode("\n", $questionLines));
        $questionText = preg_replace('/^(?:Soal\s*(?:No\.?|Nomor)?\s*|No\.?\s*)?\d+[\.\:\)]\s*/i', '', $questionText);
        $questionText = trim($questionText);

        if (empty($questionText)) {
            $questionText = !empty($options['a']) ? 'Soal Pilihan Ganda' : 'Soal Ujian';
        }

        // Cocokkan teks kunci jika berupa isi jawaban
        if ($correctKey && !in_array(strtolower($correctKey), ['a', 'b', 'c', 'd', 'e'])) {
            $cleanKey = strtolower(trim($correctKey));
            foreach (['a', 'b', 'c', 'd'] as $k) {
                if (!empty($options[$k]) && strtolower(trim($options[$k])) === $cleanKey) {
                    $correctKey = $k;
                    break;
                }
            }
            if (!in_array(strtolower($correctKey), ['a', 'b', 'c', 'd'])) {
                $correctKey = 'a';
            }
        } else {
            $correctKey = strtolower($correctKey ?: 'a');
        }

        if ($type === 'multiple_select') {
            if (str_contains($correctKey, ',')) {
                $sequenceData = array_map('trim', explode(',', $correctKey));
            } else {
                $sequenceData = [$correctKey];
            }
        }

        // Validasi opsi pilihan ganda / kompleks agar tidak pernah bernilai kosong di database
        if (in_array($type, ['multiple_choice', 'multiple_select'])) {
            if (empty($options['a']) && empty($options['b'])) {
                $qLines = array_values(array_filter($questionLines));
                if (count($qLines) >= 3) {
                    $questionText = $qLines[0];
                    $options['a'] = $qLines[1];
                    $options['b'] = $qLines[2];
                    $options['c'] = $qLines[3] ?? 'Pilihan C';
                    $options['d'] = $qLines[4] ?? 'Pilihan D';
                } else {
                    $options['a'] = 'Pilihan A';
                    $options['b'] = 'Pilihan B';
                    $options['c'] = 'Pilihan C';
                    $options['d'] = 'Pilihan D';
                }
            } else {
                if (empty($options['a'])) $options['a'] = 'Pilihan A';
                if (empty($options['b'])) $options['b'] = 'Pilihan B';
                if (empty($options['c'])) $options['c'] = 'Pilihan C';
            }
        }

        $explanation = !empty($explanationLines) ? trim(implode("\n", $explanationLines)) : null;
        $materi = !empty($materiLines) ? trim(implode(' ', $materiLines)) : null;
        $indikator = !empty($indikatorLines) ? trim(implode(' ', $indikatorLines)) : null;

        return [
            'question_text' => $questionText,
            'materi' => $materi,
            'indikator' => $indikator,
            'cognitive_level' => $cognitiveLevel,
            'bobot' => $bobot,
            'type' => $type,
            'option_a' => in_array($type, ['multiple_choice', 'multiple_select']) ? $options['a'] : null,
            'option_b' => in_array($type, ['multiple_choice', 'multiple_select']) ? $options['b'] : null,
            'option_c' => in_array($type, ['multiple_choice', 'multiple_select']) ? $options['c'] : null,
            'option_d' => in_array($type, ['multiple_choice', 'multiple_select']) ? $options['d'] : null,
            'correct_option' => $type === 'multiple_choice' ? $correctKey : ($type === 'multiple_select' ? (is_array($correctKey) ? implode(',', $correctKey) : $correctKey) : null),
            'explanation' => $explanation,
            'pair_data' => in_array($type, ['matching', 'true_false_table']) ? (empty($pairData) ? null : $pairData) : null,
            'sequence_data' => in_array($type, ['ordering', 'multiple_select']) ? (empty($sequenceData) ? null : $sequenceData) : null,
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
