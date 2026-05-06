<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('region_workbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('reporting_year_id')->constrained('reporting_years')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('file_name', 255);                // "1.1-1.5-жадваллар (макро).xlsx"
            $table->string('file_path', 512)->nullable();    // relative path under storage
            $table->string('sha256', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'reporting_year_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_workbooks');
    }
};
