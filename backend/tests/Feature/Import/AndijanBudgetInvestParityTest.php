<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanBudgetInvestDistrictCode(string $nameFull): ?int
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

test('Andijan budget_invest import reproduces DATA.regional.budget_investment and DATA.districts[*].data.budget_investment within tolerance', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx'))) {
        $this->markTestSkipped('Andijan budget_invest data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'budget_invest',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'budget_investment')->get();

    expect($rows)->toHaveCount(51);

    // ----- Region rollup: 3 periods -----
    $regional = $expected['regional']['budget_investment'];
    foreach (['q1', 'h1', 'year'] as $period) {
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for period $period");
        expect($actual->plan_value)->toBeNumericallyClose($regional['limit'], 0.5);
        if (isset($regional["{$period}_absorption"])) {
            expect($actual->actual_hokimyat)->toBeNumericallyClose($regional["{$period}_absorption"], 0.5);
        }
        if (isset($regional["{$period}_pct"])) {
            expect($actual->pct_of_plan)->toBeNumericallyClose($regional["{$period}_pct"], 0.05);
        }
        expect($actual->count_extra)->toBe($regional['objects']);
        if ($period === 'year' && isset($regional['commissioning_year_count'])) {
            expect($actual->count_extra_2)->toBe($regional['commissioning_year_count']);
        } else {
            expect($actual->count_extra_2)->toBeNull();
        }
    }

    // ----- District rows -----
    $matched = 0;
    $unmatched = [];
    foreach ($expected['districts'] as $expectedDistrict) {
        $bi = $expectedDistrict['data']['budget_investment'] ?? null;
        if ($bi === null) continue;

        $districtCode = andijanBudgetInvestDistrictCode($expectedDistrict['name']);
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

            expect($actual->plan_value)->toBeNumericallyClose($bi['limit'], 0.5);
            if (isset($bi["{$period}_absorption"])) {
                expect($actual->actual_hokimyat)->toBeNumericallyClose($bi["{$period}_absorption"], 0.5);
            }
            if (isset($bi["{$period}_pct"])) {
                expect($actual->pct_of_plan)->toBeNumericallyClose($bi["{$period}_pct"], 0.05);
            }
            expect($actual->count_extra)->toBe($bi['objects']);
            if ($period === 'year' && isset($bi['commissioning_year_count'])) {
                expect($actual->count_extra_2)->toBe($bi['commissioning_year_count']);
            }
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(45);

    if (! empty($unmatched)) {
        echo "\nUnmatched budget_invest entries: " . implode(', ', $unmatched) . "\n";
    }
});
