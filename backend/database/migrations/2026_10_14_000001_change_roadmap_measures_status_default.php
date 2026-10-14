<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** New measures have no progress rows yet — the honest default is no_report (spec 2026-10-14). Existing rows are rewritten by `roadmaps:recompute`, run once after deploy. */
    public function up(): void
    {
        Schema::table('roadmap_measures', function (Blueprint $table) {
            $table->string('status', 16)->default('no_report')->change();
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_measures', function (Blueprint $table) {
            $table->string('status', 16)->default('in_progress')->change();
        });
    }
};
