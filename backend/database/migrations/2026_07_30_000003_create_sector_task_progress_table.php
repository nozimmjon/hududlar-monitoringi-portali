<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sector_task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_task_id')->constrained('sector_tasks')->cascadeOnDelete();
            $table->smallInteger('line_no');                      // column B — sheet-global, stable key
            $table->string('metric_label', 255);                  // column D
            $table->string('unit', 48)->nullable();               // column E
            $table->string('deadline_text', 64)->nullable();      // column F raw
            $table->string('deadline_code', 8);                   // year | h2 | q3 | q4
            $table->string('report_period', 16);                  // '2026-H2' | '2026-Q3' | '2026-08'
            $table->string('period_type', 8);                     // half | quarter | month
            $table->decimal('plan_value', 20, 6)->nullable();
            $table->decimal('actual_value', 20, 6)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();    // recomputed, never from file
            $table->date('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['sector_task_id', 'line_no', 'report_period'], 'uq_stp_line_period');
            $table->index(['sector_task_id', 'report_period'], 'idx_stp_task_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_task_progress');
    }
};
