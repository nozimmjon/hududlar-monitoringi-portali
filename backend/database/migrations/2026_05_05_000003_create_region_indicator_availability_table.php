<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('region_indicator_availability', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->string('indicator_code', 48);
            $table->string('status', 16)->default('available');
            $table->text('note')->nullable();
            $table->date('blocked_until')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['region_code', 'indicator_code'], 'uq_ria_region_indicator');
            $table->foreign('region_code')->references('code')->on('regions')->cascadeOnDelete();
            $table->foreign('indicator_code')->references('code')->on('indicators')->cascadeOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_indicator_availability');
    }
};
