<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanEmploymentDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 1703)
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

/** Maps DATA blob employment keys to (indicator_code, period). */
function employmentKeyMapping(): array
{
    return [
        'unemployment_h1'    => ['unemployment',  'h1'],
        'unemployment_year'  => ['unemployment',  'year'],
        'poverty_h1'         => ['poverty',       'h1'],
        'poverty_year'       => ['poverty',       'year'],
        'mfy_h1'             => ['mfy_clear',     'h1'],
        'mfy_year'           => ['mfy_clear',     'year'],
        'jobs_h1'            => ['jobs',          'h1'],
        'jobs_year'          => ['jobs',          'year'],
        'legalization_h1'    => ['legalization',  'h1'],
        'legalization_year'  => ['legalization',  'year'],
        'microprojects_h1'   => ['microprojects', 'h1'],
        'microprojects_year' => ['microprojects', 'year'],
    ];
}

function assertEmploymentCellMatches($actual, mixed $expectedValue): void
{
    if (is_string($expectedValue) && str_contains($expectedValue, 'холи ҳудуд')) {
        expect($actual->is_sentinel)->toBeTrue();
        expect($actual->sentinel_label)->toContain('холи ҳудуд');
        expect($actual->plan_value)->toBeNull();
    } else {
        expect($actual->is_sentinel)->toBeFalse();
        if (is_numeric($expectedValue)) {
            expect($actual->plan_value)->toBeNumericallyClose($expectedValue, 0.05);
        }
    }
}

test('Andijan employment import reproduces DATA.regional.employment and DATA.districts[*].data.employment with sentinel support', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx'))) {
        $this->markTestSkipped('Andijan employment data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'employment',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->whereIn('indicator_code', ['unemployment','poverty','jobs','legalization','mfy_clear','microprojects'])
        ->get();

    expect($rows)->toHaveCount(204);

    $mapping = employmentKeyMapping();

    // Region rollup
    $regional = $expected['regional']['employment'];
    foreach ($mapping as $dataKey => [$indicatorCode, $period]) {
        if (! isset($regional[$dataKey])) continue;
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null
            && $r->indicator_code === $indicatorCode
            && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for $indicatorCode/$period");
        assertEmploymentCellMatches($actual, $regional[$dataKey]);
    }

    // District rows
    $matched = 0;
    foreach ($expected['districts'] as $expectedDistrict) {
        $emp = $expectedDistrict['data']['employment'] ?? null;
        if ($emp === null) continue;

        $districtCode = andijanEmploymentDistrictCode($expectedDistrict['name']);
        if ($districtCode === null) continue;

        foreach ($mapping as $dataKey => [$indicatorCode, $period]) {
            if (! isset($emp[$dataKey])) continue;
            $actual = $rows->first(fn ($r) =>
                $r->district_code === $districtCode
                && $r->indicator_code === $indicatorCode
                && $r->period->value === $period
            );
            if ($actual === null) continue;
            $matched++;
            assertEmploymentCellMatches($actual, $emp[$dataKey]);
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(150);   // 16 districts × 12 cells, allow some misses

    // Sentinel issues should exist (Андижон шаҳри poverty_year)
    $sentinels = DB::table('data_quality_issues')
        ->where('import_run_id', $run->id)
        ->where('issue_kind', 'sentinel')->count();
    expect($sentinels)->toBeGreaterThan(0);
});
