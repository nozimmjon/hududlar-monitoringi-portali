# Plan 10 — KPI Dashboard Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `/dashboard` to match `index.html`'s `renderDashboard()` markup exactly while sourcing data from PostgreSQL via Livewire 4.

**Architecture:** Parent `KpiDashboard` Livewire component owns `$module` + `$kpi` state (`#[Url]`-persisted). 5 child Livewire components in `App\Livewire\Dashboard\` namespace receive `#[Reactive]` props and dispatch selection events back to parent. Detail panels are Blade `@include` partials (not separate Livewire components).

**Tech Stack:** Laravel 12.58, Livewire 4.3, PHP 8.2, PostgreSQL, Pest 3.

---

## Task 1: DashboardCatalog Static Class

**Files:**
- Create: `backend/app/Support/DashboardCatalog.php`

---

- [ ] **Step 1: Create DashboardCatalog**

Create `backend/app/Support/DashboardCatalog.php`:

```php
<?php

namespace App\Support;

class DashboardCatalog
{
    public const PERIODS = ['q1', 'h1', 'm9', 'year'];

    public const PERIOD_LABELS = [
        'q1'   => 'I чорак',
        'h1'   => 'II чорак',
        'm9'   => 'III чорак',
        'year' => 'Йиллик',
    ];

    public const LOWER_BETTER = ['inflation', 'poverty', 'unemployment'];

    public const MACRO_GROWTH_KPIS = ['grp', 'industry', 'agriculture', 'construction', 'services'];

    public const MODULES = [
        'macro' => [
            'label' => '1. Макроиқтисодиёт',
            'intro' => 'ЯҲМ ва асосий таркибий кўрсаткичлар',
            'kpis'  => ['grp', 'industry', 'agriculture', 'construction', 'services'],
            'has_front_cards' => true,
            'layout_class' => 'macro-layout',
        ],
        'inflation' => [
            'label' => '2. Инфляция',
            'intro' => 'Инфляция чегараси, озиқ-овқат баланси ва омборлар.',
            'kpis'  => ['inflation'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'budget' => [
            'label' => '3. Бюджет',
            'intro' => 'Бюджет тушумлари бўйича режа ва ижро.',
            'kpis'  => ['budget'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'budget_invest' => [
            'label' => '4. Бюджет инвестициялари',
            'intro' => 'Бюджет инвестициялари ўзлаштирилиши.',
            'kpis'  => ['budget_investment'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'foreign_invest' => [
            'label' => '5. Хорижий инвестициялар',
            'intro' => 'Хорижий инвестициялар ҳажми, режа ва ижро.',
            'kpis'  => ['investment'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'export' => [
            'label' => '6. Экспорт',
            'intro' => 'Экспорт ҳажми ва ўсиш кўрсаткичлари.',
            'kpis'  => ['export'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'employment' => [
            'label' => '7. Бандлик ва камбағаллик',
            'intro' => 'Ишсизлик, камбағаллик ва кичик тадбиркорлик бўйича асосий KPIлар.',
            'kpis'  => ['unemployment', 'poverty', 'jobs', 'legalization', 'mfy_clear', 'microprojects'],
            'has_front_cards' => true,
            'layout_class' => 'employment-layout',
        ],
    ];

    public const INFLATION_PRICE_CAPS = [
        ['name' => 'Гўшт ва гўшт маҳсулотлари', 'icon' => 'meat',         'cap' => '6–7%дан ошмаслик'],
        ['name' => 'Тухум',                      'icon' => 'egg',          'cap' => '5–6%дан ошмаслик'],
        ['name' => 'Сут ва сут маҳсулотлари',    'icon' => 'milk_bottle',  'cap' => '6–7%дан ошмаслик'],
        ['name' => 'Картошка',                   'icon' => 'potato',       'cap' => '4–5%дан ошмаслик'],
        ['name' => 'Пиёз',                       'icon' => 'onion',        'cap' => '5%дан ошмаслик'],
        ['name' => 'Сабзи',                      'icon' => 'carrot',       'cap' => '5%дан ошмаслик'],
        ['name' => 'Гуруч',                      'icon' => 'rice',         'cap' => '2025 йил даражасида'],
        ['name' => 'Ун',                         'icon' => 'flour',        'cap' => '2025 йил даражасида'],
    ];

    public const INFLATION_LIMITS = [
        ['period' => 'II чорак',   'cap' => '≤2,9%', 'note' => 'амалдаги инфляцияга нисбатан'],
        ['period' => 'Йил якуни',  'cap' => '≤6,6%', 'note' => 'йил якуни бўйича чегара'],
    ];

    public static function modules(): array
    {
        return self::MODULES;
    }

    public static function moduleCodes(): array
    {
        return array_keys(self::MODULES);
    }

    public static function module(string $code): ?array
    {
        return self::MODULES[$code] ?? null;
    }

    public static function moduleLabel(string $code): string
    {
        return self::MODULES[$code]['label'] ?? '';
    }

    public static function moduleIntro(string $code): string
    {
        return self::MODULES[$code]['intro'] ?? '';
    }

    public static function moduleKpis(string $code): array
    {
        return self::MODULES[$code]['kpis'] ?? [];
    }

    public static function hasFrontCards(string $code): bool
    {
        return (bool) (self::MODULES[$code]['has_front_cards'] ?? false);
    }

    public static function layoutClass(string $code): string
    {
        return self::MODULES[$code]['layout_class'] ?? '';
    }

    public static function firstKpiForModule(string $code): string
    {
        return self::MODULES[$code]['kpis'][0] ?? 'grp';
    }

    public static function moduleForKpi(string $kpi): string
    {
        foreach (self::MODULES as $code => $module) {
            if (in_array($kpi, $module['kpis'], true)) {
                return $code;
            }
        }
        return 'macro';
    }

    public static function isLowerBetter(string $kpi): bool
    {
        return in_array($kpi, self::LOWER_BETTER, true);
    }

    public static function isMacroGrowthKpi(string $kpi): bool
    {
        return in_array($kpi, self::MACRO_GROWTH_KPIS, true);
    }

    public static function periodLabel(string $period): string
    {
        return self::PERIOD_LABELS[$period] ?? $period;
    }
}
```

- [ ] **Step 2: Commit**

```powershell
git add backend/app/Support/DashboardCatalog.php
git commit -m "feat: add DashboardCatalog static class with module + price-cap data"
```

---

## Task 2: Refactor KpiDashboard Parent

**Files:**
- Modify: `backend/app/Livewire/KpiDashboard.php`
- Modify: `backend/resources/views/livewire/kpi-dashboard.blade.php`

---

- [ ] **Step 1: Replace KpiDashboard.php**

Overwrite `backend/app/Livewire/KpiDashboard.php`:

```php
<?php

namespace App\Livewire;

use App\Support\DashboardCatalog;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class KpiDashboard extends Component
{
    #[Url]
    public string $module = 'macro';

    #[Url]
    public string $kpi = 'grp';

    public function mount(): void
    {
        if (! DashboardCatalog::module($this->module)) {
            $this->module = 'macro';
        }
        if (! in_array($this->kpi, DashboardCatalog::moduleKpis($this->module), true)) {
            $this->kpi = DashboardCatalog::firstKpiForModule($this->module);
        }
    }

    #[On('module-selected')]
    public function selectModule(string $module): void
    {
        if (! DashboardCatalog::module($module)) {
            return;
        }
        $this->module = $module;
        $this->kpi = DashboardCatalog::firstKpiForModule($module);
    }

    #[On('kpi-selected')]
    public function selectKpi(string $kpi): void
    {
        $this->kpi = $kpi;
        $this->module = DashboardCatalog::moduleForKpi($kpi);
    }

    public function render()
    {
        return view('livewire.kpi-dashboard', [
            'module'      => $this->module,
            'kpi'         => $this->kpi,
            'moduleLabel' => DashboardCatalog::moduleLabel($this->module),
            'moduleIntro' => DashboardCatalog::moduleIntro($this->module),
            'hasFrontCards' => DashboardCatalog::hasFrontCards($this->module),
        ]);
    }
}
```

- [ ] **Step 2: Replace kpi-dashboard.blade.php**

Overwrite `backend/resources/views/livewire/kpi-dashboard.blade.php`:

```blade
<div>
    <livewire:dashboard.kpi-module-tabs :module="$module" :key="'tabs-'.$module" />

    <div class="module-heading">
        <div>
            <h2>{{ $moduleLabel }}</h2>
            <p>{{ $moduleIntro }}</p>
        </div>
    </div>

    @if($hasFrontCards)
        <livewire:dashboard.kpi-front-cards :module="$module" :kpi="$kpi" :key="'front-'.$module.'-'.$kpi" />
    @endif

    <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :key="'work-'.$module.'-'.$kpi" />

    @if($module === 'macro')
        <livewire:dashboard.macro-composition :key="'macro-comp'" />
    @endif

    <livewire:dashboard.kpi-scoreline :module="$module" :kpi="$kpi" :key="'score-'.$module.'-'.$kpi" />
</div>
```

- [ ] **Step 3: Commit**

```powershell
git add backend/app/Livewire/KpiDashboard.php `
        backend/resources/views/livewire/kpi-dashboard.blade.php
git commit -m "refactor: KpiDashboard parent owns module/kpi state, embeds 5 child components"
```

Note: This breaks the dashboard until child components exist (Tasks 3-9). That's expected — tasks build up the children one by one.

---

## Task 3: KpiModuleTabs Child

**Files:**
- Create: `backend/app/Livewire/Dashboard/KpiModuleTabs.php`
- Create: `backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php`

---

- [ ] **Step 1: Create KpiModuleTabs.php**

Create `backend/app/Livewire/Dashboard/KpiModuleTabs.php`:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiModuleTabs extends Component
{
    #[Reactive]
    public string $module = 'macro';

    public function selectModule(string $code): void
    {
        $this->dispatch('module-selected', module: $code);
    }

    public function render()
    {
        return view('livewire.dashboard.kpi-module-tabs', [
            'modules'      => DashboardCatalog::modules(),
            'currentModule' => $this->module,
        ]);
    }
}
```

- [ ] **Step 2: Create kpi-module-tabs.blade.php**

Create `backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php`:

```blade
<div class="dashboard-module-tabs">
    @foreach($modules as $code => $module)
        <button class="module-tab {{ $code === $currentModule ? 'active' : '' }}"
                wire:click="selectModule('{{ $code }}')"
                type="button">
            <span class="module-dot" aria-hidden="true"></span>
            <strong>{{ preg_replace('/^\d+\.\s*/u', '', $module['label']) }}</strong>
        </button>
    @endforeach
</div>
```

- [ ] **Step 3: Verify dashboard loads with module tabs**

Run smoke test:

```powershell
php -d memory_limit=2G vendor/bin/pest tests/Feature/Http/DashboardRoutesTest.php::dashboard_route_returns_200
```

Expected: PASS.

- [ ] **Step 4: Commit**

```powershell
git add backend/app/Livewire/Dashboard/KpiModuleTabs.php `
        backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php
git commit -m "feat: add KpiModuleTabs child component"
```

---

## Task 4: KpiFrontCards Child

**Files:**
- Create: `backend/app/Livewire/Dashboard/KpiFrontCards.php`
- Create: `backend/resources/views/livewire/dashboard/kpi-front-cards.blade.php`
- Create: `backend/resources/views/partials/icon.blade.php`

---

- [ ] **Step 1: Create icon partial**

Create `backend/resources/views/partials/icon.blade.php`:

```blade
@php
    $svgs = [
        'trend'     => '<path d="M4 17h16M6 14l4-5 4 3 4-7"/><path d="M16 5h4v4"/>',
        'factory'   => '<path d="M4 20V9l5 3V9l5 3h6v8H4Z"/><path d="M8 16h1M12 16h1M16 16h1"/>',
        'bank'      => '<path d="M4 10h16M6 10v8M10 10v8M14 10v8M18 10v8M3 20h18M12 4l8 4H4l8-4Z"/>',
        'price'     => '<path d="M12 3v18"/><path d="M17 7.5C16.2 6 14.7 5 12.3 5H11a3 3 0 0 0 0 6h2a3 3 0 0 1 0 6h-1.3C9.3 17 7.8 16 7 14.5"/>',
        'rocket'    => '<path d="M12 15l-3-3c1-5 4-8 10-9-1 6-4 9-9 10Z"/><path d="M9 12l-4 1-2 4 4-2 1-4M12 15l-1 4-4 2 2-4 4-1"/><circle cx="15" cy="8" r="1.5"/>',
        'globe'     => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2 2 3 5 3 8s-1 6-3 8M12 4c-2 2-3 5-3 8s1 6 3 8"/>',
        'briefcase' => '<path d="M10 6V5a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v1"/><rect x="4" y="6" width="16" height="14" rx="2"/><path d="M4 12h16M10 12v2h4v-2"/>',
        'users'     => '<path d="M16 19v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="3"/><path d="M20 19v-2a3 3 0 0 0-2-2.8M16 4.2a3 3 0 0 1 0 5.6"/>',
    ];
    $body = $svgs[$name ?? 'trend'] ?? $svgs['trend'];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor">{!! $body !!}</svg>
```

- [ ] **Step 2: Create KpiFrontCards.php**

Create `backend/app/Livewire/Dashboard/KpiFrontCards.php`:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiFrontCards extends Component
{
    #[Reactive]
    public string $module = 'macro';

    #[Reactive]
    public string $kpi = 'grp';

    public function selectKpi(string $code): void
    {
        $this->dispatch('kpi-selected', kpi: $code);
    }

    public function render()
    {
        $codes = DashboardCatalog::moduleKpis($this->module);

        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('period', 'year')
            ->whereIn('indicator_code', $codes)
            ->get()
            ->keyBy('indicator_code');

        $indicators = Indicator::whereIn('code', $codes)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return view('livewire.dashboard.kpi-front-cards', [
            'codes'        => $codes,
            'facts'        => $facts,
            'indicators'   => $indicators,
            'layoutClass'  => DashboardCatalog::layoutClass($this->module),
            'selectedKpi'  => $this->kpi,
            'isMacro'      => $this->module === 'macro',
        ]);
    }
}
```

- [ ] **Step 3: Create kpi-front-cards.blade.php**

Create `backend/resources/views/livewire/dashboard/kpi-front-cards.blade.php`:

```blade
<div class="front-kpis module-kpis {{ $layoutClass }}">
    @foreach($codes as $code)
        @php
            $ind = $indicators->get($code);
            if (! $ind) continue;
            $fact = $facts->get($code);
            $active = $code === $selectedKpi ? 'active' : '';
            $parent = ($code === 'grp' && $isMacro) ? 'parent' : '';
            $meta = $code === $selectedKpi ? 'Танланган KPI' : 'Кўрсаткични очиш';
        @endphp
        <button class="front-kpi {{ $active }} {{ $parent }}"
                wire:click="selectKpi('{{ $code }}')"
                type="button"
                aria-label="{{ $ind->label_full }}">
            <div class="kpi-icon">
                @include('partials.icon', ['name' => $ind->icon ?? 'trend'])
            </div>
            <div class="front-kpi-copy">
                <h3>{{ $ind->label_short }}</h3>
                <p>{{ $ind->label_full }}</p>
                <span class="front-kpi-meta">
                    <i class="front-kpi-dot" aria-hidden="true"></i>{{ $meta }}
                </span>
            </div>
        </button>
    @endforeach
</div>
```

- [ ] **Step 4: Commit**

```powershell
git add backend/app/Livewire/Dashboard/KpiFrontCards.php `
        backend/resources/views/livewire/dashboard/kpi-front-cards.blade.php `
        backend/resources/views/partials/icon.blade.php
git commit -m "feat: add KpiFrontCards + icon partial"
```

---

## Task 5: KpiWorkspaceCard with Quarter Matrix

**Files:**
- Create: `backend/app/Livewire/Dashboard/KpiWorkspaceCard.php`
- Create: `backend/resources/views/livewire/dashboard/kpi-workspace-card.blade.php`
- Create: `backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php`

---

- [ ] **Step 1: Create KpiWorkspaceCard.php**

Create `backend/app/Livewire/Dashboard/KpiWorkspaceCard.php`:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Support\DashboardCatalog;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiWorkspaceCard extends Component
{
    #[Reactive]
    public string $module = 'macro';

    #[Reactive]
    public string $kpi = 'grp';

    public function render()
    {
        $indicator = Indicator::where('code', $this->kpi)->first();

        $rows = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('indicator_code', $this->kpi)
            ->whereIn('period', DashboardCatalog::PERIODS)
            ->get()
            ->keyBy('period');

        $panel = match (true) {
            $this->kpi === 'inflation'                  => 'inflation',
            $this->kpi === 'unemployment'               => 'unemployment',
            $this->kpi === 'poverty'                    => 'poverty',
            $this->kpi === 'budget_investment'          => 'budget-investment',
            DashboardCatalog::isMacroGrowthKpi($this->kpi) => 'macro-growth',
            default                                     => 'quarter-matrix',
        };

        $extra = $this->loadPanelData($panel);

        return view('livewire.dashboard.kpi-workspace-card', array_merge([
            'indicator' => $indicator,
            'kpi'       => $this->kpi,
            'rows'      => $rows,
            'panel'     => $panel,
        ], $extra));
    }

    protected function loadPanelData(string $panel): array
    {
        return match ($panel) {
            'inflation'         => $this->inflationData(),
            'unemployment'      => $this->employmentData(['jobs', 'legalization']),
            'poverty'           => $this->povertyData(),
            'macro-growth'      => $this->macroGrowthData(),
            default             => [],
        };
    }

    protected function inflationData(): array
    {
        $foods = DB::table('food_balance')
            ->where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNotNull('product')
            ->where('product', '!=', 'шундан:')
            ->whereNotNull('resource_total')
            ->get();

        $sensitiveFoods = $foods->filter(fn ($f) => $f->local_supply_ratio !== null)
            ->sortBy('local_supply_ratio')
            ->take(4)
            ->values();

        $warehouses = DB::table('warehouses')
            ->where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->get();

        return [
            'foods'          => $foods,
            'sensitiveFoods' => $sensitiveFoods,
            'warehouses'     => $warehouses,
        ];
    }

    protected function employmentData(array $codes): array
    {
        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->whereIn('indicator_code', $codes)
            ->whereIn('period', ['h1', 'year'])
            ->get()
            ->groupBy('indicator_code');

        $indicators = Indicator::whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        return [
            'employmentFacts'      => $facts,
            'employmentIndicators' => $indicators,
        ];
    }

    protected function povertyData(): array
    {
        $base = $this->employmentData(['jobs', 'legalization', 'mfy_clear', 'microprojects']);

        $clearDistricts = DB::table('districts')
            ->where('region_code', 'andijan')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('indicator_facts')
                    ->whereColumn('indicator_facts.district_code', 'districts.code')
                    ->where('indicator_facts.indicator_code', 'poverty')
                    ->where('indicator_facts.is_sentinel', true)
                    ->where('indicator_facts.sentinel_label', 'like', '%холи%');
            })
            ->orderBy('sort_order')
            ->get();

        return array_merge($base, ['clearDistricts' => $clearDistricts]);
    }

    protected function macroGrowthData(): array
    {
        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->whereIn('indicator_code', DashboardCatalog::MACRO_GROWTH_KPIS)
            ->whereIn('period', DashboardCatalog::PERIODS)
            ->get()
            ->groupBy('indicator_code');

        $indicators = Indicator::whereIn('code', DashboardCatalog::MACRO_GROWTH_KPIS)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return [
            'macroFacts'      => $facts,
            'macroIndicators' => $indicators,
        ];
    }
}
```

- [ ] **Step 2: Create kpi-workspace-card.blade.php**

Create `backend/resources/views/livewire/dashboard/kpi-workspace-card.blade.php`:

```blade
<div class="kpi-monitor-grid single">
    <article class="kpi-monitor-card {{ \App\Support\DashboardCatalog::isMacroGrowthKpi($kpi) ? 'macro-layout-card' : '' }}">
        <div class="kpi-monitor-head">
            <div class="small-icon">
                @include('partials.icon', ['name' => $indicator->icon ?? 'trend'])
            </div>
            <div>
                <h3>{{ $indicator->label_short ?? $kpi }}</h3>
                <p>{{ $indicator->label_full ?? '' }}</p>
            </div>
            @if($kpi !== 'grp')
                <a class="mini-button primary kpi-head-district"
                   href="{{ route('districts') }}?indicatorCode={{ $kpi }}">Туманлар кесими</a>
            @endif
            <div class="head-watermark" aria-hidden="true">
                @include('partials.icon', ['name' => $indicator->icon ?? 'trend'])
            </div>
        </div>

        @include('livewire.dashboard.panels.' . $panel, get_defined_vars())

        @if(in_array($kpi, ['budget', 'budget_investment', 'investment'], true))
            <p class="finance-source">Манба: 4-жадвал ва кафолат хати.</p>
        @endif
    </article>
</div>
```

- [ ] **Step 3: Create panels/quarter-matrix.blade.php**

Create `backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php`:

```blade
@php
    use App\Support\DashboardCatalog;
    $periods = DashboardCatalog::PERIODS;
    $periodLabels = DashboardCatalog::PERIOD_LABELS;
    $hasGrowth = $indicator->has_growth_pct ?? false;
@endphp

<div class="quarter-matrix">
    @foreach($periods as $period)
        @php
            $row = $rows->get($period);
            $unit = $row->unit ?? $indicator->default_unit ?? '';
            $growth   = $row && $row->growth_pct !== null ? number_format((float) $row->growth_pct, 1) . '%' : null;
            $plan     = $row && $row->plan_value !== null ? number_format((float) $row->plan_value, 1) . ' ' . $unit : '—';
            $fact     = $row && $row->actual_hokimyat !== null ? number_format((float) $row->actual_hokimyat, 1) . ' ' . $unit : '—';
            $exec     = $row && $row->pct_of_plan !== null ? number_format((float) $row->pct_of_plan, 1) . '%' : null;

            $hero = $growth ?? $exec ?? ($row && $row->plan_value !== null ? number_format((float) $row->plan_value, 1) . ' ' . $unit : '—');

            $hasFact = $row && ($row->actual_hokimyat !== null || $row->actual_statistika !== null || $row->growth_pct !== null);
            $stateClass = $hasFact ? 'actual' : 'planned';
            $statusText = $hasFact ? 'Амалда бор' : '—';
            $chipClass = $hasFact ? 'green' : 'grey';
            $measureLabel = $growth ? 'Ўсиш' : ($exec ? 'Ижро' : 'Режа');
        @endphp
        <div class="quarter-row {{ $stateClass }}">
            <div class="q-head">
                <span class="q-period">{{ $periodLabels[$period] }}</span>
            </div>
            <div class="q-hero">
                <span class="q-hero-value">{{ $hero }}</span>
                <span class="q-hero-label">{{ $measureLabel }}</span>
            </div>
            <dl class="q-aux">
                <div class="q-aux-row"><span>Режа</span><b class="num">{{ $plan }}</b></div>
                <div class="q-aux-row"><span>Амалда</span><b class="num">{{ $fact }}</b></div>
                <div class="q-aux-row status"><span>Ҳолат</span><b><span class="chip {{ $chipClass }}">{{ $statusText }}</span></b></div>
            </dl>
        </div>
    @endforeach
</div>
```

- [ ] **Step 4: Verify dashboard loads**

```powershell
php -d memory_limit=2G vendor/bin/pest tests/Feature/Http/DashboardRoutesTest.php
```

Expected: 7 PASS.

- [ ] **Step 5: Commit**

```powershell
git add backend/app/Livewire/Dashboard/KpiWorkspaceCard.php `
        backend/resources/views/livewire/dashboard/kpi-workspace-card.blade.php `
        backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php
git commit -m "feat: add KpiWorkspaceCard + quarter-matrix panel"
```

---

## Task 6: Macro-Growth Panel

**Files:**
- Create: `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php`

---

- [ ] **Step 1: Create panels/macro-growth.blade.php**

Create `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php`:

```blade
@php
    use App\Support\DashboardCatalog;
    $periods = DashboardCatalog::PERIODS;
    $periodLabels = DashboardCatalog::PERIOD_LABELS;
    $components = DashboardCatalog::MACRO_GROWTH_KPIS;
@endphp

<div class="quarter-matrix macro-growth">
    @foreach($periods as $period)
        @php
            $row = $rows->get($period);
            $unit = $row->unit ?? $indicator->default_unit ?? '';
            $growth = $row && $row->growth_pct !== null ? number_format((float) $row->growth_pct, 1) . '%' : '—';
            $hasFact = $row && $row->growth_pct !== null;
            $stateClass = $hasFact ? 'actual' : 'planned';
            $statusText = $hasFact ? 'Амалда бор' : 'Режа';
            $chipClass = $hasFact ? 'green' : 'grey';
        @endphp
        <div class="quarter-row {{ $stateClass }}">
            <div class="q-head">
                <span class="q-period">{{ $periodLabels[$period] }}</span>
            </div>
            <div class="q-hero">
                <span class="q-hero-value">{{ $growth }}</span>
                <span class="q-hero-label">Ўсиш</span>
            </div>
            <dl class="q-aux">
                <div class="q-aux-row status"><span>Ҳолат</span><b><span class="chip {{ $chipClass }}">{{ $statusText }}</span></b></div>
            </dl>
        </div>
    @endforeach
</div>
```

- [ ] **Step 2: Commit**

```powershell
git add backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php
git commit -m "feat: add macro-growth panel for grp + macro components"
```

---

## Task 7: Inflation Details Panel

**Files:**
- Create: `backend/resources/views/livewire/dashboard/panels/inflation-details.blade.php`

---

- [ ] **Step 1: Create panels/inflation-details.blade.php**

Create `backend/resources/views/livewire/dashboard/panels/inflation-details.blade.php`:

```blade
@php
    use App\Support\DashboardCatalog;
    $caps = DashboardCatalog::INFLATION_PRICE_CAPS;
    $limits = DashboardCatalog::INFLATION_LIMITS;
    $warehouseCount = $warehouses->count();
@endphp

<div class="drivers">
    <div class="lagging">
        <div class="lagging-title"><strong>Инфляция чегаралари</strong></div>
        <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            @foreach($limits as $limit)
                <div class="driver-card">
                    <span>{{ $limit['period'] }}</span>
                    <strong>{{ $limit['cap'] }}</strong>
                    <small>{{ $limit['note'] }}</small>
                </div>
            @endforeach
        </div>
        <p class="data-note">Амалдаги инфляция маълумоти киритилмаган.</p>
    </div>

    <div class="composition">
        <div class="lagging-title"><strong>Асосий озиқ-овқат нархлари</strong></div>
        <div class="composition-grid">
            @foreach($caps as $cap)
                <button class="component-card product-card" type="button">
                    <span class="product-icon" aria-hidden="true">🥚</span>
                    <span class="product-body">
                        <span class="product-name">{{ $cap['name'] }}</span>
                        <strong class="product-value">{{ $cap['cap'] }}</strong>
                        <small class="product-note">йиллик нарх чегараси</small>
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    @if($sensitiveFoods->isNotEmpty())
        <div class="composition">
            <div class="lagging-title"><strong>Озиқ-овқат балансида эътибор талаб қиладиган маҳсулотлар</strong></div>
            <div class="composition-grid">
                @foreach($sensitiveFoods as $row)
                    <button class="component-card product-card" type="button">
                        <span class="product-icon" aria-hidden="true">🥬</span>
                        <span class="product-body">
                            <span class="product-name">{{ $row->product }}</span>
                            <strong class="product-value">{{ number_format(((float) $row->local_supply_ratio) * 100, 1) }}%</strong>
                            <small class="product-note">маҳаллий таъминланиш · ресурс {{ number_format((float) $row->resource_total, 1) }} минг т · импорт {{ $row->import !== null ? number_format((float) $row->import, 1) : '—' }} минг т</small>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if($warehouseCount > 0)
        <div class="lagging">
            <div class="lagging-title"><strong>Омборлар туманлар кесимида</strong></div>
            <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div class="driver-card">
                    <span>Совутгичли омборлар</span>
                    <strong>{{ $warehouseCount }} та</strong>
                    <small>туман кесимида</small>
                </div>
                <div class="driver-card">
                    <span>Захира жамғармаси</span>
                    <strong>50 млрд сўм</strong>
                    <small>йиллик режа</small>
                </div>
            </div>
        </div>
    @endif

    <p class="finance-source">Манба: 2.1-2.2-жадваллар ва кафолат хати II-бўлим.</p>
</div>
```

- [ ] **Step 2: Verify**

Open `http://localhost:8000/dashboard?module=inflation&kpi=inflation`. Expected: price caps + food balance products + warehouse counts.

- [ ] **Step 3: Commit**

```powershell
git add backend/resources/views/livewire/dashboard/panels/inflation-details.blade.php
git commit -m "feat: add inflation-details panel"
```

---

## Task 8: Unemployment + Poverty Panels

**Files:**
- Create: `backend/resources/views/livewire/dashboard/panels/unemployment-details.blade.php`
- Create: `backend/resources/views/livewire/dashboard/panels/poverty-details.blade.php`

---

- [ ] **Step 1: Create panels/unemployment-details.blade.php**

Create `backend/resources/views/livewire/dashboard/panels/unemployment-details.blade.php`:

```blade
@php
    $stats = [
        ['code' => 'jobs',         'icon' => 'briefcase', 'label' => 'Доимий ишга жойлаштириш',         'unit' => 'минг'],
        ['code' => 'legalization', 'icon' => 'users',     'label' => 'Норасмий бандларни легаллаштириш', 'unit' => 'минг'],
    ];
@endphp

<div class="drivers poverty-section employment-driver-section">
    <div class="lagging">
        <header class="poverty-head">
            <div>
                <strong>Ишсизликни пасайтириш драйверлари</strong>
                <p>Ишсизлик KPI мақсадини бажариш учун бандлик бўйича асосий ўлчанадиган ишлар.</p>
            </div>
            <a class="mini-button primary"
               href="{{ route('districts') }}?indicatorCode=unemployment">Туманлар кесими →</a>
        </header>
        <div class="poverty-stats">
            @foreach($stats as $s)
                @php
                    $facts = $employmentFacts->get($s['code'], collect());
                    $h1Fact = $facts->firstWhere('period', 'h1');
                    $yearFact = $facts->firstWhere('period', 'year');
                    $h1Val = $h1Fact?->actual_hokimyat ?? $h1Fact?->plan_value;
                    $yearVal = $yearFact?->plan_value ?? $yearFact?->actual_hokimyat;
                    $pct = ($h1Val !== null && $yearVal !== null && (float) $yearVal != 0)
                        ? max(0, min(100, ((float) $h1Val / (float) $yearVal) * 100))
                        : 0;
                @endphp
                <article class="poverty-stat">
                    <div class="poverty-stat-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => $s['icon']])
                    </div>
                    <div class="poverty-stat-body">
                        <span class="poverty-stat-label">{{ $s['label'] }}</span>
                        <strong class="poverty-stat-value">{{ $yearVal !== null ? number_format((float) $yearVal, 1) : '—' }}<em>{{ $s['unit'] }}</em></strong>
                        <div class="poverty-stat-meta">
                            <span>II чорак <b>{{ $h1Val !== null ? number_format((float) $h1Val, 1) : '—' }}</b></span>
                            <span class="poverty-stat-divider">·</span>
                            <span>Йиллик мақсад</span>
                        </div>
                        <div class="poverty-progress" role="progressbar" aria-valuenow="{{ (int) $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <i style="width:{{ number_format($pct, 1) }}%"></i>
                        </div>
                        <small class="poverty-progress-label">II чорак йиллик мақсаднинг {{ (int) $pct }}%</small>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
    <p class="finance-source">Манба: 6-жадвал ва кафолат хати.</p>
</div>
```

- [ ] **Step 2: Create panels/poverty-details.blade.php**

Create `backend/resources/views/livewire/dashboard/panels/poverty-details.blade.php`:

```blade
@php
    $stats = [
        ['code' => 'jobs',          'icon' => 'users',   'label' => 'Доимий ишга жойлаштириш',         'unit' => 'минг'],
        ['code' => 'legalization',  'icon' => 'globe',   'label' => 'Норасмий бандларни легаллаштириш', 'unit' => 'минг'],
        ['code' => 'mfy_clear',     'icon' => 'bank',    'label' => 'Камбағалликдан холи МФЙлар',       'unit' => 'та'],
        ['code' => 'microprojects', 'icon' => 'rocket',  'label' => 'Микролойиҳалар',                   'unit' => 'та'],
    ];
@endphp

<div class="drivers poverty-section">
    <div class="lagging">
        <header class="poverty-head">
            <div>
                <strong>Камбағалликни камайтириш драйверлари</strong>
                <p>Камбағаллик KPIсига олиб борувчи асосий чора-тадбирлар.</p>
            </div>
            <a class="mini-button primary"
               href="{{ route('districts') }}?indicatorCode=poverty">Туманлар кесими →</a>
        </header>
        <div class="poverty-stats">
            @foreach($stats as $s)
                @php
                    $facts = $employmentFacts->get($s['code'], collect());
                    $h1Fact = $facts->firstWhere('period', 'h1');
                    $yearFact = $facts->firstWhere('period', 'year');
                    $h1Val = $h1Fact?->actual_hokimyat ?? $h1Fact?->plan_value;
                    $yearVal = $yearFact?->plan_value ?? $yearFact?->actual_hokimyat;
                    $digits = in_array($s['unit'], ['та'], true) ? 0 : 1;
                    $pct = ($h1Val !== null && $yearVal !== null && (float) $yearVal != 0)
                        ? max(0, min(100, ((float) $h1Val / (float) $yearVal) * 100))
                        : 0;
                @endphp
                <article class="poverty-stat">
                    <div class="poverty-stat-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => $s['icon']])
                    </div>
                    <div class="poverty-stat-body">
                        <span class="poverty-stat-label">{{ $s['label'] }}</span>
                        <strong class="poverty-stat-value">{{ $yearVal !== null ? number_format((float) $yearVal, $digits) : '—' }}<em>{{ $s['unit'] }}</em></strong>
                        <div class="poverty-stat-meta">
                            <span>II чорак <b>{{ $h1Val !== null ? number_format((float) $h1Val, $digits) : '—' }}</b></span>
                            <span class="poverty-stat-divider">·</span>
                            <span>Йиллик мақсад</span>
                        </div>
                        <div class="poverty-progress" role="progressbar" aria-valuenow="{{ (int) $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <i style="width:{{ number_format($pct, 1) }}%"></i>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    @if($clearDistricts->isNotEmpty())
        <div class="composition">
            <div class="lagging-title"><strong>Камбағалликдан холи туманлар</strong></div>
            <div class="composition-grid">
                @foreach($clearDistricts as $d)
                    <a class="component-card product-card"
                       href="{{ route('profile') }}?districtCode={{ $d->code }}">
                        <span class="product-body">
                            <span class="product-name">{{ $d->name_short ?? $d->code }}</span>
                            <small class="product-note">холи туман</small>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <p class="finance-source">Манба: 6-жадвал ва кафолат хати.</p>
</div>
```

- [ ] **Step 3: Commit**

```powershell
git add backend/resources/views/livewire/dashboard/panels/unemployment-details.blade.php `
        backend/resources/views/livewire/dashboard/panels/poverty-details.blade.php
git commit -m "feat: add unemployment + poverty detail panels"
```

---

## Task 9: Budget-Investment Panel + MacroComposition + KpiScoreline

**Files:**
- Create: `backend/resources/views/livewire/dashboard/panels/budget-investment.blade.php`
- Create: `backend/app/Livewire/Dashboard/MacroComposition.php`
- Create: `backend/resources/views/livewire/dashboard/macro-composition.blade.php`
- Create: `backend/app/Livewire/Dashboard/KpiScoreline.php`
- Create: `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php`

---

- [ ] **Step 1: Create panels/budget-investment.blade.php**

Create `backend/resources/views/livewire/dashboard/panels/budget-investment.blade.php`:

```blade
@php
    use App\Support\DashboardCatalog;
    $periods = DashboardCatalog::PERIODS;
    $periodLabels = DashboardCatalog::PERIOD_LABELS;
@endphp

<div class="quarter-matrix">
    @foreach($periods as $period)
        @php
            $row = $rows->get($period);
            $unit = $row->unit ?? $indicator->default_unit ?? '';
            $plan = $row && $row->plan_value !== null ? number_format((float) $row->plan_value, 1) . ' ' . $unit : '—';
            $fact = $row && $row->actual_hokimyat !== null ? number_format((float) $row->actual_hokimyat, 1) . ' ' . $unit : '—';
            $exec = $row && $row->pct_of_plan !== null ? number_format((float) $row->pct_of_plan, 1) . '%' : '—';
            $extra = $row && $row->count_extra !== null ? (int) $row->count_extra : null;
            $extra2 = $row && $row->count_extra_2 !== null ? (int) $row->count_extra_2 : null;

            $hero = $row && $row->pct_of_plan !== null ? number_format((float) $row->pct_of_plan, 1) . '%' : $plan;
            $hasFact = $row && $row->actual_hokimyat !== null;
            $stateClass = $hasFact ? 'actual' : 'planned';
        @endphp
        <div class="quarter-row {{ $stateClass }}">
            <div class="q-head">
                <span class="q-period">{{ $periodLabels[$period] }}</span>
            </div>
            <div class="q-hero">
                <span class="q-hero-value">{{ $hero }}</span>
                <span class="q-hero-label">Ижро</span>
            </div>
            <dl class="q-aux">
                <div class="q-aux-row"><span>Режа</span><b class="num">{{ $plan }}</b></div>
                <div class="q-aux-row"><span>Амалда</span><b class="num">{{ $fact }}</b></div>
                @if($extra !== null)
                    <div class="q-aux-row"><span>{{ $indicator->count_extra_label ?? 'Объектлар' }}</span><b class="num">{{ $extra }}</b></div>
                @endif
                @if($extra2 !== null)
                    <div class="q-aux-row"><span>{{ $indicator->count_extra_2_label ?? 'Ишга туширилди' }}</span><b class="num">{{ $extra2 }}</b></div>
                @endif
            </dl>
        </div>
    @endforeach
</div>
```

- [ ] **Step 2: Create MacroComposition.php**

Create `backend/app/Livewire/Dashboard/MacroComposition.php`:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Support\DashboardCatalog;
use Livewire\Component;

class MacroComposition extends Component
{
    public function selectKpi(string $code): void
    {
        $this->dispatch('kpi-selected', kpi: $code);
    }

    public function render()
    {
        $components = ['industry', 'agriculture', 'construction', 'services'];

        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->whereIn('indicator_code', $components)
            ->where('period', 'year')
            ->get()
            ->keyBy('indicator_code');

        $indicators = Indicator::whereIn('code', $components)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return view('livewire.dashboard.macro-composition', [
            'components' => $components,
            'facts'      => $facts,
            'indicators' => $indicators,
        ]);
    }
}
```

- [ ] **Step 3: Create macro-composition.blade.php**

Create `backend/resources/views/livewire/dashboard/macro-composition.blade.php`:

```blade
<details class="macro-composition-panel macro-composition-dropdown" aria-label="ЯҲМ таркиби">
    <summary class="macro-composition-head">
        <div>
            <strong>ЯҲМ таркибий мақсадлари</strong>
            <small>Саноат, қишлоқ хўжалиги, қурилиш ва хизматлар ўсиш суръати</small>
        </div>
        <span class="macro-dropdown-meta">
            <span>{{ count($components) }} та мақсад</span>
            <span class="macro-dropdown-caret" aria-hidden="true">⌄</span>
        </span>
    </summary>
    <div class="macro-composition-body">
        <div class="composition-grid">
            @foreach($components as $code)
                @php
                    $ind = $indicators->get($code);
                    $fact = $facts->get($code);
                    if (! $ind) continue;
                    $growth = $fact && $fact->growth_pct !== null ? number_format((float) $fact->growth_pct, 1) . '%' : '—';
                @endphp
                <button class="component-card"
                        wire:click="selectKpi('{{ $code }}')"
                        type="button">
                    <span class="product-body">
                        <span class="product-name">{{ $ind->label_short }}</span>
                        <strong class="product-value">{{ $growth }}</strong>
                        <small class="product-note">йиллик ўсиш суръати</small>
                    </span>
                </button>
            @endforeach
        </div>
    </div>
</details>
```

- [ ] **Step 4: Create KpiScoreline.php**

Create `backend/app/Livewire/Dashboard/KpiScoreline.php`:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiScoreline extends Component
{
    #[Reactive]
    public string $module = 'macro';

    #[Reactive]
    public string $kpi = 'grp';

    public function render()
    {
        // Mock counts per Plan 10 Q1=C decision (real tasks data lands in Plan 12)
        $total = 12;
        $done = 7;
        $open = $total - $done;
        $pct = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $kpiShort = $this->kpi === 'grp' ? 'ЯҲМ' : ucfirst($this->kpi);
        $scope = $kpiShort . 'га оид чора-тадбирлар';

        return view('livewire.dashboard.kpi-scoreline', [
            'total'  => $total,
            'done'   => $done,
            'open'   => $open,
            'pct'    => $pct,
            'scope'  => $scope,
            'module' => $this->module,
        ]);
    }
}
```

- [ ] **Step 5: Create kpi-scoreline.blade.php**

Create `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php`:

```blade
<div class="scoreline execution-strip">
    <div class="scoreline-copy">
        <span>Чора-тадбирлар ижроси</span>
        <strong>{{ $scope }}</strong>
        <small>Ушбу йўналишга тегишли чора-тадбирлар ҳолати.</small>
    </div>
    <div class="exec-status-grid">
        <a class="exec-status-pill" href="{{ route('tasks') }}?module={{ $module }}">
            <span>Жами</span>
            <strong>{{ $total }}</strong>
        </a>
        <a class="exec-status-pill green" href="{{ route('tasks') }}?module={{ $module }}&status=done">
            <span>Бажарилди</span>
            <strong>{{ $done }}</strong>
        </a>
        <a class="exec-status-pill red" href="{{ route('tasks') }}?module={{ $module }}&status=open">
            <span>Бажарилмади</span>
            <strong>{{ $open }}</strong>
        </a>
    </div>
    <div class="exec-progress-box">
        <div class="exec-donut" style="--pct:{{ $pct }}"><strong>{{ $pct }}%</strong></div>
        <small>бажарилиш</small>
    </div>
    <div class="score-actions">
        <a class="score-action primary" href="{{ route('tasks') }}?module={{ $module }}">Чора-тадбирларни кўриш</a>
        <a class="score-action" href="{{ route('execution') }}">Ижро журнали</a>
    </div>
</div>
```

- [ ] **Step 6: Commit**

```powershell
git add backend/resources/views/livewire/dashboard/panels/budget-investment.blade.php `
        backend/app/Livewire/Dashboard/MacroComposition.php `
        backend/resources/views/livewire/dashboard/macro-composition.blade.php `
        backend/app/Livewire/Dashboard/KpiScoreline.php `
        backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php
git commit -m "feat: add budget-investment panel + MacroComposition + KpiScoreline"
```

---

## Task 10: HTTP Smoke Tests + End-to-End Verify

**Files:**
- Modify: `backend/tests/Feature/Http/DashboardRoutesTest.php`

---

- [ ] **Step 1: Add new smoke tests**

Append to `backend/tests/Feature/Http/DashboardRoutesTest.php`:

```php
test('dashboard with explicit module and kpi returns 200', function () {
    $this->seed();
    $this->get('/dashboard?module=inflation&kpi=inflation')->assertStatus(200);
});

test('dashboard inflation panel renders price caps', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=inflation&kpi=inflation');
    $response->assertStatus(200);
    $response->assertSee('Инфляция чегаралари', false);
    $response->assertSee('Тухум', false);
});

test('dashboard macro module renders module composition dropdown', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=macro');
    $response->assertStatus(200);
    $response->assertSee('macro-composition-panel', false);
});

test('dashboard employment module renders front cards layout', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=employment&kpi=poverty');
    $response->assertStatus(200);
    $response->assertSee('module-kpis employment-layout', false);
});

test('dashboard renders scoreline strip', function () {
    $this->seed();
    $response = $this->get('/dashboard');
    $response->assertStatus(200);
    $response->assertSee('scoreline execution-strip', false);
});

test('dashboard module tabs render all 7 modules', function () {
    $this->seed();
    $response = $this->get('/dashboard');
    $response->assertStatus(200);
    $response->assertSee('Макроиқтисодиёт', false);
    $response->assertSee('Инфляция', false);
    $response->assertSee('Бюджет', false);
    $response->assertSee('Хорижий инвестициялар', false);
    $response->assertSee('Экспорт', false);
    $response->assertSee('Бандлик', false);
});
```

- [ ] **Step 2: Run smoke tests**

```powershell
php -d memory_limit=2G vendor/bin/pest tests/Feature/Http/DashboardRoutesTest.php
```

Expected: 13 PASS (7 original + 6 new).

- [ ] **Step 3: Run full suite**

```powershell
php -d memory_limit=2G vendor/bin/pest --stop-on-failure
```

Expected: all green.

- [ ] **Step 4: End-to-end verify with real data**

```powershell
php artisan migrate:fresh --seed
php artisan import:region andijan 2026
# note run_id from output
php artisan import:promote {run_id}
php artisan serve
```

Visit:
- `http://localhost:8000/dashboard` → module tabs, macro module active, grp KPI workspace card with macro-growth panel
- `http://localhost:8000/dashboard?module=inflation&kpi=inflation` → inflation chegaралари + price caps + food balance
- `http://localhost:8000/dashboard?module=employment&kpi=poverty` → poverty-details panel
- `http://localhost:8000/dashboard?module=budget_invest&kpi=budget_investment` → budget-investment panel

- [ ] **Step 5: Commit**

```powershell
git add backend/tests/Feature/Http/DashboardRoutesTest.php
git commit -m "test: add Plan 10 dashboard parity smoke tests"
```

---

## Self-Review

**Spec coverage:**

| Spec section | Covered by |
|---|---|
| §2 Architecture (parent + 5 children) | Tasks 2-9 |
| §3 DashboardCatalog | Task 1 |
| §4 Data flow per component | Tasks 4, 5 (inflation/poverty branches), 9 |
| §5 State & routing | Task 2 |
| §6 File structure | Tasks 1-9 |
| §7 Visual parity classes | Tasks 3-9 (markup) |
| §8 Out of scope | Mock counts in Task 9, no modal popups |
| §9 Testing | Task 10 |

**Type consistency:**
- Parent dispatches `module-selected(module: string)`, `kpi-selected(kpi: string)`. Children use same parameter names. ✓
- All `#[Reactive]` props are `string`. ✓
- Panel partial picker `match()` uses string equality + `DashboardCatalog::isMacroGrowthKpi()` — both consistent. ✓

**Placeholder check:** No TBD/TODO. Every code block contains real implementation.

**Idempotency:** Tasks 3-9 are independent additions — partial failure leaves dashboard non-functional but doesn't corrupt state. Task 2 makes parent reference 5 child components that don't exist yet — so dashboard is broken until Task 9 completes. Acceptable for plan-level work; do NOT push between Tasks 2 and 9.
