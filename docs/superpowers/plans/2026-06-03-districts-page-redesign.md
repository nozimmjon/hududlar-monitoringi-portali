# Districts Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Compact `/districts` from 2+ screens to ~1.5 by collapsing the duplicate district listings (leaderboard + detail table) into one merged table, removing the cryptic `D-`/`T-` header cards, and slimming the 5-block header to a compact toolbar.

**Architecture:** Pure front-of-stack change to one Livewire page. The view (`districts-page.blade.php`) is restructured into Toolbar → [Map | Detail panel] → Merged table. The component (`DistrictsPage.php`) only loses three now-unused computed props; all data plumbing already exists. CSS is hand-edited in `public/css/portal.css` (no build step). Feature tests that assert the old markup are rewritten first (TDD).

**Tech Stack:** Laravel 12, Livewire 3, Blade, PostgreSQL, Pest 3 / PHPUnit 11. Hand-maintained `portal.css`.

---

## File Structure

| File | Change | Responsibility |
| --- | --- | --- |
| `backend/tests/Feature/Http/DistrictsPageTest.php` | Modify | Assert the new markup/labels (red first). |
| `backend/resources/views/livewire/districts-page.blade.php` | Rewrite (4 block edits) | New page structure. Map SVG block stays untouched. |
| `backend/app/Livewire/DistrictsPage.php` | Modify | Delete `coverage()`, `targetCount()`, `taskCount()`. |
| `backend/public/css/portal.css` | Modify | Add toolbar/pill/merged-table styles; delete data-layer, kpi-option, leaderboard rules. |

**Reused (no new abstractions):** `.district-table` (merged table base), `.district-summary-card` (detail panel), `.mini-button`, `.chip`, `DistrictMetricResolver`, `DistrictTableConfig`, and existing computed props `rankedDistricts`, `factMatrix`, `tableConfig`, `selectedDistrict`, `taskCountByDistrict`, `targetCountByDistrict`, `colorScale`, `colorRange`, `mapGeometry`, `moduleOptions`, `kpiOptions`.

**Important conventions (from CLAUDE.md / project memory):**
- UI language is Cyrillic Uzbek. Do not translate labels.
- `portal.css` is hand-maintained — edit directly, **no `npm run build`**.
- Tests share one Postgres DB (`hududlar_monitoringi_test`). **Never run two suites at once.** Run the targeted filter only.

---

## Task 1: Rewrite feature tests to expect the new markup (red)

**Files:**
- Test: `backend/tests/Feature/Http/DistrictsPageTest.php`

Three tests assert markup we are deleting. Rewrite them to assert the new structure. The two KPI-column-header tests (`industry-specific` / `budget-specific`) stay unchanged — those metric labels move into the detail panel, where `assertSee` still finds them.

- [ ] **Step 1: Update the "map and table markup" test**

Replace this test:

```php
test('GET /districts returns 200 with map and table markup', function () {
    $response = $this->get('/districts');

    $response->assertOk();
    $response->assertSee('districts-map', false);
    $response->assertSee('districts-side', false);
    $response->assertSee('district-detail-table', false);
    $response->assertSee('Андижон шаҳри', false);
    $response->assertSee('Асака тумани', false);
});
```

with:

```php
test('GET /districts returns 200 with map and merged table markup', function () {
    $response = $this->get('/districts');

    $response->assertOk();
    $response->assertSee('districts-map', false);
    $response->assertSee('districts-table', false);
    $response->assertSee('district-detail-table', false);
    $response->assertSee('Андижон шаҳри', false);
    $response->assertSee('Асака тумани', false);
});
```

- [ ] **Step 2: Replace the leaderboard test with a merged-table test**

Replace this test:

```php
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
```

with:

```php
test('merged table renders ranked rows and detail panel, no leaderboard', function () {
    $response = $this->get('/districts');
    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('districts-table');
    expect($html)->toContain('dt-rank');
    expect($html)->toContain('dt-exec');
    expect($html)->toContain('district-count-split');
    expect($html)->toContain('district-summary-metrics');
    expect($html)->toContain('district-summary-actions');
    expect($html)->not->toContain('districts-leaderboard');
    expect($html)->not->toContain('lb-row');
});
```

- [ ] **Step 3: Replace the D-/T- label test with a plain-label test**

Replace this test:

```php
test('detail table renders T-topshiriq and D-maqsad cells', function () {
    $response = $this->get('/districts');
    $html = $response->getContent();
    expect($html)->toContain('T-топшириқ');
    expect($html)->toContain('D-мақсад');
});
```

with:

```php
test('district list uses plain task and target labels, not D-/T- codes', function () {
    $response = $this->get('/districts');
    $html = $response->getContent();
    expect($html)->toContain('Топшириқлар');
    expect($html)->toContain('Кафолат мажбурияти');
    expect($html)->not->toContain('T-топшириқ');
    expect($html)->not->toContain('D-мақсад');
});
```

- [ ] **Step 4: Run the tests to verify they fail**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: FAIL. The three rewritten tests fail because the new markup (`districts-table`, `dt-rank`, `dt-exec`, `Кафолат мажбурияти`) does not exist yet, and the old markup (`districts-leaderboard`, `T-топшириқ`) is still present.

Do **not** commit yet — implementation lands the page and tests go green together in Task 5.

---

## Task 2: Rewrite the Blade view

**Files:**
- Modify: `backend/resources/views/livewire/districts-page.blade.php`

Four block replacements. The map `<section class="districts-map"> … </section>` (current lines ~143-223) is **left untouched**.

- [ ] **Step 1: Replace the `@php … @endphp` header block**

Replace the entire block from `@php` (line 1) through `@endphp` (line 65) with:

```php
@php
    use App\Support\DistrictMetricResolver;

    $tableConfig           = $this->tableConfig;
    $factMatrix            = $this->factMatrix;
    $taskCountByDistrict   = $this->taskCountByDistrict;
    $targetCountByDistrict = $this->targetCountByDistrict;

    $statusLabel = [
        'green' => 'Яхши', 'amber' => 'Ўртача', 'red' => 'Эътибор', 'grey' => 'Маълумот йўқ',
    ];

    // Tasks shown as done/total (flip from the old unfinished/total).
    $taskDone      = fn (array $t): int => max(0, $t['total'] - $t['unfinished']);
    $taskChipClass = function (array $t): string {
        if ($t['total'] > 0 && $t['unfinished'] > 0) return 'red';
        if ($t['total'] > 0) return 'green';
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

    $districts       = $this->districts;
    $rollup          = $this->rollup;
    $rankedDistricts = $this->rankedDistricts;
    $selectedRow     = $this->selectedDistrict;
    $moduleOptions   = $this->moduleOptions;
    $kpiOptions      = $this->kpiOptions;
    $indicator       = $this->indicator;

    $selectedCode        = $selectedRow ? $selectedRow['district']->code : '';
    $selectedDistrict    = $selectedRow ? $selectedRow['district'] : null;
    $selectedFact        = $selectedRow ? $selectedRow['fact'] : null;
    $selectedStatus      = $selectedRow ? $selectedRow['status'] : 'grey';
    $selectedTasks       = $selectedRow ? ($taskCountByDistrict[$selectedCode] ?? ['unfinished' => 0, 'total' => 0]) : ['unfinished' => 0, 'total' => 0];
    $selectedTargetCount = $selectedRow ? ($targetCountByDistrict[$selectedCode] ?? 0) : 0;

    $fmt = function ($v, int $decimals = 1): string {
        if ($v === null || $v === '') return '—';
        return number_format((float) $v, $decimals, ',', ' ');
    };

    $kpiShort = $indicator?->label_short ?? $kpi;
    $kpiFull  = $indicator?->label_full  ?? $kpi;
@endphp
```

- [ ] **Step 2: Replace the header block with the compact toolbar**

Replace the entire block from `<header class="districts-head">` (line 68) through its closing `</header>` (line 140) with:

```blade
    <header class="districts-head">
        <div class="districts-toolbar">
            <div class="dashboard-module-tabs district-module-tabs">
                @foreach($moduleOptions as $m)
                    <button class="module-tab {{ $m->code === $module ? 'active' : '' }}"
                            wire:click="selectModule('{{ $m->code }}')"
                            type="button">
                        <span class="module-dot" aria-hidden="true"></span>
                        <strong>{{ preg_replace('/^\d+\.\s*/u', '', $m->label) }}</strong>
                    </button>
                @endforeach
            </div>

            <div class="districts-head-actions">
                <label class="districts-control">
                    <span>Саралаш</span>
                    <select wire:model.live="sort">
                        <option value="attention">Эътибор талаб</option>
                        <option value="execution">Юқоридан</option>
                        <option value="plan">Режа каттадан</option>
                        <option value="name">Алифбо бўйича</option>
                    </select>
                </label>
                <label class="districts-control districts-control--search">
                    <span>Қидириш</span>
                    <input wire:model.live.debounce.300ms="search" placeholder="Туман қидириш">
                </label>
            </div>
        </div>

        @if($kpiOptions->count() > 1)
            <div class="district-kpi-pills">
                @foreach($kpiOptions as $i)
                    <button class="district-kpi-pill {{ $i->code === $kpi ? 'active' : '' }}"
                            wire:click="selectKpi('{{ $i->code }}')"
                            type="button"
                            title="{{ $i->label_full }} · {{ \App\Support\DistrictTableConfig::for($i->code)['source'] }}">
                        <span class="kpi-mini-icon" aria-hidden="true">@include('partials.icon', ['name' => $i->icon ?? 'trend'])</span>
                        <strong>{{ $i->label_short }}</strong>
                    </button>
                @endforeach
            </div>
        @endif
    </header>
```

- [ ] **Step 3: Replace the right-side aside with the detail panel**

Replace the entire block from `<aside class="districts-side">` (line 225) through its closing `</aside>` (line 294) with:

```blade
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
                        <span class="chip {{ $taskChipClass($selectedTasks) }}">Топшириқлар {{ $taskDone($selectedTasks) }}/{{ $selectedTasks['total'] }}</span>
                        <span class="chip {{ $targetChipClass($selectedTargetCount) }}">Кафолат мажбурияти {{ $selectedTargetCount }}</span>
                    </div>
                </div>
                <div class="district-summary-metrics">
                    @foreach($tableConfig['columns'] as $col)
                        @php $cell = $resolveCell($col, $selectedCode); @endphp
                        <div class="district-summary-metric">
                            <span>{{ $col['label'] }}</span>
                            <strong>{{ $cell['value'] }}</strong>
                            <small>{{ $cell['note'] }}</small>
                        </div>
                    @endforeach
                </div>
                <div class="district-summary-actions">
                    <a class="mini-button primary" href="{{ route('profile') }}?districtCode={{ $selectedCode }}">Профил</a>
                    <a class="mini-button" href="{{ route('execution') }}?indicator={{ $kpi }}&district={{ $selectedCode }}&period={{ $period }}">Журнал</a>
                </div>
            @else
                <p class="muted">Харита ёки жадвалдан туман танланг.</p>
            @endif
        </section>
```

Note: this `<section>` becomes the second direct child of `.districts-grid` (the map is the first). The old `<aside class="districts-side">` wrapper is gone.

- [ ] **Step 4: Replace the bottom detail table with the merged table**

Replace the entire block from `<section class="panel district-detail-table">` (line 297) through its closing `</section>` (line 347) with:

```blade
    <section class="panel district-detail-table">
        <div class="panel-head">
            <div>
                <h3>Туманлар рейтинги</h3>
                <p>{{ $tableConfig['title'] }}. {{ $tableConfig['description'] }}</p>
            </div>
            <span class="chip grey">{{ $tableConfig['source'] }}</span>
        </div>
        <div class="district-table-wrap">
            <table class="district-table districts-table">
                <thead>
                    <tr>
                        <th class="num">#</th>
                        <th>Туман/шаҳар</th>
                        <th class="num">Ижро %</th>
                        <th class="num">Режа</th>
                        <th class="num">Факт</th>
                        <th class="num">Топшириқлар</th>
                        <th class="num">Мажбурият</th>
                        <th>Амал</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rankedDistricts as $idx => $row)
                        @php
                            $rd      = $row['district'];
                            $code    = $rd->code;
                            $rf      = $row['fact'];
                            $rs      = $row['status'];
                            $tasks   = $taskCountByDistrict[$code] ?? ['unfinished' => 0, 'total' => 0];
                            $targets = $targetCountByDistrict[$code] ?? 0;
                            $rPct    = $rf?->pct_of_plan !== null ? (float) $rf->pct_of_plan : null;
                            $barW    = $rPct !== null ? max(0, min(100, $rPct)) : 0;
                            $execText = $rPct !== null ? $fmt($rPct, 1) . '%' : '—';
                            $planText = $rf?->plan_value !== null ? $fmt($rf->plan_value, 1) : '—';
                            $factRaw  = $rf?->actual_hokimyat ?? $rf?->actual_statkom ?? null;
                            $factText = $factRaw !== null ? $fmt($factRaw, 1) : '—';
                        @endphp
                        <tr class="clickable {{ $rs }} {{ $code === $selectedCode ? 'active-row' : '' }}"
                            wire:click="selectDistrict('{{ $code }}')">
                            <td class="num dt-rank">{{ $idx + 1 }}</td>
                            <td class="row-title"><span class="dt-status" aria-hidden="true"></span><strong>{{ $rd->name_full }}</strong></td>
                            <td class="num dt-exec">
                                <strong>{{ $execText }}</strong>
                                <span class="dt-bar"><i style="width:{{ number_format($barW, 1, '.', '') }}%"></i></span>
                            </td>
                            <td class="num">{{ $planText }}</td>
                            <td class="num">{{ $factText }}</td>
                            <td class="num"><span class="chip {{ $taskChipClass($tasks) }}">{{ $taskDone($tasks) }}/{{ $tasks['total'] }}</span></td>
                            <td class="num"><span class="chip {{ $targetChipClass($targets) }}">{{ $targets }}</span></td>
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

- [ ] **Step 5: Run the page tests to track progress**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: the three Task 1 tests now PASS (markup present, old markup gone). The two KPI-column-header tests still PASS (metric labels render in the detail panel). If `industry-specific` / `budget-specific` fail, confirm the detail panel loops over **all** `$tableConfig['columns']` (Step 3) — those labels come from there.

---

## Task 3: Remove the now-unused computed props

**Files:**
- Modify: `backend/app/Livewire/DistrictsPage.php`

After Task 2 the view no longer reads `$this->coverage`, `$this->targetCount`, or `$this->taskCount`. Delete them.

- [ ] **Step 1: Delete the `coverage()` computed**

Delete this block (currently lines ~204-215):

```php
    #[Computed]
    public function coverage(): array
    {
        $count = $this->facts->count();
        $periods = IndicatorFact::where('region_code', $this->regionCode)
            ->where('indicator_code', $this->kpi)
            ->whereNotNull('district_code')
            ->distinct()
            ->pluck('period')
            ->all();
        return ['count' => $count, 'periods' => $periods];
    }
```

- [ ] **Step 2: Delete the `targetCount()` computed**

Delete this block (currently lines ~217-224):

```php
    #[Computed]
    public function targetCount(): int
    {
        return PromiseTarget::where('region_code', $this->regionCode)
            ->where('indicator_code', $this->kpi)
            ->whereNotNull('target_districts')
            ->count();
    }
```

- [ ] **Step 3: Delete the `taskCount()` computed**

Delete this block (currently lines ~226-232):

```php
    #[Computed]
    public function taskCount(): int
    {
        return Task::forRegion($this->regionCode)
            ->forIndicator($this->kpi)
            ->count();
    }
```

Leave all other methods, imports, and the `render()` method as-is (`PromiseTarget`, `Task`, `IndicatorFact` are still used by `targetCountByDistrict()`, `taskCountByDistrict()`, `facts()`, etc., so do not remove their `use` statements).

- [ ] **Step 4: Run the page tests to confirm nothing broke**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: PASS (same as end of Task 2 — removing unused computed props changes nothing the view reads).

---

## Task 4: Add and remove CSS in `portal.css`

**Files:**
- Modify: `backend/public/css/portal.css`

Hand-edited, no build. Add the new rules, then delete the obsolete ones.

- [ ] **Step 1: Add the toolbar, KPI pill, and merged-table rules**

Insert this block immediately **before** `.districts-grid {` (currently line ~4097):

```css
    .districts-toolbar {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: flex-end;
      gap: 12px;
      margin-bottom: 12px;
    }

    .districts-toolbar .district-module-tabs { margin-bottom: 0; }

    .district-kpi-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 14px;
    }

    .district-kpi-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid var(--line);
      border-radius: 999px;
      background: #fff;
      padding: 7px 14px 7px 9px;
      color: var(--ink);
      font-size: 13px;
      font-weight: 750;
      cursor: pointer;
      transition: border-color var(--motion), background var(--motion), box-shadow var(--motion);
    }

    .district-kpi-pill:hover,
    .district-kpi-pill.active {
      border-color: rgba(23, 105, 224, .44);
      background: #f7fbff;
      box-shadow: var(--shadow-sm);
    }

    .district-kpi-pill.active { box-shadow: inset 0 -2px 0 var(--blue), var(--shadow-sm); }

    .district-kpi-pill .kpi-mini-icon {
      width: 24px;
      height: 24px;
      border-radius: 7px;
      display: grid;
      place-items: center;
      color: var(--blue);
      background: var(--blue-soft);
    }

    .district-kpi-pill .kpi-mini-icon svg { width: 15px; height: 15px; stroke-width: 1.9; }

    /* Merged district table (extends .district-table) */
    .districts-table .dt-rank {
      width: 34px;
      color: var(--muted);
      font-variant-numeric: tabular-nums;
    }

    .districts-table .row-title .dt-status {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      margin-right: 8px;
      vertical-align: middle;
      background: var(--map-nodata-stroke);
    }

    .districts-table tr.green .dt-status { background: var(--map-good-stroke); }
    .districts-table tr.amber .dt-status { background: var(--map-mid-stroke); }
    .districts-table tr.red   .dt-status { background: var(--map-attn-stroke); }

    .districts-table .dt-exec strong {
      display: block;
      font-variant-numeric: tabular-nums;
    }

    .districts-table .dt-bar {
      display: block;
      height: 4px;
      margin-top: 4px;
      border-radius: 999px;
      background: rgba(98, 148, 162, .14);
      overflow: hidden;
    }

    .districts-table .dt-bar i {
      display: block;
      height: 100%;
      border-radius: 999px;
      background: var(--map-nodata-stroke);
      transition: width var(--motion-slow);
    }

    .districts-table tr.green .dt-bar i { background: var(--map-good-stroke); }
    .districts-table tr.amber .dt-bar i { background: var(--map-mid-stroke); }
    .districts-table tr.red   .dt-bar i { background: var(--map-attn-stroke); }
```

- [ ] **Step 2: Delete the obsolete KPI-selector rules**

Delete the `.district-kpi-selector` rule and every `.district-kpi-option…` rule (currently lines ~3918-3992): `.district-kpi-selector`, `.district-kpi-option`, `.district-kpi-option:hover, .district-kpi-option.active`, `.district-kpi-option.active`, `.district-kpi-option .kpi-mini-icon`, `.district-kpi-option svg`, `.district-kpi-option strong, .district-kpi-option small`, `.district-kpi-option strong`, `.district-kpi-option small`.

- [ ] **Step 3: Delete the obsolete data-layer rules**

Delete every `.district-data-layers`, `.district-data-layer…`, and `.district-layer-note…` rule (currently lines ~3994 up to but **not** including `.districts-grid` at ~4097). Note `.district-data-layer span` is grouped with `.district-layer-note span` — delete that combined selector too.

- [ ] **Step 4: Delete the obsolete leaderboard rules**

Delete every leaderboard rule (currently lines ~4344-4473): `.districts-leaderboard`, `.districts-leaderboard::before`, `.districts-lb-head`, `.districts-lb-head strong`, `.districts-lb-head span`, `.districts-lb-list`, `.lb-row`, `.lb-row:hover`, `.lb-row.selected`, `.lb-rank`, `.lb-name`, `.lb-value`, `.lb-bar`, `.lb-bar i`, `.lb-row.green .lb-bar i`, `.lb-row.amber .lb-bar i`, `.lb-row.red .lb-bar i`, `.lb-row.grey .lb-bar i`, `.lb-empty`.

- [ ] **Step 5: Fix the responsive block reference**

In the `@media (max-width: …)` block that lists `.district-data-layers { grid-template-columns: 1fr; }` (currently ~line 4904), delete that single declaration line. Leave the surrounding `.district-summary-metrics, .district-summary-actions { grid-template-columns: 1fr; }` line intact (still used by the detail panel). The `.districts-side` reference on ~line 4906 is harmless if left, but you may remove `.districts-side` from that selector list since the wrapper no longer renders.

- [ ] **Step 6: Verify CSS has no dangling references**

Run: `cd backend; Select-String -Path public/css/portal.css -Pattern 'district-data-layer|districts-leaderboard|\.lb-|district-kpi-option' | Select-Object -First 5`
Expected: **no output** (all deleted). If any line prints, delete that rule block too.

---

## Task 5: Verify green and commit

**Files:** none (verification + commit).

- [ ] **Step 1: Run the full districts filter (alone — shared test DB)**

Run: `cd backend; php artisan test --filter=Districts`
Expected: PASS — `DistrictsPageTest` (8 tests incl. the 3 rewritten), `DistrictsPageSelectionTest` (2 tests), `DistrictsRegionCodeTest`. Green.

- [ ] **Step 2: Manual visual check**

Run: `cd backend; php artisan serve` and open `http://127.0.0.1:8000/districts`. Confirm:
- One compact toolbar (module tabs + sort/search on top, KPI pills below) — no `Туманлар` heading, no `D-`/`T-` cards.
- Map + detail panel side by side; clicking a map cell or a table row updates both.
- One merged table with `#`, status dot, `Ижро %` bar, plan/fact, `Топшириқлар` (done/total) and `Мажбурият` chips, actions.
- No separate leaderboard.

- [ ] **Step 3: Commit the redesign**

```bash
git add backend/resources/views/livewire/districts-page.blade.php backend/app/Livewire/DistrictsPage.php backend/public/css/portal.css backend/tests/Feature/Http/DistrictsPageTest.php
git commit -m "feat(districts): map + single merged table; drop D-/T- cards"
```

(Single cohesive commit keeps the branch green at every commit. End the commit message with the project's `Co-Authored-By` trailer.)

---

## Self-review notes

- **Spec coverage:** toolbar slim (Task 2 Steps 1-2) · remove D-/T- cards (Task 2 + Task 3) · curated merged table (Task 2 Step 4) · full-metric detail panel (Task 2 Step 3) · plain labels + done/total flip (Task 2 `$taskDone`, chip labels) · CSS add/remove (Task 4) · test rewrites grounded in real tests (Task 1) · run targeted alone (Task 5). All spec sections map to a task.
- **Type consistency:** `$taskDone`, `$taskChipClass`, `$targetChipClass`, `$resolveCell` defined in Task 2 Step 1 and used in Steps 3-4. Class hooks `districts-table`, `dt-rank`, `dt-exec`, `dt-status`, `dt-bar` defined in markup (Task 2) and styled (Task 4) and asserted (Task 1) — names match.
- **Conceptual integrity:** rollup banner + `Ижро %`/`Режа`/`Факт` keep plan-vs-fact; map → row → panel → Профил/Журнал keeps the drill chain.
- **No new computed props required** — detail panel reuses `tableConfig` + `factMatrix` via `resolveCell`.
