<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CbtSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Users
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator SimulasiKu',
                'email' => 'admin@simulasiku.id',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'grade_level' => 'ALL',
            ]
        );

        $guru = User::firstOrCreate(
            ['username' => 'guru1'],
            [
                'name' => 'Budi Santoso, S.Pd.',
                'email' => 'guru1@simulasiku.id',
                'password' => Hash::make('password'),
                'role' => 'guru',
                'grade_level' => 'SMP/SMA',
            ]
        );

        $murid = User::firstOrCreate(
            ['username' => 'siswa2026'],
            [
                'name' => 'Siswa Simulasi',
                'email' => 'siswa2026@simulasiku.id',
                'password' => Hash::make('password'),
                'role' => 'murid',
                'grade_level' => 'SD Kelas 4',
            ]
        );

        // 2. Create Subjects
        $mtk = Subject::create([
            'title' => 'Matematika',
            'code' => 'MTK',
            'description' => 'Pelatihan logika, aritmatika, dan geometri.',
            'icon' => 'calculator',
            'teacher_id' => $guru->id,
        ]);

        $bin = Subject::create([
            'title' => 'Bahasa Indonesia',
            'code' => 'BIN',
            'description' => 'Pemahaman bacaan, ejaan, dan tata bahasa.',
            'icon' => 'book-open',
            'teacher_id' => $guru->id,
        ]);

        $ipa = Subject::create([
            'title' => 'IPA',
            'code' => 'IPA',
            'description' => 'Ilmu Pengetahuan Alam, Fisika & Biologi dasar.',
            'icon' => 'atom',
            'teacher_id' => $guru->id,
        ]);

        // 3. Create Exam for Matematika
        $examMtk = Exam::create([
            'subject_id' => $mtk->id,
            'title' => 'Simulasi Matematika Dasar',
            'duration_minutes' => 15,
            'total_questions' => 10,
            'passing_score' => 70,
            'grade_level' => 'SD Kelas 4',
            'created_by' => $guru->id,
            'status' => 'active',
        ]);

        // Soal 1 (sesuai screenshot referensi)
        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Sebuah persegi panjang memiliki panjang 12 cm dan lebar 7 cm. Berapa luasnya?',
            'option_a' => '84 cm²',
            'option_b' => '19 cm²',
            'option_c' => '38 cm²',
            'option_d' => '96 cm²',
            'correct_option' => 'a',
            'explanation' => 'Luas persegi panjang dihitung dengan rumus L = p × l. Diketahui panjang (p) = 12 cm dan lebar (l) = 7 cm. Maka Luas = 12 cm × 7 cm = 84 cm².',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Berapakah hasil perhitungan dari 15 × 8 - 20?',
            'option_a' => '100',
            'option_b' => '110',
            'option_c' => '120',
            'option_d' => '105',
            'correct_option' => 'a',
            'explanation' => 'Dahulukan operasi perkalian: 15 × 8 = 120. Kemudian kurangi 20: 120 - 20 = 100.',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Sebuah lingkaran memiliki jari-jari 7 cm. Berapakah keliling lingkaran tersebut? (Gunakan π = 22/7)',
            'option_a' => '44 cm',
            'option_b' => '154 cm',
            'option_c' => '22 cm',
            'option_d' => '88 cm',
            'correct_option' => 'a',
            'explanation' => 'Rumus keliling lingkaran K = 2 × π × r. K = 2 × (22/7) × 7 = 44 cm.',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Jika 3x + 5 = 20, berapakah nilai x?',
            'option_a' => '5',
            'option_b' => '4',
            'option_c' => '6',
            'option_d' => '3',
            'correct_option' => 'a',
            'explanation' => '3x = 20 - 5 => 3x = 15 => x = 15 / 3 = 5.',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Berapa FPB dari 24 dan 36?',
            'option_a' => '12',
            'option_b' => '6',
            'option_c' => '72',
            'option_d' => '4',
            'correct_option' => 'a',
            'explanation' => 'Faktor 24: 1, 2, 3, 4, 6, 8, 12, 24. Faktor 36: 1, 2, 3, 4, 6, 9, 12, 18, 36. FPB terbesar adalah 12.',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Berapa luas segitiga dengan alas 10 cm dan tinggi 14 cm?',
            'option_a' => '70 cm²',
            'option_b' => '140 cm²',
            'option_c' => '35 cm²',
            'option_d' => '24 cm²',
            'correct_option' => 'a',
            'explanation' => 'Luas segitiga = ½ × alas × tinggi = ½ × 10 × 14 = 70 cm².',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Bentuk persen dari pecahan 3/5 adalah...',
            'option_a' => '60%',
            'option_b' => '30%',
            'option_c' => '50%',
            'option_d' => '75%',
            'correct_option' => 'a',
            'explanation' => '3/5 × 100% = 300 / 5 = 60%.',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Sebuah kubus memiliki panjang rusuk 5 cm. Berapa volume kubus tersebut?',
            'option_a' => '125 cm³',
            'option_b' => '25 cm³',
            'option_c' => '150 cm³',
            'option_d' => '100 cm³',
            'correct_option' => 'a',
            'explanation' => 'Volume kubus = s³ = 5 × 5 × 5 = 125 cm³.',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Jika sebuah mobil menempuh jarak 120 km dalam waktu 2 jam, berapakah kecepatan rata-rata mobil tersebut?',
            'option_a' => '60 km/jam',
            'option_b' => '80 km/jam',
            'option_c' => '50 km/jam',
            'option_d' => '240 km/jam',
            'correct_option' => 'a',
            'explanation' => 'Kecepatan v = s / t = 120 km / 2 jam = 60 km/jam.',
        ]);

        Question::create([
            'exam_id' => $examMtk->id,
            'question_text' => 'Rata-rata dari nilai 7, 8, 9, 6, dan 10 adalah...',
            'option_a' => '8',
            'option_b' => '8.5',
            'option_c' => '7.5',
            'option_d' => '9',
            'correct_option' => 'a',
            'explanation' => 'Jumlah nilai = 7 + 8 + 9 + 6 + 10 = 40. Rata-rata = 40 / 5 = 8.',
        ]);

        // 4. Create Exam for Bahasa Indonesia
        $examBin = Exam::create([
            'subject_id' => $bin->id,
            'title' => 'Simulasi Bahasa Indonesia',
            'duration_minutes' => 12,
            'total_questions' => 8,
            'passing_score' => 70,
            'grade_level' => 'SD Kelas 5',
            'created_by' => $guru->id,
            'status' => 'active',
        ]);

        Question::create([
            'exam_id' => $examBin->id,
            'question_text' => 'Manakah di bawah ini yang merupakan kata baku dalam Bahasa Indonesia?',
            'option_a' => 'Kualitas',
            'option_b' => 'Kwalitas',
            'option_c' => 'Kwalitet',
            'option_d' => 'Kwalitash',
            'correct_option' => 'a',
            'explanation' => 'Menurut KBBI, kata yang baku adalah "Kualitas", bukan kwalitas.',
        ]);

        Question::create([
            'exam_id' => $examBin->id,
            'question_text' => 'Ide pokok dalam suatu paragraf biasanya terletak pada...',
            'option_a' => 'Kalimat utama',
            'option_b' => 'Kalimat penjelas',
            'option_c' => 'Judul teks',
            'option_d' => 'Kesimpulan akhir',
            'correct_option' => 'a',
            'explanation' => 'Gagasan atau ide pokok terkandung di dalam kalimat utama paragraf.',
        ]);

        // 5. Create Exam for IPA
        $examIpa = Exam::create([
            'subject_id' => $ipa->id,
            'title' => 'Simulasi IPA & Fisika Dasar',
            'duration_minutes' => 15,
            'total_questions' => 10,
            'passing_score' => 70,
            'grade_level' => 'SMP Kelas 7',
            'created_by' => $guru->id,
            'status' => 'active',
        ]);

        Question::create([
            'exam_id' => $examIpa->id,
            'question_text' => 'Proses pembuatan makanan pada tumbuhan hijau dengan bantuan cahaya matahari disebut...',
            'option_a' => 'Fotosintesis',
            'option_b' => 'Respirasi',
            'option_c' => 'Transpirasi',
            'option_d' => 'Osmosis',
            'correct_option' => 'a',
            'explanation' => 'Fotosintesis adalah proses klorofil tumbuhan mengubah air dan CO2 menjadi glukosa dan oksigen dengan bantuan cahaya matahari.',
        ]);

        Question::create([
            'exam_id' => $examIpa->id,
            'question_text' => 'Organ tubuh manusia yang berfungsi memompa darah ke seluruh tubuh adalah...',
            'option_a' => 'Jantung',
            'option_b' => 'Paru-paru',
            'option_c' => 'Hati',
            'option_d' => 'Ginjal',
            'correct_option' => 'a',
            'explanation' => 'Jantung adalah organ otot utama yang memompa darah beroksigen ke seluruh jaringan tubuh.',
        ]);
    }
}
