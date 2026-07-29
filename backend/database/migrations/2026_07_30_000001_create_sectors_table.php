<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 48)->unique();          // 'uzbekneftgaz', 'nkmk', ...
            $table->string('name_short', 96);              // "Ўзбекнефтгаз" (sheet name)
            $table->string('org_full', 255);               // "«Ўзбекнефтгаз» АЖ"
            $table->string('signer_text', 255)->nullable();// "Бошқарув раиси А. Сангинов"
            $table->smallInteger('sort_order');            // sheet order 1–17
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
