<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('region_workbook_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_workbook_id')->constrained('region_workbooks')->cascadeOnDelete();
            $table->string('sheet_name', 128);                  // actual sheet name in the .xlsx
            $table->string('logical_kind', 32);                 // 'rollup' | 'district_table' | 'warehouses' | 'comparison' | 'export_detail' | 'info' | ...
            $table->smallInteger('header_row')->nullable();     // detected by importer, cached
            $table->smallInteger('district_start_row')->nullable();
            $table->string('source_label', 255)->nullable();    // "1.1-1.5-жадваллар (макро).xlsx · 1.1. ЯҲМ"
            $table->jsonb('detection_hints')->nullable();       // signature strings the importer matched on
            $table->timestamps();

            $table->index(['region_workbook_id', 'logical_kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_workbook_sheets');
    }
};
