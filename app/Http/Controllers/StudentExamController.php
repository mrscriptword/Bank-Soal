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
            $q->select('id', 'exam_id', 'type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'pair_data', 'sequence_data');
        }]);

        // Prepare randomized items for student UI
        $exam->questions->transform(function ($question) {
            if ($question->type === 'matching' && is_array($question->pair_data)) {
                $leftItems = [];
                $rightItems = [];
                foreach ($question->pair_data as $pair) {
                    if (isset($pair['left'])) $leftItems[] = $pair['left'];
                    if (isset($pair['right'])) $rightItems[] = $pair['right'];
                }
                $shuffledRight = $rightItems;
                shuffle($shuffledRight);
                $question->left_items = $leftItems;
                $question->shuffled_right = $shuffledRight;
            } elseif ($question->type === 'ordering' && is_array($question->sequence_data)) {
                $shuffledSeq = $question->sequence_data;
                shuffle($shuffledSeq);
                $question->shuffled_sequence = $shuffledSeq;
            }
            return $question;
        });

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
        $answersData = $request->answers;
        $timeTaken = $request->input('time_taken_seconds', 0);

        $totalQuestions = $exam->questions->count();
        $correctCount = 0;

        $attempt = ExamAttempt::create([
            'user_id' => $user ? $user->id : 1,
            'exam_id' => $exam->id,
            'total_questions' => $totalQuestions,
            'time_taken_seconds' => $timeTaken,
            'status' => 'completed',
            'started_at' => now()->subSeconds($timeTaken),
            'completed_at' => now(),
        ]);

        $reviewItems = [];

        foreach ($exam->questions as $index => $question) {
            $rawAnswer = $answersData[$question->id] ?? null;
            $isCorrect = false;
            $selectedOptionStr = null;
            $answerPayload = null;
            $triggeredErrorPattern = null;
            $triggeredDiagnosis = null;
            $triggeredTreatment = null;

            if ($question->type === 'matching') {
                // Matching pairs: expected rawAnswer format: {"Left Text": "Right Text", ...}
                $answerPayload = is_array($rawAnswer) ? $rawAnswer : json_decode($rawAnswer, true);
                if (!is_array($answerPayload)) {
                    $answerPayload = [];
                }

                $correctPairs = $question->pair_data ?: [];
                $matchingCorrect = 0;
                $totalPairs = count($correctPairs);

                if ($totalPairs > 0) {
                    foreach ($correctPairs as $pair) {
                        $left = $pair['left'] ?? '';
                        $expectedRight = $pair['right'] ?? '';
                        if (isset($answerPayload[$left]) && trim($answerPayload[$left]) === trim($expectedRight)) {
                            $matchingCorrect++;
                        }
                    }
                    $isCorrect = ($matchingCorrect === $totalPairs);
                }

            } elseif ($question->type === 'ordering') {
                // Sequence ordering: expected rawAnswer format: ["Step 1", "Step 2", ...]
                $answerPayload = is_array($rawAnswer) ? $rawAnswer : json_decode($rawAnswer, true);
                if (!is_array($answerPayload)) {
                    $answerPayload = [];
                }

                $correctSeq = $question->sequence_data ?: [];
                $isCorrect = (count($answerPayload) === count($correctSeq) && $answerPayload === $correctSeq);

            } else {
                // Standard multiple choice
                $selectedOptionStr = is_string($rawAnswer) ? strtolower($rawAnswer) : null;
                $isCorrect = ($selectedOptionStr && $selectedOptionStr === strtolower($question->correct_option));

                // If wrong, look up error pattern / diagnosis / treatment
                if (!$isCorrect && $selectedOptionStr && is_array($question->wrong_answer_data)) {
                    $diag = $question->wrong_answer_data[$selectedOptionStr] ?? null;
                    if ($diag) {
                        $triggeredErrorPattern = $diag['error_pattern'] ?? null;
                        $triggeredDiagnosis    = $diag['diagnosis'] ?? null;
                        $triggeredTreatment    = $diag['treatment'] ?? null;
                    }
                }
            }

            if ($isCorrect) {
                $correctCount++;
            }

            ExamAttemptAnswer::create([
                'exam_attempt_id'         => $attempt->id,
                'question_id'             => $question->id,
                'selected_option'         => $selectedOptionStr,
                'answer_payload'          => $answerPayload,
                'is_correct'              => $isCorrect,
                'triggered_error_pattern' => $triggeredErrorPattern,
                'triggered_diagnosis'     => $triggeredDiagnosis,
                'triggered_treatment'     => $triggeredTreatment,
            ]);

            $optionMap = [
                'a' => $question->option_a,
                'b' => $question->option_b,
                'c' => $question->option_c,
                'd' => $question->option_d,
            ];

            $reviewItems[] = [
                'number'            => $index + 1,
                'type'              => $question->type ?: 'multiple_choice',
                'materi'            => $question->materi,
                'indikator'         => $question->indikator,
                'cognitive_level'   => $question->cognitive_level,
                'bobot'             => $question->bobot ?? 1,
                'question_text'     => $question->question_text,
                'options'           => $optionMap,
                'selected_option'   => $selectedOptionStr,
                'correct_option'    => strtolower($question->correct_option ?? ''),
                'selected_text'     => $optionMap[$selectedOptionStr] ?? 'Tidak dijawab',
                'correct_text'      => $optionMap[strtolower($question->correct_option ?? '')] ?? '',
                'answer_payload'    => $answerPayload,
                'pair_data'         => $question->pair_data,
                'sequence_data'     => $question->sequence_data,
                'is_correct'        => $isCorrect,
                'explanation'       => $question->explanation ?: 'Belum ada pembahasan khusus untuk soal ini.',
                'error_pattern'     => $triggeredErrorPattern,
                'diagnosis'         => $triggeredDiagnosis,
                'treatment'         => $triggeredTreatment,
                'wrong_answer_data' => $question->wrong_answer_data,
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

        // Build indicator mastery summary
        $indicatorMap = [];
        foreach ($reviewItems as $ri) {
            if (!empty($ri['indikator'])) {
                $ind = $ri['indikator'];
                if (!isset($indicatorMap[$ind])) {
                    $indicatorMap[$ind] = ['total' => 0, 'correct' => 0];
                }
                $indicatorMap[$ind]['total']++;
                if ($ri['is_correct']) {
                    $indicatorMap[$ind]['correct']++;
                }
            }
        }
        $indicatorMastery = [];
        foreach ($indicatorMap as $ind => $counts) {
            $pct = $counts['total'] > 0 ? round(($counts['correct'] / $counts['total']) * 100) : 0;
            $indicatorMastery[] = [
                'indikator' => $ind,
                'total'     => $counts['total'],
                'correct'   => $counts['correct'],
                'percent'   => $pct,
                'mastered'  => $pct >= 60,
            ];
        }

        $minutes = floor($timeTaken / 60);
        $seconds = $timeTaken % 60;
        $formattedTime = ($minutes > 0 ? "{$minutes} menit " : '') . "{$seconds} detik";

        return response()->json([
            'success'              => true,
            'attempt_id'           => $attempt->id,
            'score'                => $calculatedScore,
            'score_display'        => "{$calculatedScore} / 100",
            'correct_answers'      => $correctCount,
            'total_questions'      => $totalQuestions,
            'time_taken_formatted' => $formattedTime,
            'time_taken_seconds'   => $timeTaken,
            'allow_repeat'         => (bool) $exam->allow_repeat,
            'indicator_mastery'    => $indicatorMastery,
            'review'               => $reviewItems,
        ]);
    }

    public function getAttemptReview(ExamAttempt $attempt)
    {
        $attempt->load(['exam.subject', 'answers.question']);

        $reviewItems = [];
        foreach ($attempt->answers as $index => $ans) {
            $q = $ans->question;
            $userOpt = strtolower($ans->selected_option ?? '');
            $correctOpt = strtolower($q->correct_option ?? '');

            $optionMap = [
                'a' => $q->option_a,
                'b' => $q->option_b,
                'c' => $q->option_c,
                'd' => $q->option_d,
            ];

            $reviewItems[] = [
                'number' => $index + 1,
                'type' => $q->type ?: 'multiple_choice',
                'question_text' => $q->question_text,
                'options' => $optionMap,
                'selected_option' => $userOpt,
                'correct_option' => $correctOpt,
                'selected_text' => $optionMap[$userOpt] ?? 'Tidak dijawab',
                'correct_text' => $optionMap[$correctOpt] ?? '',
                'answer_payload' => $ans->answer_payload,
                'pair_data' => $q->pair_data,
                'sequence_data' => $q->sequence_data,
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
