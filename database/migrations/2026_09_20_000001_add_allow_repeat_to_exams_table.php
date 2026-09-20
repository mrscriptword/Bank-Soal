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
        if (!Schema::hasColumn('exams', 'allow_repeat')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->boolean('allow_repeat')->default(false)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('exams', 'allow_repeat')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn('allow_repeat');
            });
        }
    }
};
