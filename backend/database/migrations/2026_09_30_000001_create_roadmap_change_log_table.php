<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_change_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();           // null = terminal import
            $table->foreignId('roadmap_id')->constrained('roadmaps')->cascadeOnDelete();
            $table->foreignId('roadmap_measure_id')->nullable()->constrained('roadmap_measures')->cascadeOnDelete();   // null for an import entry
            $table->foreignId('roadmap_measure_line_id')->nullable()->constrained('roadmap_measure_lines')->nullOnDelete();   // the entry outlives a removed line
            $table->string('report_period', 16)->nullable();
            $table->string('action', 16);          // actual · note · label · unit · plan · line_added · line_removed · confirm · import
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at');
            $table->index(['roadmap_measure_id', 'created_at'], 'ix_roadmap_change_log_measure');
            $table->index(['roadmap_id', 'created_at'], 'ix_roadmap_change_log_roadmap');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_change_log');
    }
};
