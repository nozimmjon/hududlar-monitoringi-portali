# Admin Panel Visual Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the road-map admin panel the approved «Ёруғ шиша» look: white sheets floating on a soft blue-grey ground, numbers at display size, and an entry cell that is the strongest element on the screen.

**Architecture:** Pure presentation work inside the panel. All tokens live in one new block of the hand-maintained `backend/public/css/portal.css`, scoped to `.adm-body` so the public portal cannot be touched. The entry grid stops being a `<table>` and becomes one sheet per section, keeping every `data-*` attribute so the Alpine controller in `backend/public/js/ad-entry.js` only changes selectors. One new support class computes the overdue count that the region list sorts by; it reads measures already loaded by `ProgressSheet::build()`, so no extra queries and no migration.

**Tech Stack:** Laravel 12, Livewire 4.4 with bundled Alpine 3, Blade, hand-written CSS (no build step), Pest 3 on PostgreSQL, PHP 8.2.

**Spec:** `docs/superpowers/specs/2026-10-02-admin-panel-visual-design.md` — read §3 and §4 for the token values before starting. The approved mockup is at https://claude.ai/code/artifact/72d28416-0265-437b-831f-c37733bd523a (Вариант 2).

---

## Conventions for every task

- Work in `backend/`. Run tests on an isolated database so a parallel run cannot deadlock:

  ```bash
  PGPASSWORD=123 createdb -h 127.0.0.1 -U postgres hududlar_rd_<N>
  cd backend && DB_DATABASE=hududlar_rd_<N> php artisan test --filter=<Filter>
  PGPASSWORD=123 dropdb -h 127.0.0.1 -U postgres hududlar_rd_<N>
  ```

  In PowerShell use `$env:DB_DATABASE='hududlar_rd_<N>'` instead of the inline prefix. Never touch the dev database, `.env` or `storage/`.
- `backend/public/css/portal.css` is hand-maintained and dense (one rule per line). Match that style. There is no `npm run build`.
- UI strings stay Cyrillic Uzbek. PHP is 8.2, there is no Pint, so keep the existing manual alignment.
- Commit with an explicit pathspec (`git commit -F <msgfile> -- <paths>`) and never stage `backend/composer.lock` or `.claude/worktrees/sector-logos`.
- Conventional Commits, and end every message with:

  ```
  Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
  ```

---

## File structure

| File | Responsibility after this plan |
| --- | --- |
| `backend/public/css/portal.css` | Adds one `.adm-body` token block; the `ad-` primitives, entry-grid, upload and shell blocks are rewritten in place. Nothing outside the admin blocks changes. |
| `backend/resources/views/layouts/admin.blade.php` | Loads Manrope alongside Inter. |
| `backend/app/Support/Roadmaps/RoadmapOverdue.php` | **New.** One function: how many measures of a sheet are past their deadline and not done. |
| `backend/app/Livewire/Admin/RoadmapOverview.php` | Adds the overdue count per card, the four totals, the attention split and the sort. |
| `backend/resources/views/livewire/admin/roadmap-overview.blade.php` | KPI tiles, two card groups, restyled card body. |
| `backend/app/Livewire/Admin/RoadmapEntry.php` | Adds the region's overdue count to the view data. |
| `backend/resources/views/livewire/admin/roadmap-entry.blade.php` | Header with the big ring; the table becomes sheets of measures. |
| `backend/public/js/ad-entry.js` | Selector-level changes only: `.ad-m` instead of `tbody.ad-m`, `measureOf()`, sheet-aware `showGroup()`. |
| `backend/resources/views/livewire/admin/partials/correction-panel.blade.php`, `partials/history-drawer.blade.php`, `livewire/admin/progress-upload.blade.php`, `auth/login.blade.php` | Restyled to the same tokens. |
| `backend/tests/Feature/Admin/*` | Markup assertions updated; new tests for the overdue count and the card order. |
| `backend/docs/roadmap-monitoring.md`, `CLAUDE.md` | Describe the new look where they describe the panel. |

---

## Task 1: Tokens, fonts and shared primitives

**Files:**
- Modify: `backend/resources/views/layouts/admin.blade.php` (the Google Fonts `<link>`)
- Modify: `backend/public/css/portal.css` (the block starting `/* ===================== admin panel: shared primitives ===================== */` and the `.adm-body` rule in the shell block)

- [ ] **Step 1: Load Manrope in the admin layout**

In `backend/resources/views/layouts/admin.blade.php`, replace the fonts stylesheet line with:

```html
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Manrope:wght@600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
```

- [ ] **Step 2: Add the token block**

In `backend/public/css/portal.css`, find `.adm-body{display:block;min-height:100vh}` in the shell block and replace that single line with:

```css
.adm-body{display:block;min-height:100vh;
  background:
    radial-gradient(760px 300px at 85% -10%, rgba(23,105,224,.10), transparent 60%),
    radial-gradient(620px 260px at 5% 0%, rgba(99,179,237,.12), transparent 60%),
    linear-gradient(180deg,#FBFDFF,#EEF3FA);
  background-attachment:fixed;
  --ad-sheet:rgba(255,255,255,.92);
  --ad-sheet-line:rgba(14,28,50,.07);
  --ad-sheet-shadow:0 2px 4px rgba(12,26,48,.04), 0 20px 46px rgba(12,26,48,.10);
  --ad-card-shadow:0 2px 4px rgba(12,26,48,.04), 0 16px 36px rgba(12,26,48,.09);
  --ad-ctl-shadow:0 8px 22px rgba(12,26,48,.08);
  --ad-accent:#1769E0;
  --ad-accent-grad:linear-gradient(135deg,#1769E0,#3E8BFF);
  --ad-accent-glow:0 8px 20px rgba(23,105,224,.30);
  --ad-focus:0 0 0 4px rgba(23,105,224,.14);
  --ad-pos-grad:linear-gradient(135deg,#0F9D63,#2FBE85);
  --ad-warn-ink:#8A5000;
  --ad-warn-bg:linear-gradient(135deg,#FFF4E3,#FFE9CC);
  --ad-carry:#F0B860;
  --ad-chip-bg:#F2F6FB;
  --ad-chip-ink:#64748B;
  --ad-ink:#0B1424;
  --ad-muted:#5A6B82;
  --ad-faint:#8A99AC;
  --ad-hair:#F0F4F9;
  --ad-hair-strong:#E8EEF6;
  --ad-track:#E7EDF5;
  --font-num:'Manrope','Inter',system-ui,sans-serif}
```

- [ ] **Step 3: Rewrite the shared primitives**

Replace everything from `/* ===================== admin panel: shared primitives ===================== */` down to (but not including) `/* overview cards */` with:

```css
/* ===================== admin panel: shared primitives ===================== */
.ad-shell{display:flex;flex-direction:column;gap:18px;min-width:0;color:var(--ad-ink)}
.ad-shell .tnum,.ad-num{font-variant-numeric:tabular-nums}
.ad-num{font-family:var(--font-num);letter-spacing:-.03em;font-weight:800}
.ad-head{display:flex;flex-direction:column;gap:12px}
.ad-head-row{display:flex;flex-wrap:wrap;align-items:center;gap:14px}
.ad-titles{flex:1;min-width:240px}
.ad-title{font-family:var(--font-num);font-size:27px;font-weight:800;letter-spacing:-.03em;margin:0;color:var(--ad-ink)}
.ad-title .rg{font-weight:700;color:var(--ad-muted)}
.ad-sub{margin:4px 0 0;font-size:12.5px;color:var(--ad-muted)}
.ad-sub .over{color:var(--ad-warn-ink);font-weight:700}
.ad-select{display:inline-flex;align-items:center;gap:9px;background:rgba(255,255,255,.9);border:1px solid rgba(14,28,50,.08);border-radius:13px;padding:8px 10px 8px 15px;box-shadow:var(--ad-ctl-shadow)}
.ad-select span{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--ad-faint)}
.ad-select select{border:0;background:transparent;font:inherit;font-size:13px;font-weight:700;color:var(--ad-ink);outline:none;max-width:220px;cursor:pointer}
.ad-btn{border:0;border-radius:999px;padding:9px 16px;font:inherit;font-size:12.5px;font-weight:750;cursor:pointer;color:#fff;background:var(--ad-accent);transition:filter .15s,transform .15s,box-shadow .15s;white-space:nowrap}
.ad-btn:hover{filter:brightness(1.06)}
.ad-btn:disabled{opacity:.45;cursor:default;filter:none;box-shadow:none}
.ad-btn.primary{background:var(--ad-accent-grad);box-shadow:var(--ad-accent-glow)}
.ad-btn.primary:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 12px 26px rgba(23,105,224,.38)}
.ad-btn.ghost{background:rgba(255,255,255,.9);color:var(--ad-accent);border:1px solid rgba(14,28,50,.08);box-shadow:0 2px 8px rgba(12,26,48,.05)}
.ad-btn.ghost:hover:not(:disabled){background:#fff;box-shadow:var(--ad-ctl-shadow)}
.ad-btn.danger{background:var(--task-red)}
.ad-btn.small{padding:6px 13px;font-size:11.5px}
.ad-ico{width:30px;height:30px;border:1px solid rgba(14,28,50,.08);border-radius:10px;background:rgba(255,255,255,.9);color:var(--ad-muted);font-size:13px;line-height:1;display:inline-grid;place-items:center;cursor:pointer;transition:color .15s,border-color .15s,box-shadow .15s}
.ad-ico:hover{color:var(--ad-accent);border-color:var(--ad-accent);box-shadow:0 4px 12px rgba(23,105,224,.18)}
/* red at rest, not only on hover: in the grey of .ad-ico the bin reads as a disabled button */
.ad-ico.danger{color:var(--task-red)}
.ad-ico.danger:hover{color:var(--task-red);border-color:var(--task-red);box-shadow:0 4px 12px rgba(192,57,43,.18)}
.ad-ico:disabled{opacity:.35;cursor:default;box-shadow:none}
/* an inline button that reads as a link (empty-filter row) */
.ad-link{border:0;background:none;padding:0;font:inherit;font-weight:800;color:var(--ad-accent);cursor:pointer;text-decoration:underline;text-underline-offset:2px}
.ad-link:hover{filter:brightness(1.15)}
.ad-chip{font-size:10.5px;font-weight:700;border-radius:9px;padding:4px 9px;background:var(--ad-chip-bg);color:var(--ad-chip-ink);white-space:nowrap}
.ad-chip.warn{background:var(--ad-warn-bg);color:var(--ad-warn-ink)}
.ad-chip.edit{background:linear-gradient(135deg,#E7F1FE,#D9E9FE);color:#0F4FA8}
.ad-chip.ok{background:linear-gradient(135deg,#E8F6EF,#D9F0E4);color:#0F7A4F}
.ad-sheet{background:var(--ad-sheet);border:1px solid var(--ad-sheet-line);border-radius:20px;box-shadow:var(--ad-sheet-shadow);overflow:hidden}
.ad-strip{display:flex;flex-wrap:wrap;gap:14px;font-size:12.5px;color:var(--ad-muted)}
.ad-strip .k{display:inline-flex;align-items:center;gap:6px}
.ad-strip .k i{width:9px;height:9px;border-radius:50%;background:var(--ad-track)}
.ad-strip .k.saved i{background:var(--ad-accent)}
.ad-strip .k.carry i{background:var(--ad-carry)}
.ad-strip .k.empty i{background:var(--ad-track)}
.ad-strip b{color:var(--ad-ink);font-family:var(--font-num)}
```

- [ ] **Step 4: Check nothing outside the panel moved**

Run:

```bash
PGPASSWORD=123 createdb -h 127.0.0.1 -U postgres hududlar_rd_1
cd backend && DB_DATABASE=hududlar_rd_1 php artisan test --filter="Admin|Http"
```

Expected: everything passes. The old classes still exist, so only colours changed.

- [ ] **Step 5: Commit**

```bash
git commit -F msg.txt -- backend/public/css/portal.css backend/resources/views/layouts/admin.blade.php
```

Message: `feat(admin): light-glass tokens, Manrope numerals and restyled panel primitives`

---

## Task 2: The overdue count

**Files:**
- Create: `backend/app/Support/Roadmaps/RoadmapOverdue.php`
- Test: `backend/tests/Unit/Roadmaps/RoadmapOverdueTest.php`

The rule must agree with the red deadline chip the grid already shows, which is
`MeasureDisplay::deadlineChip()` returning `cls => 'over'`: the deadline month is earlier than
the current month **and** the measure is not done.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Roadmaps/RoadmapOverdueTest.php`:

```php
<?php

use App\Support\Roadmaps\MeasureDisplay;
use App\Support\Roadmaps\RoadmapOverdue;

$m = fn (string $deadline, string $status) => ['deadline_text' => $deadline, 'status' => $status];

test('counts measures whose deadline month has passed and which are not done', function () use ($m) {
    $measures = [
        $m('2026 йил март', 'in_progress'),      // past, open      → counted
        $m('2026 йил март', 'open'),             // past, open      → counted
        $m('2026 йил март', 'done'),             // past but done   → not counted
        $m('2026 йил декабрь', 'in_progress'),   // future          → not counted
        $m('2026 йил октябрь', 'in_progress'),   // this month      → not counted
    ];

    expect(RoadmapOverdue::count($measures, 2026, '2026-10'))->toBe(2);
});

test('unreadable deadlines fall back to year-end, so they are never overdue mid-year', function () use ($m) {
    expect(RoadmapOverdue::count([$m('муддат кўрсатилмаган', 'in_progress')], 2026, '2026-10'))->toBe(0);
});

test('an empty sheet counts zero', function () {
    expect(RoadmapOverdue::count([], 2026, '2026-10'))->toBe(0);
});

test('the rule agrees with the red deadline chip', function () use ($m) {
    foreach ([['2026 йил март', 'in_progress'], ['2026 йил март', 'done'], ['2026 йил декабрь', 'open']] as [$d, $s]) {
        $chipSaysOver = MeasureDisplay::deadlineChip($d, 2026, $s, '2026-10')['cls'] === 'over';
        expect(RoadmapOverdue::count([$m($d, $s)], 2026, '2026-10') === 1)->toBe($chipSaysOver);
    }
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
cd backend && DB_DATABASE=hududlar_rd_2 php artisan test --filter=RoadmapOverdueTest
```

Expected: `Class "App\Support\Roadmaps\RoadmapOverdue" not found`.

- [ ] **Step 3: Write the class**

Create `backend/app/Support/Roadmaps/RoadmapOverdue.php`:

```php
<?php

namespace App\Support\Roadmaps;

/**
 * How many measures of a sheet are late: the deadline month the document names has already
 * passed and the measure is not done. Same rule as the red «over» deadline chip in
 * MeasureDisplay::deadlineChip(), so the card count and the grid chips can never disagree.
 *
 * Deadlines are never stored — RoadmapDeadline parses «2026 йил декабрь» on the way past —
 * so this reads the measures ProgressSheet::build() already loaded and costs no query.
 */
final class RoadmapOverdue
{
    /**
     * @param  iterable<array{deadline_text?: ?string, status?: string}> $measures rows of ProgressSheet::build()['measures']
     * @param  int    $year  the road map's year, for deadlines written without one
     * @param  string $month 'YYYY-MM' to judge against, normally ProgressSheet::currentPeriod()
     */
    public static function count(iterable $measures, int $year, string $month): int
    {
        $n = 0;
        foreach ($measures as $m) {
            if (($m['status'] ?? 'in_progress') === 'done') {
                continue;
            }
            if (RoadmapDeadline::monthsLeft($m['deadline_text'] ?? null, $year, $month) < 0) {
                $n++;
            }
        }

        return $n;
    }
}
```

- [ ] **Step 4: Run it and watch it pass**

```bash
cd backend && DB_DATABASE=hududlar_rd_2 php artisan test --filter=RoadmapOverdueTest
```

Expected: 4 passed.

- [ ] **Step 5: Commit**

```bash
git commit -F msg.txt -- backend/app/Support/Roadmaps/RoadmapOverdue.php backend/tests/Unit/Roadmaps/RoadmapOverdueTest.php
```

Message: `feat(admin): count overdue measures with the same rule as the red deadline chip`

---

## Task 3: Region list — data, KPI tiles, attention split

**Files:**
- Modify: `backend/app/Livewire/Admin/RoadmapOverview.php`
- Test: `backend/tests/Feature/Admin/RoadmapOverviewTest.php`

- [ ] **Step 1: Add a bare second road map to the fixture**

`AdminFixture::roadmap()` builds the Хоразм workbook and imports it, which is too heavy for a
second region. Add this helper to `backend/tests/Helpers/AdminFixture.php`, after `roadmap()`. The file
already imports `Roadmap` and `RoadmapMeasure`, so no new `use` line is needed:

```php
    /**
     * A road map with no workbook behind it: just the row and the measures a test needs, so a
     * second region costs no import. $measures are [seq_no, title, deadline_text, status].
     *
     * @param list<array{int, string, string, string}> $measures
     */
    public static function bareRoadmap(int $regionCode, array $measures, int $year = 2026): Roadmap
    {
        $roadmap = Roadmap::create([
            'domain'      => 'water',
            'region_code' => $regionCode,
            'year'        => $year,
            'source_file' => 'test',
            'imported_at' => now(),
        ]);

        foreach ($measures as $i => [$seq, $title, $deadline, $status]) {
            RoadmapMeasure::create([
                'roadmap_id'    => $roadmap->id,
                'section_no'    => 1,
                'section_title' => 'I. Вилоятда амалга ошириладиган йирик лойиҳалар',
                'seq_no'        => $seq,
                'title'         => $title,
                'deadline_text' => $deadline,
                'source_row'    => $i + 1,
                'status'        => $status,
                'lines_total'   => 0,
                'lines_done'    => 0,
            ]);
        }

        return $roadmap;
    }
```

- [ ] **Step 2: Write the failing test**

Append to `backend/tests/Feature/Admin/RoadmapOverviewTest.php`, next to the existing tests
(they seed with `$this->seed()` and sign in with `AdminFixture::user()`):

```php
test('cards carry the overdue count, and the regions that need attention come first', function () {
    $this->seed();
    AdminFixture::roadmap();                                   // Хоразм 1733, four measures, none overdue
    AdminFixture::bareRoadmap(1703, [                          // Андижон: one measure past its deadline
        [1, 'Андижон чора-тадбири', '2026 йил март', 'in_progress'],
        [2, 'Иккинчи чора-тадбир', '2026 йил декабрь', 'in_progress'],
    ]);
    $this->actingAs(AdminFixture::user());

    $html = Livewire::test(RoadmapOverview::class)->assertOk()->html();

    expect($html)->toContain('Диққат талаб қилади')
        ->and(strpos($html, 'Андижон вилояти'))->toBeLessThan(strpos($html, 'Хоразм вилояти'));

    Livewire::test(RoadmapOverview::class)
        ->assertSeeHtml('data-region="1703" data-saved="0" data-carry="0" data-empty="2" data-overdue="1"')
        ->assertSeeHtml('data-overdue="0"')
        ->assertSee('1 кечиккан')
        ->assertSee('Кечиккан');                                // the KPI tile
});
```

The test judges «кечиккан» against the real current month, so it only holds while the current
month is after March 2026. That is true for the life of this road-map year; if the suite is
ever run against an earlier month, change the past deadline to one month before
`ProgressSheet::currentPeriod()`.

- [ ] **Step 3: Run it and watch it fail**

```bash
cd backend && DB_DATABASE=hududlar_rd_3 php artisan test --filter=RoadmapOverviewTest
```

Expected: fails on the missing `data-overdue` attribute.

- [ ] **Step 4: Compute the new numbers in the component**

In `backend/app/Livewire/Admin/RoadmapOverview.php`, add `use App\Support\Roadmaps\RoadmapOverdue;`
and replace the body of the `foreach` over regions plus the `return` with:

```php
        $cards    = [];
        $totals   = ['saved' => 0, 'carry' => 0, 'empty' => 0, 'measures' => 0, 'lines' => 0, 'overdue' => 0, 'untouched' => 0];
        $today    = ProgressSheet::currentPeriod();
        // Only regions that actually have a road map: a card with nothing to enter is noise
        // (Тошкент шаҳри has no file), and the entry page's region select is built the same way.
        foreach (Region::whereIn('code', $roadmaps->keys())->orderBy('sort_order')->get() as $region) {
            $roadmap  = $roadmaps->get($region->code);
            $sheet    = ProgressSheet::build($roadmap, $this->period);
            $statuses = ['done' => 0, 'in_progress' => 0, 'open' => 0];
            $lines    = 0;
            foreach ($sheet['measures'] as $m) {
                $statuses[$m['status']]++;
                $lines += count($m['lines']);
            }
            $last    = $lastChanges->get($roadmap->id);
            $overdue = RoadmapOverdue::count($sheet['measures'], $roadmap->year, $today);
            $cards[] = [
                'region'    => $region,
                'roadmap'   => $roadmap,
                'measures'  => count($sheet['measures']),
                'lines'     => $lines,
                'counts'    => $sheet['counts'],
                'statuses'  => $statuses,
                'last'      => $last,
                'overdue'   => $overdue,
                'idle_days' => $last ? $last->created_at->diffInDays(now(ProgressSheet::TZ)) : null,
                // a region with nothing saved this month needs attention as much as a late one
                'attention' => $overdue > 0 || $sheet['counts']['saved'] === 0,
            ];
            foreach (['saved', 'carry', 'empty'] as $k) {
                $totals[$k] += $sheet['counts'][$k];
            }
            $totals['measures']  += count($sheet['measures']);
            $totals['lines']     += $lines;
            $totals['overdue']   += $overdue;
            $totals['untouched'] += $last ? 0 : 1;
        }

        // most overdue first, then the least filled; regions tie-break on the portal's own order
        usort($cards, fn ($a, $b) => [$b['overdue'], $a['counts']['saved'] / max(1, $a['measures'])]
                                 <=> [$a['overdue'], $b['counts']['saved'] / max(1, $b['measures'])]);

        return view('livewire.admin.roadmap-overview', [
            'cards'       => $cards,
            'attention'   => array_values(array_filter($cards, fn ($c) => $c['attention'])),
            'rest'        => array_values(array_filter($cards, fn ($c) => ! $c['attention'])),
            'totals'      => $totals,
            'periods'     => ProgressSheet::periodOptions(RoadmapsPage::YEAR),
            'periodLabel' => RoadmapPeriod::label($this->period),
        ]);
```

PHP's sorts have been stable since 8.0, so cards that tie on both keys keep the order the
query gave them, which is the portal's own `sort_order`.

- [ ] **Step 5: Render the tiles and the two groups**

In `backend/resources/views/livewire/admin/roadmap-overview.blade.php`, replace the
`<header class="ad-head">…</header>` block and the `<div class="ad-cards">…</div>` loop with:

```blade
  <header class="ad-head">
    <div class="ad-head-row">
      <div class="ad-titles">
        <h2 class="ad-title">Йўл хариталари · мониторинг</h2>
        <p class="ad-sub">{{ $periodLabel }} · {{ $totals['measures'] }} та чора-тадбир · {{ $totals['lines'] }} та индикатор</p>
      </div>
      <label class="ad-select">
        <span>Ой</span>
        <select wire:change="selectPeriod($event.target.value)" aria-label="Ҳисобот ойи">
          @foreach($periods as $p)
            <option value="{{ $p }}" @selected($p === $period)>{{ RoadmapPeriod::label($p) }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <div class="ad-kpis">
      <div class="ad-kpi">
        <span class="k">Тўлдирилди</span>
        <b class="v ad-num">{{ $totals['saved'] }}</b>
        <span class="d">{{ $totals['measures'] }} тадан · {{ $totals['measures'] > 0 ? round($totals['saved'] / $totals['measures'] * 100, 1) : 0 }}%</span>
      </div>
      <div class="ad-kpi">
        <span class="k">Ўтган ойдан</span>
        <b class="v ad-num">{{ $totals['carry'] }}</b>
        <span class="d">тасдиқлашни кутмоқда</span>
      </div>
      <div class="ad-kpi {{ $totals['overdue'] > 0 ? 'warn' : '' }}">
        <span class="k">Кечиккан</span>
        <b class="v ad-num">{{ $totals['overdue'] }}</b>
        <span class="d">муддати ўтган, бажарилмаган</span>
      </div>
      <div class="ad-kpi">
        <span class="k">Тегилмаган вилоят</span>
        <b class="v ad-num">{{ $totals['untouched'] }}</b>
        <span class="d">ҳали ўзгариш йўқ</span>
      </div>
    </div>
  </header>

  @foreach([['Диққат талаб қилади', $attention], ['Қолганлари', $rest]] as [$heading, $group])
    @if($group !== [])
      <section class="ad-group-head" wire:key="grp-{{ $loop->index }}">
        <h3>{{ $heading }}</h3>
        <span class="rule"></span>
        <span class="n tnum">{{ count($group) }}</span>
      </section>
      <div class="ad-cards">
        @foreach($group as $c)
          @include('livewire.admin.partials.region-card', ['c' => $c])
        @endforeach
      </div>
    @endif
  @endforeach
```

- [ ] **Step 6: Move the card body into a partial**

Create `backend/resources/views/livewire/admin/partials/region-card.blade.php` with the card
markup, now carrying the overdue data attribute and the new structure:

```blade
@php
    use App\Services\Roadmaps\ProgressSheet;
    $ct  = $c['counts'];
    $n   = max(1, $c['measures']);
    $pct = (int) round($ct['saved'] / $n * 100);
@endphp
<article class="ad-card" wire:key="ad-card-{{ $c['region']->code }}"
         data-region="{{ $c['region']->code }}" data-saved="{{ $ct['saved'] }}" data-carry="{{ $ct['carry'] }}" data-empty="{{ $ct['empty'] }}" data-overdue="{{ $c['overdue'] }}"
         title="Карточкани босинг — киритиш саҳифаси. Ҳолатлар ҳар бир чора-тадбирнинг сўнгги ҳисоботи бўйича.">
  <a class="stretch" href="{{ route('admin.roadmaps.entry', ['code' => $c['region']->code, 'davr' => $period]) }}" aria-label="{{ $c['region']->name_full }} — киритиш"></a>
  <div class="top">
    <div class="gauge" style="background:conic-gradient(from 180deg, var(--ad-accent) 0 {{ $pct }}%, var(--ad-track) {{ $pct }}% 100%)" aria-hidden="true">
      <i class="ad-num">{{ $pct }}%</i>
    </div>
    <div class="who">
      <h3>{{ $c['region']->name_full }}</h3>
      <span class="n tnum">{{ $ct['saved'] }} / {{ $c['measures'] }} тўлдирилди · {{ $c['lines'] }} та индикатор</span>
    </div>
  </div>
  <div class="bar" aria-hidden="true">
    <i class="saved" style="width:{{ round($ct['saved'] / $n * 100, 1) }}%"></i><i class="carry" style="width:{{ round($ct['carry'] / $n * 100, 1) }}%"></i>
  </div>
  <div class="foot">
    @if($c['overdue'] > 0)
      <span class="ad-chip warn tnum">{{ $c['overdue'] }} кечиккан</span>
    @elseif($c['idle_days'] === null)
      <span class="ad-chip">ҳали ўзгариш йўқ</span>
    @else
      <span class="ad-chip tnum">{{ $c['idle_days'] }} кун ўзгариш йўқ</span>
    @endif
    <span class="last">
      @if($c['last'])
        {{ $c['last']->user?->name ?? 'терминал' }} · {{ $c['last']->created_at->timezone(ProgressSheet::TZ)->format('d.m.Y H:i') }}
      @endif
    </span>
    <button type="button" class="ad-btn ghost small" title="Тўлдирилган шаблонни юклаш"
            x-on:click="$dispatch('open-upload', { code: {{ $c['region']->code }} })">Excel’дан юклаш</button>
  </div>
</article>
```

- [ ] **Step 7: Run the test and watch it pass**

```bash
cd backend && DB_DATABASE=hududlar_rd_3 php artisan test --filter=RoadmapOverviewTest
```

Expected: all green. The status-pill assertions from the old test will fail here, because the
pills move out of the card. Update those assertions to the new markup in the same run; do not
delete the test.

- [ ] **Step 8: Commit**

```bash
git commit -F msg.txt -- backend/app/Livewire/Admin/RoadmapOverview.php backend/resources/views/livewire/admin/roadmap-overview.blade.php backend/resources/views/livewire/admin/partials/region-card.blade.php backend/tests/Feature/Admin/RoadmapOverviewTest.php
```

Message: `feat(admin): region list ranks by overdue, adds KPI tiles and an attention group`

---

## Task 4: Region list styling

**Files:**
- Modify: `backend/public/css/portal.css` (the `/* overview cards */` block)

- [ ] **Step 1: Replace the overview card styles**

Replace everything from `/* overview cards */` down to (but not including)
`/* ===================== admin panel: entry grid ===================== */` with:

```css
/* overview cards */
.ad-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(190px,100%),1fr));gap:12px}
.ad-kpi{background:var(--ad-sheet);border:1px solid var(--ad-sheet-line);border-radius:18px;box-shadow:var(--ad-card-shadow);padding:14px 16px 15px;display:flex;flex-direction:column;gap:3px}
.ad-kpi .k{font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--ad-faint)}
.ad-kpi .v{font-size:30px;line-height:1.05;color:var(--ad-ink)}
.ad-kpi .d{font-size:11.5px;color:var(--ad-muted)}
.ad-kpi.warn .v{color:var(--ad-warn-ink)}
.ad-group-head{display:flex;align-items:center;gap:12px;margin:4px 0 -6px}
.ad-group-head h3{margin:0;font-size:11px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;color:var(--ad-faint)}
.ad-group-head .rule{flex:1;height:1px;background:linear-gradient(90deg,rgba(14,28,50,.12),transparent)}
.ad-group-head .n{font-size:11px;font-weight:800;color:var(--ad-muted)}
.ad-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(320px,100%),1fr));gap:14px}
.ad-card{position:relative;background:var(--ad-sheet);border:1px solid var(--ad-sheet-line);border-radius:18px;box-shadow:var(--ad-card-shadow);padding:16px 17px 14px;display:flex;flex-direction:column;gap:13px;transition:transform .18s ease,box-shadow .18s ease}
.ad-card:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(12,26,48,.05), 0 22px 44px rgba(12,26,48,.14)}
.ad-card .stretch{position:absolute;inset:0;border-radius:18px;z-index:0}
.ad-card .stretch:focus-visible{outline:2px solid var(--ad-accent);outline-offset:2px}
.ad-card > *:not(.stretch){position:relative;z-index:1;pointer-events:none}
.ad-card .foot button{pointer-events:auto}
.ad-card .top{display:flex;align-items:center;gap:14px}
.ad-card .gauge{width:56px;height:56px;border-radius:50%;flex:none;display:grid;place-items:center}
.ad-card .gauge i{width:42px;height:42px;border-radius:50%;background:#fff;display:grid;place-items:center;font-style:normal;font-size:12px;box-shadow:inset 0 1px 2px rgba(12,26,48,.05)}
.ad-card .who{min-width:0}
.ad-card h3{margin:0;font-size:14.5px;font-weight:800;letter-spacing:-.015em;color:var(--ad-ink)}
.ad-card .who .n{display:block;margin-top:3px;font-size:11.5px;color:var(--ad-muted)}
.ad-card .bar{display:flex;height:6px;border-radius:99px;overflow:hidden;background:var(--ad-track)}
.ad-card .bar i{display:block;height:100%}
.ad-card .bar .saved{background:var(--ad-accent-grad)}
.ad-card .bar .carry{background:var(--ad-carry)}
.ad-card .foot{display:flex;align-items:center;gap:9px;font-size:11.5px;color:var(--ad-muted)}
.ad-card .foot .last{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
```

- [ ] **Step 2: Look at it**

Start the dev server if it is not already running (`php artisan serve`), sign in, open
`http://localhost:8000/admin/roadmaps`, and check: four tiles, «Диққат талаб қилади» first,
gauges filled to the right percentage, the card lifting on hover, and the Excel button still
clickable inside the stretched link.

- [ ] **Step 3: Commit**

```bash
git commit -F msg.txt -- backend/public/css/portal.css
```

Message: `feat(admin): region cards and KPI tiles in the light-glass language`

---

## Task 5: Entry grid markup — sheets instead of a table

**Files:**
- Modify: `backend/resources/views/livewire/admin/roadmap-entry.blade.php`
- Modify: `backend/public/js/ad-entry.js`
- Test: `backend/tests/Feature/Admin/RoadmapEntryTest.php`

This is the one risky task. Every `data-*` attribute must survive untouched: the Alpine
controller finds measures by `data-measure`, inputs by `data-line`/`data-field`, and filters by
`data-state`, `data-deadline` and `data-search`.

- [ ] **Step 1: Replace the table with sheets**

In `backend/resources/views/livewire/admin/roadmap-entry.blade.php`, replace everything from
`<div class="ad-gridwrap">` to the closing `</div>` of that wrapper (the whole `<table>`) with:

```blade
  @foreach($groups as $g)
    <section class="ad-sheet ad-sec" wire:key="g-{{ $g['key'] }}" x-show="showGroup($el)">
      <header class="ad-sec-head">
        <div class="t">
          <span class="rn">{{ $g['roman'] }}</span>
          <span class="st">{{ $g['section_title'] }}</span>
          @if($g['district'])
            <span class="dn">{{ $g['district'] }}</span>
            @if($g['head'])<span class="hd">{{ $g['head'] }}</span>@endif
          @endif
        </div>
        <span class="cnt tnum">{{ count($g['measures']) }} та чора-тадбир</span>
        <span class="cols"><span>Режа</span><span>Амалда</span><span>%</span></span>
      </header>

      @foreach($g['measures'] as $m)
        @php
          $pctShown  = MeasureDisplay::pshow($m['pct'], $m['status'] === 'done');
          $deadline  = MeasureDisplay::deadlineChip($m['deadline_text'], $roadmap->year, $m['status'], $today);
          $search    = mb_strtolower(implode(' ', array_merge([$m['title'], $g['section_title'], (string) $g['district']], $m['details'], array_column($m['lines'], 'label'))));
          $panelOpen = $panelFor === $m['id'];
        @endphp
        <article class="ad-m" wire:key="m-{{ $m['id'] }}" data-measure="{{ $m['id'] }}" data-state="{{ $m['state'] }}" data-deadline="{{ $m['deadline_reached'] ? 1 : 0 }}"
                 data-search="{{ $search }}" :class="'st-' + statusClass({{ $m['id'] }})" x-show="show($el)" x-data="{ det: false }">
          <div class="ad-mrow">
            <span class="no ad-num" :class="mstate({{ $m['id'] }}) !== 'empty' && 'on'">{{ $m['seq_no'] }}</span>
            <div class="ttl">
              <div class="t">{{ $m['title'] }}</div>
              <div class="meta">
                <span class="due">муддат: {{ $deadline['label'] }}</span>
                @if($deadline['cls'] === 'over')<span class="ad-chip warn">кечиккан</span>@endif
                <span class="ad-chip" :class="mstateChip({{ $m['id'] }})" x-show="mstate({{ $m['id'] }}) !== 'empty'" x-text="mstateLabel({{ $m['id'] }})" x-cloak>{{ $stateLabels[$m['state']] }}</span>
              </div>
              @if($m['details'] !== [])
                <button type="button" class="wr-more" x-on:click="det = !det" :aria-expanded="det">
                  <span class="c" :class="det && 'open'">▸</span>
                  <span x-text="det ? 'Ёпиш' : 'Батафсил ({{ count($m['details']) }} банд)'">Батафсил ({{ count($m['details']) }} банд)</span>
                </button>
                <div class="wr-details" x-show="det" x-cloak>@foreach($m['details'] as $line)<p>{{ $line }}</p>@endforeach</div>
              @endif
            </div>
            <span class="ad-tools">
              <button type="button" class="ad-ico" title="Индикаторларни тузатиш" aria-label="Тузатиш" wire:click="openPanel({{ $m['id'] }})">✎</button>
              <button type="button" class="ad-ico" title="Ўзгаришлар тарихи" aria-label="Тарих" wire:click="openHistory({{ $m['id'] }})">🕓</button>
            </span>
          </div>

          @forelse($m['lines'] as $l)
            @php $pct = $l['plan'] !== null && $l['plan'] != 0.0 && $l['actual'] !== null ? $l['actual'] / $l['plan'] * 100 : null; @endphp
            <div class="ad-lrow" wire:key="l-{{ $l['id'] }}">
              <div class="c-label">{{ $l['label'] }}</div>
              <div class="c-unit">{{ $l['unit'] }}</div>
              <div class="c-plan ad-num">{{ MeasureDisplay::fmt($l['plan']) }}</div>
              <div class="c-actual">
                <div class="ad-cell actual {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'actual')" wire:key="ac-{{ $l['id'] }}-{{ $version }}"
                     @if($l['state'] === 'carry') title="{{ RoadmapPeriod::label($l['source_period']) }}дан кўчирилган" @endif>
                  <input type="text" inputmode="decimal" class="ad-num" value="{{ EntryValue::format($l['actual']) }}" placeholder="—"
                         data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="actual" data-plan="{{ $l['plan'] ?? '' }}" data-loaded="{{ $l['actual'] ?? '' }}" data-loaded-text="{{ EntryValue::format($l['actual']) }}" data-state="{{ $l['state'] }}"
                         x-on:input="onInput($event)" x-on:keydown="cellKey($event)" x-on:blur="onBlur($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — амалда">
                  <span class="msg" x-text="invalid[{{ $l['id'] }}] || ''"></span>
                </div>
              </div>
              <div class="c-pct ad-num"><span :class="pctClass({{ $l['id'] }})" x-text="pctText({{ $l['id'] }})">{{ $pct === null ? '—' : round($pct) . '%' }}</span></div>
              <div class="c-note">
                <div class="ad-cell note {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'note')" wire:key="nc-{{ $l['id'] }}-{{ $version }}">
                  <input type="text" maxlength="500" value="{{ $l['note'] }}" placeholder="Изоҳ…"
                         data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="note" data-loaded-text="{{ $l['note'] }}"
                         x-on:input="onInput($event)" x-on:keydown="cellKey($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — изоҳ">
                </div>
              </div>
            </div>
          @empty
            <div class="ad-lrow none">Индикаторлар белгиланмаган — ✎ орқали қўшинг</div>
          @endforelse

          @if($panelOpen)
            <div class="ad-prow" wire:key="p-{{ $m['id'] }}">@include('livewire.admin.partials.correction-panel')</div>
          @endif
        </article>
      @endforeach
    </section>
  @endforeach

  {{-- with every sheet hidden the page would be blank: say so, and offer the way back --}}
  <div class="ad-sheet ad-empty" x-show="visibleMeasures === 0" x-cloak>
    Фильтрга мос чора-тадбир йўқ ·
    <button type="button" class="ad-link" x-on:click="filter = 'all'; search = ''">Барчасини кўрсатиш</button>
  </div>
```

The status chip and the ring that used to sit in the measure row are gone: the number badge
carries the state now, and the percentage lives on each line.

- [ ] **Step 2: Add the `mstateChip` helper the markup calls**

In `backend/public/js/ad-entry.js`, next to `mstateLabel`, add:

```js
      /** chip colour for a measure's state: saved reads positive, carry and edited read as pending */
      mstateChip(mid) {
        const s = this.mstate(mid);
        return s === 'saved' ? 'ok' : (s === 'empty' ? '' : 'edit');
      },
```

- [ ] **Step 3: Point the controller at the new elements**

In the same file:

1. In `_index()`, change `const tbodies = Array.from(root.querySelectorAll('tbody.ad-m'));` to
   `const tbodies = Array.from(root.querySelectorAll('.ad-m'));`
2. Rename `tbodyOf(mid)` to `measureOf(mid)` (its definition is one line) and update the two
   call sites: the deadline lookup inside `statusClass()` and the state lookup inside
   `mstate()`. Rename the local `const tbody` in both to `const el`.
3. Replace `showGroup()` with the sheet-aware version:

```js
      /** a section sheet is shown when any measure inside it passes the filter */
      showGroup(sheet) {
        void this.tick; void this.search; void this.filter;
        for (const el of sheet.querySelectorAll('.ad-m')) { if (this.show(el)) return true; }
        return false;
      },
```

Everything else in the file stays as it is: the edit buffer, `parse()`, counters, hotkeys,
focus handling, toasts and the `ad-refresh` / `ad-imported` handlers do not know about the DOM
shape beyond these selectors.

- [ ] **Step 4: Update the markup assertions**

`backend/tests/Feature/Admin/RoadmapEntryTest.php` asserts table markup. Change those
assertions to the new elements, keeping them specific. For example a `<tbody class="ad-m"` assertion
becomes:

```php
        ->assertSeeHtml('<article class="ad-m" wire:key="m-')
        ->assertSeeHtml('data-state="empty"')
```

Keep every behavioural assertion (saving, filtering, the panel, history, the import event) exactly
as it is. If one of those fails, the markup changed more than it should have.

- [ ] **Step 5: Run the grid tests**

```bash
cd backend && DB_DATABASE=hududlar_rd_5 php artisan test --filter="RoadmapEntryTest|ProgressUploadTest"
node --check backend/public/js/ad-entry.js
```

Expected: all green, and the JS parses.

- [ ] **Step 6: Commit**

```bash
git commit -F msg.txt -- backend/resources/views/livewire/admin/roadmap-entry.blade.php backend/public/js/ad-entry.js backend/tests/Feature/Admin/RoadmapEntryTest.php
```

Message: `feat(admin): entry grid becomes one sheet per section (the data-* contract is unchanged)`

---

## Task 6: Entry grid header, sheets and the input cell

**Files:**
- Modify: `backend/resources/views/livewire/admin/roadmap-entry.blade.php` (header only)
- Modify: `backend/app/Livewire/Admin/RoadmapEntry.php` (overdue count for the sub-line)
- Modify: `backend/public/css/portal.css` (the entry-grid block)

- [ ] **Step 1: Pass the overdue count to the view**

In `backend/app/Livewire/Admin/RoadmapEntry.php` add `use App\Support\Roadmaps\RoadmapOverdue;`
and add one entry to the `view(...)` array in `render()`:

```php
            'overdue'      => RoadmapOverdue::count($sheet['measures'], $roadmap->year, ProgressSheet::currentPeriod()),
```

- [ ] **Step 2: Rewrite the header**

Replace the `<header class="ad-head ad-sticky">…</header>` block in
`backend/resources/views/livewire/admin/roadmap-entry.blade.php` with:

```blade
  <header class="ad-head">
    <div class="ad-head-row">
      @php $done = count($sheet['measures']) > 0 ? (int) round($sheet['counts']['saved'] / count($sheet['measures']) * 100) : 0; @endphp
      <div class="ad-ring" style="background:conic-gradient(from 180deg, var(--ad-accent) 0 {{ $done }}%, #E2EAF4 {{ $done }}% 100%)" aria-hidden="true">
        <i><b class="ad-num">{{ $done }}%</b><em>бажарилди</em></i>
      </div>
      <div class="ad-titles">
        <h2 class="ad-title">{{ $region->name_full }}</h2>
        <p class="ad-sub">{{ RoadmapPeriod::label($period) }} · {{ count($sheet['measures']) }} та чора-тадбир · {{ array_sum(array_map(fn ($m) => count($m['lines']), $sheet['measures'])) }} та индикатор@if($overdue > 0) · <span class="over">{{ $overdue }} кечиккан</span>@endif</p>
        <nav class="ad-pills" aria-label="Ҳолат бўйича фильтр">
          <button type="button" :class="filter === 'all' && 'on'" :aria-pressed="filter === 'all'" x-on:click="filter = 'all'">Барчаси <b class="tnum">{{ count($sheet['measures']) }}</b></button>
          <button type="button" :class="filter === 'unfilled' && 'on'" :aria-pressed="filter === 'unfilled'" x-on:click="filter = 'unfilled'">Тўлдирилмаган <b class="tnum" x-text="count('unfilled')">—</b></button>
          <button type="button" :class="filter === 'edited' && 'on'" :aria-pressed="filter === 'edited'" x-on:click="filter = 'edited'">Ўзгартирилган <b class="tnum" x-text="count('edited')">0</b></button>
          <button type="button" :class="filter === 'carry' && 'on'" :aria-pressed="filter === 'carry'" x-on:click="filter = 'carry'">Ўтган ойдан <b class="tnum" x-text="count('carry')">{{ $sheet['counts']['carry'] }}</b></button>
          <button type="button" :class="filter === 'saved' && 'on'" :aria-pressed="filter === 'saved'" x-on:click="filter = 'saved'">Сақланган <b class="tnum" x-text="count('saved')">{{ $sheet['counts']['saved'] }}</b></button>
        </nav>
      </div>
      <div class="ad-controls">
        <label class="ad-select">
          <span>Вилоят</span>
          <select data-current="{{ $region->code }}" x-on:change="changeRegion($event)" aria-label="Вилоят">
            @foreach($regions as $r)
              <option value="{{ $r->code }}" @selected($r->code === $region->code)>{{ $r->name_full }}</option>
            @endforeach
          </select>
        </label>
        <label class="ad-select">
          <span>Ой</span>
          <select data-current="{{ $period }}" x-on:change="changePeriod($event)" aria-label="Ҳисобот ойи">
            @foreach($periods as $p)
              <option value="{{ $p }}" @selected($p === $period)>{{ RoadmapPeriod::label($p) }}</option>
            @endforeach
          </select>
        </label>
        <div class="ad-search"><input type="search" x-model.debounce.150ms="search" placeholder="Чора-тадбир, индикатор…" aria-label="Қидирув"></div>
        {{-- an import overwrites the sheet, so it must not start on top of unsaved typing --}}
        <button type="button" class="ad-btn ghost" :disabled="editedLines > 0"
                :title="editedLines > 0 ? 'Аввал ўзгаришларни сақланг ёки бекор қилинг' : 'Тўлдирилган шаблонни юклаш'"
                wire:click="$dispatch('open-upload', { code: {{ $region->code }} })">Excel’дан юклаш</button>
      </div>
    </div>
  </header>
```

The header no longer uses `ad-sticky`; the page scrolls and only the section strips stick.

- [ ] **Step 3: Rewrite the entry-grid styles**

Replace everything from `/* ===================== admin panel: entry grid ===================== */`
down to (but not including) `/* ===================== admin panel: upload slide-over ===================== */`
with:

```css
/* ===================== admin panel: entry grid ===================== */
.ad-ring{width:88px;height:88px;border-radius:50%;flex:none;display:grid;place-items:center;box-shadow:0 14px 34px rgba(23,105,224,.22)}
.ad-ring i{width:68px;height:68px;border-radius:50%;background:linear-gradient(180deg,#fff,#F7FAFE);display:flex;flex-direction:column;align-items:center;justify-content:center;font-style:normal;box-shadow:inset 0 1px 2px rgba(12,26,48,.06)}
.ad-ring b{font-family:var(--font-num);font-size:21px;font-weight:800;letter-spacing:-.03em;line-height:1;color:var(--ad-ink)}
.ad-ring em{font-style:normal;font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--ad-faint);margin-top:2px}
.ad-controls{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-left:auto}
.ad-search input{border:1px solid rgba(14,28,50,.08);border-radius:13px;padding:9px 15px;font:inherit;font-size:13px;min-width:230px;background:rgba(255,255,255,.9);outline:none;box-shadow:var(--ad-ctl-shadow)}
.ad-search input:focus{border-color:var(--ad-accent);box-shadow:var(--ad-focus)}
.ad-pills{display:flex;flex-wrap:wrap;gap:7px;margin-top:11px}
.ad-pills button{border:1px solid var(--ad-sheet-line);border-radius:999px;background:rgba(255,255,255,.85);padding:6px 13px;font:inherit;font-size:11.5px;font-weight:700;color:var(--ad-muted);cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 8px rgba(12,26,48,.05);transition:background .15s,color .15s,box-shadow .15s}
.ad-pills button b{font-family:var(--font-num);font-weight:800;color:var(--ad-ink)}
.ad-pills button:hover{background:#fff}
.ad-pills button.on{background:var(--ad-accent-grad);border-color:transparent;color:#fff;box-shadow:var(--ad-accent-glow)}
.ad-pills button.on b{color:#fff}
.ad-alert{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#FDECEA;color:var(--task-red);border-radius:14px;padding:11px 15px;font-size:13px;font-weight:600}
/* one sheet per section; the strip parks under the 52px admin bar */
.ad-sec{display:flex;flex-direction:column}
.ad-sec-head{position:sticky;top:52px;z-index:4;display:flex;align-items:center;gap:12px;padding:11px 18px;background:linear-gradient(180deg,rgba(248,251,255,.97),rgba(241,246,252,.94));border-bottom:1px solid var(--ad-hair-strong);backdrop-filter:blur(6px)}
.ad-sec-head .t{min-width:0;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;font-size:10.5px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;color:#7B8AA0}
.ad-sec-head .rn{color:var(--ad-accent)}
.ad-sec-head .dn{font-size:13px;font-weight:800;letter-spacing:0;text-transform:none;color:var(--ad-ink)}
.ad-sec-head .hd{font-size:11.5px;font-weight:600;letter-spacing:0;text-transform:none;color:var(--ad-muted)}
.ad-sec-head .cnt{margin-left:auto;font-size:11px;font-weight:700;color:var(--ad-muted);background:#fff;border:1px solid var(--ad-hair-strong);border-radius:999px;padding:3px 10px;white-space:nowrap}
.ad-sec-head .cols{display:flex;gap:14px;font-size:9.5px;font-weight:800;letter-spacing:.08em;color:#9AA8BA}
.ad-sec-head .cols span:nth-child(1){width:112px;text-align:right}
.ad-sec-head .cols span:nth-child(2){width:168px;text-align:center}
.ad-sec-head .cols span:nth-child(3){width:64px;text-align:right}
.ad-m{display:flex;flex-direction:column;border-bottom:1px solid var(--ad-hair)}
.ad-m:last-child{border-bottom:0}
.ad-mrow{display:flex;gap:13px;align-items:flex-start;padding:15px 18px 8px}
.ad-mrow .no{width:28px;height:28px;border-radius:10px;flex:none;display:grid;place-items:center;font-size:12px;color:#fff;background:linear-gradient(135deg,#8FA5BF,#AEC0D5);box-shadow:0 5px 12px rgba(12,26,48,.14)}
.ad-mrow .no.on{background:var(--ad-accent-grad);box-shadow:0 6px 16px rgba(23,105,224,.35)}
.ad-mrow .ttl{flex:1;min-width:0}
.ad-mrow .ttl .t{font-size:14px;font-weight:700;line-height:1.45;letter-spacing:-.005em;color:var(--ad-ink);max-width:74ch;overflow-wrap:anywhere}
.ad-mrow .meta{display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap}
.ad-mrow .meta .due{font-size:11px;color:var(--ad-faint)}
.ad-tools{display:inline-flex;gap:6px;margin-left:auto}
.ad-lrow{display:grid;grid-template-columns:1fr 92px 112px 168px 64px 240px;gap:14px;align-items:center;padding:9px 18px 9px 59px;border-top:1px solid var(--ad-hair)}
.ad-lrow .c-label{font-size:12.5px;color:#4C5E75;max-width:58ch;overflow-wrap:anywhere}
.ad-lrow .c-unit{font-size:11.5px;font-weight:600;color:var(--ad-faint)}
.ad-lrow .c-plan{font-size:14px;font-weight:700;text-align:right;color:#2D4159}
.ad-lrow .c-pct{text-align:right;font-size:13px}
.ad-lrow.none{display:block;padding:12px 18px 12px 59px;color:var(--ad-muted);font-style:italic;font-size:12.5px}
.ad-empty{padding:34px 18px;text-align:center;color:var(--ad-muted);font-size:12.5px}
.ad-cell{position:relative}
.ad-cell input{width:100%;border:1.5px solid #D3DEEB;border-radius:12px;padding:9px 13px;font:inherit;font-size:15px;font-weight:800;color:var(--ad-ink);background:linear-gradient(180deg,#fff,#FBFCFE);outline:none;box-shadow:inset 0 1px 3px rgba(12,26,48,.05);transition:border-color .15s,box-shadow .15s,background .15s}
.ad-cell.actual input{text-align:right;font-family:var(--font-num);letter-spacing:-.02em}
.ad-cell.note input{border:0;border-bottom:1.5px solid #E2E9F2;border-radius:0;padding:7px 2px;font-size:12.5px;font-weight:500;color:var(--ad-muted);background:transparent;box-shadow:none}
.ad-cell input:focus{border-color:var(--ad-accent);background:#fff;box-shadow:var(--ad-focus), inset 0 1px 3px rgba(12,26,48,.04)}
.ad-cell.note input:focus{box-shadow:0 2px 0 0 var(--ad-accent)}
.ad-cell input:disabled{background:#F4F7FB;color:var(--ad-faint);box-shadow:none}
.ad-cell.carry input{color:#98A4B3;font-style:italic;border-style:dashed}
.ad-cell.empty input{border-style:dashed}
.ad-cell.edited input{border-color:#E0A33F;border-style:solid;background:linear-gradient(180deg,#FFFDF8,#FFF8EC);color:var(--ad-ink);font-style:normal}
.ad-cell.invalid input{border-color:var(--task-red);border-style:solid;background:#FFF5F4;color:var(--ad-ink);font-style:normal;box-shadow:0 0 0 4px rgba(192,57,43,.14)}
/* Always reserved: a message appearing under a cell must not push every row below it down. */
.ad-cell .msg{display:block;font-size:10.5px;color:var(--task-red);min-height:13px;line-height:1.2}
.c-pct span{font-family:var(--font-num);font-weight:800;letter-spacing:-.02em}
.c-pct .t-green{background:var(--ad-pos-grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.c-pct .t-amber{color:#B8791F}.c-pct .t-red{color:var(--task-red)}.c-pct .t-none{color:#A3B1C2;font-weight:700}
.ad-savebar{position:sticky;bottom:16px;z-index:6;margin:4px auto 0;width:fit-content;max-width:100%;display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;background:linear-gradient(135deg,#0D1832,#16294E);color:#fff;border-radius:999px;padding:11px 14px 11px 22px;box-shadow:0 16px 38px rgba(10,22,45,.35)}
.ad-savebar .s{font-size:12px;color:rgba(255,255,255,.65)}
.ad-savebar .s b,.ad-savebar .s span{color:#fff;font-family:var(--font-num)}
.ad-savebar .a{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ad-savebar .ad-btn.ghost{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.22);box-shadow:none}
.ad-savebar .ad-btn.ghost:hover{background:rgba(255,255,255,.22)}
.ad-arm{display:inline-flex;align-items:center;gap:8px;font-size:12px;background:rgba(255,255,255,.12);border-radius:999px;padding:6px 12px}
.ad-arm.small{background:var(--ad-warn-bg);color:var(--ad-warn-ink);font-size:11.5px;padding:4px 10px;border-radius:999px}
.ad-toast{position:fixed;right:22px;bottom:92px;z-index:40;background:linear-gradient(135deg,#0F9D63,#2FBE85);color:#fff;border-radius:14px;padding:11px 18px;font-size:13px;font-weight:700;box-shadow:0 14px 34px rgba(15,157,99,.35)}
.ad-toast.warn{bottom:144px;background:linear-gradient(135deg,#C97A12,#E8A33F);box-shadow:0 14px 34px rgba(201,122,18,.35)}
.ad-prow{padding:4px 18px 16px 59px}
.ad-panel{background:#fff;border:1px solid var(--ad-sheet-line);border-radius:16px;padding:14px 16px;box-shadow:var(--ad-card-shadow)}
.ad-panel .h{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px;font-size:13px;font-weight:700}
.ad-panel .h .muted{font-size:11.5px;font-weight:500}
.ad-panel .rows{display:flex;flex-direction:column;gap:7px}
.ad-panel .r{display:grid;grid-template-columns:minmax(0,1fr) 90px 110px 150px;gap:9px;align-items:start}
.ad-panel .r.head{font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--ad-faint)}
.ad-panel textarea,.ad-panel input{width:100%;border:1.5px solid #D3DEEB;border-radius:10px;padding:8px 11px;font:inherit;font-size:12.5px;color:var(--ad-ink);background:#fff;outline:none;resize:vertical}
.ad-panel textarea:focus,.ad-panel input:focus{border-color:var(--ad-accent);box-shadow:var(--ad-focus)}
.ad-panel .t{display:inline-flex;gap:4px;align-items:center;flex-wrap:wrap}
.ad-panel .f{display:flex;gap:8px;align-items:center;margin-top:12px}
.ad-panel .f .sp{flex:1}
.ad-hist{position:fixed;top:0;right:0;bottom:0;width:min(420px,100%);z-index:30;background:var(--ad-sheet);border-left:1px solid var(--ad-sheet-line);box-shadow:-20px 0 50px rgba(12,26,48,.18);padding:18px 20px;overflow:auto;backdrop-filter:blur(10px)}
.ad-hist .h{display:flex;justify-content:space-between;align-items:center;font-size:15px;font-weight:800;margin-bottom:6px}
.ad-hist .t{font-size:12.5px;color:var(--ad-muted);margin:0 0 14px;line-height:1.45}
.ad-hist .e{border-top:1px solid var(--ad-hair);padding:10px 0;font-size:12.5px}
.ad-hist .e .w{font-size:11px;color:var(--ad-faint)}
.ad-hist .e .a{font-weight:700;color:var(--ad-ink);margin-top:3px}
.ad-hist .e .v{margin-top:3px;color:var(--ad-muted)}
.ad-hist .e .v s{color:var(--task-red)}
.ad-hist .e .v b{color:#0F7A4F}
@media (max-width:1100px){
  .ad-lrow{grid-template-columns:1fr 92px 112px 168px 64px;}
  .ad-lrow .c-note{grid-column:1 / -1;padding-left:0}
  .ad-sec-head .cols{display:none}
}
@media (max-width:800px){
  .ad-lrow{grid-template-columns:1fr 140px 64px;row-gap:8px}
  .ad-lrow .c-unit,.ad-lrow .c-plan{grid-column:auto}
  .ad-mrow{padding:13px 14px 8px}
  .ad-lrow{padding-left:14px}
  .ad-prow{padding-left:14px}
  .ad-sec-head{top:0}
}
```

- [ ] **Step 4: Look at it in the browser**

Open `http://localhost:8000/admin/roadmaps/1722`. Check: the ring shows the right percentage,
section strips stop under the admin bar when scrolling, the input cell is clearly the loudest
element, the save pill floats centred, typing turns a cell amber and the percentage green.

- [ ] **Step 5: Run the grid tests again**

```bash
cd backend && DB_DATABASE=hududlar_rd_6 php artisan test --filter="RoadmapEntryTest|RoadmapOverviewTest"
```

- [ ] **Step 6: Commit**

```bash
git commit -F msg.txt -- backend/app/Livewire/Admin/RoadmapEntry.php backend/resources/views/livewire/admin/roadmap-entry.blade.php backend/public/css/portal.css
```

Message: `feat(admin): entry header with the completion ring, sticky section strips and the new input cell`

---

## Task 7: Upload drawer and login card

**Files:**
- Modify: `backend/public/css/portal.css` (the upload block and the auth block)
- Modify: `backend/resources/views/auth/login.blade.php` (classes only, no behaviour)

- [ ] **Step 1: Restyle the upload drawer**

In the `/* ===================== admin panel: upload slide-over ===================== */` block,
replace the panel, scrim and file-control rules with:

```css
.ad-scrim{position:fixed;inset:0;z-index:31;background:rgba(10,17,40,.35);backdrop-filter:blur(2px)}
.ad-upload{position:fixed;top:0;right:0;bottom:0;width:min(560px,100%);z-index:32;background:var(--ad-sheet);border-left:1px solid var(--ad-sheet-line);box-shadow:-20px 0 50px rgba(12,26,48,.20);padding:20px 22px;overflow:auto;backdrop-filter:blur(10px)}
.ad-upload .h{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px}
.ad-upload .h h3{margin:0;font-size:16px;font-weight:800;letter-spacing:-.015em}
.ad-upload .h .sub{font-size:12px;color:var(--ad-muted);margin-top:3px}
.ad-upload .f{display:flex;flex-direction:column;gap:7px;margin-bottom:14px}
.ad-upload .f > span{font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--ad-faint)}
.ad-upload select{width:100%;border:1.5px solid #D3DEEB;border-radius:12px;padding:10px 13px;font:inherit;font-size:13px;font-weight:700;background:#fff;outline:none}
.ad-upload select:focus{border-color:var(--ad-accent);box-shadow:var(--ad-focus)}
.ad-file{position:relative;display:flex;align-items:center;gap:11px;border:1.5px dashed #CBD9E8;border-radius:14px;padding:12px 14px;cursor:pointer;background:linear-gradient(180deg,#fff,#FBFCFE);transition:border-color .15s,box-shadow .15s}
.ad-file:hover{border-color:var(--ad-accent)}
.ad-file:focus-within{border-color:var(--ad-accent);box-shadow:var(--ad-focus)}
.ad-file input{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;clip:rect(0 0 0 0)}
.ad-file-btn{font-size:11.5px;font-weight:800;color:var(--ad-accent);background:#EAF2FE;border-radius:999px;padding:6px 13px;white-space:nowrap}
.ad-file-name{font-size:12.5px;color:var(--ad-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ad-upload table{width:100%;table-layout:fixed;border-collapse:collapse;margin-top:14px;font-size:12px}
.ad-upload th{font-size:10px;font-weight:800;line-height:1.15;text-transform:uppercase;letter-spacing:.04em;color:var(--ad-faint);text-align:left;vertical-align:bottom;hyphens:auto;padding:6px 7px;border-bottom:1px solid var(--ad-hair-strong)}
.ad-upload td{padding:8px 7px;border-bottom:1px solid var(--ad-hair);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ad-upload th:first-child,.ad-upload td:first-child{width:30%}
.ad-upload td:first-child{white-space:normal;overflow-wrap:anywhere}
.ad-upload th:not(:first-child),.ad-upload td:not(:first-child){width:10%;text-align:right;font-variant-numeric:tabular-nums;font-family:var(--font-num)}
```

Leave every other rule in that block unchanged: the warning list, the report line and the
buttons already inherit the new look from `.ad-btn` and the token block.

- [ ] **Step 2: Restyle the login card**

In the auth block of `portal.css`, replace the card rules with:

```css
.auth-body{display:block;min-height:100vh;margin:0;background:
    radial-gradient(760px 320px at 80% -10%, rgba(23,105,224,.18), transparent 60%),
    radial-gradient(620px 280px at 10% 10%, rgba(99,179,237,.16), transparent 60%),
    linear-gradient(160deg,#0A1128 0%,#12305E 55%,#2B61AF 100%);font-family:var(--font-sans);color:var(--ink)}
.auth-wrap{min-height:100vh;display:grid;place-items:center;padding:24px}
.auth-card{width:min(100%,390px);background:rgba(255,255,255,.96);border:1px solid rgba(255,255,255,.5);border-radius:22px;padding:30px 30px 28px;box-shadow:0 30px 70px rgba(5,20,45,.45);display:flex;flex-direction:column;backdrop-filter:blur(12px)}
.auth-brand{align-self:center;background:#0A1128;border-radius:16px;padding:12px 18px;margin-bottom:16px;box-shadow:0 10px 26px rgba(10,17,40,.35)}
.auth-brand img{height:30px;display:block}
.auth-card h1{font-size:19px;font-weight:800;letter-spacing:-.02em;text-align:center;margin:0}
.auth-card .sub{text-align:center;font-size:12.5px;color:var(--muted);margin:5px 0 20px}
.auth-card label{font-size:10.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin:10px 0 6px}
.auth-card input{border:1.5px solid #D3DEEB;border-radius:12px;padding:11px 13px;font:inherit;font-size:14px;color:var(--ink);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s}
.auth-card input:focus{border-color:#1769E0;box-shadow:0 0 0 4px rgba(23,105,224,.14)}
.auth-card button{margin-top:20px;border:0;border-radius:999px;padding:12px 16px;font:inherit;font-size:14px;font-weight:800;color:#fff;background:linear-gradient(135deg,#1769E0,#3E8BFF);box-shadow:0 12px 26px rgba(23,105,224,.36);cursor:pointer;transition:transform .15s,box-shadow .15s}
.auth-card button:hover{transform:translateY(-1px);box-shadow:0 16px 32px rgba(23,105,224,.44)}
```

No class names change, so `backend/resources/views/auth/login.blade.php` needs no edit unless
its markup lacks `.auth-wrap`; check and add the wrapper if it is missing.

- [ ] **Step 3: Verify in the browser**

Sign out, look at `/login`, sign in, open the upload drawer from a region card, and check the
file control, the month select and the report table.

- [ ] **Step 4: Run the upload tests**

```bash
cd backend && DB_DATABASE=hududlar_rd_7 php artisan test --filter="ProgressUploadTest|AuthTest"
```

- [ ] **Step 5: Commit**

```bash
git commit -F msg.txt -- backend/public/css/portal.css backend/resources/views/auth/login.blade.php
```

Message: `feat(admin): upload drawer and login card in the same light-glass language`

---

## Task 8: Documentation, memory and the full suite

**Files:**
- Modify: `backend/docs/roadmap-monitoring.md`
- Modify: `CLAUDE.md`
- Modify: `C:/Users/y.utepbergenov/.claude/projects/C--Users-y-utepbergenov-Desktop-hududlar-monitoringi-portali/memory/roadmaps-admin-panel.md`

- [ ] **Step 1: Update the runbook**

In the «Панель орқали киритиш» section of `backend/docs/roadmap-monitoring.md`:

- In **The overview**, replace the description of the three-segment bar with: four KPI tiles
  (тўлдирилди, ўтган ойдан, кечиккан, тегилмаган вилоят), then cards in two groups, «Диққат
  талаб қилади» first, sorted by overdue descending and completion ascending. Say that
  «кечиккан» means the document's deadline month has passed and the measure is not done, and
  that it is computed from `deadline_text`, never stored.
- In **The entry grid**, replace the sentence about the status pill and ring per measure with:
  the region's completion ring sits in the page header, each measure carries a numbered badge
  that turns blue once it has a value this month, the deadline is muted text, and chips appear
  only for «кечиккан», «ўзгартирилган», «ўтган ойдан» and «сақланган».

- [ ] **Step 2: Update CLAUDE.md**

In the styling note, after the namespace examples, add that the admin panel's `ad-`/`adm-`
blocks carry their own token set on `.adm-body` (ground, sheet, accent, positive, warning) and
that panel styles should use those variables rather than the portal's.

- [ ] **Step 3: Update the memory file**

Append to `memory/roadmaps-admin-panel.md`, under a new dated paragraph: the panel was
redesigned on 2026-10-02 to the «Ёруғ шиша» look; tokens live on `.adm-body`; the entry grid is
sheets, not a table, and `ad-entry.js` finds measures by `.ad-m`; «кечиккан» is the ranking
signal and is computed by `RoadmapOverdue`. Keep the existing hazards paragraph intact.

- [ ] **Step 4: Run the full suite in one process**

```bash
cd backend && DB_DATABASE=hududlar_rd_8 php artisan test
```

Expected: everything green, around 720 tests. Investigate any failure before committing.

- [ ] **Step 5: Commit**

```bash
git commit -F msg.txt -- backend/docs/roadmap-monitoring.md CLAUDE.md
```

Message: `docs(admin): describe the redesigned panel, its tokens and the overdue signal`

- [ ] **Step 6: Drop the test databases**

```bash
for n in 1 2 3 5 6 7 8; do PGPASSWORD=123 dropdb -h 127.0.0.1 -U postgres hududlar_rd_$n 2>/dev/null; done
```

---

## Done when

- Both panel screens match the approved mockup's language: soft ground, floating sheets,
  display numerals, the input cell as the loudest element.
- The region list ranks by overdue and shows the four KPI tiles.
- The full suite is green and the public portal is pixel-identical to before.
