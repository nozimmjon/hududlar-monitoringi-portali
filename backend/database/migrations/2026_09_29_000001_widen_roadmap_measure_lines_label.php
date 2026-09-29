<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The regions' own road maps carry indicator labels far longer than a label was assumed
 * to be — Қашқадарё D17 is 333 characters, Фарғона D6 is 290 — and varchar(255) meant the
 * importer silently cut them. The column becomes text; the parsers keep a 4 000-character
 * sanity limit (RoadmapMeasureLine::LABEL_MAX) that aborts naming the cell, because a
 * label that long is a pasted paragraph, not an indicator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_measure_lines', function (Blueprint $table) {
            $table->text('label')->change();
        });
    }

    /** Reverting truncates: any label longer than 255 must be shortened first. */
    public function down(): void
    {
        Schema::table('roadmap_measure_lines', function (Blueprint $table) {
            $table->string('label', 255)->change();
        });
    }
};
