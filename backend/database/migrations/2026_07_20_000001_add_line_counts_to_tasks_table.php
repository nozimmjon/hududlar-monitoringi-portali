<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Planned indicator lines in the latest period and how many are ≥100%.
            // A multi-indicator task (lines_total > 1) is done only when all its
            // planned lines are done — status is derived from these, not line 0.
            $table->smallInteger('lines_total')->default(0)->after('headline_pct');
            $table->smallInteger('lines_done')->default(0)->after('lines_total');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['lines_total', 'lines_done']);
        });
    }
};
