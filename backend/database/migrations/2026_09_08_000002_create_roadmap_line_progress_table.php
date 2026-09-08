<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_line_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_measure_line_id')->constrained('roadmap_measure_lines')->cascadeOnDelete();
            $table->string('report_period', 16);                   // '2026-09' | '2026-Q3'
            $table->string('period_type', 8);                      // month | quarter
            $table->decimal('actual_value', 20, 6)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();     // computed at import, never read from the file
            $table->string('note', 500)->nullable();               // «Изоҳ»
            $table->date('reported_at')->nullable();
            $table->timestamps();

            // Also serves the (line, period) lookups — no separate index needed.
            $table->unique(['roadmap_measure_line_id', 'report_period'], 'uq_roadmap_line_progress_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_line_progress');
    }
};
