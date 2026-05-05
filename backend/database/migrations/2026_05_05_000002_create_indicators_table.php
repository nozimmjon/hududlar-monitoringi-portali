<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 48)->unique();
            $table->string('label_full', 192);
            $table->string('label_short', 96);
            $table->string('sector', 96)->nullable();
            $table->string('module_code', 32)->nullable();
            $table->string('scope', 16);                       // 'region' | 'district' | 'both'
            $table->string('default_unit', 48);
            $table->boolean('lower_is_better')->default(false);
            $table->jsonb('supported_periods')->default(json_encode(['q1','h1','m9','year']));
            $table->boolean('has_growth_pct')->default(false);
            $table->boolean('has_pct_of_plan')->default(false);
            $table->boolean('has_sentinel')->default(false);
            $table->string('count_extra_label', 64)->nullable();
            $table->string('count_extra_2_label', 64)->nullable();
            $table->string('icon', 32)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('module_code')->references('code')->on('modules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
