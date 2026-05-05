<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_staging_indicator_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();

            $table->string('region_code', 32);
            $table->string('district_code', 64)->nullable();
            $table->smallInteger('year');
            $table->string('indicator_code', 48);
            $table->string('period', 8);

            $table->decimal('plan_value', 20, 6)->nullable();
            $table->decimal('expected_value', 20, 6)->nullable();
            $table->decimal('actual_hokimyat', 20, 6)->nullable();
            $table->decimal('actual_statkom', 20, 6)->nullable();
            $table->decimal('growth_pct', 10, 4)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();
            $table->integer('count_extra')->nullable();
            $table->integer('count_extra_2')->nullable();

            $table->boolean('is_sentinel')->default(false);
            $table->string('sentinel_label', 64)->nullable();

            $table->string('unit', 48);
            $table->string('source_label', 255);
            $table->timestamp('hokimyat_reported_at')->nullable();
            $table->timestamp('statkom_published_at')->nullable();

            $table->string('staging_status', 16)->default('pending');
            $table->jsonb('validation_errors')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'staging_status'], 'idx_stg_facts_run_status');
            $table->index(['region_code', 'district_code', 'year', 'indicator_code'],
                          'idx_stg_facts_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staging_indicator_facts');
    }
};
