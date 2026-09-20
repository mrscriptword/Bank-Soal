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
            'created_by' => Auth::id() ?? 1,
            'status' => 'active',
        ]);

        return redirect()->route('guru.dashboard')->with('success', 'Paket Ujian berhasil ditambahkan!');
    }

    public function storeQuestion(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'question_text' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_option' => 'required|in:a,b,c,d',
            'explanation' => 'nullable|string',
        ]);

        Question::create([
            'exam_id' => $request->exam_id,
            'question_text' => $request->question_text,
            'option_a' => $request->option_a,
            'option_b' => $request->option_b,
            'option_c' => $request->option_c,
            'option_d' => $request->option_d,
            'correct_option' => $request->correct_option,
            'explanation' => $request->explanation,
        ]);

        // Update total questions count on Exam
        $exam = Exam::find($request->exam_id);
        $exam->update(['total_questions' => $exam->questions()->count()]);

        return redirect()->route('guru.dashboard')->with('success', 'Soal & Pembahasan berhasil ditambahkan!');
    }

    public function updateQuestion(Request $request, Question $question)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'question_text' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_option' => 'required|in:a,b,c,d',
            'explanation' => 'nullable|string',
        ]);

        $oldExamId = $question->exam_id;

        $question->update([
            'exam_id' => $request->exam_id,
            'question_text' => $request->question_text,
            'option_a' => $request->option_a,
            'option_b' => $request->option_b,
            'option_c' => $request->option_c,
            'option_d' => $request->option_d,
            'correct_option' => $request->correct_option,
            'explanation' => $request->explanation,
        ]);

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
            'total_questions' => count($questionsData),
            'created_by' => Auth::id() ?? 1,
            'status' => 'active',
        ]);

        foreach ($questionsData as $q) {
            Question::create([
                'exam_id' => $exam->id,
                'question_text' => $q['question_text'],
                'option_a' => $q['option_a'],
                'option_b' => $q['option_b'],
                'option_c' => $q['option_c'],
                'option_d' => $q['option_d'],
                'correct_option' => $q['correct_option'],
                'explanation' => $q['explanation'],
            ]);
        }

        return redirect()->route('guru.dashboard')->with('success', 'Paket Ujian berhasil dibuat dari file PDF! (' . count($questionsData) . ' soal terimpor)');
    }

    public function downloadTemplate()
    {
        $wordHtml = "<html xmlns:o='urn:schemas-microsoft-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>\n" .
            "<head><meta charset='utf-8'><title>Template Naskah Bank Soal</title>\n" .
            "<style>\n" .
            "body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #111827; margin: 20px; }\n" .
            "h1 { font-size: 16pt; color: #4F46E5; border-bottom: 2px solid #4F46E5; padding-bottom: 5px; }\n" .
            "h2 { font-size: 13pt; color: #1F2937; margin-top: 20px; }\n" .
            ".box { background-color: #F3F4F6; border-left: 4px solid #4F46E5; padding: 10px 15px; margin: 15px 0; }\n" .
            ".soal { margin-bottom: 15px; }\n" .
            ".opsi { margin-left: 20px; }\n" .
            ".kunci { color: #059669; font-weight: bold; }\n" .
            ".pembahasan { color: #2563EB; font-style: italic; }\n" .
            "</style></head>\n" .
            "<body>\n" .
            "<h1>TEMPLATE NASKAH BANK SOAL PDF / WORD</h1>\n" .
            "<div class='box'>\n" .
            "<p><strong>Panduan Penggunaan Template:</strong></p>\n" .
            "<ol>\n" .
            "  <li>Ketik atau edit soal Anda di dalam dokumen Word ini.</li>\n" .
            "  <li>Pastikan nomor soal diawali angka dan titik, misal: <strong>1.</strong> atau <strong>1)</strong>.</li>\n" .
            "  <li>Sertakan 4 pilihan ganda (<strong>a.</strong>, <strong>b.</strong>, <strong>c.</strong>, <strong>d.</strong>) pada baris terpisah.</li>\n" .
            "  <li>Tulis kunci jawaban dengan format <code>Kunci: B</code> atau buat daftar kunci jawaban di akhir naskah.</li>\n" .
            "  <li>Setelah selesai, <strong>Simpan / Save As / Export sebagai PDF (.pdf)</strong> lalu unggah ke aplikasi.</li>\n" .
            "</ol>\n" .
            "</div>\n" .
            "<hr/>\n" .
            "<h2>CONTOH FORMAT NASKAH (INLINE):</h2>\n" .
            "<div class='soal'>\n" .
            "<p><strong>1. Hasil dari 15 + 25 x 2 adalah...</strong></p>\n" .
            "<div class='opsi'>a. 80<br/>b. 65<br/>c. 55<br/>d. 70</div>\n" .
            "<p class='kunci'>Kunci: B</p>\n" .
            "<p class='pembahasan'>Pembahasan: Dahulukan perkalian 25 x 2 = 50. Lalu 15 + 50 = 65.</p>\n" .
            "</div>\n" .
            "<div class='soal'>\n" .
            "<p><strong>2. Sebuah persegi memiliki panjang sisi 8 cm. Berapa luas persegi tersebut?</strong></p>\n" .
            "<div class='opsi'>a. 32 cm²<br/>b. 64 cm²<br/>c. 16 cm²<br/>d. 48 cm²</div>\n" .
            "<p class='kunci'>Kunci: B</p>\n" .
            "<p class='pembahasan'>Pembahasan: Luas persegi = s x s = 8 cm x 8 cm = 64 cm².</p>\n" .
            "</div>\n" .
            "<div class='soal'>\n" .
            "<p><strong>3. Manakah yang termasuk kata baku dalam Bahasa Indonesia?</strong></p>\n" .
            "<div class='opsi'>a. Apotik<br/>b. Apotek<br/>c. Apotiek<br/>d. Apoteq</div>\n" .
            "<p class='kunci'>Kunci: B</p>\n" .
            "<p class='pembahasan'>Pembahasan: Kata yang baku menurut KBBI adalah Apotek.</p>\n" .
            "</div>\n" .
            "<hr/>\n" .
            "<h2>ALTERNATIF FORMAT (Daftar Kunci di Akhir Dokumen):</h2>\n" .
            "<p><strong>KUNCI JAWABAN:</strong></p>\n" .
            "<p>1. B<br/>2. B<br/>3. B</p>\n" .
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
        if (preg_match('/(?:KUNCI|DAFTAR KUNCI|KUNCI JAWABAN|ANSWER KEY)\s*:?\s*\n?(.*)/is', $text, $mGlobal, PREG_OFFSET_CAPTURE)) {
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

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // Check if line starts with question number: e.g. "1.", "2)", "Soal 1:", "15 ."
            if (preg_match('/^(?:Soal\s*)?(\d+)[\.\)]\s+(.*)/i', $trimmed, $m)) {
                if (!empty($currentBlock)) {
                    $blocks[] = implode("\n", $currentBlock);
                    $currentBlock = [];
                }
                $questionNumber = (int)$m[1];
                $questionNumbers[] = $questionNumber;
                $currentBlock[] = $m[2];
            } else {
                if (!empty($currentBlock)) {
                    $currentBlock[] = $trimmed;
                }
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

        // 1. Extract Explanation / Pembahasan (OPTIONAL - DOES NOT FAIL IF MISSING)
        $explanation = null;
        if (preg_match('/(?:Pembahasan|Penjelasan|Explanation)\s*:\s*(.+)/is', $block, $mExp, PREG_OFFSET_CAPTURE)) {
            $explanation = trim($mExp[1][0]);
            $block = trim(substr($block, 0, $mExp[0][1]));
        }

        // 2. Extract Answer Key / Jawaban (OPTIONAL)
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

        // 3. Extract Options A, B, C, D
        $optA = null; $optB = null; $optC = null; $optD = null;
        $questionText = $block;

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
        if (!$correctOpt && $answerText) {
            $cleanAns = strtolower(trim($answerText));
            if ($optA && strtolower(trim($optA)) === $cleanAns) { $correctOpt = 'a'; $hasKeyFromPdf = true; }
            elseif ($optB && strtolower(trim($optB)) === $cleanAns) { $correctOpt = 'b'; $hasKeyFromPdf = true; }
            elseif ($optC && strtolower(trim($optC)) === $cleanAns) { $correctOpt = 'c'; $hasKeyFromPdf = true; }
            elseif ($optD && strtolower(trim($optD)) === $cleanAns) { $correctOpt = 'd'; $hasKeyFromPdf = true; }
        }

        $isDefaultKey = false;
        if (!in_array($correctOpt, ['a', 'b', 'c', 'd'])) {
            $correctOpt = 'a';
            $isDefaultKey = true;
        }

        if ($isDefaultKey && !$hasKeyFromPdf) {
            $note = "[⚠️ KUNCI DEFAULT: Tidak ada kunci jawaban pada file PDF]";
            $explanation = $explanation ? $explanation . "\n" . $note : $note;
        }

        return [
            'question_text' => trim($questionText) ?: 'Soal Ujian',
            'option_a' => $optA ?: 'Pilihan A',
            'option_b' => $optB ?: 'Pilihan B',
            'option_c' => $optC ?: 'Pilihan C',
            'option_d' => $optD ?: 'Pilihan D',
            'correct_option' => $correctOpt,
            'explanation' => $explanation ?: null,
        ];
    }
}
