<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentExamController extends Controller
{
    private function getTargetGradeLevel(?Request $request = null): ?string
    {
        $user = Auth::user();
        if ($user && $user->role === 'murid' && !empty($user->grade_level) && $user->grade_level !== 'ALL') {
            return $user->grade_level;
        }
        if ($request && $request->has('grade_level') && !empty($request->grade_level) && $request->grade_level !== 'ALL') {
            return $request->grade_level;
        }
        return null;
    }

    public function wizard(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role === 'guru') {
                return redirect()->route('guru.dashboard');
            }
        }

        $targetGrade = $this->getTargetGradeLevel($request);

        $subjects = Subject::with(['exams' => function ($query) use ($targetGrade) {
            $query->where('status', 'active');
            if ($targetGrade) {
                $query->where('grade_level', $targetGrade);
            }
            $query->withCount('questions');
        }])->get();

        return view('simulasi.interactive_wizard', compact('subjects', 'targetGrade'));
    }

    public function getSubjects(Request $request)
    {
        $targetGrade = $this->getTargetGradeLevel($request);

        $subjects = Subject::with(['exams' => function ($q) use ($targetGrade) {
            $q->where('status', 'active');
            if ($targetGrade) {
                $q->where('grade_level', $targetGrade);
            }
            $q->withCount('questions');
        }])->get();

        return response()->json([
            'success' => true,
            'target_grade' => $targetGrade,
            'subjects' => $subjects,
        ]);
    }

    public function getExamDetail(Exam $exam)
    {
        $exam->load(['subject', 'questions' => function ($q) {
            // Exclude correct_option and explanation during active test
            $q->select('id', 'exam_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d');
        }]);

        return response()->json([
            'success' => true,
            'exam' => $exam,
        ]);
    }

    public function submitExam(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'answers' => 'required|array',
            'time_taken_seconds' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $exam = Exam::with('questions')->findOrFail($request->exam_id);
        $answersData = $request->answers; // [question_id => 'a'|'b'|'c'|'d']
        $timeTaken = $request->input('time_taken_seconds', 0);

        $totalQuestions = $exam->questions->count();
        $correctCount = 0;

        $attempt = ExamAttempt::create([
            'user_id' => $user ? $user->id : 1, // Fallback for guest simulation if any
            'exam_id' => $exam->id,
            'total_questions' => $totalQuestions,
            'time_taken_seconds' => $timeTaken,
            'status' => 'completed',
            'started_at' => now()->subSeconds($timeTaken),
            'completed_at' => now(),
        ]);

        $reviewItems = [];

        foreach ($exam->questions as $index => $question) {
            $userOption = strtolower($answersData[$question->id] ?? '');
            $isCorrect = ($userOption === strtolower($question->correct_option));

            if ($isCorrect) {
                $correctCount++;
            }

            ExamAttemptAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'selected_option' => $userOption ?: null,
                'is_correct' => $isCorrect,
            ]);

            $optionMap = [
                'a' => $question->option_a,
                'b' => $question->option_b,
                'c' => $question->option_c,
                'd' => $question->option_d,
            ];

            $reviewItems[] = [
                'number' => $index + 1,
                'question_text' => $question->question_text,
                'options' => $optionMap,
                'selected_option' => $userOption,
                'correct_option' => strtolower($question->correct_option),
                'selected_text' => $optionMap[$userOption] ?? 'Tidak dijawab',
                'correct_text' => $optionMap[strtolower($question->correct_option)] ?? '',
                'is_correct' => $isCorrect,
                'explanation' => $question->explanation ?: 'Belum ada pembahasan khusus untuk soal ini.',
            ];
        }

        if ($totalQuestions > 0) {
            $rawScore = ($correctCount / $totalQuestions) * 100;
            $floorVal = floor($rawScore);
            $fractionalPart = round($rawScore - $floorVal, 6);
            if ($fractionalPart > 0.5) {
                $calculatedScore = (int) ceil($rawScore);
            } else {
                $calculatedScore = (int) $floorVal;
            }
        } else {
            $calculatedScore = 0;
        }
        
        $attempt->update([
            'score' => $calculatedScore,
            'correct_answers' => $correctCount,
        ]);

        $minutes = floor($timeTaken / 60);
        $seconds = $timeTaken % 60;
        $formattedTime = ($minutes > 0 ? "{$minutes} menit " : '') . "{$seconds} detik";

        return response()->json([
            'success' => true,
            'attempt_id' => $attempt->id,
            'score' => $calculatedScore,
            'score_display' => "{$calculatedScore} / 100",
            'correct_answers' => $correctCount,
            'total_questions' => $totalQuestions,
            'time_taken_formatted' => $formattedTime,
            'time_taken_seconds' => $timeTaken,
            'review' => $reviewItems,
        ]);
    }

    public function getAttemptReview(ExamAttempt $attempt)
    {
        $attempt->load(['exam.subject', 'answers.question']);

        $reviewItems = [];
        foreach ($attempt->answers as $index => $ans) {
            $q = $ans->question;
            $userOpt = strtolower($ans->selected_option ?? '');
            $correctOpt = strtolower($q->correct_option);

            $optionMap = [
                'a' => $q->option_a,
                'b' => $q->option_b,
                'c' => $q->option_c,
                'd' => $q->option_d,
            ];

            $reviewItems[] = [
                'number' => $index + 1,
                'question_text' => $q->question_text,
                'options' => $optionMap,
                'selected_option' => $userOpt,
                'correct_option' => $correctOpt,
                'selected_text' => $optionMap[$userOpt] ?? 'Tidak dijawab',
                'correct_text' => $optionMap[$correctOpt] ?? '',
                'is_correct' => $ans->is_correct,
                'explanation' => $q->explanation ?: 'Tidak ada pembahasan.',
            ];
        }

        return response()->json([
            'success' => true,
            'attempt' => $attempt,
            'review' => $reviewItems,
        ]);
    }
}
