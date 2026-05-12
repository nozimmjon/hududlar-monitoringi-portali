<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_staging_food_balance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();

            $table->unsignedInteger('region_code');
            $table->smallInteger('year');
            $table->string('product', 96);
            $table->smallInteger('product_sort_order')->default(0);

            $table->decimal('resource_total', 20, 6)->nullable();
            $table->decimal('year_start_stock', 20, 6)->nullable();
            $table->decimal('production', 20, 6)->nullable();
            $table->decimal('import_volume', 20, 6)->nullable();
            $table->decimal('use_total', 20, 6)->nullable();
            $table->decimal('use_household', 20, 6)->nullable();
            $table->decimal('use_processing', 20, 6)->nullable();
            $table->decimal('use_other', 20, 6)->nullable();
            $table->decimal('per_capita_norm', 20, 6)->nullable();
            $table->decimal('per_capita_balance', 20, 6)->nullable();
            $table->decimal('local_supply_ratio', 20, 6)->nullable();
            $table->decimal('year_end_stock', 20, 6)->nullable();

            $table->string('source_label', 255);
            $table->string('staging_status', 16)->default('pending');
            $table->jsonb('validation_errors')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'staging_status'], 'idx_stg_food_run_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staging_food_balance');
    }
};
