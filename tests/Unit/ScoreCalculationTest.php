<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ScoreCalculationTest extends TestCase
{
    private function calculateScore(int $correctCount, int $totalQuestions): int
    {
        if ($totalQuestions > 0) {
            $rawScore = ($correctCount / $totalQuestions) * 100;
            $floorVal = floor($rawScore);
            $fractionalPart = round($rawScore - $floorVal, 6);
            if ($fractionalPart > 0.5) {
                return (int) ceil($rawScore);
            } else {
                return (int) $floorVal;
            }
        }
        return 0;
    }

    public function test_fractional_part_greater_than_half_rounds_up()
    {
        // 76.6 -> 77 (e.g., 23 correct out of 30: 23/30*100 = 76.6666...)
        $this->assertEquals(77, $this->calculateScore(23, 30));
    }

    public function test_fractional_part_equal_to_half_rounds_down()
    {
        // 76.5 -> 76 (e.g., 153 correct out of 200: 153/200*100 = 76.5)
        $this->assertEquals(76, $this->calculateScore(153, 200));
    }

    public function test_fractional_part_less_than_half_rounds_down()
    {
        // 76.2 -> 76 (e.g., 381 correct out of 500: 381/500*100 = 76.2)
        $this->assertEquals(76, $this->calculateScore(381, 500));
    }

    public function test_exact_hundred()
    {
        $this->assertEquals(100, $this->calculateScore(10, 10));
    }

    public function test_zero_correct()
    {
        $this->assertEquals(0, $this->calculateScore(0, 10));
    }
}
