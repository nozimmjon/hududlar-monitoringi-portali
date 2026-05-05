<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();
            $table->string('module_code', 32)->nullable();
            $table->string('file_name', 255);
            $table->string('file_path', 512)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->smallInteger('sheet_count')->nullable();
            $table->boolean('parsed_ok')->default(false);
            $table->text('error_text')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'module_code'], 'idx_imp_files_run_module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_files');
    }
};
