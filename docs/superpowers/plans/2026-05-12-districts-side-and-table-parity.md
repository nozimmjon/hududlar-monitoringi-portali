# Districts side aside + detail table parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring `/districts` side aside and detail table to full prototype parity by adding two pure helper classes, rewriting both Blade fragments, and adding per-KPI dynamic columns + T/D/report cells.

**Architecture:** New `DistrictTableConfig` (per-KPI column metadata, 16 entries) and `DistrictMetricResolver` (pure formatter) drive a redesigned `.districts-side` and `.panel.district-detail-table`. Livewire component gains `tableConfig`, `factMatrix`, `taskCountByDistrict`, `targetCountByDistrict` computed properties. Zero new CSS — existing prototype classes already in `portal.css`.

**Tech Stack:** Laravel 11 + Livewire 3 + Pest 3 + PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-05-12-districts-side-and-table-parity-design.md`

**Working directory:** `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali`. All `php artisan` / `vendor/bin/pest` commands run from inside `backend/`. All `git` commands from project root.

---

## File Structure

| File | Responsibility |
|---|---|
| `backend/app/Support/DistrictTableConfig.php` | `for(string $kpi): array` returns config per KPI (16 entries + export fallback) |
| `backend/app/Support/DistrictMetricResolver.php` | `value()` / `note()` / `status()` static formatters |
| `backend/app/Livewire/DistrictsPage.php` | add 4 computed properties + period-sync in `selectKpi` |
| `backend/resources/views/livewire/districts-page.blade.php` | rewrite `.districts-side` + `.panel.district-detail-table` |
| `backend/tests/Unit/DistrictTableConfigTest.php` | per-KPI config shape assertions |
| `backend/tests/Unit/DistrictMetricResolverTest.php` | formatter edge cases |
| `backend/tests/Feature/Http/DistrictsPageTest.php` | extend w/ dynamic column + T/D + leaderboard markup assertions |

---

### Task 1: `DistrictMetricResolver` + unit test

**Files:**
- Create: `backend/app/Support/DistrictMetricResolver.php`
- Create: `backend/tests/Unit/DistrictMetricResolverTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/DistrictMetricResolverTest.php`:

```php
<?php

use App\Models\IndicatorFact;
use App\Support\DistrictMetricResolver;

test('value returns dash on null row', function () {
    expect(DistrictMetricResolver::value(null, 'growth'))->toBe('—');
    expect(DistrictMetricResolver::value(null, 'plan'))->toBe('—');
});

test('value formats growth with sign + comma decimal + percent', function () {
    $row = new IndicatorFact();
    $row->growth_pct = 8.0;
    $row->unit = 'trln';
    expect(DistrictMetricResolver::value($row, 'growth'))->toBe('+8,0%');

    $row->growth_pct = -2.1;
    expect(DistrictMetricResolver::value($row, 'growth'))->toBe('-2,1%');
});

test('value formats execution with sign + percent', function () {
    $row = new IndicatorFact();
    $row->pct_of_plan = 95.0;
    expect(DistrictMetricResolver::value($row, 'execution'))->toBe('+95,0%');
});

test('value formats plan with comma decimal and unit', function () {
    $row = new IndicatorFact();
    $row->plan_value = 100.5;
    $row->unit = 'trln';
    expect(DistrictMetricResolver::value($row, 'plan'))->toBe('100,5 trln');
});

test('value falls back to statkom when hokimyat null', function () {
    $row = new IndicatorFact();
    $row->actual_hokimyat = null;
    $row->actual_statkom = 12.3;
    $row->unit = 'млн';
    expect(DistrictMetricResolver::value($row, 'fact'))->toBe('12,3 млн');
});

test('value returns dash when the chosen metric column is null', function () {
    $row = new IndicatorFact();
    $row->growth_pct = null;
    expect(DistrictMetricResolver::value($row, 'growth'))->toBe('—');
});

test('note prefixes plan/fact/volume strings', function () {
    $row = new IndicatorFact();
    $row->plan_value = 50.0;
    $row->actual_hokimyat = 47.0;
    $row->unit = 'trln';

    expect(DistrictMetricResolver::note($row, 'plan'))->toBe('режа 50,0 trln');
    expect(DistrictMetricResolver::note($row, 'fact'))->toBe('факт 47,0 trln');
    expect(DistrictMetricResolver::note($row, 'volume'))->toBe('ҳажм 50,0 trln');
});

test('note returns empty string when row or kind null', function () {
    expect(DistrictMetricResolver::note(null, 'plan'))->toBe('');
    $row = new IndicatorFact();
    expect(DistrictMetricResolver::note($row, null))->toBe('');
});
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Unit/DistrictMetricResolverTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement the resolver**

Create `backend/app/Support/DistrictMetricResolver.php`:

```php
<?php

namespace App\Support;

use App\Models\IndicatorFact;

class DistrictMetricResolver
{
    public static function value(?IndicatorFact $row, string $kind): string
    {
        if ($row === null) return '—';

        $raw = match ($kind) {
            'growth'    => $row->growth_pct,
            'execution' => $row->pct_of_plan,
            'plan'      => $row->plan_value,
            'fact'      => $row->actual_hokimyat ?? $row->actual_statkom,
            default     => null,
        };

        if ($raw === null) return '—';

        return match ($kind) {
            'growth', 'execution' => self::pct($raw),
            'plan', 'fact'        => self::number($raw, $row->unit ?? ''),
            default               => '—',
        };
    }

    public static function note(?IndicatorFact $row, ?string $kind): string
    {
        if ($row === null || $kind === null) return '';
        $unit = $row->unit ?? '';
        return match ($kind) {
            'fact'   => 'факт ' . self::number($row->actual_hokimyat ?? $row->actual_statkom, $unit),
            'plan'   => 'режа ' . self::number($row->plan_value, $unit),
            'volume' => 'ҳажм ' . self::number($row->plan_value, $unit),
            default  => '',
        };
    }

    public static function status(?IndicatorFact $row, bool $lowerIsBetter): string
    {
        return DistrictStatus::statusFor(
            $row?->pct_of_plan !== null ? (float) $row->pct_of_plan : null,
            $row?->growth_pct !== null ? (float) $row->growth_pct : null,
            $lowerIsBetter,
        );
    }

    private static function pct($v): string
    {
        $f = (float) $v;
        $sign = $f >= 0 ? '+' : '';
        return $sign . number_format($f, 1, ',', ' ') . '%';
    }

    private static function number($v, string $unit): string
    {
        if ($v === null) return '—';
        $s = number_format((float) $v, 1, ',', ' ');
        return $unit === '' ? $s : "{$s} {$unit}";
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

```bash
cd backend && vendor/bin/pest tests/Unit/DistrictMetricResolverTest.php
```

Expected: 8 tests pass.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/DistrictMetricResolver.php backend/tests/Unit/DistrictMetricResolverTest.php
git commit -m "feat(districts): DistrictMetricResolver pure formatter for fact cells"
```

---

### Task 2: `DistrictTableConfig` + unit test

**Files:**
- Create: `backend/app/Support/DistrictTableConfig.php`
- Create: `backend/tests/Unit/DistrictTableConfigTest.php`

The config is ported from `index.html:8578-8736`. 16 entries plus an export fallback.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/DistrictTableConfigTest.php`:

```php
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
    // H1 қиймат and Йиллик қиймат are fieldColumns
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

test('all 16 documented kpis return non-empty columns', function () {
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
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Unit/DistrictTableConfigTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement the config**

Create `backend/app/Support/DistrictTableConfig.php`:

```php
<?php

namespace App\Support;

class DistrictTableConfig
{
    /**
     * Per-KPI table config ported from index.html:8578-8736.
     *
     * Returns:
     *   [
     *     'title'          => string,
     *     'description'    => string,
     *     'source'         => string,
     *     'primary_period' => 'q1' | 'q2' | 'h1' | 'm9' | 'year',
     *     'columns'        => list<[
     *         'label'  => string,
     *         'metric' => ['kpi' => string, 'period' => string, 'kind' => 'growth' | 'execution' | 'plan' | 'fact'] | null,
     *         'note'   => 'fact' | 'plan' | 'volume' | null,
     *     ]>,
     *   ]
     *
     * Unknown KPIs return the export config (matches prototype fallback).
     */
    public static function for(string $kpi): array
    {
        $configs = self::configs();
        return $configs[$kpi] ?? $configs['export'];
    }

    private static function configs(): array
    {
        $growthCols = fn (string $id) => [
            self::metricCol('I чорак амалда',         $id, 'q1',   'growth', 'fact'),
            self::metricCol('I ярим йиллик прогноз',  $id, 'h1',   'growth', 'plan'),
            self::metricCol('9 ойлик прогноз',        $id, 'm9',   'growth', 'plan'),
            self::metricCol('Йиллик прогноз',         $id, 'year', 'growth', 'plan'),
        ];

        return [
            'grp' => [
                'title'          => 'ЯҲМ таркиби: туманлар кесими',
                'description'    => 'ЯҲМ туман кесимида берилмаган; солиштириш саноат, қишлоқ хўжалиги ва хизматлар ўсиши орқали берилади.',
                'source'         => '1.1-1.5-жадваллар: 1.2, 1.4, 1.5',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('Саноат ўсиши', 'industry', 'h1', 'growth', 'volume'),
                    self::metricCol('ҚХ ўсиши', 'agriculture', 'h1', 'growth', 'volume'),
                    self::metricCol('Хизматлар ўсиши', 'services', 'h1', 'growth', 'volume'),
                    self::fieldCol('Изоҳ'),
                ],
            ],
            'industry' => [
                'title'          => 'Саноат: туманлар кесими',
                'description'    => 'Туманлар бўйича саноат маҳсулотлари ҳажми ва ўсиш суръати.',
                'source'         => '1.2-жадвал',
                'primary_period' => 'h1',
                'columns'        => $growthCols('industry'),
            ],
            'agriculture' => [
                'title'          => 'Қишлоқ хўжалиги: туманлар кесими',
                'description'    => 'Туманлар бўйича қишлоқ хўжалиги маҳсулотлари ҳажми ва ўсиш суръати.',
                'source'         => '1.4-жадвал',
                'primary_period' => 'h1',
                'columns'        => $growthCols('agriculture'),
            ],
            'services' => [
                'title'          => 'Хизматлар: туманлар кесими',
                'description'    => 'Туманлар бўйича бозор хизматлари ҳажми ва ўсиш суръати.',
                'source'         => '1.5-жадвал',
                'primary_period' => 'h1',
                'columns'        => $growthCols('services'),
            ],
            'localization' => [
                'title'          => 'Маҳаллийлаштириш дастури: туманлар кесими',
                'description'    => 'Бу кўрсаткичда I чорак/9 ойлик йўқ; Excelда I ярим йиллик ва йиллик режа берилган.',
                'source'         => '1.3-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 лойиҳа', 'localization', 'h1', 'plan'),
                    self::fieldCol('H1 қиймат'),
                    self::metricCol('Йиллик лойиҳа', 'localization', 'year', 'plan'),
                    self::fieldCol('Йиллик қиймат'),
                ],
            ],
            'energy_electricity' => [
                'title'          => 'Электр энергиясини тежаш: туманлар кесими',
                'description'    => 'Энергия самарадорлиги бўйича I ярим йиллик ва йиллик мақсадли ҳажмлар.',
                'source'         => '1.3-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 тежаш', 'energy_electricity', 'h1', 'plan'),
                    self::metricCol('Йиллик тежаш', 'energy_electricity', 'year', 'plan'),
                ],
            ],
            'energy_gas' => [
                'title'          => 'Табиий газни тежаш: туманлар кесими',
                'description'    => 'Газ тежаш бўйича I ярим йиллик ва йиллик мақсадли ҳажмлар.',
                'source'         => '1.3-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 тежаш', 'energy_gas', 'h1', 'plan'),
                    self::metricCol('Йиллик тежаш', 'energy_gas', 'year', 'plan'),
                ],
            ],
            'inflation' => [
                'title'          => 'Озиқ-овқат захира инфратузилмаси: туманлар кесими',
                'description'    => 'Туманлар бўйича инфляция фоизи берилмаган; шу ерда нарх барқарорлигига хизмат қилувчи омборлар кўрсатилади.',
                'source'         => '2.2-жадвал',
                'primary_period' => 'year',
                'columns'        => [
                    self::fieldCol('Захира омбори'),
                    self::fieldCol('Совутгичли омбор'),
                    self::fieldCol('Янги омбор режаси'),
                    self::metricCol('Жами сиғим', 'inflation', 'year', 'plan'),
                ],
            ],
            'budget' => [
                'title'          => 'Бюджет тушумлари: туманлар кесими',
                'description'    => 'II чорак, I ярим йиллик ва йиллик прогноз/кутилиш алоҳида кўрсатилади.',
                'source'         => '3-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('II чорак ижро', 'budget', 'q2', 'execution', 'fact'),
                    self::metricCol('I ярим йиллик ижро', 'budget', 'h1', 'execution', 'fact'),
                    self::metricCol('Йиллик кутилиш', 'budget', 'year', 'execution', 'fact'),
                ],
            ],
            'budget_investment' => [
                'title'          => 'Бюджет инвестициялари: туманлар кесими',
                'description'    => 'Объектлар, лимит ва ўзлаштириш динамикаси алоҳида кўрсатилади.',
                'source'         => '4.1-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::fieldCol('Объектлар'),
                    self::metricCol('I чорак ўзлаштириш', 'budget_investment', 'q1', 'execution', 'fact'),
                    self::metricCol('H1 ўзлаштириш', 'budget_investment', 'h1', 'execution', 'fact'),
                    self::metricCol('Йиллик ўзлаштириш', 'budget_investment', 'year', 'execution', 'fact'),
                ],
            ],
            'investment' => [
                'title'          => 'Хорижий инвестициялар: туманлар кесими',
                'description'    => 'I чорак факт/режа, I ярим йиллик кутилиш ва йиллик прогноз кесимида.',
                'source'         => '4.2-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('I чорак ижро', 'investment', 'q1', 'execution', 'fact'),
                    self::metricCol('H1 ижро', 'investment', 'h1', 'execution', 'fact'),
                    self::metricCol('Йиллик ижро', 'investment', 'year', 'execution', 'fact'),
                    self::fieldCol('H1 лойиҳа / иш ўрни'),
                ],
            ],
            'export' => [
                'title'          => 'Экспорт: туманлар кесими',
                'description'    => 'Экспорт ҳажми, ўсиш суръати ва экспортчи корхоналар сони.',
                'source'         => '5.1-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('I чорак амалда', 'export', 'q1', 'growth', 'fact'),
                    self::metricCol('H1 кутилиш', 'export', 'h1', 'growth', 'fact'),
                    self::metricCol('Йиллик кутилиш', 'export', 'year', 'growth', 'fact'),
                    self::fieldCol('Экспортчилар'),
                ],
            ],
            'unemployment' => [
                'title'          => 'Ишсизлик даражаси: туманлар кесими',
                'description'    => '6-жадвалда I ярим йиллик ва йиллик мақсадли даражалар берилган.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 мақсад', 'unemployment', 'h1', 'plan'),
                    self::metricCol('Йиллик мақсад', 'unemployment', 'year', 'plan'),
                    self::metricCol('Ишга жойлаштириш H1', 'jobs', 'h1', 'plan'),
                    self::metricCol('Легаллаштириш H1', 'legalization', 'h1', 'plan'),
                ],
            ],
            'poverty' => [
                'title'          => 'Камбағаллик даражаси: туманлар кесими',
                'description'    => 'Камбағаллик даражаси ва унга боғланган драйверлар бир жадвалда.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 камбағаллик', 'poverty', 'h1', 'plan'),
                    self::metricCol('Йиллик камбағаллик', 'poverty', 'year', 'plan'),
                    self::metricCol('Камбағалликдан холи МФЙлар H1', 'mfy_clear', 'h1', 'plan'),
                    self::metricCol('Микролойиҳа H1', 'microprojects', 'h1', 'plan'),
                ],
            ],
            'jobs' => [
                'title'          => 'Доимий ишга жойлаштириш: туманлар кесими',
                'description'    => 'Ишга жойлаштириш мақсадлари I ярим йиллик ва йиллик кесимда.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 ишга жойлаштириш', 'jobs', 'h1', 'plan'),
                    self::metricCol('Йиллик ишга жойлаштириш', 'jobs', 'year', 'plan'),
                    self::metricCol('H1 легаллаштириш', 'legalization', 'h1', 'plan'),
                    self::metricCol('H1 микролойиҳа', 'microprojects', 'h1', 'plan'),
                ],
            ],
            'legalization' => [
                'title'          => 'Норасмий бандларни легаллаштириш: туманлар кесими',
                'description'    => 'Легаллаштириш мақсадлари I ярим йиллик ва йиллик кесимда.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 мақсад', 'legalization', 'h1', 'plan'),
                    self::metricCol('Йиллик мақсад', 'legalization', 'year', 'plan'),
                    self::metricCol('Ишга жойлаштириш H1', 'jobs', 'h1', 'plan'),
                ],
            ],
            'mfy_clear' => [
                'title'          => 'Холи МФЙлар: туманлар кесими',
                'description'    => 'Камбағаллик ва ишсизликдан холи ҳудудга айлантириладиган МФЙлар.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 МФЙ', 'mfy_clear', 'h1', 'plan'),
                    self::metricCol('Йиллик МФЙ', 'mfy_clear', 'year', 'plan'),
                    self::metricCol('Камбағаллик H1', 'poverty', 'h1', 'plan'),
                ],
            ],
            'microprojects' => [
                'title'          => 'Микролойиҳалар: туманлар кесими',
                'description'    => 'Камбағалликни қисқартиришга боғланган микролойиҳалар.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 микролойиҳа', 'microprojects', 'h1', 'plan'),
                    self::metricCol('Йиллик микролойиҳа', 'microprojects', 'year', 'plan'),
                    self::metricCol('Ишга жойлаштириш H1', 'jobs', 'h1', 'plan'),
                ],
            ],
        ];
    }

    private static function metricCol(string $label, string $kpi, string $period, string $kind, ?string $note = null): array
    {
        return [
            'label'  => $label,
            'metric' => ['kpi' => $kpi, 'period' => $period, 'kind' => $kind],
            'note'   => $note,
        ];
    }

    private static function fieldCol(string $label): array
    {
        return [
            'label'  => $label,
            'metric' => null,
            'note'   => null,
        ];
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

```bash
cd backend && vendor/bin/pest tests/Unit/DistrictTableConfigTest.php
```

Expected: 6 tests pass.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/DistrictTableConfig.php backend/tests/Unit/DistrictTableConfigTest.php
git commit -m "feat(districts): DistrictTableConfig — 18 per-KPI column configs"
```

---

### Task 3: Extend `DistrictsPage` Livewire with new computed properties

**Files:**
- Modify: `backend/app/Livewire/DistrictsPage.php`

- [ ] **Step 1: Add new computed properties and modify `selectKpi`**

Open `backend/app/Livewire/DistrictsPage.php`. Add the import below the existing `use App\Support\DistrictStatus;` line:

```php
use App\Support\DistrictTableConfig;
```

Replace the existing `selectKpi` method body with the version that syncs period:

```php
    public function selectKpi(string $code): void
    {
        $this->kpi = $code;
        $indicator = Indicator::where('code', $code)->first();
        if ($indicator?->module_code) {
            $this->module = $indicator->module_code;
        }
        $this->period = DistrictTableConfig::for($code)['primary_period'];
    }
```

Append four new computed properties after the existing `taskCount()` method and before `render()`:

```php
    #[Computed]
    public function tableConfig(): array
    {
        return DistrictTableConfig::for($this->kpi);
    }

    /**
     * Build [$kpi][$district_code][$period] => IndicatorFact|null lookup
     * for every (kpi, period) pair referenced by the current tableConfig.
     */
    #[Computed]
    public function factMatrix(): array
    {
        $cfg = $this->tableConfig;
        $pairs = [];
        foreach ($cfg['columns'] as $col) {
            if ($col['metric'] === null) continue;
            $pairs[$col['metric']['kpi'] . '|' . $col['metric']['period']] = [
                $col['metric']['kpi'],
                $col['metric']['period'],
            ];
        }
        if (empty($pairs)) return [];

        $query = IndicatorFact::where('region_code', self::REGION_CODE)
            ->whereNotNull('district_code');

        $query->where(function ($q) use ($pairs) {
            foreach ($pairs as [$k, $p]) {
                $q->orWhere(function ($w) use ($k, $p) {
                    $w->where('indicator_code', $k)->where('period', $p);
                });
            }
        });

        $out = [];
        foreach ($query->get() as $row) {
            $out[$row->indicator_code][$row->district_code][$row->period] = $row;
        }
        return $out;
    }

    /**
     * @return array<string, array{unfinished:int,total:int}>
     */
    #[Computed]
    public function taskCountByDistrict(): array
    {
        $out = [];
        $tasks = Task::forRegion(self::REGION_CODE)
            ->forIndicator($this->kpi)
            ->with('districts:id,code')
            ->get();

        foreach ($tasks as $task) {
            foreach ($task->districts as $d) {
                $out[$d->code] ??= ['unfinished' => 0, 'total' => 0];
                $out[$d->code]['total']++;
                if ($task->status !== 'done') {
                    $out[$d->code]['unfinished']++;
                }
            }
        }
        return $out;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function targetCountByDistrict(): array
    {
        $out = [];
        $targets = PromiseTarget::where('region_code', self::REGION_CODE)
            ->where('indicator_code', $this->kpi)
            ->whereNotNull('target_districts')
            ->get();

        foreach ($targets as $target) {
            $codes = is_array($target->target_districts) ? $target->target_districts : [];
            foreach ($codes as $code) {
                $out[$code] = ($out[$code] ?? 0) + 1;
            }
        }
        return $out;
    }
```

- [ ] **Step 2: Sanity check**

```bash
cd backend && php -l app/Livewire/DistrictsPage.php
```

Expected: `No syntax errors`.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Livewire/DistrictsPage.php
git commit -m "feat(districts): tableConfig + factMatrix + task/target counts on DistrictsPage"
```

---

### Task 4: Rewrite Blade view — side aside + detail table

**Files:**
- Modify: `backend/resources/views/livewire/districts-page.blade.php`

This rewrites two sections: the aside (`.districts-side`) and the bottom panel (`.panel.district-detail-table`). The `<header class="districts-head">` and `<section class="districts-map">` blocks at the top stay intact.

- [ ] **Step 1: Update the `@php` header to import resolvers and define helpers**

In the existing `@php` block at the top of the file, add these imports and helper variables. Insert immediately after the existing `use App\Support\AndijanMapGeometry;` line:

```php
    use App\Support\DistrictMetricResolver;

    $tableConfig         = $this->tableConfig;
    $factMatrix          = $this->factMatrix;
    $taskCountByDistrict = $this->taskCountByDistrict;
    $targetCountByDistrict = $this->targetCountByDistrict;

    $statusLabel = [
        'green' => 'Яхши', 'amber' => 'Ўртача', 'red' => 'Эътибор', 'grey' => 'Маълумот йўқ',
    ];

    $taskChipClass = function (array $tasks): string {
        if ($tasks['total'] > 0 && $tasks['unfinished'] > 0) return 'red';
        if ($tasks['total'] > 0) return 'green';
        return 'grey';
    };
    $targetChipClass = fn (int $n): string => $n > 0 ? 'blue' : 'grey';

    $resolveCell = function ($col, string $code) use ($factMatrix) {
        if ($col['metric'] === null) {
            return ['value' => '—', 'note' => ''];
        }
        $fact = $factMatrix[$col['metric']['kpi']][$code][$col['metric']['period']] ?? null;
        return [
            'value' => DistrictMetricResolver::value($fact, $col['metric']['kind']),
            'note'  => DistrictMetricResolver::note($fact, $col['note'] ?? null),
        ];
    };

    $selectedTasks  = $selectedRow ? ($taskCountByDistrict[$selectedCode] ?? ['unfinished' => 0, 'total' => 0]) : ['unfinished' => 0, 'total' => 0];
    $selectedTargetCount = $selectedRow ? ($targetCountByDistrict[$selectedCode] ?? 0) : 0;
```

- [ ] **Step 2: Replace the `<aside class="districts-side">` block**

Locate the existing `<aside class="districts-side">…</aside>` block. Replace its entire contents (everything between the opening `<aside …>` and closing `</aside>` tags, inclusive) with:

```blade
        <aside class="districts-side">
            <section class="district-summary-card {{ $selectedDistrict ? '' : 'empty' }}">
                <header class="district-summary-head">
                    <div>
                        <span>Танланган ҳудуд</span>
                        <h3>{{ $selectedDistrict?->name_full ?? 'Туман танланмаган' }}</h3>
                    </div>
                    @if($selectedDistrict)
                        <span class="chip {{ $selectedStatus }}">{{ $statusLabel[$selectedStatus] ?? '—' }}</span>
                    @endif
                </header>
                @if($selectedDistrict)
                    <div class="district-summary-value">
                        <div>
                            <strong>{{ $selectedFact?->pct_of_plan !== null ? $fmt($selectedFact->pct_of_plan, 1) . '%' : '—' }}</strong>
                            <span>Ижро бажарилиши · {{ $kpiShort }}</span>
                        </div>
                        <div class="district-count-split">
                            <span class="chip {{ $taskChipClass($selectedTasks) }}">T: {{ $selectedTasks['unfinished'] }}/{{ $selectedTasks['total'] }}</span>
                            <span class="chip {{ $targetChipClass($selectedTargetCount) }}">D: {{ $selectedTargetCount }}</span>
                        </div>
                    </div>
                    <div class="district-summary-metrics">
                        @foreach(array_slice($tableConfig['columns'], 0, 4) as $col)
                            @php $cell = $resolveCell($col, $selectedCode); @endphp
                            <div class="district-summary-metric">
                                <span>{{ $col['label'] }}</span>
                                <strong>{{ $cell['value'] }}</strong>
                                <small>{{ $cell['note'] }}</small>
                            </div>
                        @endforeach
                    </div>
                    <div class="district-summary-actions">
                        <a class="mini-button primary" href="{{ route('profile') }}?districtCode={{ $selectedCode }}">Туман профили</a>
                        <a class="mini-button" href="{{ route('execution') }}?indicator={{ $kpi }}&district={{ $selectedCode }}&period={{ $period }}">Ижро журнали</a>
                        <a class="mini-button" href="{{ route('tasks') }}?indicator={{ $kpi }}&district={{ $selectedCode }}">Топшириқлар</a>
                    </div>
                    <span class="chip grey">ҳисобот йўқ</span>
                @else
                    <p class="muted">Харита ёки рейтингдан туман/шаҳарни танланг.</p>
                @endif
            </section>

            <section class="districts-leaderboard">
                <header class="districts-lb-head">
                    <strong>Туманлар</strong>
                    <span>{{ count($rankedDistricts) }} та туманлар · {{ $kpiShort }}</span>
                </header>
                <ol class="districts-lb-list">
                    @foreach($rankedDistricts as $idx => $row)
                        @php
                            $rd = $row['district'];
                            $rf = $row['fact'];
                            $rs = $row['status'];
                            $rPct = $rf?->pct_of_plan !== null ? (float) $rf->pct_of_plan : null;
                            $barW = $rPct !== null ? max(0, min(100, $rPct)) : 0;
                            $primary = $rf?->pct_of_plan !== null
                                ? $fmt($rf->pct_of_plan, 1) . '%'
                                : ($rf?->growth_pct !== null ? $fmt($rf->growth_pct, 1) . '%' : '—');
                        @endphp
                        <li class="lb-row {{ $rs }} {{ $rd->code === $selectedCode ? 'selected' : '' }}"
                            wire:click="selectDistrict('{{ $rd->code }}')"
                            tabindex="0">
                            <span class="lb-rank">{{ $idx + 1 }}</span>
                            <span class="lb-name">{{ $rd->name_full }}</span>
                            <span class="lb-value">{{ $primary }}</span>
                            <span class="lb-bar"><i style="width:{{ number_format($barW, 1, '.', '') }}%"></i></span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
```

- [ ] **Step 3: Replace the `<section class="panel district-detail-table">` block**

Locate the existing `<section class="panel district-detail-table">…</section>` block. Replace its entire contents with:

```blade
    <section class="panel district-detail-table">
        <div class="panel-head">
            <div>
                <h3>Батафсил жадвал</h3>
                <p>{{ $tableConfig['title'] }}. {{ $tableConfig['description'] }}</p>
            </div>
            <span class="chip grey">{{ $tableConfig['source'] }}</span>
        </div>
        <div class="district-table-wrap">
            <table class="district-table">
                <thead>
                    <tr>
                        <th>Туман/шаҳар</th>
                        @foreach($tableConfig['columns'] as $col)
                            <th>{{ $col['label'] }}</th>
                        @endforeach
                        <th class="num">T-топшириқ</th>
                        <th class="num">D-мақсад</th>
                        <th>Ҳисобот / таъсир</th>
                        <th>Амал</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rankedDistricts as $row)
                        @php
                            $rd = $row['district'];
                            $code = $rd->code;
                            $rs = $row['status'];
                            $tasks = $taskCountByDistrict[$code] ?? ['unfinished' => 0, 'total' => 0];
                            $targets = $targetCountByDistrict[$code] ?? 0;
                        @endphp
                        <tr class="clickable {{ $code === $selectedCode ? 'active-row' : '' }}"
                            wire:click="selectDistrict('{{ $code }}')">
                            <td class="row-title"><strong>{{ $rd->name_full }}</strong><span>ҳокимлик</span></td>
                            @foreach($tableConfig['columns'] as $col)
                                @php $cell = $resolveCell($col, $code); @endphp
                                <td><strong>{{ $cell['value'] }}</strong><small>{{ $cell['note'] }}</small></td>
                            @endforeach
                            <td class="num"><span class="chip {{ $taskChipClass($tasks) }}">{{ $tasks['unfinished'] }}/{{ $tasks['total'] }}</span></td>
                            <td class="num"><span class="chip {{ $targetChipClass($targets) }}">{{ $targets }}</span></td>
                            <td><span class="chip grey">ҳисобот йўқ</span><small>амалдаги натижа киритилмаган</small></td>
                            <td>
                                <div class="action-row compact">
                                    <a class="mini-button profile" href="{{ route('profile') }}?districtCode={{ $code }}">Профил</a>
                                    <a class="mini-button" href="{{ route('execution') }}?indicator={{ $kpi }}&district={{ $code }}&period={{ $period }}">Журнал</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
```

- [ ] **Step 4: Commit**

```bash
git add backend/resources/views/livewire/districts-page.blade.php
git commit -m "feat(districts): rewrite aside + detail-table for prototype parity"
```

---

### Task 5: Extend feature test with dynamic column + leaderboard assertions

**Files:**
- Modify: `backend/tests/Feature/Http/DistrictsPageTest.php`

- [ ] **Step 1: Add new tests at the end of the existing file**

Open `backend/tests/Feature/Http/DistrictsPageTest.php`. After the existing `test('status thresholds drive cell coloring', …);` block (the last test in the file), add:

```php
test('detail table shows industry-specific column headers for industry KPI', function () {
    $response = $this->get('/districts?kpi=industry');
    $response->assertOk();
    $response->assertSee('I чорак амалда', false);
    $response->assertSee('I ярим йиллик прогноз', false);
    $response->assertSee('Йиллик прогноз', false);
});

test('detail table shows budget-specific column headers when budget KPI active', function () {
    // Seed an indicator + fact for budget so kpiOptions includes it
    DB::table('indicators')->insert([
        'code' => 'budget', 'label_full' => 'Бюджет', 'label_short' => 'Бюджет',
        'scope' => 'both', 'default_unit' => 'млрд', 'module_code' => 'macro',
        'lower_is_better' => false, 'has_growth_pct' => false, 'has_pct_of_plan' => true,
        'has_sentinel' => false, 'sort_order' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    \App\Models\IndicatorFact::create([
        'region_code' => 'andijan', 'district_code' => 'andijan_city',
        'indicator_code' => 'budget', 'period' => 'h1', 'year' => 2026,
        'unit' => 'млрд', 'source_label' => 'test',
        'plan_value' => 200, 'actual_hokimyat' => 180,
        'pct_of_plan' => 90.0,
    ]);

    $response = $this->get('/districts?kpi=budget');
    $response->assertOk();
    $response->assertSee('II чорак ижро', false);
    $response->assertSee('I ярим йиллик ижро', false);
    $response->assertDontSee('I чорак амалда', false);
});

test('side aside renders T/D count chips, metric tiles, and leaderboard markup', function () {
    $response = $this->get('/districts');
    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('district-count-split');
    expect($html)->toContain('district-summary-metrics');
    expect($html)->toContain('district-summary-actions');
    expect($html)->toContain('districts-leaderboard');
    expect($html)->toContain('districts-lb-list');
    expect($html)->toContain('lb-row');
    expect($html)->toContain('lb-rank');
});

test('detail table renders T-topshiriq and D-maqsad cells', function () {
    $response = $this->get('/districts');
    $html = $response->getContent();
    expect($html)->toContain('T-топшириқ');
    expect($html)->toContain('D-мақсад');
    expect($html)->toContain('ҳисобот йўқ');
});
```

- [ ] **Step 2: Run the file**

```bash
cd backend && vendor/bin/pest tests/Feature/Http/DistrictsPageTest.php
```

Expected: previous 6 tests + these 4 new tests pass (10 total).

If `budget KPI` test fails because the component falls back to the seeded `industry` KPI (URL param not respected for unknown KPIs), inspect the actual rendered KPI:

```bash
cd backend && vendor/bin/pest tests/Feature/Http/DistrictsPageTest.php --filter "budget-specific"
```

and confirm that `?kpi=budget` is being read. Adjust the seeding (indicator `budget` must exist with `module_code='macro'` matching default `module='macro'`) or call `Livewire::test()->set('kpi', 'budget')` if URL state is not honored on initial render.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Http/DistrictsPageTest.php
git commit -m "test(districts): assert dynamic columns + aside parity markup"
```

---

### Task 6: User visual QA

**Files:** none.

- [ ] **Step 1: User runs server**

```bash
cd backend && php artisan serve
```

- [ ] **Step 2: User walks the page**

Open `http://127.0.0.1:8000/districts` and verify:

1. Default view (macro / industry / h1): detail table header shows `Туман/шаҳар`, `I чорак амалда`, `I ярим йиллик прогноз`, `9 ойлик прогноз`, `Йиллик прогноз`, `T-топшириқ`, `D-мақсад`, `Ҳисобот / таъсир`, `Амал`.
2. Switch KPI to `budget`: header changes to `II чорак ижро`, `I ярим йиллик ижро`, `Йиллик кутилиш` (3 metric cols) + the fixed 4.
3. Side aside: status chip, big number, T/D split chips visible. 4 metric tiles under the value. 3 pill buttons (Туман профили / Ижро журнали / Топшириқлар). Trailing `ҳисобот йўқ` chip.
4. Leaderboard list: numbered, name + value + bar per row. Clicking a row selects the district.
5. KPIs with `fieldColumn` data (localization, energy, inflation): those cells render `—`. Documented gap.

Report any visual regressions.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| §3 Strategy — two helpers + 4 computed + Blade rewrite | Tasks 1, 2, 3, 4 |
| §4 DistrictTableConfig shape + 16 entries | Task 2 |
| §5 DistrictMetricResolver formatters | Task 1 |
| §6.1 New computed props | Task 3 |
| §6.2 Period sync in selectKpi | Task 3 |
| §7.1 Side aside rewrite | Task 4 (Step 2) |
| §7.2 Leaderboard markup fix | Task 4 (Step 2, embedded in aside) |
| §7.3 Detail table rewrite | Task 4 (Step 3) |
| §8 Tests | Tasks 1, 2, 5 |
| §9 Files touched | each task |
| §10 Known parity gaps | documented in Task 2 (fieldColumn) and Task 4 templates (ҳисобот йўқ literal, ҳокимлик literal) |

**Placeholder scan:** none. Every code block is complete; every command has exact arguments.

**Type/name consistency:**

- Class names `App\Support\DistrictTableConfig` and `App\Support\DistrictMetricResolver` used identically across tasks.
- Config keys: `title`, `description`, `source`, `primary_period`, `columns` — same in Task 2 (defs), Task 3 (computed), Task 4 (Blade reads).
- Column entry keys: `label`, `metric` (with `kpi`/`period`/`kind`), `note` — same across tasks.
- Computed property names: `tableConfig`, `factMatrix`, `taskCountByDistrict`, `targetCountByDistrict` — same in Task 3 (defs) and Task 4 (reads).
- Action method names: `selectKpi`, `selectDistrict`, `selectModule` — unchanged from prior plan.
