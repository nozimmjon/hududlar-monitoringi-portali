<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('indicator_facts', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->string('district_code', 64)->nullable();         // NULL = region rollup
            $table->smallInteger('year');
            $table->string('indicator_code', 48);
            $table->string('period', 8);                              // q1 | h1 | m9 | year

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
            $table->timestamps();

            $table->unique(
                ['region_code', 'district_code', 'year', 'indicator_code', 'period'],
                'uq_indicator_facts'
            );
            $table->index(['region_code', 'year', 'indicator_code'], 'idx_facts_rgn_yr_ind');
            $table->index(['region_code', 'district_code', 'year'], 'idx_facts_rgn_dist_yr');
            $table->index(['year', 'indicator_code', 'period'], 'idx_facts_yr_ind_per');

            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign(['region_code', 'district_code'])
                  ->references(['region_code', 'code'])->on('districts');
            $table->foreign('year')->references('year')->on('reporting_years');
            $table->foreign('indicator_code')->references('code')->on('indicators');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_facts');
    }
};
