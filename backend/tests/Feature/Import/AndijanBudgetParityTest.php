<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanBudgetDistrictCode(string $nameFull): ?int
{
    $code = DB::table('districts')
        ->where('region_code', 1703)
        ->where('name_full', $nameFull)
        ->value('code');
    if ($code !== null) return (int) $code;

    $districts = DB::table('districts')->where('region_code', 1703)->get(['code', 'alt_labels']);
    foreach ($districts as $d) {
        $alts = json_decode($d->alt_labels ?? '[]', true) ?: [];
        if (in_array($nameFull, $alts, true)) {
            return (int) $d->code;
        }
    }
    return null;
}

test('Andijan budget import reproduces DATA.regional.budget and DATA.districts[*].data.budget within 0.05', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/3-жадвал (бюджет).xlsx'))) {
        $this->markTestSkipped('Andijan budget data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'budget',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'budget')->get();

    expect($rows)->toHaveCount(51);

    // ----- Region rollup: 3 periods -----
    $regional = $expected['regional']['budget'];
    foreach (['year', 'h1', 'q2'] as $period) {
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for period $period");
        if (isset($regional["{$period}_plan"])) {
            expect($actual->plan_value)->toBeNumericallyClose($regional["{$period}_plan"], 0.05);
        }
        if (isset($regional["{$period}_expected"])) {
            expect($actual->expected_value)->toBeNumericallyClose($regional["{$period}_expected"], 0.05);
        }
        if (isset($regional["{$period}_execution_pct"])) {
            expect($actual->pct_of_plan)->toBeNumericallyClose($regional["{$period}_execution_pct"], 0.05);
        }
    }

    // ----- District rows -----
    $matched = 0;
    $unmatched = [];
    foreach ($expected['districts'] as $expectedDistrict) {
        $b = $expectedDistrict['data']['budget'] ?? null;
        if ($b === null) continue;

        $districtCode = andijanBudgetDistrictCode($expectedDistrict['name']);
        if ($districtCode === null) {
            $unmatched[] = "lookup-failed:{$expectedDistrict['name']}";
            continue;
        }

        foreach (['year', 'h1', 'q2'] as $period) {
            $actual = $rows->first(fn ($r) =>
                $r->district_code === $districtCode && $r->period->value === $period
            );
            if ($actual === null) {
                $unmatched[] = "no-row:{$districtCode}/{$period}";
                continue;
            }
            $matched++;

            if (isset($b["{$period}_plan"])) {
                expect($actual->plan_value)->toBeNumericallyClose($b["{$period}_plan"], 0.05);
            }
            if (isset($b["{$period}_expected"])) {
                expect($actual->expected_value)->toBeNumericallyClose($b["{$period}_expected"], 0.05);
            }
            if (isset($b["{$period}_execution_pct"])) {
                expect($actual->pct_of_plan)->toBeNumericallyClose($b["{$period}_execution_pct"], 0.05);
            }
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(45);

    if (! empty($unmatched)) {
        echo "\nUnmatched budget entries: " . implode(', ', $unmatched) . "\n";
    }
});
