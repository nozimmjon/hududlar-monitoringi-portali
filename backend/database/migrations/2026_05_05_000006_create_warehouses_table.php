<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->string('district_code', 64)->nullable();

            $table->smallInteger('year');

            $table->integer('reserve_warehouses')->nullable();
            $table->integer('reserve_capacity_t')->nullable();
            $table->integer('cold_storage_count')->nullable();
            $table->integer('cold_storage_capacity_t')->nullable();
            $table->integer('new_small_cold_count')->nullable();
            $table->integer('new_small_cold_capacity_t')->nullable();
            $table->integer('new_small_cold_mfys')->nullable();
            $table->integer('new_large_cold_count')->nullable();
            $table->integer('new_large_cold_capacity_t')->nullable();

            $table->string('source_label', 255);
            $table->timestamps();

            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign(['region_code', 'district_code'])
                  ->references(['region_code', 'code'])->on('districts');
            $table->foreign('year')->references('year')->on('reporting_years');
        });

        // PostgreSQL treats NULL as distinct in multi-column unique indexes, so a single
        // composite unique can't dedupe region-rollup rows (district_code IS NULL).
        // Use two partial unique indexes to cover both cases — same pattern as indicator_facts.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_warehouses_district
                ON warehouses (region_code, district_code, year)
                WHERE district_code IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_warehouses_rollup
                ON warehouses (region_code, year)
                WHERE district_code IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
