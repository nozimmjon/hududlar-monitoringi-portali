<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_measure_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_measure_id')->constrained('roadmap_measures')->cascadeOnDelete();
            $table->smallInteger('line_no');                       // 1..n, row order inside the measure block of the template
            $table->string('label', 255);                          // «Насос агрегати таъмирланди»
            $table->string('unit', 48)->nullable();                // та | км | минг га | % | млрд сўм …
            $table->decimal('plan_value', 20, 6)->nullable();
            $table->timestamps();

            $table->unique(['roadmap_measure_id', 'line_no'], 'uq_roadmap_measure_lines_pos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_measure_lines');
    }
};
