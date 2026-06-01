<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->smallInteger('line_no')->default(0);          // 0 = headline metric, 1+ = sub-metrics
            $table->string('metric_label', 255)->nullable();      // col D
            $table->string('unit', 48)->nullable();               // col E
            $table->string('report_period', 16);                  // '2026-03' | '2026-Q1'
            $table->string('period_type', 8);                     // 'month' | 'quarter'
            $table->decimal('plan_value', 20, 6)->nullable();
            $table->decimal('actual_value', 20, 6)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();
            $table->date('reported_at')->nullable();
            $table->foreignId('import_run_id')->nullable()->constrained('import_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'line_no', 'report_period'], 'uq_task_progress_line_period');
            $table->index(['task_id', 'report_period'], 'idx_task_progress_task_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_progress');
    }
};
