<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Jika Anda ingin memastikan tipe data dan auto_increment-nya benar tanpa bentrok primary key:
        DB::statement('ALTER TABLE questions MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // Lakukan hal yang sama untuk exam_attempt_answers jika sudah memiliki primary key
        DB::statement('ALTER TABLE exam_attempt_answers MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        // No down needed
    }
};