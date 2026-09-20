<?php

namespace Tests\Unit;

use App\Http\Controllers\GuruController;
use PHPUnit\Framework\TestCase;

class PdfExamParserTest extends TestCase
{
    public function test_parsing_pdf_without_explanation()
    {
        $samplePdfText = "
1. Sifat distributif pada (4 x 10) - (4 x 3) bisa ditulis dengan..
a. 4 x (10 - 4) x 3
b. 4 x (10 - 3)
c. (4 x 10) - 3
d. 4 x (10 - 4 x 3)
Jawaban: B

2. Nilai mana yang menempati ratusan ribu pada 243.976?
a. 2
b. 4
c. 3
d. 9
Jawaban: A
";

        $controller = new GuruController();
        $questions = $controller->parsePdfTextToQuestions($samplePdfText);

        $this->assertCount(2, $questions);

        // Q1 without explanation
        $this->assertEquals('Sifat distributif pada (4 x 10) - (4 x 3) bisa ditulis dengan..', $questions[0]['question_text']);
        $this->assertEquals('4 x (10 - 4) x 3', $questions[0]['option_a']);
        $this->assertEquals('4 x (10 - 3)', $questions[0]['option_b']);
        $this->assertEquals('b', $questions[0]['correct_option']);
        $this->assertNull($questions[0]['explanation']);

        // Q2 without explanation
        $this->assertEquals('Nilai mana yang menempati ratusan ribu pada 243.976?', $questions[1]['question_text']);
        $this->assertEquals('a', $questions[1]['correct_option']);
        $this->assertNull($questions[1]['explanation']);
    }

    public function test_parsing_value_based_answer_and_explanation()
    {
        $samplePdfText = "
3. Taksiran atas dari 98 x 46 adalah...
a. 4.500
b. 5.000
c. 1.600
d. 3.100
Jawaban: 5.000
Pembahasan: 98 dibulatkan ke atas menjadi 100 dan 46 dibulatkan ke atas menjadi 50. Jadi taksiran 98 x 46 = 100 x 50 = 5.000
";

        $controller = new GuruController();
        $questions = $controller->parsePdfTextToQuestions($samplePdfText);

        $this->assertCount(1, $questions);
        $this->assertEquals('Taksiran atas dari 98 x 46 adalah...', $questions[0]['question_text']);
        $this->assertEquals('b', $questions[0]['correct_option']);
        $this->assertEquals('98 dibulatkan ke atas menjadi 100 dan 46 dibulatkan ke atas menjadi 50. Jadi taksiran 98 x 46 = 100 x 50 = 5.000', $questions[0]['explanation']);
    }

    public function test_parsing_multiline_answer_key()
    {
        $samplePdfText = "
20. Pertanyaan
Hasil dari 245 + 378 adalah …
a. 613
b. 623
c. 633
d. 643
Jawaban Benar
B. 623
";

        $controller = new GuruController();
        $questions = $controller->parsePdfTextToQuestions($samplePdfText);

        $this->assertCount(1, $questions);
        $this->assertEquals('b', $questions[0]['correct_option']);
        $this->assertEquals('623', $questions[0]['option_b']);
    }

    public function test_parsing_global_answer_key_table()
    {
        $samplePdfText = "
1. Pertanyaan satu
a. Opsi 1A
b. Opsi 1B
c. Opsi 1C
d. Opsi 1D

2. Pertanyaan dua
a. Opsi 2A
b. Opsi 2B
c. Opsi 2C
d. Opsi 2D

KUNCI JAWABAN:
1. C
2. D
";

        $controller = new GuruController();
        $questions = $controller->parsePdfTextToQuestions($samplePdfText);

        $this->assertCount(2, $questions);
        $this->assertEquals('c', $questions[0]['correct_option']);
        $this->assertEquals('d', $questions[1]['correct_option']);
    }

    public function test_parsing_question_without_key_attaches_warning_note()
    {
        $samplePdfText = "
1. Pertanyaan tanpa kunci
a. Opsi A
b. Opsi B
c. Opsi C
d. Opsi D
";

        $controller = new GuruController();
        $questions = $controller->parsePdfTextToQuestions($samplePdfText);

        $this->assertCount(1, $questions);
        $this->assertEquals('a', $questions[0]['correct_option']);
        $this->assertStringContainsString('KUNCI DEFAULT', $questions[0]['explanation']);
    }
}
