<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanExportDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 'andijan')
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

function assertExportPeriodRow($actual, array $e, string $period): void
{
    if ($period === 'q1') {
        expect($actual->plan_value)->toBeNull();
        expect($actual->expected_value)->toBeNull();
        expect($actual->actual_hokimyat)->toBeNumericallyClose($e['q1_value'], 0.5);
        expect($actual->count_extra)->toBe($e['q1_exporters']);
    } elseif ($period === 'h1') {
        expect($actual->plan_value)->toBeNull();
        expect($actual->expected_value)->toBeNumericallyClose($e['h1_expected'], 0.5);
        expect($actual->actual_hokimyat)->toBeNull();
        expect($actual->count_extra)->toBe($e['h1_exporters']);
    } else { // year
        expect($actual->plan_value)->toBeNumericallyClose($e['year_forecast'], 0.5);
        expect($actual->expected_value)->toBeNumericallyClose($e['year_expected'], 0.5);
        expect($actual->actual_hokimyat)->toBeNull();
        expect($actual->count_extra)->toBe($e['year_exporters']);
    }
    if (isset($e["{$period}_growth"]) && is_numeric($e["{$period}_growth"])) {
        expect($actual->growth_pct)->toBeNumericallyClose($e["{$period}_growth"], 0.05);
    }
}

test('Andijan export import reproduces DATA.regional.export and DATA.districts[*].data.export within tolerance', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx'))) {
        $this->markTestSkipped('Andijan export data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'export',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'export')->get();

    expect($rows)->toHaveCount(51);

    $regional = $expected['regional']['export'];
    foreach (['q1', 'h1', 'year'] as $period) {
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for period $period");
        assertExportPeriodRow($actual, $regional, $period);
    }

    $matched = 0;
    $unmatched = [];
    foreach ($expected['districts'] as $expectedDistrict) {
        $ex = $expectedDistrict['data']['export'] ?? null;
        if ($ex === null) continue;

        $districtCode = andijanExportDistrictCode($expectedDistrict['name']);
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
            assertExportPeriodRow($actual, $ex, $period);
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(45);

    if (! empty($unmatched)) {
        echo "\nUnmatched export entries: " . implode(', ', $unmatched) . "\n";
    }
});
