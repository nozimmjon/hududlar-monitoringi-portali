<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The guarantee-letter parsing pipeline (guarantee_letters → promise_targets,
     * tasks.guarantee_letter_id) was designed but never populated: both tables held
     * 0 rows and the only consumer was an always-empty query on the districts page.
     * Tasks now come from the partner XLSX import instead. Restorable from git if
     * the feature is ever revived.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guarantee_letter_id');
        });

        Schema::dropIfExists('promise_targets');
        Schema::dropIfExists('guarantee_letters');
    }

    public function down(): void
    {
        Schema::create('guarantee_letters', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('region_code');
            $table->smallInteger('year');
            $table->string('source_path', 512)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->integer('paragraph_count')->nullable();
            $table->text('raw_text')->nullable();
            $table->date('signed_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['region_code', 'year'], 'uq_guarantee_letter');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('year')->references('year')->on('reporting_years');
        });

        Schema::create('promise_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guarantee_letter_id')->constrained('guarantee_letters')->cascadeOnDelete();
            $table->unsignedInteger('region_code');
            $table->smallInteger('year');
            $table->string('kind', 16);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('sector', 96)->nullable();
            $table->string('indicator_code', 48)->nullable();
            $table->string('period', 8)->nullable();
            $table->decimal('target_value', 20, 6)->nullable();
            $table->string('target_text', 128)->nullable();
            $table->string('direction', 16)->nullable();
            $table->jsonb('target_districts')->nullable();
            $table->integer('source_paragraph_index')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'year'], 'idx_pt_region_year');
            $table->index(['indicator_code', 'period'], 'idx_pt_indicator_period');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('indicator_code')->references('code')->on('indicators')->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('guarantee_letter_id')->nullable()
                  ->after('region_code')
                  ->constrained('guarantee_letters')->nullOnDelete();
        });
    }
};
