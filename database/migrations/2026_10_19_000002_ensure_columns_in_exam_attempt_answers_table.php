<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_attempt_answers', 'triggered_error_pattern')) {
                $table->string('triggered_error_pattern')->nullable()->after('answer_payload');
            }
            if (!Schema::hasColumn('exam_attempt_answers', 'triggered_diagnosis')) {
                $table->text('triggered_diagnosis')->nullable()->after('triggered_error_pattern');
            }
            if (!Schema::hasColumn('exam_attempt_answers', 'triggered_treatment')) {
                $table->text('triggered_treatment')->nullable()->after('triggered_diagnosis');
            }
            if (!Schema::hasColumn('exam_attempt_answers', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            $cols = [];
            foreach (['triggered_error_pattern', 'triggered_diagnosis', 'triggered_treatment', 'created_at', 'updated_at'] as $col) {
                if (Schema::hasColumn('exam_attempt_answers', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
