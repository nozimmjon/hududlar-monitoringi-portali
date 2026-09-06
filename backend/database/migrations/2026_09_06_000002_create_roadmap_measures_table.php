<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roadmap_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_id')->constrained('roadmaps')->cascadeOnDelete();
            $table->smallInteger('section_no');                          // Roman numeral of the section header → int
            $table->string('section_title', 255);                        // header text after the numeral
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('district_head_text', 255)->nullable();       // "туман ҳокими Ж.Назаров"
            $table->smallInteger('seq_no');                              // 1-based inside (section, district)
            $table->text('title');                                       // first line / text before "жумладан"
            $table->text('details')->nullable();                         // remaining lines, "\n"-separated
            $table->text('body_raw');                                    // full cell text, "\n"-separated
            $table->text('funding_text')->nullable();                    // column 3
            $table->string('deadline_text', 128)->nullable();            // column 4
            $table->text('responsible_text')->nullable();                // column 5
            $table->smallInteger('source_row');                          // 0-based row in the docx table
            $table->timestamps();

            // NULL district_id rows are distinct for Postgres; the importer guarantees
            // uniqueness itself, the index guards the district case.
            $table->unique(['roadmap_id', 'section_no', 'district_id', 'seq_no'], 'uq_roadmap_measures_pos');
            $table->index(['roadmap_id', 'district_id'], 'idx_roadmap_measures_district');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_measures');
    }
};
