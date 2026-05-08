<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->foreignId('guarantee_letter_id')->nullable()->constrained('guarantee_letters')->nullOnDelete();
            $table->string('task_number', 16);
            $table->text('title');
            $table->string('deadline_text', 128)->nullable();
            $table->string('period_code', 16)->nullable();
            $table->text('executor_text');
            $table->string('kind', 16);
            $table->string('module_code', 32)->nullable();
            $table->string('indicator_code', 48)->nullable();
            $table->string('section_path', 16);
            $table->string('section_label', 255);
            $table->integer('source_paragraph_index');
            // Reserved for future status-tracking spec. Default 'open'.
            $table->string('status', 16)->default('open');
            $table->timestamps();

            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('module_code')->references('code')->on('modules')->nullOnDelete();
            $table->foreign('indicator_code')->references('code')->on('indicators')->nullOnDelete();

            $table->unique(['region_code', 'task_number'], 'uq_tasks_region_number');
            $table->index(['region_code', 'module_code'], 'idx_tasks_region_module');
            $table->index(['region_code', 'indicator_code'], 'idx_tasks_region_indicator');
            $table->index(['region_code', 'kind'], 'idx_tasks_region_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
