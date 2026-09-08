<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_measures', function (Blueprint $table) {
            $table->string('latest_period', 16)->nullable();                 // max period among the measure's progress rows
            $table->string('status', 16)->default('in_progress');           // in_progress | done | open
            $table->decimal('pct', 6, 2)->nullable();                        // mean of capped line percents, latest period
            $table->smallInteger('lines_total')->default(0);                 // lines with a plan
            $table->smallInteger('lines_done')->default(0);                  // of those, ≥100 % in the latest period
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_measures', function (Blueprint $table) {
            $table->dropColumn(['latest_period', 'status', 'pct', 'lines_total', 'lines_done']);
        });
    }
};
