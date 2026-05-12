<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('food_balance', function (Blueprint $table) {
            $table->id();
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
            $table->timestamps();

            $table->unique(['region_code', 'year', 'product'], 'uq_food_balance');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('year')->references('year')->on('reporting_years');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_balance');
    }
};
