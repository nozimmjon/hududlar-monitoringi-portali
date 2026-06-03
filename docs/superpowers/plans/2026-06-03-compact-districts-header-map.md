# Compact Districts Header + Map Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the `/districts` header + map fit one screen at 1366×768 — slim the header (drop the KPI stat-cards for a thin KPI pill switcher, shrink the hero/module control), cap the map height so the whole SVG scales down, and remove the legend; keep every other map element.

**Architecture:** Pure sizing change to one Livewire page. The view swaps the chunky KPI stat-cards for a thin label-only switcher and drops the legend element; `DistrictsPage` loses the now-unused `moduleKpiStats()`; `portal.css` shrinks the header/hero and caps the map height (uniform SVG scale-down — `MapLabelLayout` math untouched).

**Tech Stack:** Laravel 12, Livewire 3, Blade + SVG, Pest 3. Hand-maintained `portal.css` (no build step).

---

## File Structure

| File | Change |
| --- | --- |
| `backend/resources/views/livewire/districts-page.blade.php` | Drop `$moduleKpiStats` line; replace `.kpi-stats` block with `.kpi-switch`; remove `.map-legend`. |
| `backend/app/Livewire/DistrictsPage.php` | Remove `moduleKpiStats()` computed. |
| `backend/public/css/portal.css` | Shrink header/hero/module-seg; add `.kpi-switch*`; remove `.kpi-stat*` + `.map-legend*`; cap `.region-map` height; shrink `.mapstage-head` + `.districts-mapstage` padding. |

**Conventions:** Cyrillic UI (don't translate). `portal.css` hand-maintained — **no `npm run build`**. Tests share one Postgres DB — run ONLY the targeted filter. Windows PowerShell: chain with `;`; artisan from `backend/`. Single cohesive commit at the end.

---

## Task 1: View — KPI switcher, drop legend, drop `$moduleKpiStats`

**Files:**
- Modify: `backend/resources/views/livewire/districts-page.blade.php`

- [ ] **Step 1: Remove the `$moduleKpiStats` line from the top `@php` block**

Delete this line (line 4):

```php
    $moduleKpiStats        = $this->moduleKpiStats;
```

- [ ] **Step 2: Replace the `.kpi-stats` block with the thin KPI switcher**

Replace this entire block:

```blade
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
```

with:

```blade
        @if($kpiOptions->count() > 1)
            <div class="kpi-switch">
                @foreach($kpiOptions as $i)
                    <button class="kpi-switch-btn {{ $i->code === $kpi ? 'on' : '' }}"
                            wire:click="selectKpi('{{ $i->code }}')" type="button"
                            title="{{ $i->label_full }}">{{ $i->label_short }}</button>
                @endforeach
            </div>
        @endif
```

- [ ] **Step 3: Remove the legend element from the map canvas**

Find and delete this block (the first child of `.mapstage-canvas`):

```blade
            <div class="map-legend">
                <span><i class="ok"></i>Режада</span>
                <span><i class="bad"></i>Эътибор</span>
                <span><i class="nd"></i>Маълумот йўқ</span>
            </div>
```

(Leave the `<div class="mapstage-canvas" …>` opener and the `<svg …>` that follows intact.)

- [ ] **Step 4: Confirm the page still renders**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: PASS (bare-GET asserts `districts-header`/`module-seg`/`districts-mapstage`/`map-pill` — all retained; nothing asserts `kpi-stats`/`map-legend`). `$statText`/`$statUp` are still defined in the `@php` block and still used by the hero, so no undefined-variable error.

---

## Task 2: Component — remove `moduleKpiStats()`

**Files:**
- Modify: `backend/app/Livewire/DistrictsPage.php`

- [ ] **Step 1: Delete the `moduleKpiStats()` computed (with its docblock)**

Delete the docblock that begins `/**` … `Region-level value per KPI in the current module, for the header stat-cards.` …, its `#[Computed]` attribute, and the whole method:

```php
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

Leave the `use App\Support\DistrictTableConfig;` and `use App\Models\IndicatorFact;` imports — still used by `selectKpi()` and `facts()`.

- [ ] **Step 2: Confirm no remaining references**

Run: `cd backend; Select-String -Path app,resources -Pattern 'moduleKpiStats' -Recurse | Select-Object -First 5`
Expected: NO output.

- [ ] **Step 3: Run the page tests**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: PASS.

---

## Task 3: CSS — shrink header/hero, add switcher, remove stat/legend, cap map

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Slim the header + module control**

Replace:

```css
    .districts-header { background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:var(--shadow-sm); padding:6px 6px 0; margin-bottom:14px; }
    .districts-header-top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:8px 10px; }
    .module-seg { display:inline-flex; background:#f1f4f9; border-radius:11px; padding:3px; gap:2px; }
    .module-seg-btn { border:0; background:transparent; font:inherit; font-size:13px; font-weight:650; color:var(--muted); padding:8px 15px; border-radius:8px; cursor:pointer; transition:background var(--motion), color var(--motion); }
```

with:

```css
    .districts-header { background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:var(--shadow-sm); padding:4px 4px 0; margin-bottom:10px; }
    .districts-header-top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:6px 8px; }
    .module-seg { display:inline-flex; background:#f1f4f9; border-radius:10px; padding:3px; gap:2px; }
    .module-seg-btn { border:0; background:transparent; font:inherit; font-size:12.5px; font-weight:650; color:var(--muted); padding:6px 12px; border-radius:8px; cursor:pointer; transition:background var(--motion), color var(--motion); }
```

- [ ] **Step 2: Shrink the hero**

Replace:

```css
    .districts-hero { display:flex; align-items:center; gap:14px; padding:6px 12px 14px; flex-wrap:wrap; }
    .districts-hero-icon { width:48px; height:48px; border-radius:13px; flex:none; background:linear-gradient(135deg,#1769e0,#0f2d63); color:#fff; display:grid; place-items:center; }
    .districts-hero-icon svg { width:24px; height:24px; }
    .districts-hero-title h2 { margin:0; font-size:22px; letter-spacing:-.02em; }
    .districts-hero-title span { font-size:12.5px; color:var(--muted); }
    .districts-hero-value { margin-left:auto; text-align:right; }
    .districts-hero-value strong { display:block; font-size:28px; font-weight:850; letter-spacing:-.02em; line-height:1; color:var(--ink); }
```

with:

```css
    .districts-hero { display:flex; align-items:center; gap:11px; padding:2px 10px 8px; flex-wrap:wrap; }
    .districts-hero-icon { width:34px; height:34px; border-radius:10px; flex:none; background:linear-gradient(135deg,#1769e0,#0f2d63); color:#fff; display:grid; place-items:center; }
    .districts-hero-icon svg { width:18px; height:18px; }
    .districts-hero-title h2 { margin:0; font-size:17px; letter-spacing:-.02em; }
    .districts-hero-title span { font-size:11px; color:var(--muted); }
    .districts-hero-value { margin-left:auto; text-align:right; }
    .districts-hero-value strong { display:block; font-size:22px; font-weight:850; letter-spacing:-.02em; line-height:1; color:var(--ink); }
```

- [ ] **Step 3: Replace the KPI stat-card rules with the thin switcher**

Replace this block (the `.kpi-stats` … `.kpi-stat-trend.down` rules):

```css
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
```

with:

```css
    .kpi-switch { display:flex; gap:6px; flex-wrap:wrap; padding:8px 10px 10px; border-top:1px solid var(--line); }
    .kpi-switch-btn { border:1px solid var(--line); background:#fff; font:inherit; font-size:12px; font-weight:650; color:var(--muted); padding:5px 12px; border-radius:999px; cursor:pointer; transition:border-color var(--motion), background var(--motion), color var(--motion); }
    .kpi-switch-btn:hover { border-color:rgba(23,105,224,.4); }
    .kpi-switch-btn.on { background:var(--blue); border-color:var(--blue); color:#fff; }
```

- [ ] **Step 4: Remove the legend rules**

Delete these rules:

```css
    .map-legend { position: absolute; top: 12px; right: 12px; z-index: 3;
      display: inline-flex; gap: 14px; align-items: center; color: #cdd8f0; font-size: 11px; font-weight: 600; white-space: nowrap;
      background: rgba(14, 28, 63, .72); border: 1px solid rgba(255, 255, 255, .14); border-radius: 999px; padding: 6px 14px; }
    .map-legend span { display: inline-flex; align-items: center; }
    .map-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
    .map-legend i.ok { background: #37a34a; }
    .map-legend i.bad { background: #e0473a; }
    .map-legend i.nd { background: #6b748c; }
```

- [ ] **Step 5: Shrink the map stage + cap the map height**

Replace:

```css
    .districts-mapstage {
      position: relative;
      background: #0e1c3f;
      border: 1px solid #16264d;
      border-radius: 18px;
      padding: 14px 14px 18px;
      margin-bottom: 14px;
      box-shadow: 0 10px 30px rgba(20, 40, 90, .18);
    }
    .mapstage-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin: 2px 6px 8px; }
    .mapstage-head strong { color: #eaf1ff; font-size: 15px; }
    .mapstage-head span { display: block; color: #8ea0c8; font-size: 11.5px; margin-top: 2px; }
    .mapstage-canvas { position: relative; }
    .region-map { width: 100%; height: auto; display: block; }
```

with:

```css
    .districts-mapstage {
      position: relative;
      background: #0e1c3f;
      border: 1px solid #16264d;
      border-radius: 16px;
      padding: 8px 12px 10px;
      margin-bottom: 10px;
      box-shadow: 0 10px 30px rgba(20, 40, 90, .18);
    }
    .mapstage-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin: 2px 6px 6px; }
    .mapstage-head strong { color: #eaf1ff; font-size: 13.5px; }
    .mapstage-head span { display: block; color: #8ea0c8; font-size: 11px; margin-top: 1px; }
    .mapstage-canvas { position: relative; }
    .region-map { width: 100%; height: auto; display: block; margin: 0 auto; max-height: calc(100vh - 220px); }
```

- [ ] **Step 6: Verify selectors**

Run: `cd backend; Select-String -Path public/css/portal.css -Pattern '\.kpi-stat|\.map-legend' | Select-Object -First 8`
Expected: NO output.
Run: `cd backend; Select-String -Path public/css/portal.css -Pattern '\.kpi-switch|max-height: calc' | Select-Object -First 5`
Expected: matches present.

- [ ] **Step 7: Run the page tests**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: PASS.

---

## Task 4: Fit verification + commit

**Files:** none (verify + commit).

- [ ] **Step 1: Targeted suites (alone — shared DB)**

Run: `cd backend; php artisan test --filter=Districts`  → expected PASS.
Run: `cd backend; php artisan test --filter=MapLabelLayout`  → expected PASS (7, unaffected).

- [ ] **Step 2: One-screen screenshot at 1366×768 (the gate)**

Dev server on 8000 (start if needed: `cd backend; php artisan serve --host=127.0.0.1 --port=8000`). Capture at the exact target viewport:

```
& "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless=new --disable-gpu --hide-scrollbars --no-first-run --force-device-scale-factor=1 --user-data-dir="C:/Users/y.utepbergenov/AppData/Local/Temp/edge-fit" --window-size=1366,768 --screenshot="C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/.superpowers/verify-fit.png" "http://127.0.0.1:8000/districts"
```

Open `verify-fit.png`. The ENTIRE header + map must be visible with no clipping — especially the lowest perimeter pills (Булоқбоши / Шаҳрихон / Асака / Мархамат) and a small bottom margin. Confirm the legend is gone, the KPI switcher is a thin pill row, and pills still hug the map.

- [ ] **Step 3: Tune the cap if needed**

If the bottom is still clipped, lower the map cap: edit `.region-map` `max-height: calc(100vh - 220px)` → try `230px`, `240px`, re-screenshot until it fits with a small margin. If there's excessive empty space below, raise it slightly (`210px`). Re-run Step 2 after each change. (Only the `220` constant changes.)

- [ ] **Step 4: Commit**

```bash
git add backend/resources/views/livewire/districts-page.blade.php backend/app/Livewire/DistrictsPage.php backend/public/css/portal.css
git commit -m "feat(districts): compact header + map to fit one screen"
```

End the commit message with the project's `Co-Authored-By` trailer.

---

## Self-review notes

- **Spec coverage:** slim header (T3 S1-S2) · drop stat-cards → thin KPI switcher (T1 S2, T3 S3) · remove legend (T1 S3, T3 S4) · cap map height / shrink stage (T3 S5) · remove `moduleKpiStats` (T1 S1, T2) · keep all other map elements (untouched: pills/leaders/dots/peek/tooltip) · 1366×768 fit gate + tuning (T4 S2-S3). All spec sections map to a task.
- **Consistency:** new classes `.kpi-switch`/`.kpi-switch-btn(.on)` defined in markup (T1) and CSS (T3); removed `.kpi-stat*`/`.map-legend*` gone from both; `$statText`/`$statUp` remain used by the hero so the `@php` block stays valid after removing the `$moduleKpiStats` line.
- **No new tests:** this is a sizing change with no asserted-markup additions; existing feature tests (which assert retained hooks) are the regression guard, plus the screenshot gate for the visual goal.
- **Map integrity:** the cap scales the SVG uniformly — `MapLabelLayout` placement and all map interactions are unchanged.
