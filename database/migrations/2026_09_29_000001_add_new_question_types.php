<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds support for two new question types:
     *  - true_false_table : Tabel pernyataan, tiap baris dijawab Benar/Salah
     *    Data disimpan di pair_data: [{"statement":"...","answer":"benar"}, ...]
     *  - multiple_select  : Pilihan ganda kompleks, jawaban benar > 1
     *    Data disimpan di sequence_data: ["a","b", ...] (huruf-huruf yang benar)
     *
     * Tidak perlu kolom baru — kedua tipe memanfaatkan kolom JSON yang sudah ada.
     */
    public function up(): void
    {
        // No new columns needed; pair_data and sequence_data already exist and are nullable JSON.
        // This migration serves as documentation and a marker for the feature addition.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to reverse.
    }
};
