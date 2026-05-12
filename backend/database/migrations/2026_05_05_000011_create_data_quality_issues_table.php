<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('data_quality_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->nullable()
                  ->constrained('import_runs')->nullOnDelete();
            $table->unsignedInteger('region_code');
            $table->unsignedInteger('district_code')->nullable();
            $table->string('indicator_code', 48)->nullable();
            $table->smallInteger('year')->nullable();
            $table->string('period', 8)->nullable();
            $table->string('issue_kind', 48);
            $table->string('severity', 16);
            $table->text('detail');
            $table->text('detected_value')->nullable();
            $table->text('expected_value')->nullable();
            $table->string('source_label', 255)->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('resolution_kind', 32)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'year', 'severity', 'resolved_at'], 'idx_dqi_rgn_yr_sev');
            $table->index(['issue_kind', 'severity'], 'idx_dqi_kind_severity');
            $table->index('import_run_id', 'idx_dqi_run');

            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('indicator_code')->references('code')->on('indicators')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_issues');
    }
};
