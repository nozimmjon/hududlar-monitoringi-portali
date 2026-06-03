# Districts Page Redesign v2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `/districts` as a one-screen, map-centric page: a rich hero header card (segmented module + KPI value + KPI stat-cards), a map beside a compact rank list, no pre-selected district, and a slide-over peek panel for detail — removing the long table entirely.

**Architecture:** One Livewire page. The component loses the table machinery (`tableConfig`/`factMatrix`), stops pre-selecting (`selectedDistrict` returns null unless explicitly chosen), gains `clearDistrict()` and `moduleKpiStats()`. The view is restructured via targeted block edits (the map SVG block is reused). CSS is hand-edited in `portal.css` (no build). Feature tests are rewritten first (TDD).

**Tech Stack:** Laravel 12, Livewire 3, Blade, Alpine (already loaded), PostgreSQL, Pest 3 / PHPUnit 11. Hand-maintained `portal.css`.

---

## File Structure

| File | Change | Responsibility |
| --- | --- | --- |
| `backend/tests/Feature/Http/DistrictsPageTest.php` | Modify | Assert new shell + peek-on-?district + no pre-selection (red first). |
| `backend/tests/Feature/Livewire/DistrictsPageSelectionTest.php` | Modify | Add: `selectedDistrict` is null with no selection. |
| `backend/app/Livewire/DistrictsPage.php` | Modify | No-fallback selection; add `clearDistrict()`, `moduleKpiStats()`; drop `tableConfig()`, `factMatrix()`. |
| `backend/resources/views/livewire/districts-page.blade.php` | Rewrite (5 block edits) | Header card + map + rank list + slide-over peek. Map SVG kept. |
| `backend/public/css/portal.css` | Modify | Add header/ranklist/peek styles; remove v1 table + old detail-panel rules. |

**Conventions (from CLAUDE.md / memory):** UI is Cyrillic Uzbek (don't translate). `portal.css` is hand-maintained — **no `npm run build`**. Tests share one Postgres DB — run ONLY the targeted filter, never the full suite, never two at once. Windows PowerShell: chain with `;`. Run artisan from `backend/`.

---

## Task 1: Rewrite feature tests (red)

**Files:**
- Test: `backend/tests/Feature/Http/DistrictsPageTest.php`

The page markup changes substantially. Replace the markup-asserting tests; keep the state-action tests.

- [ ] **Step 1: Replace the "merged table markup" test** with a new-shell test

Find and replace this test:

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

with:

```php
test('GET /districts renders header card, map, and rank list without pre-selection', function () {
    $response = $this->get('/districts');

    $response->assertOk();
    $response->assertSee('districts-header', false);
    $response->assertSee('module-seg', false);
    $response->assertSee('districts-map', false);
    $response->assertSee('districts-ranklist', false);
    $response->assertSee('Андижон шаҳри', false);
    $response->assertSee('Асака тумани', false);
    // no pre-selection: the peek only renders its contents when a district is chosen
    $response->assertDontSee('Танланган ҳудуд', false);
    $response->assertDontSee('district-peek open', false);
    $response->assertDontSee('districts-table', false);
});
```

- [ ] **Step 2: Replace the "ranked rows + detail panel" test** with a peek-on-?district test

Find and replace this test:

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

with:

```php
test('clicking a district opens the slide-over peek with stats and profile link', function () {
    $response = $this->get('/districts?district=1703224');
    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('district-peek');
    expect($html)->toContain('Танланган ҳудуд');
    expect($html)->toContain('Режа');
    expect($html)->toContain('Факт');
    expect($html)->toContain('/profile?districtCode=1703224');
    expect($html)->not->toContain('districts-table');
    expect($html)->not->toContain('districts-leaderboard');
});
```

- [ ] **Step 3: Replace the plain-labels test** to target the peek (labels live in the peek now)

Find and replace this test:

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

with:

```php
test('peek uses plain task and target labels, not D-/T- codes', function () {
    $response = $this->get('/districts?district=1703224');
    $html = $response->getContent();
    expect($html)->toContain('Топшириқлар');
    expect($html)->toContain('Кафолат мажбурияти');
    expect($html)->not->toContain('T-топшириқ');
    expect($html)->not->toContain('D-мақсад');
});
```

- [ ] **Step 4: Delete the now-invalid tests**

Delete these three tests entirely (the per-KPI metric columns and the all-rows profile links no longer exist on this page — metrics moved to the profile page; profile links now live only in the peek):

- `detail table contains profile link for each district`
- `detail table shows industry-specific column headers for industry KPI`
- `detail table shows budget-specific column headers when budget KPI active`

- [ ] **Step 5: Add a `clearDistrict` test**

Add this test (anywhere among the Livewire tests in the file):

```php
test('clearDistrict resets the selection', function () {
    Livewire::test(DistrictsPage::class)
        ->set('district', '1703224')
        ->call('clearDistrict')
        ->assertSet('district', '');
});
```

- [ ] **Step 6: Run the tests to verify the rewritten ones fail**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: FAIL — the new shell classes (`districts-header`, `module-seg`, `districts-ranklist`), the peek (`district-peek`), and `clearDistrict()` do not exist yet. The kept state tests (`selectModule`, `selectKpi`, `selectDistrict updates state`) still pass.

Do NOT commit (one cohesive commit at the end).

---

## Task 2: Component changes (`DistrictsPage.php`)

**Files:**
- Modify: `backend/app/Livewire/DistrictsPage.php`

- [ ] **Step 1: Remove the pre-selection fallback in `selectedDistrict()`**

Replace this method:

```php
    #[Computed]
    public function selectedDistrict(): ?array
    {
        $rows = $this->rankedDistricts;
        if ($this->district !== '') {
            foreach ($rows as $row) {
                if ((string) $row['district']->code === $this->district) {
                    return $row;
                }
            }
        }
        return $rows[0] ?? null;
    }
```

with:

```php
    #[Computed]
    public function selectedDistrict(): ?array
    {
        if ($this->district === '') {
            return null;
        }
        foreach ($this->rankedDistricts as $row) {
            if ((string) $row['district']->code === $this->district) {
                return $row;
            }
        }
        return null;
    }
```

- [ ] **Step 2: Add `clearDistrict()` next to `selectDistrict()`**

Find:

```php
    public function selectDistrict(string $code): void
    {
        $this->district = $code;
    }
```

Add immediately after it:

```php
    public function clearDistrict(): void
    {
        $this->district = '';
    }
```

- [ ] **Step 3: Add the `moduleKpiStats()` computed**

Add this computed method (e.g., right after `kpiOptions()`):

```php
    /**
     * Region-level value per KPI in the current module, for the header stat-cards.
     *
     * @return array<string, array{indicator:\App\Models\Indicator, value:?float, kind:string}>
     */
    #[Computed]
    public function moduleKpiStats(): array
    {
        $out = [];
        foreach ($this->kpiOptions as $ind) {
            $period = DistrictTableConfig::for($ind->code)['primary_period'];
            $fact = IndicatorFact::where('region_code', $this->regionCode)
                ->where('indicator_code', $ind->code)
                ->where('period', $period)
                ->whereNull('district_code')
                ->first();
            $val = $fact?->pct_of_plan ?? $fact?->growth_pct;
            $out[$ind->code] = [
                'indicator' => $ind,
                'value'     => $val !== null ? (float) $val : null,
                'kind'      => $fact?->pct_of_plan !== null ? 'execution' : 'growth',
            ];
        }
        return $out;
    }
```

- [ ] **Step 4: Delete the unused `tableConfig()` and `factMatrix()` computed properties**

Delete this method:

```php
    #[Computed]
    public function tableConfig(): array
    {
        return DistrictTableConfig::for($this->kpi);
    }
```

And delete the entire `factMatrix()` method (the `#[Computed]` block with the doc-comment `Build [$kpi][$district_code][$period] => IndicatorFact|null lookup ...`, the `pairs` builder, and the query loop returning `$out`).

Leave the `use App\Support\DistrictTableConfig;` and `use App\Models\IndicatorFact;` imports — both are still used (`selectKpi()`, `moduleKpiStats()`, `facts()`). Leave `render()` unchanged.

- [ ] **Step 5: Confirm nothing else references the removed computeds**

Run: `cd backend; Select-String -Path app,resources -Pattern '->tableConfig|->factMatrix|this->tableConfig|this->factMatrix' -Recurse | Select-Object -First 5`
Expected: NO output (the view edits in Task 3 remove the last references). If output appears, it is from the not-yet-edited view — that is expected until Task 3; proceed.

- [ ] **Step 6: Sanity-check the component compiles**

Run: `cd backend; php artisan test --filter='clearDistrict'`
Expected: the `clearDistrict resets the selection` test PASSES (the action exists now). Other tests may still fail (view not yet rebuilt) — that is fine.

---

## Task 3: Rewrite the view (`districts-page.blade.php`)

**Files:**
- Modify: `backend/resources/views/livewire/districts-page.blade.php`

Five block edits. The map `<div class="districts-map-canvas">…</div>` (the SVG) stays untouched.

- [ ] **Step 1: Replace the `@php … @endphp` block (lines 1–55)**

Replace the entire top `@php … @endphp` block with:

```php
@php
    $taskCountByDistrict   = $this->taskCountByDistrict;
    $targetCountByDistrict = $this->targetCountByDistrict;
    $moduleKpiStats        = $this->moduleKpiStats;

    $statusLabel = [
        'green' => 'Яхши', 'amber' => 'Ўртача', 'red' => 'Эътибор', 'grey' => 'Маълумот йўқ',
    ];

    $taskDone      = fn (array $t): int => max(0, $t['total'] - $t['unfinished']);
    $taskChipClass = function (array $t): string {
        if ($t['total'] > 0 && $t['unfinished'] > 0) return 'red';
        if ($t['total'] > 0) return 'green';
        return 'grey';
    };
    $targetChipClass = fn (int $n): string => $n > 0 ? 'blue' : 'grey';

    $fmt = function ($v, int $decimals = 1): string {
        if ($v === null || $v === '') return '—';
        return number_format((float) $v, $decimals, ',', ' ');
    };

    // KPI value text: execution -> "NN,N%"; growth -> "+/−NN,N%"
    $statText = function (?float $v, string $kind) use ($fmt): string {
        if ($v === null) return '—';
        if ($kind === 'growth') return ($v >= 0 ? '+' : '−') . $fmt(abs($v), 1) . '%';
        return $fmt($v, 1) . '%';
    };
    $statUp = fn (?float $v, string $kind): bool => $v !== null && ($kind === 'execution' ? $v >= 100 : $v >= 0);

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

    $kpiShort = $indicator?->label_short ?? $kpi;
    $kpiFull  = $indicator?->label_full  ?? $kpi;

    $regionName = \App\Support\CurrentRegion::current()->name_full;
    $heroVal    = $rollup?->pct_of_plan ?? $rollup?->growth_pct;
    $heroVal    = $heroVal !== null ? (float) $heroVal : null;
    $heroKind   = $rollup?->pct_of_plan !== null ? 'execution' : 'growth';
@endphp
```

- [ ] **Step 2: Replace the `<header class="districts-head"> … </header>` block (lines 58–101)** with the new header card

```blade
    <header class="districts-header">
        <div class="districts-header-top">
            <div class="module-seg">
                @foreach($moduleOptions as $m)
                    <button class="module-seg-btn {{ $m->code === $module ? 'on' : '' }}"
                            wire:click="selectModule('{{ $m->code }}')" type="button">
                        {{ preg_replace('/^\d+\.\s*/u', '', $m->label) }}
                    </button>
                @endforeach
            </div>
            <div class="districts-tools">
                <label class="districts-control districts-control--search">
                    <span>Қидириш</span>
                    <input wire:model.live.debounce.300ms="search" placeholder="Туман қидириш">
                </label>
                <label class="districts-control">
                    <span>Саралаш</span>
                    <select wire:model.live="sort">
                        <option value="attention">Эътибор талаб</option>
                        <option value="execution">Юқоридан</option>
                        <option value="plan">Режа каттадан</option>
                        <option value="name">Алифбо бўйича</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="districts-hero">
            <span class="districts-hero-icon" aria-hidden="true">@include('partials.icon', ['name' => $indicator?->icon ?? 'trend'])</span>
            <div class="districts-hero-title">
                <h2>{{ $kpiFull }}</h2>
                <span>{{ $regionName }} · туманлар кесими</span>
            </div>
            <div class="districts-hero-value">
                <strong class="{{ $heroKind === 'growth' ? ($statUp($heroVal, $heroKind) ? 'up' : 'down') : '' }}">{{ $statText($heroVal, $heroKind) }}</strong>
                <small>вилоят бўйича</small>
            </div>
            <span class="chip blue districts-hero-period">{{ $period }}</span>
        </div>

        @if($kpiOptions->count() > 1)
            <div class="kpi-stats">
                @foreach($kpiOptions as $i)
                    @php
                        $st = $moduleKpiStats[$i->code] ?? null;
                        $sv = $st['value'] ?? null;
                        $sk = $st['kind'] ?? 'growth';
                    @endphp
                    <button class="kpi-stat-card {{ $i->code === $kpi ? 'on' : '' }}"
                            wire:click="selectKpi('{{ $i->code }}')" type="button"
                            title="{{ $i->label_full }}">
                        <span class="kpi-stat-icon" aria-hidden="true">@include('partials.icon', ['name' => $i->icon ?? 'trend'])</span>
                        <span class="kpi-stat-body">
                            <small>{{ $i->label_short }}</small>
                            <strong>{{ $statText($sv, $sk) }}</strong>
                        </span>
                        @if($sv !== null)
                            <span class="kpi-stat-trend {{ $statUp($sv, $sk) ? 'up' : 'down' }}" aria-hidden="true">{{ $statUp($sv, $sk) ? '▲' : '▼' }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    </header>
```

- [ ] **Step 3: Remove the rollup banner from inside the map and simplify the map head**

Find this block (lines 105–123) — the map head, the `@php $regionName/$rollupValue` block, and the rollup banner div:

```blade
            <header class="districts-map-head">
                <div>
                    <strong>{{ $kpiShort }} — {{ $kpiFull }}</strong>
                    <span>Ҳар бир туман ранги вилоятдаги ўрнига нисбатан.</span>
                </div>
            </header>
            @php
                $regionName = \App\Support\CurrentRegion::current()->name_full;
                $rollupValue = $rollup?->pct_of_plan !== null
                    ? $fmt($rollup->pct_of_plan, 1) . '%'
                    : ($rollup?->growth_pct !== null ? $fmt($rollup->growth_pct, 1) . '%' : '—');
            @endphp
            <div class="districts-rollup-banner">
                <div>
                    <span class="rollup-label">{{ $regionName }} · {{ $kpiShort }}</span>
                    <strong class="rollup-value">{{ $rollupValue }}</strong>
                </div>
                <span class="chip blue">{{ $period }}</span>
            </div>
```

Replace it with just the simplified map head:

```blade
            <header class="districts-map-head">
                <div>
                    <strong>Ҳудудлар харитаси</strong>
                    <span>Ҳар туман ранги вилоятдаги ўрнига нисбатан. Туман устига босинг.</span>
                </div>
            </header>
```

(`$regionName` is now defined in the top `@php` block, so it is no longer needed here.)

- [ ] **Step 4: Replace the detail panel `<section class="district-summary-card …"> … </section>` block (lines 186–224)** with the rank list

```blade
        <section class="districts-ranklist">
            <header class="ranklist-head">
                <strong>Туманлар рейтинги</strong>
                <span>{{ count($rankedDistricts) }} та</span>
            </header>
            <ol class="ranklist-rows">
                @foreach($rankedDistricts as $idx => $row)
                    @php
                        $rd = $row['district']; $code = $rd->code; $rf = $row['fact']; $rs = $row['status'];
                        $rPct = $rf?->pct_of_plan !== null ? (float) $rf->pct_of_plan : null;
                        $rGrowth = $rf?->growth_pct !== null ? (float) $rf->growth_pct : null;
                        $primary = $rPct !== null ? $fmt($rPct, 1) . '%' : ($rGrowth !== null ? $fmt($rGrowth, 1) . '%' : '—');
                        $barW = $rPct !== null ? max(0, min(100, $rPct)) : 0;
                    @endphp
                    <li class="rank-row {{ $rs }} {{ $code === $selectedCode ? 'selected' : '' }}"
                        wire:click="selectDistrict('{{ $code }}')"
                        tabindex="0"
                        x-on:keydown.enter="$wire.selectDistrict('{{ $code }}')"
                        x-on:keydown.space.prevent="$wire.selectDistrict('{{ $code }}')">
                        <span class="rank-rk">{{ $idx + 1 }}</span>
                        <span class="rank-dot" aria-hidden="true"></span>
                        <span class="rank-nm">{{ $rd->name_full }}</span>
                        <span class="rank-vbar">
                            <span class="rank-bar"><i style="width:{{ number_format($barW, 1, '.', '') }}%"></i></span>
                            <span class="rank-vv">{{ $primary }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>
```

- [ ] **Step 5: Replace the table `<section class="panel district-detail-table"> … </section>` block (lines 227–291)** with the slide-over peek

```blade
    <div class="district-peek-backdrop {{ $selectedDistrict ? 'open' : '' }}" wire:click="clearDistrict"></div>
    <aside class="district-peek {{ $selectedDistrict ? 'open' : '' }}" aria-hidden="{{ $selectedDistrict ? 'false' : 'true' }}">
        @if($selectedDistrict)
            <button class="district-peek-x" wire:click="clearDistrict" type="button" aria-label="Ёпиш">×</button>
            <div class="district-peek-head">
                <span class="district-peek-eyebrow">Танланган ҳудуд</span>
                <h2>{{ $selectedDistrict->name_full }}</h2>
                <span class="chip {{ $selectedStatus }}">{{ $statusLabel[$selectedStatus] ?? '—' }}</span>
            </div>
            <div class="district-peek-value">
                <strong>{{ $selectedFact?->pct_of_plan !== null ? $fmt($selectedFact->pct_of_plan, 1) . '%' : '—' }}</strong>
                <span>Ижро бажарилиши · {{ $kpiShort }}</span>
            </div>
            <div class="district-peek-pf">
                <div><small>Режа</small><strong>{{ $selectedFact?->plan_value !== null ? $fmt($selectedFact->plan_value, 1) : '—' }}</strong></div>
                <div><small>Факт</small><strong>{{ ($selectedFact?->actual_hokimyat ?? $selectedFact?->actual_statkom) !== null ? $fmt($selectedFact->actual_hokimyat ?? $selectedFact->actual_statkom, 1) : '—' }}</strong></div>
            </div>
            <div class="district-peek-chips">
                <span class="chip {{ $taskChipClass($selectedTasks) }}">Топшириқлар {{ $taskDone($selectedTasks) }}/{{ $selectedTasks['total'] }}</span>
                <span class="chip {{ $targetChipClass($selectedTargetCount) }}">Кафолат мажбурияти {{ $selectedTargetCount }}</span>
            </div>
            <div class="district-peek-actions">
                <a class="mini-button primary" href="{{ route('profile') }}?districtCode={{ $selectedCode }}">Профил</a>
                <a class="mini-button" href="{{ route('execution') }}?indicator={{ $kpi }}&district={{ $selectedCode }}&period={{ $period }}">Журнал</a>
            </div>
        @endif
    </aside>
```

- [ ] **Step 6: Run the page tests**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: the rewritten Task 1 tests PASS. If `clicking a district opens the slide-over peek` fails on `Танланган ҳудуд`, confirm `?district=1703224` matches a seeded district and `selectedDistrict()` (Task 2) has no fallback removed incorrectly.

---

## Task 4: CSS — add new styles, remove obsolete (`portal.css`)

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Add the new styles** immediately before `.districts-grid {`

```css
    /* ===== districts header card (hero + segmented module + KPI stat cards) ===== */
    .districts-header { background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:var(--shadow-sm); padding:6px 6px 0; margin-bottom:14px; }
    .districts-header-top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:8px 10px; }
    .module-seg { display:inline-flex; background:#f1f4f9; border-radius:11px; padding:3px; gap:2px; }
    .module-seg-btn { border:0; background:transparent; font:inherit; font-size:13px; font-weight:650; color:var(--muted); padding:8px 15px; border-radius:8px; cursor:pointer; transition:background var(--motion), color var(--motion); }
    .module-seg-btn.on { background:var(--blue); color:#fff; box-shadow:0 2px 6px rgba(23,105,224,.3); }
    .districts-tools { display:inline-flex; gap:8px; }
    .districts-hero { display:flex; align-items:center; gap:14px; padding:6px 12px 14px; flex-wrap:wrap; }
    .districts-hero-icon { width:48px; height:48px; border-radius:13px; flex:none; background:linear-gradient(135deg,#1769e0,#0f2d63); color:#fff; display:grid; place-items:center; }
    .districts-hero-icon svg { width:24px; height:24px; }
    .districts-hero-title h2 { margin:0; font-size:22px; letter-spacing:-.02em; }
    .districts-hero-title span { font-size:12.5px; color:var(--muted); }
    .districts-hero-value { margin-left:auto; text-align:right; }
    .districts-hero-value strong { display:block; font-size:28px; font-weight:850; letter-spacing:-.02em; line-height:1; color:var(--ink); }
    .districts-hero-value strong.up { color:var(--map-good-stroke); }
    .districts-hero-value strong.down { color:var(--map-attn-stroke); }
    .districts-hero-value small { font-size:11.5px; color:var(--muted); }
    .districts-hero-period { align-self:center; }
    .kpi-stats { display:flex; gap:9px; padding:12px 10px; border-top:1px solid var(--line); flex-wrap:wrap; }
    .kpi-stat-card { flex:1 1 160px; display:flex; align-items:center; gap:11px; background:#fff; border:1px solid var(--line); border-radius:12px; padding:10px 13px; cursor:pointer; text-align:left; transition:border-color var(--motion), box-shadow var(--motion); }
    .kpi-stat-card:hover { border-color:rgba(23,105,224,.4); }
    .kpi-stat-card.on { border-color:rgba(23,105,224,.55); box-shadow:0 0 0 1px rgba(23,105,224,.3), var(--shadow-sm); }
    .kpi-stat-icon { width:34px; height:34px; border-radius:9px; flex:none; background:var(--blue-soft); color:var(--blue); display:grid; place-items:center; }
    .kpi-stat-icon svg { width:18px; height:18px; }
    .kpi-stat-body { min-width:0; }
    .kpi-stat-body small { display:block; font-size:11.5px; color:var(--muted); font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .kpi-stat-body strong { font-size:17px; font-weight:850; letter-spacing:-.01em; }
    .kpi-stat-trend { margin-left:auto; font-size:12px; font-weight:800; }
    .kpi-stat-trend.up { color:var(--map-good-stroke); }
    .kpi-stat-trend.down { color:var(--map-attn-stroke); }

    /* ===== rank list ===== */
    .districts-ranklist { background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:var(--shadow-sm); padding:14px 8px 10px 14px; display:flex; flex-direction:column; min-width:0; }
    .ranklist-head { display:flex; align-items:baseline; justify-content:space-between; padding-right:8px; margin-bottom:8px; }
    .ranklist-head strong { font-size:15px; }
    .ranklist-head span { font-size:11.5px; color:var(--muted); }
    .ranklist-rows { list-style:none; margin:0; padding:0 6px 0 0; overflow-y:auto; max-height:440px; }
    .rank-row { display:grid; grid-template-columns:20px 10px minmax(0,1fr) auto; align-items:center; gap:10px; padding:9px 8px; border-radius:9px; cursor:pointer; border:1px solid transparent; outline:none; transition:background var(--motion), border-color var(--motion); }
    .rank-row + .rank-row { margin-top:1px; }
    .rank-row:hover { background:#f5f8fd; }
    .rank-row:focus-visible { border-color:rgba(23,105,224,.5); }
    .rank-row.selected { background:#eef5ff; border-color:rgba(23,105,224,.4); }
    .rank-rk { font-size:11px; color:var(--muted); font-weight:700; text-align:center; font-variant-numeric:tabular-nums; }
    .rank-dot { width:8px; height:8px; border-radius:50%; background:var(--map-nodata-stroke); }
    .rank-row.green .rank-dot { background:var(--map-good-stroke); }
    .rank-row.amber .rank-dot { background:var(--map-mid-stroke); }
    .rank-row.red .rank-dot { background:var(--map-attn-stroke); }
    .rank-nm { font-size:13px; font-weight:550; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .rank-vbar { display:flex; align-items:center; gap:9px; }
    .rank-bar { width:66px; height:5px; border-radius:99px; background:#eef1f6; overflow:hidden; }
    .rank-bar i { display:block; height:100%; border-radius:99px; background:var(--map-nodata-stroke); transition:width var(--motion-slow); }
    .rank-row.green .rank-bar i { background:var(--map-good-stroke); }
    .rank-row.amber .rank-bar i { background:var(--map-mid-stroke); }
    .rank-row.red .rank-bar i { background:var(--map-attn-stroke); }
    .rank-vv { font-size:12.5px; font-weight:800; font-variant-numeric:tabular-nums; min-width:50px; text-align:right; }

    /* ===== slide-over peek ===== */
    .district-peek-backdrop { position:fixed; inset:0; background:rgba(16,32,58,.28); opacity:0; visibility:hidden; transition:opacity var(--motion), visibility var(--motion); z-index:60; }
    .district-peek-backdrop.open { opacity:1; visibility:visible; }
    .district-peek { position:fixed; top:0; right:0; bottom:0; width:360px; max-width:88vw; background:#fff; border-left:1px solid var(--line); box-shadow:-14px 0 34px rgba(20,40,90,.18); padding:20px; display:flex; flex-direction:column; gap:13px; z-index:61; transform:translateX(100%); transition:transform var(--motion-slow); overflow-y:auto; }
    .district-peek.open { transform:translateX(0); }
    .district-peek-x { position:absolute; top:14px; right:14px; width:28px; height:28px; border-radius:8px; border:1px solid var(--line); background:#fff; color:var(--muted); cursor:pointer; font-size:16px; line-height:1; }
    .district-peek-head .district-peek-eyebrow { font-size:11px; color:var(--muted); font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
    .district-peek-head h2 { margin:3px 0 8px; font-size:21px; letter-spacing:-.015em; }
    .district-peek-value { background:var(--blue-soft); border:1px solid rgba(23,105,224,.18); border-radius:13px; padding:14px; }
    .district-peek-value strong { display:block; font-size:32px; color:var(--blue); line-height:1; letter-spacing:-.02em; }
    .district-peek-value span { font-size:12px; color:var(--muted); }
    .district-peek-pf { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .district-peek-pf div { border:1px solid var(--line); border-radius:11px; padding:9px 11px; }
    .district-peek-pf small { display:block; font-size:10.5px; color:var(--muted); font-weight:800; text-transform:uppercase; letter-spacing:.03em; }
    .district-peek-pf strong { font-size:15px; }
    .district-peek-chips { display:flex; gap:8px; flex-wrap:wrap; }
    .district-peek-actions { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:auto; }
    .district-peek-actions .mini-button { min-height:40px; text-align:center; }
```

- [ ] **Step 2: Delete the obsolete v1 + old-detail rule blocks**

Read the file and delete every rule whose selector starts with any of these (they belong to markup removed in Task 3). Delete the full rule block for each:

- v1 toolbar: `.districts-toolbar`, `.districts-toolbar .district-module-tabs`, `.district-kpi-pills`, `.district-kpi-pill` (and `:hover`/`.active`/`.kpi-mini-icon`/`svg` variants)
- v1 table: `.districts-table .dt-rank`, `.districts-table .row-title .dt-status`, `.districts-table tr.green/.amber/.red .dt-status`, `.districts-table .row-title strong`, `.districts-table .dt-exec strong`, `.districts-table .dt-bar`, `.districts-table .dt-bar i`, `.districts-table tr.green/.amber/.red .dt-bar i`
- old detail panel: `.district-summary-card`, `.district-summary-card.empty`, `.district-summary-head`, `.district-summary-head span`, `.district-summary-head h3`, `.district-summary-value`, `.district-summary-value strong`, `.district-summary-value span, .district-summary-value small`, `.district-summary-metrics`, `.district-summary-metric`, `.district-summary-metric span`, `.district-summary-metric strong`, `.district-summary-metric small`, `.district-summary-actions`, `.district-summary-actions .mini-button`, `.district-count-split`
- rollup banner: `.districts-rollup-banner`, `.rollup-label`, `.rollup-value`

- [ ] **Step 3: Delete the old detail-table base rules — but grep-guard first**

Run: `cd backend; Select-String -Path resources -Pattern 'district-table|district-detail-table' -Recurse | Select-Object -First 10`
Expected after Task 3: NO output (the table markup is gone from the only view that used it). If NO output, delete these rule blocks: `.district-detail-table`, `.district-table-wrap`, `.district-table`, `.district-table td:first-child, .district-table th:first-child`, `.district-table .row-title strong`, `.district-table .row-title span, .district-table small`, `.district-table tr.active-row td:first-child`. If any output appears (another view uses them), leave those rules in place.

- [ ] **Step 4: Fix responsive references**

In the `@media (max-width: …)` blocks, remove any declaration line that targets a now-deleted selector: `.district-summary-metrics, .district-summary-actions { … }` and any `.districts-rollup-banner`/`.district-data-layers` leftover. Leave `.districts-grid { grid-template-columns: 1fr; }` and `.districts-map { … }` intact (still used).

- [ ] **Step 5: Verify no dangling references**

Run: `cd backend; Select-String -Path public/css/portal.css -Pattern 'districts-toolbar|district-kpi-pill|\.dt-rank|\.dt-status|\.dt-bar|\.dt-exec|district-summary-|district-count-split|districts-rollup-banner' | Select-Object -First 10`
Expected: NO output.

Run: `cd backend; Select-String -Path public/css/portal.css -Pattern 'districts-header|module-seg|districts-hero|kpi-stat|districts-ranklist|rank-row|district-peek' | Select-Object -First 8`
Expected: several matches (new styles present).

- [ ] **Step 6: Sanity test**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: all DistrictsPage tests PASS (CSS does not affect server-rendered assertions, but confirms nothing broke).

---

## Task 5: Selection test + verify + commit

**Files:**
- Modify: `backend/tests/Feature/Livewire/DistrictsPageSelectionTest.php`

- [ ] **Step 1: Add a no-pre-selection test**

Add to `DistrictsPageSelectionTest.php`:

```php
test('selectedDistrict is null when nothing is selected', function () {
    $component = Livewire::test(DistrictsPage::class);
    expect(invade($component->instance())->selectedDistrict())->toBeNull();
});
```

- [ ] **Step 2: Run the full districts filter (alone — shared DB)**

Run: `cd backend; php artisan test --filter=Districts`
Expected: PASS — `DistrictsPageTest`, `DistrictsPageSelectionTest`, `DistrictsRegionCodeTest` all green.

- [ ] **Step 3: Manual visual check (headless screenshot)**

The dev server may already be running on port 8000. If not: `cd backend; php artisan serve --host=127.0.0.1 --port=8000` (background). Then capture and inspect:

```
& "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless=new --disable-gpu --hide-scrollbars --no-first-run --user-data-dir="C:/Users/y.utepbergenov/AppData/Local/Temp/edge-verify" --window-size=1600,1400 --screenshot="C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/.superpowers/verify-districts.png" "http://127.0.0.1:8000/districts"
```

Confirm: header card (segmented module + hero KPI value + stat-cards), map + compact rank list, **nothing pre-selected**, no long table. Then load `http://127.0.0.1:8000/districts?district=<code>` and confirm the slide-over peek opens.

- [ ] **Step 4: Commit**

```bash
git add backend/resources/views/livewire/districts-page.blade.php backend/app/Livewire/DistrictsPage.php backend/public/css/portal.css backend/tests/Feature/Http/DistrictsPageTest.php backend/tests/Feature/Livewire/DistrictsPageSelectionTest.php
git commit -m "feat(districts): map + rank list + slide-over peek; no pre-selection"
```

End the commit message with the project's `Co-Authored-By` trailer.

---

## Self-review notes

- **Spec coverage:** header card (T3 S2) · hero value replacing banner (T3 S1/S2/S3) · KPI stat-cards + `moduleKpiStats` (T2 S3, T3 S2) · map + rank list (T3 S3/S4) · no pre-selection (T2 S1, tests) · slide-over peek + `clearDistrict` (T2 S2, T3 S5) · remove table/`tableConfig`/`factMatrix` (T2 S4, T3 S5) · CSS add/remove (T4) · test rewrites (T1, T5). All spec sections map to a task.
- **Type consistency:** `$statText`/`$statUp` defined in T3 S1, used in T3 S2; `moduleKpiStats` shape (`indicator`/`value`/`kind`) defined in T2 S3, consumed in T3 S2; class hooks `districts-header`, `module-seg`, `kpi-stat-card`, `districts-ranklist`, `rank-row`, `district-peek` defined in markup (T3), styled (T4), asserted (T1). Names match.
- **Conceptual integrity:** hero region value + rank-list exec bars + peek Режа/Факт keep plan-vs-fact; map/rank → peek → Профил/Журнал keep the drill chain; full metric depth intentionally relocated to the profile page.
- **No pre-selection correctness:** `selectedDistrict()` returns null when `district === ''`; the peek `@if($selectedDistrict)` renders nothing, and tests assert `Танланган ҳудуд` absent on bare GET.
