<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Region-staff reviews asked for several duplicated tasks to be removed from the
 * boards. Deleting the rows is not durable (import:task-progress would recreate
 * them from the partner file), so suppressed tasks are flagged instead: the data
 * stays, every board query skips them (Task::scopeHasPlan), and the list of
 * suppressed (region, task_number) pairs lives in TaskReviewFixes::SUPPRESSED.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('hidden')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('hidden');
        });
    }
};
