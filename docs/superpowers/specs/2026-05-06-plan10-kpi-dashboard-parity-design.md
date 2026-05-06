# Plan 10 — KPI Dashboard Parity (index.html → Livewire, DB-backed)

**Date:** 2026-05-06
**Status:** Draft, awaiting user review.
**Branch:** `v7-design-polish`
**Scope:** Rebuild `/dashboard` to match `index.html`'s `renderDashboard()` markup exactly while sourcing data from PostgreSQL. Other 4 pages untouched.

**Predecessors:**
- Plan 9 (committed): Blade + Livewire skeleton, 5 routes, `import:promote` command, 620 facts in production tables for Andijan 2026.
- Current `KpiDashboard` Livewire component renders only module tabs + simple front-KPI tiles. Far short of index.html's dashboard.

---

## 1. Context

`index.html` `renderDashboard()` (line 6742) emits a dense layout per selected module: 7-module tab strip, module heading + intro, conditional front-KPI grid (macro and employment modules only), one big workspace card with quarter matrix or special detail panel based on selected KPI, macro composition `<details>` dropdown for macro module, and scoreline strip with task counts.

Plan 10 rebuilds this in Blade + Livewire 4 with data from `indicator_facts`, `food_balance`, `warehouses`, `indicators`, `districts`. Static text (module intros, regulatory price caps, finance source notes) stays hardcoded — same strings as index.html.

Existing `portal.css` already contains all needed classes — no CSS changes. Visual parity is achieved by emitting the same class names with the same hierarchy as index.html.

---

## 2. Architecture

**Parent: `KpiDashboard` (modified)**

Owns minimal state — `$module` and `$kpi` — both persisted to URL via `#[Url]` for refresh-safe deep links. Listens for `module-selected` and `kpi-selected` events from children. No DB queries.

**Children: 5 nested Livewire components**

| Component | `#[Reactive]` props | Dispatches | DB queries |
|---|---|---|---|
| `Dashboard\KpiModuleTabs` | `$module` | `module-selected(module)` | none |
| `Dashboard\KpiFrontCards` | `$module`, `$kpi` | `kpi-selected(kpi)` | IndicatorFact rollup rows for module's KPIs |
| `Dashboard\KpiWorkspaceCard` | `$module`, `$kpi` | none (cross-page jumps are `<a href>`) | varies by panel — see §4 |
| `Dashboard\MacroComposition` | none (only renders for macro) | `kpi-selected(kpi)` on component card click | IndicatorFact growth_pct for industry/agriculture/construction/services |
| `Dashboard\KpiScoreline` | `$module`, `$kpi` | none | none (mock counts) |

**Detail panels** are Blade `@include` partials inside `KpiWorkspaceCard.blade.php`, not separate Livewire components. KpiWorkspaceCard picks one of six partials per render based on `$kpi`:
- `panels.quarter-matrix` (default fallback)
- `panels.macro-growth` (kpi = grp or any macro component)
- `panels.inflation-details` (kpi = inflation)
- `panels.unemployment-details` (kpi = unemployment)
- `panels.poverty-details` (kpi = poverty)
- `panels.budget-investment` (kpi = budget_investment)

**Layout in parent shell:**

```
<livewire:dashboard.kpi-module-tabs />
<div class="module-heading"><h2>{label}</h2><p>{intro}</p></div>
<livewire:dashboard.kpi-front-cards />        // hidden when module not in [macro, employment]
<livewire:dashboard.kpi-workspace-card />
<livewire:dashboard.macro-composition />      // only when module === 'macro'
<livewire:dashboard.kpi-scoreline />
```

---

## 3. Static Data — `DashboardCatalog`

`app/Support/DashboardCatalog.php` — pure-PHP static class. No DB. Single source of truth for static dashboard metadata so children stay thin.

**Public API:**

```php
DashboardCatalog::modules(): array               // all 7 modules in display order
DashboardCatalog::moduleLabel(string $code): string
DashboardCatalog::moduleIntro(string $code): string
DashboardCatalog::moduleKpis(string $code): array   // indicator codes in display order
DashboardCatalog::firstKpiForModule(string $code): string
DashboardCatalog::moduleForKpi(string $kpi): string
DashboardCatalog::hasFrontCards(string $module): bool   // true for macro + employment
DashboardCatalog::inflationPriceCaps(): array     // 8 hardcoded products → cap text
DashboardCatalog::inflationLimits(): array        // II чорак ≤2,9% / Йил якуни ≤6,6%
DashboardCatalog::periods(): array                // ['q1', 'h1', 'm9', 'year']
DashboardCatalog::periodLabel(string $code): string
DashboardCatalog::isLowerBetter(string $kpi): bool   // inflation, poverty, unemployment
DashboardCatalog::isMacroGrowthKpi(string $kpi): bool   // grp + components
```

**Module list (hardcoded in display order):**

| code | label | indicator codes |
|---|---|---|
| `macro` | 1. Макроиқтисодиёт | grp, industry, agriculture, construction, services |
| `inflation` | 2. Инфляция | inflation |
| `budget` | 3. Бюджет | budget |
| `budget_invest` | 4. Бюджет инвестициялари | budget_investment |
| `foreign_invest` | 5. Хорижий инвестициялар | investment |
| `export` | 6. Экспорт | export |
| `employment` | 7. Бандлик ва камбағаллик | unemployment, poverty, jobs, legalization, mfy_clear, microprojects |

**Module intros:** copied verbatim from `dashboardModuleIntro()` in index.html (line 6395).

**Inflation price caps:** 8 products with regulatory cap strings, copied verbatim from `renderInflationDetails()` (line 7415):
- Гўшт ва гўшт маҳсулотлари → 6–7%дан ошмаслик
- Тухум → 5–6%дан ошмаслик
- Сут ва сут маҳсулотлари → 6–7%дан ошмаслик
- Картошка → 4–5%дан ошмаслик
- Пиёз → 5%дан ошмаслик
- Сабзи → 5%дан ошмаслик
- Гуруч → 2025 йил даражасида
- Ун → 2025 йил даражасида

---

## 4. Data Flow Per Component

### `KpiModuleTabs`
- No DB query. Reads `DashboardCatalog::modules()`.
- Renders `<button class="module-tab {{ active }}">` for each. Click → `$dispatch('module-selected', module: $code)`.

### `KpiFrontCards`
- Hidden via early `return view()` empty when `! DashboardCatalog::hasFrontCards($module)`.
- Query (year=2026, region=andijan):
  ```php
  IndicatorFact::where('region_code', 'andijan')
      ->where('year', 2026)
      ->whereNull('district_code')
      ->where('period', 'year')
      ->whereIn('indicator_code', DashboardCatalog::moduleKpis($module))
      ->get()->keyBy('indicator_code');
  ```
- Indicator metadata: `Indicator::whereIn('code', $codes)->orderBy('sort_order')->get()->keyBy('code');`
- Renders `.front-kpis.module-kpis.{macro-layout|employment-layout}` grid of `<button class="front-kpi {{ active }} {{ parent }}">` cards. Click → `$dispatch('kpi-selected', kpi: $code)`.

### `KpiWorkspaceCard`
- Always queries 4 periods (q1, h1, m9, year) for `$kpi`:
  ```php
  $rows = IndicatorFact::where('region_code', 'andijan')
      ->where('year', 2026)
      ->whereNull('district_code')
      ->where('indicator_code', $kpi)
      ->whereIn('period', ['q1', 'h1', 'm9', 'year'])
      ->get()->keyBy('period');
  ```
- Indicator metadata for `$kpi`.
- Picks panel partial:
  - `$kpi === 'inflation'` → load `food_balance` + `warehouses` rollup rows; render `panels.inflation-details`.
  - `$kpi === 'unemployment'` → also load jobs/legalization rollup facts (h1, year); render `panels.unemployment-details`.
  - `$kpi === 'poverty'` → also load jobs/legalization/mfy_clear/microprojects rollup facts; load districts where `poverty` district fact has `is_sentinel = true` and `sentinel_label` LIKE '%холи%'; render `panels.poverty-details`.
  - `$kpi === 'budget_investment'` → use 4-period rows + `count_extra` / `count_extra_2`; render `panels.budget-investment`.
  - `DashboardCatalog::isMacroGrowthKpi($kpi)` (grp / industry / agriculture / construction / services) → render `panels.macro-growth`.
  - default → render `panels.quarter-matrix`.

### `MacroComposition`
- Only mounted when parent's `$module === 'macro'` (via `@if` in parent blade).
- Query: industry, agriculture, construction, services growth_pct across q1/h1/m9/year:
  ```php
  IndicatorFact::where('region_code', 'andijan')
      ->where('year', 2026)
      ->whereNull('district_code')
      ->whereIn('indicator_code', ['industry', 'agriculture', 'construction', 'services'])
      ->whereIn('period', ['q1', 'h1', 'm9', 'year'])
      ->get()->groupBy('indicator_code');
  ```
- Renders `<details class="macro-composition-panel macro-composition-dropdown">` with summary + body.
- Component card click → `$dispatch('kpi-selected', kpi: $code)`.

### `KpiScoreline`
- No DB. Mock counts (hardcoded constants per Q1=C decision):
  ```php
  $total = 12; $done = 7; $open = 5; $pct = 58;
  ```
- Renders `.scoreline.execution-strip` matching index.html. "Чора-тадбирларни кўриш" → `<a href="/tasks?module={{ $module }}">`. "Ижро журнали" → `<a href="/execution">`.

---

## 5. State & Routing

**Parent `KpiDashboard`:**

```php
#[Url]
public string $module = 'macro';

#[Url]
public string $kpi = 'grp';

#[On('module-selected')]
public function selectModule(string $module): void
{
    $this->module = $module;
    // Reset kpi to first of new module so workspace card has valid selection
    $this->kpi = DashboardCatalog::firstKpiForModule($module);
}

#[On('kpi-selected')]
public function selectKpi(string $kpi): void
{
    $this->kpi = $kpi;
    $this->module = DashboardCatalog::moduleForKpi($kpi);
}
```

**URL examples:**
- `/dashboard` → defaults: module=macro, kpi=grp
- `/dashboard?module=inflation&kpi=inflation`
- `/dashboard?module=employment&kpi=poverty`

**Cross-page jumps** (rendered as `<a href>`, no modal):
- "Туманлар кесими →" inside KPI head → `route('districts') . '?indicatorCode=' . $kpi`
- "Ижро журнали" inside scoreline → `route('execution')`
- District-name links inside poverty/unemployment district lists → `route('profile') . '?districtCode=' . $code`

---

## 6. File Structure

**New (15 files):**

```
backend/
  app/
    Support/
      DashboardCatalog.php
    Livewire/
      Dashboard/
        KpiModuleTabs.php
        KpiFrontCards.php
        KpiWorkspaceCard.php
        MacroComposition.php
        KpiScoreline.php
  resources/
    views/
      livewire/
        dashboard/
          kpi-module-tabs.blade.php
          kpi-front-cards.blade.php
          kpi-workspace-card.blade.php
          macro-composition.blade.php
          kpi-scoreline.blade.php
          panels/
            quarter-matrix.blade.php
            macro-growth.blade.php
            inflation-details.blade.php
            unemployment-details.blade.php
            poverty-details.blade.php
            budget-investment.blade.php
```

**Modified (2):**
- `backend/app/Livewire/KpiDashboard.php` — strip current logic, replace with parent owning `$module`+`$kpi`, listeners, no DB.
- `backend/resources/views/livewire/kpi-dashboard.blade.php` — replace tile grid with shell embedding 5 child components.

**Untouched:** `routes/web.php`, `layouts/app.blade.php`, `pages/dashboard.blade.php`, `public/css/portal.css`, all other Livewire components, all other pages.

---

## 7. Visual Parity Rules

- Class names emitted match index.html exactly. Examples: `dashboard-module-tabs`, `module-tab`, `module-dot`, `module-heading`, `front-kpis`, `module-kpis`, `macro-layout`, `employment-layout`, `front-kpi`, `kpi-icon`, `front-kpi-copy`, `front-kpi-meta`, `front-kpi-dot`, `kpi-monitor-grid`, `kpi-monitor-card`, `kpi-monitor-head`, `small-icon`, `head-watermark`, `mini-button`, `kpi-head-district`, `quarter-matrix`, `quarter-row`, `q-head`, `q-period`, `q-hero`, `q-hero-value`, `q-trend`, `q-aux`, `q-aux-row`, `chip`, `scoreline`, `execution-strip`, `scoreline-copy`, `exec-status-grid`, `exec-status-pill`, `exec-progress-box`, `exec-donut`, `score-actions`, `score-action`, `macro-composition-panel`, `macro-composition-dropdown`, `macro-composition-head`, `macro-dropdown-meta`, `macro-dropdown-caret`, `macro-composition-body`, `drivers`, `lagging`, `lagging-title`, `driver-grid`, `driver-card`, `composition`, `composition-grid`, `component-card`, `product-card`, `product-icon`, `product-body`, `product-name`, `product-value`, `product-note`, `data-note`, `finance-source`, `poverty-section`, `employment-driver-section`, `poverty-head`, `poverty-stats`, `poverty-stat`, `poverty-stat-icon`, `poverty-stat-body`, `poverty-stat-label`, `poverty-stat-value`, `poverty-stat-meta`, `poverty-stat-divider`, `poverty-progress`, `poverty-progress-label`.

- Number formatting:
  - `growth_pct` → `number_format(value, 1) . '%'`
  - `plan_value` / `actual_hokimyat` / `expected_value` → `number_format(value, 1) . ' ' . $unit`
  - `pct_of_plan` → `number_format(value, 1) . '%'`
  - Null values → `'—'` (em-dash matching index.html `n()` helper)

- Icons: blade helper `@include('partials.icon', ['name' => $indicator->icon])` reading from existing icon SVG library matching index.html's `icon()` function. If no SVG library exists yet, inline SVG strings in DashboardCatalog mapped by name. Names already in seeder: trend, factory, bank, globe, price, briefcase, rocket, users.

- Static text strings copied verbatim from index.html — Cyrillic Uzbek throughout. Examples: "Бошқарув сигнали", "Даврлар кесими", "Чора-тадбирлар ижроси", "Туманлар кесими", "Ижро журнали".

---

## 8. Out of Scope (Deferred)

- **Real task counts** — scoreline shows mock data. Replace in Plan 12 (tasks table).
- **Modal popups** — task detail modal, execution journal modal not implemented. Buttons render but become navigation links instead of modals.
- **Other 4 pages** — Districts, Profile, Execution, Tasks pages stay as currently built. Separate parity plans later.
- **Period switcher in shared toolbar** — already removed from layout. Each panel handles its own period internally where needed.
- **Multi-region** — Andijan only.
- **Multi-year** — 2026 only.
- **Macro composition details body content** — basic dropdown summary + closed body. Full body content (period switcher, per-quarter growth bars) deferred unless trivial during implementation.
- **renderKpiSignal** (Бошқарув сигнали) — index.html has a side-by-side signal panel for some KPIs. Skipped for Plan 10 unless naturally falls out of quarter-matrix work.
- **renderBudgetInvestmentPanel deep features** (count_extra_2 progress bars beyond plan/fact split) — basic panel only.

---

## 9. Testing

**HTTP smoke tests** (`tests/Feature/Http/DashboardRoutesTest.php` — extend existing):

```php
test('dashboard with explicit module and kpi returns 200', function () {
    $this->seed();
    $this->get('/dashboard?module=inflation&kpi=inflation')->assertStatus(200);
});

test('dashboard inflation panel renders price caps', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=inflation&kpi=inflation');
    $response->assertStatus(200);
    $response->assertSee('Инфляция чегаралари');
    $response->assertSee('Тухум', false);  // false = no HTML escaping check
});

test('dashboard macro module renders module composition dropdown', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=macro');
    $response->assertStatus(200);
    $response->assertSee('macro-composition-panel', false);
});

test('dashboard employment module renders front cards', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=employment&kpi=poverty');
    $response->assertStatus(200);
    $response->assertSee('module-kpis employment-layout', false);
});
```

**No unit tests for `DashboardCatalog`** — it's pure constants; tests would just duplicate definitions.

**End-to-end manual verify** (after `import:promote`):
- Navigate `/dashboard` → module=macro selected, grp KPI workspace card visible with quarter matrix or macro-growth panel.
- Click each module tab → URL updates, content swaps.
- Click each front-KPI card → `$kpi` updates, workspace card swaps.
- `/dashboard?module=inflation&kpi=inflation` → renders price caps + food balance grid + warehouse counts.
- `/dashboard?module=employment&kpi=poverty` → renders poverty-details panel with employment stats.

---

## 10. Task Estimate

~10 tasks for the implementation plan:

1. `DashboardCatalog` static class with full module/intro/price-cap data.
2. Refactor `KpiDashboard` parent — strip queries, add `#[Url]` + listeners.
3. `KpiModuleTabs` child component + blade template.
4. `KpiFrontCards` child component + blade template.
5. `KpiWorkspaceCard` child component + `panels/quarter-matrix.blade.php`.
6. `panels/macro-growth.blade.php` partial.
7. `panels/inflation-details.blade.php` partial.
8. `panels/unemployment-details.blade.php` + `panels/poverty-details.blade.php`.
9. `panels/budget-investment.blade.php` + `MacroComposition` component + `KpiScoreline` component.
10. HTTP smoke tests + end-to-end verify with `php artisan serve`.
