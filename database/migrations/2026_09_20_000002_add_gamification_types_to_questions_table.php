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
            $table->string('type')->default('multiple_choice')->after('exam_id');
            $table->json('pair_data')->nullable()->after('explanation');
            $table->json('sequence_data')->nullable()->after('pair_data');

            // Make options nullable for non-multiple-choice question types
            $table->text('option_a')->nullable()->change();
            $table->text('option_b')->nullable()->change();
            $table->text('option_c')->nullable()->change();
            $table->text('option_d')->nullable()->change();
            $table->string('correct_option', 10)->nullable()->change();
        });

        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            $table->json('answer_payload')->nullable()->after('is_correct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['type', 'pair_data', 'sequence_data']);
        });

        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            $table->dropColumn('answer_payload');
        });
    }
};
