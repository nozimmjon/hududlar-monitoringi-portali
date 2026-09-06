<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 24)->default('water');     // road-map family; 'water' for now
            $table->unsignedInteger('region_code');             // SOATO, FK regions.code
            $table->smallInteger('year');
            $table->text('title_text');                         // the three title paragraphs joined
            $table->text('approvers_text')->nullable();         // table-1 "ТАСДИҚЛАЙМАН" cells joined with ' | '
            $table->string('source_file', 255);                 // basename of the imported docx
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->foreign('region_code')->references('code')->on('regions');
            $table->unique(['domain', 'region_code', 'year'], 'uq_roadmaps_domain_region_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmaps');
    }
};
