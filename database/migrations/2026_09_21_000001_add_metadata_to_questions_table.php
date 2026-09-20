<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('materi')->nullable()->after('exam_id');
            $table->string('indikator')->nullable()->after('materi');
            $table->string('cognitive_level', 5)->nullable()->after('indikator'); // C1, C2, C3, C4
            $table->unsignedTinyInteger('bobot')->default(1)->after('cognitive_level');
            // JSON: { "a": { "error_pattern": "...", "diagnosis": "...", "treatment": "..." }, "b": {...} }
            $table->json('wrong_answer_data')->nullable()->after('explanation');
        });

        // Add diagnosis-related columns to exam_attempt_answers so we can store
        // which diagnosis was triggered per answer
        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            $table->string('triggered_error_pattern')->nullable()->after('answer_payload');
            $table->text('triggered_diagnosis')->nullable()->after('triggered_error_pattern');
            $table->text('triggered_treatment')->nullable()->after('triggered_diagnosis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['materi', 'indikator', 'cognitive_level', 'bobot', 'wrong_answer_data']);
        });

        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            $table->dropColumn(['triggered_error_pattern', 'triggered_diagnosis', 'triggered_treatment']);
        });
    }
};
