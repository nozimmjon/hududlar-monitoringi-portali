<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 1703)
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

test('Andijan macro import reproduces the inlined DATA blob within 1e-6', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx'))) {
        $this->markTestSkipped('Andijan data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'macro',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);
    expect($run->rows_staged)->toBe(212);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)->get();

    // ----- Region rollup: 5 indicators × 4 periods = 20 rows -----
    $rollupIndicators = ['grp', 'industry', 'agriculture', 'construction', 'services'];
    foreach ($expected['regional']['macro'] as $i => $expectedRow) {
        $code = $rollupIndicators[$i];
        foreach (['q1', 'h1', 'm9', 'year'] as $period) {
            $actual = $rows->first(fn ($r) =>
                $r->indicator_code === $code &&
                $r->district_code === null &&
                $r->period->value === $period
            );
            expect($actual)->not->toBeNull("missing rollup row $code/$period");
            expect($actual->plan_value)->toBeNumericallyClose($expectedRow["{$period}_value"], 0.05);
            if ($expectedRow["{$period}_growth"] !== null) {
                expect($actual->growth_pct)->toBeNumericallyClose($expectedRow["{$period}_growth"], 0.05);
            }
        }
    }

    // ----- District rows: 16 × 3 indicators × 4 periods = 192 rows -----
    foreach ($expected['districts'] as $expectedDistrict) {
        $districtCode = andijanDistrictCode($expectedDistrict['name']);
        expect($districtCode)->not->toBeNull("district code lookup failed for {$expectedDistrict['name']}");

        foreach (['industry', 'agriculture', 'services'] as $indicator) {
            $block = $expectedDistrict['data'][$indicator] ?? null;
            if ($block === null) continue;

            foreach (['q1', 'h1', 'm9', 'year'] as $period) {
                $actual = $rows->first(fn ($r) =>
                    $r->indicator_code === $indicator &&
                    $r->district_code === $districtCode &&
                    $r->period->value === $period
                );
                expect($actual)->not->toBeNull("missing $districtCode/$indicator/$period");
                expect($actual->plan_value)->toBeNumericallyClose($block["{$period}_value"], 0.05);
                if (isset($block["{$period}_growth"]) && $block["{$period}_growth"] !== null) {
                    expect($actual->growth_pct)->toBeNumericallyClose($block["{$period}_growth"], 0.05);
                }
            }
        }
    }
});
