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

        $exams = Exam::with(['subject', 'questions'])
            ->withCount(['attempts'])
            ->get();

        $attempts = ExamAttempt::with(['user', 'exam.subject'])
            ->latest()
            ->take(15)
            ->get();

        $totalQuestions = Question::count();
        $totalAttempts = ExamAttempt::count();
        $averageScore = round(ExamAttempt::avg('score') ?? 0, 1);

        return view('guru.dashboard', compact('subjects', 'exams', 'attempts', 'totalQuestions', 'totalAttempts', 'averageScore'));
    }

    public function storeExam(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'passing_score' => 'required|integer|min:0|max:100',
        ]);

        $exam = Exam::create([
            'subject_id' => $request->subject_id,
            'title' => $request->title,
            'duration_minutes' => $request->duration_minutes,
            'passing_score' => $request->passing_score,
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

    public function parsePdfTextToQuestions(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Split text line by line to locate question headers
        $lines = explode("\n", $text);
        $blocks = [];
        $currentBlock = [];

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
        foreach ($blocks as $block) {
            $parsed = $this->formatParsedQuestionBlock($block);
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
                        $content = $splitBlocks[$i + 1] ?? '';
                        $i++;
                        $parsed = $this->formatParsedQuestionBlock($content);
                        if ($parsed) {
                            $questions[] = $parsed;
                        }
                    }
                }
            }
        }

        return $questions;
    }

    private function formatParsedQuestionBlock(string $block): ?array
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
        $correctOpt = null;
        $answerText = null;

        if (preg_match('/(?:Kunci|Jawaban|Kunci Jawaban|Jawaban Benar)\s*:?\s*(?:Benar\s*)?\n?\s*([a-d])(?:\.|\s|\n|$)/i', $block, $mKey, PREG_OFFSET_CAPTURE)) {
            $correctOpt = strtolower($mKey[1][0]);
            $block = trim(substr($block, 0, $mKey[0][1]));
        } elseif (preg_match('/(?:Kunci|Jawaban|Kunci Jawaban|Jawaban Benar)\s*:\s*(.+)/i', $block, $mVal, PREG_OFFSET_CAPTURE)) {
            $answerText = trim($mVal[1][0]);
            $block = trim(substr($block, 0, $mVal[0][1]));
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
            if ($optA && strtolower(trim($optA)) === $cleanAns) $correctOpt = 'a';
            elseif ($optB && strtolower(trim($optB)) === $cleanAns) $correctOpt = 'b';
            elseif ($optC && strtolower(trim($optC)) === $cleanAns) $correctOpt = 'c';
            elseif ($optD && strtolower(trim($optD)) === $cleanAns) $correctOpt = 'd';
        }

        if (!in_array($correctOpt, ['a', 'b', 'c', 'd'])) {
            $correctOpt = 'a';
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
