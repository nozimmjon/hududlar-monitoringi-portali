<?php

use App\Support\DistrictTableConfig;

test('industry returns 4 growth columns', function () {
    $cfg = DistrictTableConfig::for('industry');
    expect($cfg)->toHaveKeys(['title', 'description', 'source', 'primary_period', 'columns']);
    expect($cfg['primary_period'])->toBe('h1');
    expect($cfg['columns'])->toHaveCount(4);

    expect($cfg['columns'][0]['label'])->toBe('I чорак амалда');
    expect($cfg['columns'][0]['metric'])->toBe(['kpi' => 'industry', 'period' => 'q1', 'kind' => 'growth']);
    expect($cfg['columns'][0]['note'])->toBe('fact');

    expect($cfg['columns'][3]['label'])->toBe('Йиллик прогноз');
    expect($cfg['columns'][3]['metric']['period'])->toBe('year');
});

test('budget returns 3 execution columns', function () {
    $cfg = DistrictTableConfig::for('budget');
    expect($cfg['columns'])->toHaveCount(3);
    foreach ($cfg['columns'] as $col) {
        expect($col['metric']['kind'])->toBe('execution');
        expect($col['metric']['kpi'])->toBe('budget');
    }
});

test('unemployment references cross-indicators jobs and legalization', function () {
    $cfg = DistrictTableConfig::for('unemployment');
    $kpis = array_map(fn ($c) => $c['metric']['kpi'] ?? null, $cfg['columns']);
    expect($kpis)->toBe(['unemployment', 'unemployment', 'jobs', 'legalization']);
});

test('localization includes fieldColumn rows with null metric', function () {
    $cfg = DistrictTableConfig::for('localization');
    expect($cfg['columns'])->toHaveCount(4);
    $nullMetricLabels = array_column(
        array_filter($cfg['columns'], fn ($c) => $c['metric'] === null),
        'label'
    );
    expect($nullMetricLabels)->toContain('H1 қиймат', 'Йиллик қиймат');
});

test('unknown kpi falls back to export config', function () {
    $cfg = DistrictTableConfig::for('xyz_unknown');
    $exportCfg = DistrictTableConfig::for('export');
    expect($cfg['title'])->toBe($exportCfg['title']);
});

test('all 18 documented kpis return non-empty columns', function () {
    $kpis = [
        'grp', 'industry', 'agriculture', 'services', 'localization',
        'energy_electricity', 'energy_gas', 'inflation', 'budget',
        'budget_investment', 'investment', 'export', 'unemployment',
        'poverty', 'jobs', 'legalization', 'mfy_clear', 'microprojects',
    ];
    foreach ($kpis as $kpi) {
        $cfg = DistrictTableConfig::for($kpi);
        expect($cfg['title'])->toBeString()->not->toBe('');
        expect($cfg['columns'])->toBeArray()->not->toBeEmpty();
        expect($cfg['primary_period'])->toBeIn(['q1', 'q2', 'h1', 'm9', 'year']);
    }
});
