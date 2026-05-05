<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guarantee_letters', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->smallInteger('year');
            $table->string('source_path', 512)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->integer('paragraph_count')->nullable();
            $table->text('raw_text')->nullable();
            $table->date('signed_at')->nullable();
            $table->string('status', 16)->default('pending');     // 'pending' | 'imported' | 'archived'
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['region_code', 'year'], 'uq_guarantee_letter');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('year')->references('year')->on('reporting_years');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantee_letters');
    }
};
