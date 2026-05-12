<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanForeignInvestDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 1703)
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

function assertForeignInvestPeriodRow($actual, array $e, string $period): void
{
    if ($period === 'q1') {
        expect($actual->plan_value)->toBeNumericallyClose($e['q1_plan'], 0.5);
        expect($actual->actual_hokimyat)->toBeNumericallyClose($e['q1_actual'], 0.5);
        expect($actual->expected_value)->toBeNull();
        expect($actual->count_extra)->toBe($e['q1_projects']);
        expect($actual->count_extra_2)->toBeNull();
    } elseif ($period === 'h1') {
        expect($actual->plan_value)->toBeNumericallyClose($e['h1_plan'], 0.5);
        expect($actual->expected_value)->toBeNumericallyClose($e['h1_expected'], 0.5);
        expect($actual->actual_hokimyat)->toBeNull();
        expect($actual->count_extra)->toBe($e['h1_projects']);
        expect($actual->count_extra_2)->toBe($e['h1_jobs']);
    } else { // year
        expect($actual->plan_value)->toBeNumericallyClose($e['year_forecast'], 0.5);
        expect($actual->expected_value)->toBeNumericallyClose($e['year_expected'], 0.5);
        expect($actual->actual_hokimyat)->toBeNull();
        expect($actual->count_extra)->toBeNull();
        expect($actual->count_extra_2)->toBeNull();
    }
    if (isset($e["{$period}_pct"]) && is_numeric($e["{$period}_pct"])) {
        expect($actual->pct_of_plan)->toBeNumericallyClose($e["{$period}_pct"], 0.05);
    }
}

test('Andijan foreign_invest import reproduces DATA.regional.foreign_investment and DATA.districts[*].data.foreign_investment within tolerance', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx'))) {
        $this->markTestSkipped('Andijan foreign_invest data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'foreign_invest',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'investment')->get();

    expect($rows)->toHaveCount(51);

    // Region rollup
    $regional = $expected['regional']['foreign_investment'];
    foreach (['q1', 'h1', 'year'] as $period) {
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for period $period");
        assertForeignInvestPeriodRow($actual, $regional, $period);
    }

    // District rows
    $matched = 0;
    $unmatched = [];
    foreach ($expected['districts'] as $expectedDistrict) {
        $fi = $expectedDistrict['data']['foreign_investment'] ?? null;
        if ($fi === null) continue;

        $districtCode = andijanForeignInvestDistrictCode($expectedDistrict['name']);
        if ($districtCode === null) {
            $unmatched[] = "lookup-failed:{$expectedDistrict['name']}";
            continue;
        }

        foreach (['q1', 'h1', 'year'] as $period) {
            $actual = $rows->first(fn ($r) =>
                $r->district_code === $districtCode && $r->period->value === $period
            );
            if ($actual === null) {
                $unmatched[] = "no-row:{$districtCode}/{$period}";
                continue;
            }
            $matched++;
            assertForeignInvestPeriodRow($actual, $fi, $period);
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(45);

    if (! empty($unmatched)) {
        echo "\nUnmatched foreign_invest entries: " . implode(', ', $unmatched) . "\n";
    }
});
