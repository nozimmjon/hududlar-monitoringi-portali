<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            // Real organisation name for the UI; null when name_short already is one.
            // name_short itself must stay the sheet-matching label (import relies on it).
            $table->string('display_name')->nullable()->after('name_short');
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
