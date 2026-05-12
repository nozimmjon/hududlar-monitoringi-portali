<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_staging_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();

            $table->unsignedInteger('region_code');
            $table->unsignedInteger('district_code')->nullable();
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
            $table->string('staging_status', 16)->default('pending');
            $table->jsonb('validation_errors')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'staging_status'], 'idx_stg_warehouses_run_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staging_warehouses');
    }
};
