# Districts side aside + detail table parity

**Date:** 2026-05-12
**Status:** Approved (pending user spec review)
**Scope:** Bring the `/districts` page's `.districts-side` aside and `.panel.district-detail-table` table to full visual parity with `index.html#districtsPage`. The existing implementation has simplified, fixed-shape versions; this spec replaces them with the per-KPI dynamic shape from the prototype.

---

## 1. Goal

Two parts of `/districts` ship simpler than the prototype:

- **Side aside (`.districts-side`)** currently misses the T/D count chips, the 4 per-KPI metric tiles, the multi-button action row, the trailing report chip, and uses wrong leaderboard class names (`.district-leaderboard` / `.leaderboard-row` instead of `.districts-leaderboard` / `.districts-lb-list` / `.lb-row`).
- **Detail table (`.panel.district-detail-table`)** uses a fixed 6-column shape (`Плана / Амалда / Ўсиш % / Ижро % / Ҳолат / Профил`). Prototype uses per-KPI dynamic columns from `districtTableConfig(kpi)` (3-4 metric cols varying by KPI) plus fixed `T-топшириқ / D-мақсад / Ҳисобот / Амал` columns.

This spec adds two pure helper classes and rewrites both Blade fragments. Andijan-only stays.

## 2. Non-goals

- No CSS. All required classes already exist in `portal.css`.
- No migrations, no new tables.
- No `reports` table. The "Ҳисобот / таъсир" column hardcodes a `ҳисобот йўқ` grey chip until a reports spec lands.
- No `district.data` JSONB. The prototype's `fieldColumn(data.foo.bar)` columns render `—`. Affected KPIs documented in §10.
- No "Ҳисобот киритиш" entry modal — drop that action button.
- No district `owner` field — render `'ҳокимлик'` literal beneath each district name.
- No other regions, no schema changes.

## 3. Strategy

Two new pure-data classes plus three new computed properties on `DistrictsPage`:

| File | Responsibility |
|---|---|
| `backend/app/Support/DistrictTableConfig.php` | `for(string $kpi): array` returns `{title, description, source, primary_period, columns: [{label, metric?, note?}]}` per KPI. 16 entries mirroring `districtTableConfig` from `index.html:8578`. |
| `backend/app/Support/DistrictMetricResolver.php` | pure formatter: `value(?IndicatorFact, string $kind): string` and `note(?IndicatorFact, ?string $noteKind): string` and `status(?IndicatorFact, bool $lowerIsBetter): string`. |
| `backend/app/Livewire/DistrictsPage.php` | add `tableConfig`, `factMatrix`, `taskCountByDistrict`, `targetCountByDistrict` computed properties. |
| `backend/resources/views/livewire/districts-page.blade.php` | rewrite `.districts-side` (full prototype shape) and `.district-detail-table` (dynamic columns). |

## 4. `DistrictTableConfig`

### 4.1 Shape

`for(string $kpi): array` returns:

```php
[
    'title'          => 'Саноат: туманлар кесими',
    'description'    => 'Туманлар бўйича саноат маҳсулотлари ҳажми ва ўсиш суръати.',
    'source'         => '1.2-жадвал',
    'primary_period' => 'h1',
    'columns'        => [
        [
            'label'  => 'I чорак амалда',
            'metric' => ['kpi' => 'industry', 'period' => 'q1',   'kind' => 'growth'],
            'note'   => 'fact',
        ],
        [
            'label'  => 'I ярим йиллик прогноз',
            'metric' => ['kpi' => 'industry', 'period' => 'h1',   'kind' => 'growth'],
            'note'   => 'plan',
        ],
        // …
    ],
],
```

- `metric.kind` ∈ `{growth, execution, plan, fact}` — selects which IndicatorFact column the resolver reads.
- `note` ∈ `{fact, plan, volume, null}` — selects which secondary line to print under the cell value.
- A column with `metric === null` represents the prototype's `fieldColumn` (raw district JSONB data) and renders `—` in this spec.

### 4.2 Per-KPI configs

Ported verbatim from `districtTableConfig` (`index.html:8578-8736`). Key configs:

- **`grp`** — primary `state.period`. 4 cols: Саноат ўсиши (growth), ҚХ ўсиши (growth), Хизматлар ўсиши (growth), Изоҳ (no metric).
- **`industry`** / **`agriculture`** / **`services`** — primary `state.period`. 4 cols: I чорак амалда / I ярим йиллик прогноз / 9 ойлик прогноз / Йиллик прогноз. Each = growth-kind metric for the same KPI in q1/h1/m9/year.
- **`localization`** — primary `h1`. 4 cols: H1 лойиҳа (plan-kind), H1 қиймат (fieldColumn → `—`), Йиллик лойиҳа (plan), Йиллик қиймат (fieldColumn → `—`).
- **`energy_electricity`** / **`energy_gas`** — primary `h1`. 2 cols: H1 тежаш (plan), Йиллик тежаш (plan).
- **`inflation`** — primary `year`. 4 cols: Захира омбори (fieldColumn → `—`), Совутгичли омбор (fieldColumn → `—`), Янги омбор режаси (fieldColumn → `—`), Жами сиғим (plan).
- **`budget`** — primary `h1`. 3 cols: II чорак ижро (execution), I ярим йиллик ижро (execution), Йиллик кутилиш (execution).
- **`budget_investment`** — primary `h1`. 4 cols: Объектлар (fieldColumn → `—`), I чорак ўзлаштириш (execution), H1 ўзлаштириш (execution), Йиллик ўзлаштириш (execution).
- **`investment`** — primary `h1`. 4 cols: I чорак ижро (execution), H1 ижро (execution), Йиллик ижро (execution), H1 лойиҳа / иш ўрни (fieldColumn → `—`).
- **`export`** — primary `h1`. 4 cols: I чорак амалда (growth), H1 кутилиш (growth), Йиллик кутилиш (growth), Экспортчилар (fieldColumn → `—`).
- **`unemployment`** — primary `h1`. 4 cols: H1 мақсад (plan), Йиллик мақсад (plan), Ишга жойлаштириш H1 (plan via `jobs`), Легаллаштириш H1 (plan via `legalization`).
- **`poverty`** — primary `h1`. 4 cols: H1 камбағаллик (plan), Йиллик камбағаллик (plan), Камбағалликдан холи МФЙлар H1 (plan via `mfy_clear`), Микролойиҳа H1 (plan via `microprojects`).
- **`jobs`** — primary `h1`. 4 cols (mirrors prototype).
- **`legalization`** — primary `h1`. 3 cols.
- **`mfy_clear`** — primary `h1`. 3 cols.
- **`microprojects`** — primary `h1`. 3 cols.

Fallback: unknown `$kpi` returns the `export` config (matches prototype's `configs[id] || configs.export`).

## 5. `DistrictMetricResolver`

```php
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

## 6. DistrictsPage component additions

### 6.1 New computed properties

- `tableConfig(): array` — `DistrictTableConfig::for($this->kpi)`.
- `factMatrix(): array` — `[$kpi][$district_code][$period] = IndicatorFact|null`. Built by:
  - collecting all `(kpi, period)` pairs from `tableConfig['columns'][i].metric`
  - one query: `IndicatorFact::where('region_code','andijan')->whereNotNull('district_code')->whereIn(['indicator_code','period'], $pairs)->get()->groupBy(...)`
  - indexed in PHP for O(1) lookup in the Blade
- `taskCountByDistrict(): array` — `[district_code => ['unfinished' => int, 'total' => int]]`. Single query: `Task::forRegion('andijan')->forIndicator($this->kpi)->with('districts')->get()` then group/sum.
- `targetCountByDistrict(): array` — `[district_code => int]`. Single query: `PromiseTarget::where('region_code','andijan')->where('indicator_code', $this->kpi)->whereNotNull('target_districts')->get()` then count by exploding the JSONB `target_districts` array.

### 6.2 Period switching

`period` keeps existing default `'h1'` but `selectKpi(string $code)` additionally sets `$this->period = DistrictTableConfig::for($code)['primary_period']`. This matches the prototype's `cfg.primaryPeriod` behaviour.

`selectModule(string $code)` already cascades `kpi`; that cascade now also goes through `selectKpi` semantics so period sync stays consistent.

## 7. Blade rewrites

### 7.1 `.districts-side` — selected summary card + leaderboard

The full markup pattern matches prototype lines 8932-8964 + 8969-8991. Key elements:

- `district-summary-card`:
  - `district-summary-head` (eyebrow + name + status chip)
  - `district-summary-value` (big number + label) + `district-count-split` (T/D chips)
  - `district-summary-metrics` — first 4 columns from `tableConfig`, each `<div class="district-summary-metric"><span>{label}</span><strong>{value}</strong><small>{note}</small></div>`. Value from `factMatrix[$col.metric.kpi][$selectedCode][$col.metric.period]` resolved via `DistrictMetricResolver::value`. Note via `DistrictMetricResolver::note`.
  - `district-summary-actions` — three pill buttons (Туман профили / Ижро журнали / Топшириқлар) linking to `route('profile')?districtCode=…`, `route('execution')?…`, `route('tasks')?…`.
  - Trailing `<span class="chip grey">ҳисобот йўқ</span>`.
- `districts-leaderboard`:
  - `districts-lb-head` = `<strong>Туманлар</strong><span>{count} та туманлар · {kpiShort}</span>`
  - `districts-lb-list` = `<ol>` of `<li class="lb-row {status} {selected}">` w/ `<span class="lb-rank">{idx+1}</span><span class="lb-name">{name}</span><span class="lb-value">{primary}</span><span class="lb-bar"><i style="width:{pct}%"></i></span>`. Click selects district.

Chip-class helpers (inline in Blade):

- `taskChipClass` = `'red'` if unfinished > 0; `'green'` if total > 0; else `'grey'`.
- `targetChipClass` = `'blue'` if target_count > 0 else `'grey'`.

### 7.2 `.panel.district-detail-table` — dynamic columns

Header row: `Туман/шаҳар`, one `<th>` per `tableConfig['columns'][i].label`, then `T-топшириқ`, `D-мақсад`, `Ҳисобот / таъсир`, `Амал`.

Each body row:

```blade
<tr class="clickable {{ $selected ? 'active-row' : '' }}" wire:click="selectDistrict('{{ $code }}')">
  <td class="row-title"><strong>{{ $d->name_full }}</strong><span>ҳокимлик</span></td>
  @foreach($tableConfig['columns'] as $col)
    @php
      $fact = $col['metric']
        ? ($factMatrix[$col['metric']['kpi']][$code][$col['metric']['period']] ?? null)
        : null;
      $cellValue = $col['metric']
        ? DistrictMetricResolver::value($fact, $col['metric']['kind'])
        : '—';
      $cellNote  = $col['metric'] ? DistrictMetricResolver::note($fact, $col['note'] ?? null) : '';
    @endphp
    <td><strong>{{ $cellValue }}</strong><small>{{ $cellNote }}</small></td>
  @endforeach
  <td class="num"><span class="chip {{ $taskChipClass }}">{{ $tasks['unfinished'] }}/{{ $tasks['total'] }}</span></td>
  <td class="num"><span class="chip {{ $targetChipClass }}">{{ $targetCount }}</span></td>
  <td><span class="chip grey">ҳисобот йўқ</span><small>амалдаги натижа киритилмаган</small></td>
  <td>
    <div class="action-row compact">
      <a class="mini-button profile" href="{{ route('profile') }}?districtCode={{ $code }}">Профил</a>
      <a class="mini-button" href="{{ route('execution') }}?indicator={{ $kpi }}&district={{ $code }}&period={{ $period }}">Журнал</a>
    </div>
  </td>
</tr>
```

`row-title` second span is `'ҳокимлик'` literal (no `district.owner` field exists; documented).

Panel head shows `tableConfig['title']` + description; source chip shows `tableConfig['source']`.

## 8. Tests

### 8.1 `tests/Unit/DistrictTableConfigTest.php`

- 16 known KPIs return non-empty config with `title`, `description`, `source`, `primary_period`, `columns` keys.
- `industry` has 4 columns, first labelled `'I чорак амалда'` with `metric.period === 'q1'` and `metric.kind === 'growth'`.
- `budget` has 3 columns with `metric.kind === 'execution'`.
- `unemployment` has 4 columns referencing kpis `unemployment`, `unemployment`, `jobs`, `legalization` respectively.
- Unknown `'xyz'` returns export config.

### 8.2 `tests/Unit/DistrictMetricResolverTest.php`

- `value(null, 'growth')` → `'—'`.
- Growth `8.0` → `'+8,0%'`.
- Growth `-2.1` → `'-2,1%'`.
- Execution `95.0` → `'+95,0%'` (or matches whatever `pct()` outputs — sign formatting fixed in test).
- Plan with unit `'trln'` → `'100,5 trln'`.
- Note `'fact'` includes `'факт '` prefix.

### 8.3 `tests/Feature/Http/DistrictsPageTest.php` (extend existing)

- New test: GET `/districts?kpi=industry` HTML contains `'I чорак амалда'` `<th>`.
- New test: GET `/districts?kpi=budget` HTML contains `'II чорак ижро'` `<th>` and DOES NOT contain `'I чорак амалда'`.
- New test: leaderboard markup uses `.districts-lb-list` and `.lb-row` (existing test currently checks `districts-side` only; broaden).
- New test: row renders `T-топшириқ` chip + `D-мақсад` chip + `ҳисобот йўқ` chip.

## 9. Files touched

| File | Action |
|---|---|
| `backend/app/Support/DistrictTableConfig.php` | new |
| `backend/app/Support/DistrictMetricResolver.php` | new |
| `backend/app/Livewire/DistrictsPage.php` | modify (add computed + period sync) |
| `backend/resources/views/livewire/districts-page.blade.php` | modify (rewrite aside + table) |
| `backend/tests/Unit/DistrictTableConfigTest.php` | new |
| `backend/tests/Unit/DistrictMetricResolverTest.php` | new |
| `backend/tests/Feature/Http/DistrictsPageTest.php` | extend |

No CSS, no migrations, no other Blade files.

## 10. Known parity gaps

- `fieldColumn` data (raw `data.*` JSONB on district) — Laravel has no equivalent. Columns render `—`. Affects `localization`, `energy_*`, `inflation` warehouses, `budget_investment.objects/limit`, `investment.foreign_investment.h1_*`, `export.exporters`.
- "Ҳисобот / таъсир" column — no `reports` table yet. Hardcoded `ҳисобот йўқ` grey chip.
- "Ҳисобот киритиш" action button dropped (no entry modal).
- District `owner` field — not in `districts` table. Render `'ҳокимлик'` literal under each name.
- `state.period` cross-mapping with `primary_period` — `selectKpi` now syncs `period` to KPI's primary; if user manually toggles period via a future `selectPeriod` they override. Out of scope.

## 11. Risks and mitigations

- **Risk:** `factMatrix` query might fan out across many (kpi, period) pairs for KPIs like `unemployment` that reference `jobs`/`legalization` etc. *Mitigation:* one batched query w/ `whereIn` on a tuple list; capped at ~6 pairs per config max.
- **Risk:** `targetCountByDistrict` requires parsing JSONB `target_districts` arrays in PHP. *Mitigation:* `PromiseTarget` already casts `target_districts` to array; iterate in PHP.
- **Risk:** Per-KPI column count varies (2-4) — narrow viewports may overflow. *Mitigation:* existing `.table-scroll` wrapper + CSS handles horizontal scroll.
- **Risk:** Prototype's `metricColumn` note callable takes `(d, row) => string` w/ access to district + row data; ours is declarative. *Mitigation:* `note` enum (`fact/plan/volume`) covers all observed prototype variants; new variants can extend the enum.
