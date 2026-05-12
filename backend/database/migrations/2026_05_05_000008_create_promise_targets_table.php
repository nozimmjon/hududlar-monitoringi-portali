<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promise_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guarantee_letter_id')->constrained('guarantee_letters')->cascadeOnDelete();
            $table->unsignedInteger('region_code');
            $table->smallInteger('year');
            $table->string('kind', 16);                                  // 'numeric' | 'narrative'
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('sector', 96)->nullable();
            $table->string('indicator_code', 48)->nullable();
            $table->string('period', 8)->nullable();
            $table->decimal('target_value', 20, 6)->nullable();
            $table->string('target_text', 128)->nullable();
            $table->string('direction', 16)->nullable();                  // 'higher' | 'lower' | 'unspecified'
            $table->jsonb('target_districts')->nullable();                // ['city', 'shahrikhan_district']
            $table->integer('source_paragraph_index')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'year'], 'idx_pt_region_year');
            $table->index(['indicator_code', 'period'], 'idx_pt_indicator_period');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('indicator_code')->references('code')->on('indicators')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promise_targets');
    }
};
