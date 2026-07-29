<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sector_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sector_id');
            $table->smallInteger('task_no');                       // column A
            $table->text('title');                                 // column C
            $table->string('status', 16)->default('in_progress');  // done | open | in_progress
            $table->integer('lines_total')->default(0);
            $table->integer('lines_done')->default(0);
            // Denormalized latest-period headline snapshot (same style as tasks)
            $table->string('latest_period', 16)->nullable();
            $table->string('headline_unit', 48)->nullable();
            $table->decimal('headline_plan', 20, 6)->nullable();
            $table->decimal('headline_actual', 20, 6)->nullable();
            $table->decimal('headline_pct', 10, 4)->nullable();
            $table->timestamps();

            $table->foreign('sector_id')->references('id')->on('sectors')->cascadeOnDelete();
            $table->unique(['sector_id', 'task_no'], 'uq_sector_tasks_sector_no');
            $table->index(['sector_id', 'status'], 'idx_sector_tasks_sector_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_tasks');
    }
};
