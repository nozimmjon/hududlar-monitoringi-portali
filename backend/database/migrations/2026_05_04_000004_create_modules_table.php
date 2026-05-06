<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();    // 'macro', 'inflation', 'budget', ...
            $table->string('label', 128);            // Cyrillic
            $table->text('description')->nullable();
            $table->smallInteger('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
