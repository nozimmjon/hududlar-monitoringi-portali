<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Budget-investment H1 and year figures are «кутилиш (тезкор)» — operational
 * FORECASTS — but the old parser wrote them into actual_hokimyat, so the UI
 * coloured a forecast as achievement. Move those forecasts into expected_value
 * (leaving Q1 «амалда» untouched, and leaving region rows the tasks bridge has
 * already filled with a real reported actual, i.e. hokimyat_reported_at set).
 *
 * The fixed BudgetInvestModuleParser keeps future imports correct; this backfills
 * the data already loaded for all 14 regions.
 */
return new class extends Migration {
    public function up(): void
    {
        // District rows: the bridge never touches districts, so every H1/year
        // actual here is a кутилиш value -> move it.
        DB::table('indicator_facts')
            ->where('indicator_code', 'budget_investment')
            ->whereIn('period', ['h1', 'year'])
            ->whereNotNull('district_code')
            ->whereNotNull('actual_hokimyat')
            ->whereNull('expected_value')
            ->update([
                'expected_value'  => DB::raw('actual_hokimyat'),
                'actual_hokimyat' => null,
            ]);

        // Region rollup rows: only those NOT filled by the tasks bridge
        // (hokimyat_reported_at is null) are forecasts.
        DB::table('indicator_facts')
            ->where('indicator_code', 'budget_investment')
            ->whereIn('period', ['h1', 'year'])
            ->whereNull('district_code')
            ->whereNull('hokimyat_reported_at')
            ->whereNotNull('actual_hokimyat')
            ->whereNull('expected_value')
            ->update([
                'expected_value'  => DB::raw('actual_hokimyat'),
                'actual_hokimyat' => null,
            ]);
    }

    public function down(): void
    {
        // Restore the forecast back into actual_hokimyat for rows this migration moved.
        DB::table('indicator_facts')
            ->where('indicator_code', 'budget_investment')
            ->whereIn('period', ['h1', 'year'])
            ->whereNull('actual_hokimyat')
            ->whereNotNull('expected_value')
            ->update([
                'actual_hokimyat' => DB::raw('expected_value'),
                'expected_value'  => null,
            ]);
    }
};
