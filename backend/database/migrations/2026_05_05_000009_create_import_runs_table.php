<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('region_code');
            $table->smallInteger('year');
            $table->foreignId('triggered_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('trigger_kind', 16);                          // 'cli' | 'filament' | 'scheduled'
            $table->string('status', 16);
            $table->timestamp('started_at');
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('files_processed')->default(0);
            $table->integer('rows_staged')->default(0);
            $table->integer('rows_promoted')->default(0);
            $table->integer('issues_open_count')->default(0);
            $table->integer('issues_blocker_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'year', 'status'], 'idx_runs_rgn_yr_status');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('year')->references('year')->on('reporting_years');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
