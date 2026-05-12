<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedInteger('code')->unique();              // slug: andijan, fergana, ...
            $table->string('name_short', 64);                  // "Андижон"
            $table->string('name_full', 128);                  // "Андижон вилояти"
            $table->string('name_latin', 64)->nullable();      // "Andijan"
            $table->string('folder_name', 128)->nullable();    // "2. Андижон"
            $table->smallInteger('sort_order');
            $table->boolean('has_districts')->default(true);   // false for Tashkent city
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
