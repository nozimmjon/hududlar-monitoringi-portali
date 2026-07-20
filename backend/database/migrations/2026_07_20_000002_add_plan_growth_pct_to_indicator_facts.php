<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('indicator_facts', function (Blueprint $table) {
            // The promised growth rate for the period. growth_pct holds the reported
            // one once TaskFactBridge writes an actual, so the promise needs its own
            // column — otherwise plan-vs-fact is lost for macro KPIs.
            $table->decimal('plan_growth_pct', 10, 4)->nullable()->after('growth_pct');
        });
    }

    public function down(): void
    {
        Schema::table('indicator_facts', function (Blueprint $table) {
            $table->dropColumn('plan_growth_pct');
        });
    }
};
