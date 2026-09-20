<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuruController extends Controller
{
    public function dashboard()
    {
        $guru = Auth::user();

        $subjects = Subject::withCount(['exams'])->get();

        $students = \App\Models\User::where('role', 'murid')->get();

        $exams = Exam::with(['subject', 'questions', 'attempts.user'])
            ->withCount(['attempts'])
            ->get()
            ->map(function ($exam) use ($students) {
                // Get latest attempt per user for this exam
                $attemptsByUser = $exam->attempts->sortByDesc('created_at')->unique('user_id');
                $completedUserIds = $attemptsByUser->pluck('user_id')->toArray();

                // Target students matching exam grade level
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

        $attempts = ExamAttempt::with(['user', 'exam.subject'])
            ->latest()
            ->take(15)
            ->get();

        $totalQuestions = Question::count();
        $totalAttempts = ExamAttempt::count();
        $averageScore = round(ExamAttempt::avg('score') ?? 0, 1);

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
            'subject_id' => $request->subject_id,
            'title' => $request->title,
            'duration_minutes' => $request->duration_minutes,
            'passing_score' => $request->passing_score,
            'grade_level' => $request->grade_level,
            'allow_repeat' => $request->boolean('allow_repeat'),
            'created_by' => Auth::id() ?? 1,
            'status' => 'active',
        ]);

        return redirect()->route('guru.dashboard')->with('success', 'Paket Ujian berhasil ditambahkan!');
    }

    public function storeQuestion(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'type' => 'nullable|in:multiple_choice,matching,ordering',
            'question_text' => 'required|string',
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

        if ($type === 'matching') {
            $data['pair_data'] = array_values(array_filter($request->input('pair_data', []), function ($pair) {
                return !empty($pair['left']) && !empty($pair['right']);
            }));
        } elseif ($type === 'ordering') {
            $data['sequence_data'] = array_values(array_filter($request->input('sequence_data', []), function ($item) {
                return !empty(trim($item));
            }));
        } else {
            $data['option_a']       = $request->option_a;
            $data['option_b']       = $request->option_b;
            $data['option_c']       = $request->option_c;
            $data['option_d']       = $request->option_d;
            $data['correct_option'] = strtolower($request->correct_option ?? 'a');

            // Build wrong_answer_data from per-option error/diagnosis/treatment fields
            $correctOpt = strtolower($request->correct_option ?? 'a');
            $wrongAnswerData = [];
            foreach (['a', 'b', 'c', 'd'] as $opt) {
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

        return redirect()->route('guru.dashboard')->with('success', 'Soal & Pembahasan berhasil ditambahkan!');
    }

    public function updateQuestion(Request $request, Question $question)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'type' => 'nullable|in:multiple_choice,matching,ordering',
            'question_text' => 'required|string',
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

        if ($type === 'matching') {
            $data['pair_data'] = array_values(array_filter($request->input('pair_data', []), function ($pair) {
                return !empty($pair['left']) && !empty($pair['right']);
            }));
            $data['sequence_data']    = null;
            $data['wrong_answer_data'] = null;
        } elseif ($type === 'ordering') {
            $data['sequence_data'] = array_values(array_filter($request->input('sequence_data', []), function ($item) {
                return !empty(trim($item));
            }));
            $data['pair_data']        = null;
            $data['wrong_answer_data'] = null;
        } else {
            $data['option_a']       = $request->option_a;
            $data['option_b']       = $request->option_b;
            $data['option_c']       = $request->option_c;
            $data['option_d']       = $request->option_d;
            $data['correct_option'] = strtolower($request->correct_option ?? 'a');
            $data['pair_data']      = null;
            $data['sequence_data']  = null;

            // Build wrong_answer_data
            $correctOpt = strtolower($request->correct_option ?? 'a');
            $wrongAnswerData = [];
            foreach (['a', 'b', 'c', 'd'] as $opt) {
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

        return redirect()->route('guru.dashboard')->with('success', 'Paket Ujian berhasil dibuat dari file PDF! (' . count($questionsData) . ' soal terimpor)');
    }

    public function downloadTemplate()
    {
        $wordHtml = "<html xmlns:o='urn:schemas-microsoft-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>\n" .
            "<head><meta charset='utf-8'><title>Template Naskah Bank Soal</title>\n" .
            "<style>\n" .
            "body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #000000; margin: 20px; }\n" .
            "</style></head>\n" .
            "<body>\n" .
            "1. Hasil dari 15 + 25 x 2 adalah...<br/>\n" .
            "Materi: Operasi Hitung Campuran<br/>\n" .
            "Indikator: Siswa dapat menghitung perkalian dan penjumlahan<br/>\n" .
            "Level: C3<br/>\n" .
            "Bobot: 2<br/>\n" .
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
            "2. Sebuah persegi memiliki panjang sisi 8 cm. Berapa luas persegi tersebut?<br/>\n" .
            "Materi: Bangun Datar<br/>\n" .
            "Indikator: Siswa dapat menghitung luas persegi<br/>\n" .
            "Level: C2<br/>\n" .
            "Bobot: 1<br/>\n" .
            "Tipe: multiple_choice<br/>\n" .
            "a. 32 cm²<br/>\n" .
            "b. 64 cm²<br/>\n" .
            "c. 16 cm²<br/>\n" .
            "d. 48 cm²<br/>\n" .
            "Kunci: B<br/>\n" .
            "Error A: Mengalikan panjang sisi dengan 4 (keliling persegi)<br/>\n" .
            "Diagnosis A: Siswa tertukar antara rumus luas dan keliling<br/>\n" .
            "Treatment A: Penguatan beda konsep luas (s x s) dan keliling (4 x s)<br/>\n" .
            "Pembahasan: Luas persegi = s x s = 8 cm x 8 cm = 64 cm².<br/>\n" .
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
}
