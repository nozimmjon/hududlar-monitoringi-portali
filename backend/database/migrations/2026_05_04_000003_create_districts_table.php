<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->unsignedInteger('code');                    // slug, unique within region
            $table->string('name_short', 96);              // "Андижон ш." (workbook abbreviation)
            $table->string('name_full', 128);              // "Андижон шаҳри"
            $table->string('name_latin', 96)->nullable();
            $table->jsonb('alt_labels')->nullable();       // ["Андижон шаҳар", "Andijan", ...]
            $table->string('kind', 16);                    // 'city' | 'district' | 'special'
            $table->smallInteger('sort_order');
            $table->timestamps();

            $table->unique(['region_id', 'code']);
            $table->index(['region_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
