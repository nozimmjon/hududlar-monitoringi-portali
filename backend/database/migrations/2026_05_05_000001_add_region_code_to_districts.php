<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->string('region_code', 32)->nullable()->after('region_id');
        });

        DB::statement(<<<'SQL'
            UPDATE districts d
               SET region_code = r.code
              FROM regions r
             WHERE d.region_id = r.id
        SQL);

        Schema::table('districts', function (Blueprint $table) {
            $table->string('region_code', 32)->nullable(false)->change();
            $table->unique(['region_code', 'code'], 'uq_districts_region_code_code');
            $table->index('region_code', 'idx_districts_region_code');
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->dropUnique('uq_districts_region_code_code');
            $table->dropIndex('idx_districts_region_code');
            $table->dropColumn('region_code');
        });
    }
};
