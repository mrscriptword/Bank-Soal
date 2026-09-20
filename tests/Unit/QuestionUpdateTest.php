<?php

namespace Tests\Unit;

use App\Models\Question;
use PHPUnit\Framework\TestCase;

class QuestionUpdateTest extends TestCase
{
    public function test_question_array_updates_correctly()
    {
        $questionData = [
            'id' => 1,
            'exam_id' => 2,
            'question_text' => 'Soal Sebelum Edit',
            'option_a' => 'A1',
            'option_b' => 'B1',
            'option_c' => 'C1',
            'option_d' => 'D1',
            'correct_option' => 'a',
            'explanation' => 'Pembahasan lama',
        ];

        $updatedData = array_merge($questionData, [
            'question_text' => 'Soal Setelah Diperbarui',
            'correct_option' => 'c',
            'explanation' => 'Pembahasan baru yang lebih lengkap',
        ]);

        $this->assertEquals('Soal Setelah Diperbarui', $updatedData['question_text']);
        $this->assertEquals('c', $updatedData['correct_option']);
        $this->assertEquals('Pembahasan baru yang lebih lengkap', $updatedData['explanation']);
    }
}
