# District Profile Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace bare `RegionProfile` Livewire component with full 4-section profile (filter, hero, KPIs grid, bottom = reports + tasks) matching the `index.html` prototype's `renderProfilePage()` output.

**Architecture:** Single Livewire component owning two URL-synced properties (`$districtCode`, `$kpi`) and ~10 computed properties (`district`, `facts`, `availableKpis`, `selectedIndicator`, `tableConfig`, `selectedFact`, `status`, `tasksForKpi`, `taskCounts`). Main blade view includes five focused partials (empty, filter, hero, kpis-grid, bottom). Reuses existing `DistrictStatus`, `DistrictTableConfig`, `DashboardCatalog` helpers. CSS classes mirror existing `profile-*` styles in `public/css/portal.css`.

**Tech Stack:** PHP 8.3 · Laravel 11 · Livewire 3 · Pest 3 · PostgreSQL · Cyrillic Uzbek UI labels. All commands run from `backend/`.

---

## File structure

| File | Responsibility |
|---|---|
| `backend/app/Livewire/RegionProfile.php` | rewrite — orchestrator with computed properties + selectKpi action |
| `backend/resources/views/livewire/region-profile.blade.php` | rewrite — thin orchestrator including 5 partials |
| `backend/resources/views/livewire/profile/empty.blade.php` | new — empty-state placeholder for missing district |
| `backend/resources/views/livewire/profile/filter.blade.php` | new — district + KPI selects + action buttons |
| `backend/resources/views/livewire/profile/hero.blade.php` | new — 2-col grid: focus card + quick-status side panel |
| `backend/resources/views/livewire/profile/kpis-grid.blade.php` | new — all-KPIs button grid |
| `backend/resources/views/livewire/profile/bottom.blade.php` | new — 2-col grid: reports empty + tasks list |
| `backend/tests/Feature/Livewire/RegionProfileTest.php` | new — 8 tests covering URL state, KPI fallback, computed properties, smoke |

---

### Task 1: Component rewrite + empty partial + orchestrator

**Files:**
- Modify: `backend/app/Livewire/RegionProfile.php`
- Modify: `backend/resources/views/livewire/region-profile.blade.php`
- Create: `backend/resources/views/livewire/profile/empty.blade.php`
- Create: `backend/tests/Feature/Livewire/RegionProfileTest.php`

- [ ] **Step 1: Write the failing test scaffolding**

Create `backend/tests/Feature/Livewire/RegionProfileTest.php`:

```php
<?php

use App\Livewire\RegionProfile;
use App\Models\Indicator;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('renders empty state when districtCode is missing', function () {
    Livewire::test(RegionProfile::class)
        ->assertSee('Туман танланмаган');
});

test('renders empty state when districtCode does not match any district', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '999999'])
        ->assertSee('Туман топилмади');
});

test('mounts with valid districtCode and kpi', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'industry'])
        ->assertViewHas('district', fn ($d) => $d !== null && $d->code === 1703401)
        ->assertViewHas('selectedIndicator', fn ($i) => $i !== null && $i->code === 'industry');
});

test('mount falls back to first available kpi when current kpi is unknown', function () {
    $component = Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'nonexistent_kpi']);
    expect($component->get('kpi'))->not->toBe('nonexistent_kpi');
    expect($component->get('kpi'))->toBeString()->not->toBeEmpty();
});

test('selectKpi action updates kpi state', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'industry'])
        ->call('selectKpi', 'grp')
        ->assertSet('kpi', 'grp');
});

test('taskCounts reflects tasks linked to district and KPI', function () {
    $task = Task::factory()->create([
        'region_code' => 1703, 'module_code' => 'macro',
        'indicator_code' => 'industry', 'status' => 'open',
    ]);
    $districtId = \DB::table('districts')->where('code', 1703401)->value('id');
    \DB::table('task_districts')->insert(['task_id' => $task->id, 'district_id' => $districtId]);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'industry'])
        ->assertViewHas('taskCounts', fn ($c) => $c['total'] === 1 && $c['unfinished'] === 1);
});

test('tasks panel renders empty state when no tasks match', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'grp'])
        ->assertSee('Бу KPI бўйича топшириқ топилмади');
});

test('reports panel always shows empty state', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'industry'])
        ->assertSee('Ҳисобот йўқ');
});
```

- [ ] **Step 2: Run tests → expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire/RegionProfileTest.php
```

Expected: 8/8 FAIL — current `RegionProfile` lacks computed properties, selectKpi action, and the partials don't exist.

- [ ] **Step 3: Rewrite `RegionProfile.php`**

Replace `backend/app/Livewire/RegionProfile.php` entirely:

```php
<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Models\Task;
use App\Support\DistrictStatus;
use App\Support\DistrictTableConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class RegionProfile extends Component
{
    private const REGION_CODE = 1703;
    private const YEAR        = 2026;

    #[Url]
    public string $districtCode = '';

    #[Url]
    public string $kpi = 'industry';

    public function selectKpi(string $code): void
    {
        $this->kpi = $code;
    }

    public function mount(): void
    {
        $kpis = $this->availableKpis();
        if ($kpis->isNotEmpty() && ! $kpis->firstWhere('code', $this->kpi)) {
            $this->kpi = $kpis->first()->code;
        }
    }

    #[Computed]
    public function district(): ?District
    {
        if ($this->districtCode === '') return null;
        return District::where('region_code', self::REGION_CODE)
            ->where('code', (int) $this->districtCode)
            ->first();
    }

    #[Computed]
    public function facts(): Collection
    {
        if (! $this->district) return collect();
        return IndicatorFact::where('region_code', self::REGION_CODE)
            ->where('year', self::YEAR)
            ->where('district_code', $this->district->code)
            ->where('period', 'year')
            ->get()
            ->keyBy('indicator_code');
    }

    #[Computed]
    public function availableKpis(): Collection
    {
        $codes = DB::table('region_indicator_availability')
            ->where('region_code', self::REGION_CODE)
            ->where('status', 'available')
            ->pluck('indicator_code');

        return Indicator::whereIn('code', $codes)
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function selectedIndicator(): ?Indicator
    {
        return $this->availableKpis->firstWhere('code', $this->kpi);
    }

    #[Computed]
    public function tableConfig(): array
    {
        return DistrictTableConfig::for($this->kpi);
    }

    #[Computed]
    public function selectedFact(): ?IndicatorFact
    {
        return $this->facts->get($this->kpi);
    }

    #[Computed]
    public function status(): string
    {
        $fact = $this->selectedFact;
        return $fact ? DistrictStatus::for($fact) : 'grey';
    }

    #[Computed]
    public function tasksForKpi(): Collection
    {
        if (! $this->district) return collect();
        return Task::forRegion(self::REGION_CODE)
            ->forIndicator($this->kpi)
            ->forDistrict($this->district->id)
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function taskCounts(): array
    {
        if (! $this->district) return ['total' => 0, 'unfinished' => 0];
        $base = Task::forRegion(self::REGION_CODE)
            ->forIndicator($this->kpi)
            ->forDistrict($this->district->id);
        $total = (clone $base)->count();
        $done  = (clone $base)->where('status', 'done')->count();
        return ['total' => $total, 'unfinished' => $total - $done];
    }

    public function render()
    {
        return view('livewire.region-profile', [
            'district'             => $this->district,
            'facts'                => $this->facts,
            'availableKpis'        => $this->availableKpis,
            'selectedIndicator'    => $this->selectedIndicator,
            'tableConfig'          => $this->tableConfig,
            'selectedFact'         => $this->selectedFact,
            'status'               => $this->status,
            'tasks'                => $this->tasksForKpi,
            'taskCounts'           => $this->taskCounts,
            'districtTargetsCount' => 0,
        ]);
    }
}
```

- [ ] **Step 4: Create thin orchestrator view**

Replace `backend/resources/views/livewire/region-profile.blade.php` entirely:

```blade
<div>
    @if(! $districtCode || ! $district)
        @include('livewire.profile.empty', ['districtCode' => $districtCode])
    @else
        @include('livewire.profile.filter',    ['district' => $district, 'kpi' => $kpi, 'availableKpis' => $availableKpis])
        @include('livewire.profile.hero',      ['district' => $district, 'indicator' => $selectedIndicator, 'fact' => $selectedFact, 'status' => $status, 'tableConfig' => $tableConfig, 'taskCounts' => $taskCounts, 'districtTargetsCount' => $districtTargetsCount])
        @include('livewire.profile.kpis-grid', ['district' => $district, 'availableKpis' => $availableKpis, 'kpi' => $kpi, 'facts' => $facts])
        @include('livewire.profile.bottom',    ['district' => $district, 'kpi' => $kpi, 'tasks' => $tasks, 'taskCounts' => $taskCounts, 'indicator' => $selectedIndicator])
    @endif
</div>
```

- [ ] **Step 5: Create empty partial**

Create `backend/resources/views/livewire/profile/empty.blade.php`:

```blade
@if(! $districtCode)
    <div style="padding: 32px; text-align: center; color: var(--muted);">
        <p>Туман танланмаган. <a href="{{ route('districts') }}">Туманлар</a> саҳифасидан туманни танланг.</p>
    </div>
@else
    <div style="padding: 32px; text-align: center; color: var(--muted);">
        <p>Туман топилмади: <code>{{ $districtCode }}</code></p>
    </div>
@endif
```

- [ ] **Step 6: Create empty stub partials so includes don't crash**

Create empty placeholders that will be filled in later tasks. These keep Task 1's tests passing without 500 errors on missing includes:

```bash
mkdir -p backend/resources/views/livewire/profile
for p in filter hero kpis-grid bottom; do
    echo '<div class="profile-stub-'"$p"'"></div>' > "backend/resources/views/livewire/profile/${p}.blade.php"
done
```

- [ ] **Step 7: Run tests → expect 5-6 PASS, 2-3 FAIL**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire/RegionProfileTest.php
```

Expected at this stage:
- PASS: empty state tests, mount tests, selectKpi, taskCounts.
- FAIL: `assertSee('Бу KPI бўйича топшириқ топилмади')` (bottom partial stub doesn't render text) and `assertSee('Ҳисобот йўқ')` (same).

These two will pass once Task 5 fills `bottom.blade.php`. Carry forward.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Livewire/RegionProfile.php backend/resources/views/livewire/region-profile.blade.php backend/resources/views/livewire/profile/ backend/tests/Feature/Livewire/RegionProfileTest.php
git commit -m "feat(profile): RegionProfile orchestrator + computed properties + empty state"
```

---

### Task 2: Filter partial

**Files:**
- Modify: `backend/resources/views/livewire/profile/filter.blade.php`

The filter has two selects (district, KPI) plus three action buttons matching the prototype's `profile-filter`.

- [ ] **Step 1: Write the partial**

Replace `backend/resources/views/livewire/profile/filter.blade.php`:

```blade
@php
    $districts = \App\Models\District::where('region_code', 1703)->orderBy('sort_order')->get(['code', 'name_full']);
    $moduleCode = $availableKpis->firstWhere('code', $kpi)?->module_code ?? 'macro';
@endphp

<div class="profile-filter">
    <label>Туман/шаҳар танлаш
        <select wire:model.live="districtCode">
            @foreach($districts as $d)
                <option value="{{ $d->code }}" @selected((string) $d->code === $districtCode)>{{ $d->name_full }}</option>
            @endforeach
        </select>
    </label>
    <label>KPI / маълумот тури
        <select wire:model.live="kpi">
            @foreach($availableKpis as $i)
                <option value="{{ $i->code }}" @selected($i->code === $kpi)>{{ $i->label_short }} — {{ $i->label_full }}</option>
            @endforeach
        </select>
    </label>
    <div class="action-row" style="margin-top:0">
        <a class="mini-button" href="{{ route('districts') }}?kpi={{ $kpi }}">Туманлар кесимига қайтиш</a>
        <button class="mini-button" type="button" disabled title="Тез орада">Ҳисобот киритиш</button>
        <a class="mini-button primary" href="{{ route('kpi') }}?module={{ $moduleCode }}&kpi={{ $kpi }}">KPI экрани</a>
    </div>
</div>
```

- [ ] **Step 2: Manual verification**

Run dev server, visit `/profile?districtCode=1703401&kpi=industry`. Confirm:
- Two selects appear, populated with districts and KPIs.
- Changing district select updates URL `?districtCode=<new>`.
- Changing KPI select updates URL `?kpi=<new>`.
- "Туманлар кесимига қайтиш" link goes to `/districts?kpi=industry`.
- "Ҳисобот киритиш" button visible but disabled.
- "KPI экрани" link goes to `/kpi?module=macro&kpi=industry`.

- [ ] **Step 3: Commit**

```bash
git add backend/resources/views/livewire/profile/filter.blade.php
git commit -m "feat(profile): filter partial — district select + KPI select + action buttons"
```

---

### Task 3: Hero partial

**Files:**
- Modify: `backend/resources/views/livewire/profile/hero.blade.php`

The hero is a 2-column grid: profile-focus (left) + Қисқа ҳолат side panel (right).

- [ ] **Step 1: Write the partial**

Replace `backend/resources/views/livewire/profile/hero.blade.php`:

```blade
@php
    $statusLabel = ['green' => 'Яхши', 'amber' => 'Ўртача', 'red' => 'Эътибор', 'grey' => 'Маълумот йўқ'][$status] ?? 'Маълумот йўқ';
    $taskChip = $taskCounts['total'] > 0 && $taskCounts['unfinished'] > 0 ? 'red'
              : ($taskCounts['total'] > 0 ? 'green' : 'grey');
    $primaryValue = match (true) {
        $fact?->pct_of_plan !== null => number_format((float) $fact->pct_of_plan, 1, ',', ' ') . '%',
        $fact?->growth_pct !== null  => number_format((float) $fact->growth_pct, 1, ',', ' ') . '%',
        $fact?->plan_value !== null  => number_format((float) $fact->plan_value, 1, ',', ' '),
        default                      => '—',
    };
    $unit = $indicator?->default_unit ?? '';
    $moduleLabel = $indicator?->module_code ? \App\Support\DashboardCatalog::MODULES[$indicator->module_code]['label'] ?? '' : '';
@endphp

<div class="profile-grid">
    <article class="profile-focus">
        <div class="profile-hero">
            <div>
                <div class="eyebrow">{{ preg_replace('/^\d+\.\s*/u', '', $moduleLabel) }}</div>
                <h3>{{ $district->name_full }}: {{ $indicator?->label_short ?? $kpi ?? '' }}</h3>
                <p>Танланган KPI бўйича туман ҳолати: режа, амалдаги натижа, ҳисобот таъсири ва очиқ топшириқлар.</p>
                <div class="action-row">
                    <span class="chip blue">Туман профили</span>
                    <span class="chip {{ $status }}">{{ $statusLabel }}</span>
                    <span class="chip {{ $taskChip }}">{{ $taskCounts['unfinished'] }}/{{ $taskCounts['total'] }} T-топшириқ</span>
                    <span class="chip grey">{{ $districtTargetsCount }} D-мақсад</span>
                    <span class="chip grey">ҳисобот йўқ</span>
                </div>
            </div>
            <div class="profile-main-value">
                <strong>{{ $primaryValue }}</strong>
                <span>{{ $unit }}</span>
            </div>
        </div>
        <div class="profile-metrics">
            @foreach($tableConfig['columns'] ?? [] as $col)
                @php
                    $cellFact = isset($col['metric']) ? $facts[$col['metric']['kpi']] ?? null : $fact;
                    $val = \App\Support\DistrictMetricResolver::value($cellFact, $col['metric']['kind'] ?? 'value');
                    $note = \App\Support\DistrictMetricResolver::note($cellFact, $col['note'] ?? null);
                @endphp
                <div class="profile-metric">
                    <span>{{ $col['label'] }}</span>
                    <strong>{{ $val }}</strong>
                    <small>{{ $note }}</small>
                </div>
            @endforeach
        </div>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <h3>Қисқа ҳолат</h3>
                <p>Шу туман бўйича тезкор қарор учун керакли маълумот.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="profile-side-stat"><span>Масъул</span><strong>ҳокимлик</strong></div>
            <div class="profile-side-stat"><span>Жорий маълумот</span><strong>{{ $primaryValue }}</strong></div>
            <div class="profile-side-stat"><span>Бажарилмаган T-топшириқ</span><strong>{{ $taskCounts['unfinished'] }}/{{ $taskCounts['total'] }}</strong></div>
            <div class="profile-side-stat"><span>Туман мақсадлари</span><strong>{{ $districtTargetsCount }}</strong></div>
            <div class="profile-side-stat"><span>Ҳисобот таъсири</span><strong>ҳисобот йўқ</strong></div>
            <div class="profile-actions" style="margin-top:12px">
                <button class="mini-button primary" type="button" disabled title="Тез орада">Ҳисобот киритиш</button>
                <a class="mini-button" href="{{ route('districts') }}?kpi={{ $kpi }}">Туманлар жадвали</a>
                <button class="mini-button" type="button" disabled title="Тез орада">Ижро журнали</button>
            </div>
        </div>
    </article>
</div>
```

The hero needs `$facts` for the per-column metric resolver. Update the orchestrator include to pass `$facts`:

In `backend/resources/views/livewire/region-profile.blade.php`, change:

```blade
@include('livewire.profile.hero', ['district' => $district, 'indicator' => $selectedIndicator, 'fact' => $selectedFact, 'status' => $status, 'tableConfig' => $tableConfig, 'taskCounts' => $taskCounts, 'districtTargetsCount' => $districtTargetsCount])
```

to:

```blade
@include('livewire.profile.hero', ['district' => $district, 'indicator' => $selectedIndicator, 'fact' => $selectedFact, 'status' => $status, 'tableConfig' => $tableConfig, 'taskCounts' => $taskCounts, 'districtTargetsCount' => $districtTargetsCount, 'kpi' => $kpi, 'facts' => $facts])
```

- [ ] **Step 2: Manual verification**

Visit `/profile?districtCode=1703401&kpi=industry`. Verify:
- Title: "Андижон шаҳри: Саноат" or similar.
- Status chip color matches `DistrictStatus::for(industry-year-fact)` for Andijan city.
- T-task count chip matches `Task::forRegion(1703)->forIndicator('industry')->forDistrict(district id for 1703401)->count()`.
- Primary value shows percentage from `fact.pct_of_plan` or `growth_pct`.
- Quick-status side panel has 5 stat rows.

- [ ] **Step 3: Commit**

```bash
git add backend/resources/views/livewire/profile/hero.blade.php backend/resources/views/livewire/region-profile.blade.php
git commit -m "feat(profile): hero partial — focus card + quick-status side panel"
```

---

### Task 4: KPIs grid partial

**Files:**
- Modify: `backend/resources/views/livewire/profile/kpis-grid.blade.php`

A panel containing one button per available indicator, each showing its short label, primary value for the selected district, and note (unit / measure).

- [ ] **Step 1: Write the partial**

Replace `backend/resources/views/livewire/profile/kpis-grid.blade.php`:

```blade
<article class="panel profile-secondary">
    <div class="panel-head">
        <div>
            <h3>Шу туман бўйича KPIлар</h3>
            <p>Кўрсаткични босинг: юқоридаги профиль шу KPIга мослашади.</p>
        </div>
        <span class="chip blue">{{ $district->name_full }}</span>
    </div>
    <div class="panel-body">
        <div class="district-kpis">
            @foreach($availableKpis as $i)
                @php
                    $fact = $facts->get($i->code);
                    $value = match (true) {
                        $fact?->pct_of_plan !== null => number_format((float) $fact->pct_of_plan, 1, ',', ' ') . '%',
                        $fact?->growth_pct !== null  => number_format((float) $fact->growth_pct, 1, ',', ' ') . '%',
                        $fact?->plan_value !== null  => number_format((float) $fact->plan_value, 1, ',', ' '),
                        default                      => '—',
                    };
                    $note = $i->default_unit ?? '';
                @endphp
                <button class="district-kpi {{ $i->code === $kpi ? 'active' : '' }}"
                        wire:click="selectKpi('{{ $i->code }}')"
                        type="button">
                    <span>{{ $i->label_short }}</span>
                    <strong>{{ $value }}</strong>
                    <small>{{ $note }}</small>
                </button>
            @endforeach
        </div>
    </div>
</article>
```

- [ ] **Step 2: Manual verification**

Visit `/profile?districtCode=1703401&kpi=industry`. Verify:
- Multiple KPI buttons appear (all `available` indicators for region 1703).
- Industry button has `active` style.
- Clicking another KPI button (e.g. "ЯҲМ" / grp) updates the hero card and URL `?kpi=grp`.

- [ ] **Step 3: Commit**

```bash
git add backend/resources/views/livewire/profile/kpis-grid.blade.php
git commit -m "feat(profile): kpis-grid partial — all-KPIs button grid"
```

---

### Task 5: Bottom partial (reports + tasks)

**Files:**
- Modify: `backend/resources/views/livewire/profile/bottom.blade.php`

Two-column grid. Left panel: reports empty state. Right panel: tasks list (up to 4) + "Барча топшириқлар" jump.

- [ ] **Step 1: Write the partial**

Replace `backend/resources/views/livewire/profile/bottom.blade.php`:

```blade
@php
    $kindLabels = ['measure' => 'Чора-тадбир', 'guarantee' => 'Кафолат', 'kpi' => 'KPI', 'monitoring' => 'Мониторинг'];
    $taskChip = $taskCounts['total'] > 0 && $taskCounts['unfinished'] > 0 ? 'red'
              : ($taskCounts['total'] > 0 ? 'green' : 'grey');
@endphp

<div class="profile-bottom-grid">
    <article class="panel">
        <div class="panel-head">
            <div>
                <h3>Ҳисоботлар</h3>
                <p>{{ $district->name_full }} бўйича киритилган амалдаги натижалар ва уларнинг KPIга таъсири.</p>
            </div>
            <span class="chip grey">йўқ</span>
        </div>
        <div class="panel-body">
            <div class="profile-report">
                <div class="empty">
                    <b>Ҳисобот йўқ</b><br>Бу туман бўйича ҳали ижро ҳисоботи киритилмаган.
                </div>
            </div>
        </div>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <h3>{{ $indicator?->label_short ?? 'KPI' }} топшириқлари</h3>
                <p>Фақат танланган KPI бўйича қисқа рўйхат.</p>
            </div>
            <span class="chip {{ $taskChip }}">{{ $taskCounts['unfinished'] }}/{{ $taskCounts['total'] }}</span>
        </div>
        <div class="panel-body">
            <div class="profile-task-list">
                @forelse($tasks as $task)
                    <article class="task-card">
                        <header>
                            <strong>№ {{ $task->task_number }}</strong>
                            <span class="chip grey">{{ $kindLabels[$task->kind] ?? $task->kind }}</span>
                        </header>
                        <p>{{ \Illuminate\Support\Str::limit($task->title, 200) }}</p>
                        <footer>
                            <span>{{ $task->executor_text }}</span>
                            <span>{{ $task->deadline_text }}</span>
                        </footer>
                    </article>
                @empty
                    <p class="muted">Бу KPI бўйича топшириқ топилмади.</p>
                @endforelse
            </div>
            <div class="action-row">
                <a class="mini-button" href="{{ route('tasks') }}?district={{ $district->code }}&kpi={{ $kpi }}&status=open">Барча топшириқлар</a>
            </div>
        </div>
    </article>
</div>
```

- [ ] **Step 2: Run tests → expect ALL PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire/RegionProfileTest.php
```

Expected: 8/8 PASS (including the previously-failing `assertSee('Бу KPI бўйича топшириқ топилмади')` and `assertSee('Ҳисобот йўқ')` tests).

- [ ] **Step 3: Manual verification**

Visit `/profile?districtCode=1703401&kpi=industry`. Verify:
- Left panel "Ҳисоботлар" shows empty state.
- Right panel shows up to 4 task cards or "Бу KPI бўйича топшириқ топилмади".
- "Барча топшириқлар" link goes to `/tasks?district=1703401&kpi=industry&status=open`.

- [ ] **Step 4: Commit**

```bash
git add backend/resources/views/livewire/profile/bottom.blade.php
git commit -m "feat(profile): bottom partial — reports empty state + tasks list"
```

---

### Task 6: Browser smoke

**Files:** none (operator verification).

- [ ] **Step 1: Fresh DB + dev server**

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:all-regions 2026
php artisan serve --port=8000
```

- [ ] **Step 2: Click through scenarios**

Open each URL and verify:

| URL | Expected |
|---|---|
| `/profile` | "Туман танланмаган" empty state with link to districts |
| `/profile?districtCode=999999` | "Туман топилмади: 999999" empty state |
| `/profile?districtCode=1703401&kpi=industry` | full 4-section layout for Andijan city + industry KPI |
| `/profile?districtCode=1703203&kpi=agriculture` | layout for Andijan district + agriculture KPI |

For the last two URLs:
1. Confirm hero status chip color matches DB SQL (`SELECT pct_of_plan, growth_pct FROM indicator_facts WHERE region_code=1703 AND district_code=? AND indicator_code=? AND period='year'`).
2. Click another KPI button in the grid → URL updates, hero rebuilds.
3. Change district select → URL updates, all sections rebuild.
4. Click "Туманлар кесимига қайтиш" → arrives at `/districts?kpi=<current>`.
5. Click "Барча топшириқлар" → arrives at `/tasks?district=<code>&kpi=<current>&status=open`.

- [ ] **Step 3: Empty commit to record smoke success**

```bash
git commit --allow-empty -m "test(profile): browser smoke — 4-section layout renders end-to-end"
```

---

## Self-review

**Spec coverage:**
- §3 Strategy → Task 1 (component + view orchestrator).
- §4.1 Component → Task 1 Step 3.
- §4.2 Blade orchestrator → Task 1 Step 4.
- §4.3 Partials → Tasks 1 (empty), 2 (filter), 3 (hero), 4 (kpis-grid), 5 (bottom).
- §5 Tests → Task 1 Step 1 (8 tests, all passing by end of Task 5).
- §7 Operator smoke → Task 6.
- §8 Risks (action button stubs, hardcoded region, DistrictTableConfig fallback) → addressed inline in hero/filter (disabled buttons, fallback to empty columns when config missing).

**No placeholders.** All code blocks are concrete.

**Type consistency:**
- `$districtCode` (string, URL-synced) cast to `(int)` in `district()` query; matches districts-page-fix pattern.
- `$kpi` (string, URL-synced); compared via Indicator code strings.
- `taskCounts['total']`, `taskCounts['unfinished']` (int).
- `availableKpis` (Collection of Indicator).
- `tableConfig` (array — return of `DistrictTableConfig::for`).
- Partials receive consistent param names across `@include` calls.
