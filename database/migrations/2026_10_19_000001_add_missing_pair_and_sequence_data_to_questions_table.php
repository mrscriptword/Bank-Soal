<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'pair_data')) {
                $table->json('pair_data')->nullable();
            }
            if (!Schema::hasColumn('questions', 'sequence_data')) {
                $table->json('sequence_data')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('questions', 'pair_data')) {
                $cols[] = 'pair_data';
            }
            if (Schema::hasColumn('questions', 'sequence_data')) {
                $cols[] = 'sequence_data';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
