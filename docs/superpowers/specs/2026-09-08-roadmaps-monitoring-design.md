# Water road maps — monitoring (status + indicators per measure) design

**Date:** 2026-09-08
**Status:** implemented (2026-09-08) on branch `roadmaps`; Хоразм lines not yet reviewed/imported
**Phase:** 2 of N for `/roadmaps` (phase 1 = registry, see `2026-09-06-water-roadmaps-design.md`)
**Branch:** `roadmaps`

> **As-built deviations** (accepted during implementation; operator runbook:
> `backend/docs/roadmap-monitoring.md`). **Template:** sheet titles use
> `regions.name_full`, not `name_short` — 1726 and 1727 are both «Тошкент» and a clashing
> title would be silently renamed by PhpSpreadsheet, so a duplicate throws instead; merged
> measure blocks get **explicit row heights** (Excel auto-fits wrapped text only in unmerged
> cells, so a long «Чора-тадбир» would be clipped), all text cells are written explicitly so
> a value starting with `=` never becomes a formula, landscape print setup with the two title
> rows repeating, the G validation also carries an input prompt («Йил бошидан жами, «Ўлчов»
> устунидаги бирликда»), the «Йўриқнома» sheet has **10** numbered lines (the last two explain
> number formats and that every indicator must be re-reported each period), `--all` together
> with `--region` is a clear error, an unwritable `--out` (file still open in Excel) is an
> operator message, not a stack trace, the **default `--out` filename** is `name_full` too (the
> same «Тошкент» clash would otherwise overwrite one region's file with the other's), and
> `--year`/`--domain` are validated up front exactly as `import:roadmap` validates them.
> **`LineSuggester`:** standalone `сўм`, `%` and «фоиз» count as units (the last normalised to
> `%`), a grammatical suffix after a unit is consumed («гектарда», «тадан», «кВтлик»,
> «фоизга»), a leading list marker («1.», «•», «–») is stripped before matching so it cannot
> merge into the following number, and a title quantity that equals the sum of the same-unit
> detail lines is dropped as a duplicate total («64 нафар» over «9 нафар — Урганч, 20 нафар —
> Хива, …»); `т` as a tonne abbreviation is **not** recognised (too many false hits) — only the
> spelled-out «тонна». **Reader / `import:roadmap-progress`:** beyond
> non-numeric cells, **percent-formatted, date-formatted, boolean and comma-grouped «1 240»**
> cells are rejected naming the cell (a percent-formatted 0,5 would silently divide the report
> by 100), formulas are evaluated, `7,8`/`7.8`/`1 240`/`1 240,5` with NBSP and narrow spaces
> accepted; **two consecutive blank rows close a block** (one is a filler) so a stray «ЖАМИ»
> below the table aborts instead of being absorbed; keys are canonicalised
> (`RoadmapKey::canonical`, hand-edited padding still matches) and a duplicate key names both
> places; `--year`/`--domain` are options here too, validated before the workbook is opened;
> every warning names its region (lines removed · relabelled history · cleared «Амалда» ·
> **measures advanced to a newer period with no «Амалда» value at all** — the give-away that an
> *unfilled* template was imported, which drops the whole region back to «Бажарилмоқда» until
> the filled file arrives · measures missing from the file) and the summary's first count column
> is **«Файлда/жами»** (measures in the file / the road map's total) rather than «Тадбирлар».
> **`RoadmapKey`:** the regex is `^\d{4}-\d+-\d+-\d+$` — no digit caps on the section or seq
> parts, since neither has a real upper bound. **`import:roadmap`:**
> upserts measures by position as specified, and additionally warns about **retitled** measures
> that carry indicator lines and previews both counts in `--dry-run`. **Recomputer:**
> `pct_of_plan` is clamped to ±999999,9999 (numeric(10,4)); each line's share is floored at 0
> as well as capped at 100; a measure with no planned lines is `in_progress` (nothing can be
> behind); `recompute()` returns the computed values (the callers count statuses from them) and
> wraps a bad period in an error naming the measure. **Deadline parser:** month words accept
> Uzbek/Russian endings («декабрда», «майгача», «декабря»), quarters accept Roman *and* Arabic
> numerals, a year only counts when followed by «йил» and a year earlier than the road map's
> year is ignored as a citation («2019-йил … ПФ-5742-сон қарор»); everything unrecognised —
> including Latin-script months and Cyrillic «І» — falls back to December.
> **`RoadmapPeriod::label()`** reuses `TaskPeriod::reportPeriodLabel()` («2026 йил сентябрь»),
> not the «2026 · сентябрь» form named below, so road maps and the tasks board read alike.
> **Page:** the expand toggle collapses with «Камроқ»; a card whose last report is older than
> the road map's latest period carries a 📅 period chip; the deadline chip shows the document text as written («2026 йил декабрь»), red once the
> month has passed (judged against today in Asia/Tashkent) — the countdown («декабргача
> 3 ой») was dropped on user feedback 2026-09-09; the hero ring and the district mini-bars share one helper
> (`MeasureDisplay::meanPct`) so they cannot drift; indicator rows render through
> `resources/views/livewire/partials/wr-line.blade.php`; the **funding chip was removed 2026-09-30** —
> the regions' xlsx road maps have no funding column, so `import:roadmap` clears `funding_text`
> on an xlsx import and the search ignores it. **`roadmaps:recompute`** validates
> `--region` as digits and loads one road map at a time (`measures.lines.progress` eager) to
> keep memory flat. **`label` is `text`, not `varchar(255)`** (migration
> `2026_09_29_000001`): the regions' own road maps carry indicator names of 290–333
> characters (Қашқадарё D17/D19, Фарғона D6) and the 255 cap was cutting them silently.
> Both readers now store the label whole and abort, naming the cell, only past 4 000
> characters (`RoadmapMeasureLine::LABEL_MAX`) — that is a pasted paragraph in the wrong
> column, not an indicator. `unit` (48) and `note` (500) keep their caps.

## Background

Phase 1 shipped `/roadmaps`: a registry of the region's water-management road-map
measures (чора-тадбирлар) parsed from the regional `.docx` files, filterable by section
and district. It shows no execution state. The portal's core question — *promise vs
fact* — is unanswered for road maps.

The user wants monitoring elements on every measure like the Топшириқлар board
(status, plan vs actual, percent), in a design that improves on that board. Facts
established while brainstorming (2026-09-08):

- **Data source: xlsx files from the regions**, one per report period, in a layout we
  define ourselves (nothing exists yet). Regions must find the file easy to fill.
- **Numeric, not free text.** The user rejected a free-text «амалдаги ҳолат» column.
  Monitoring = indicator lines with a plan and a reported actual.
- **We define the indicator lines** (plan side); the region fills only the actual.
  Measures whose funding is «Маблағ талаб этилмайди» are simply measures without a
  money line — money is an optional line type, never required.
- 71 of the 89 Хоразм measures carry quantities in their text (`24 та насос
  агрегатларини таъмирлаш`, `7,8 км`, `26,7 минг гектар`, `574 тонна`); 18 do not
  (илмий тавсия, таълим, изланиш). Raw regex hits are noisy (`158 км` is a canal length,
  not a plan), so suggestions need one human review pass per region.
- Chosen in the visual companion: page layout **B** («премиум карточкалар» — a soft
  card per measure with a ring gauge and always-visible indicator rows) and template
  layout **A** (document-shaped sheet, one row per indicator, measure text merged down
  its rows).

## Decisions (with alternatives considered)

| Topic | Decision | Rejected |
| --- | --- | --- |
| Source of status | Regions fill an xlsx we generate; `import:roadmap-progress` reads it back | Manual entry in the portal (no auth exists), both, seeded placeholders |
| What regions fill | Only «Амалда» per indicator line (+ optional short «Изоҳ») | Free-text condition; region-defined lines; single self-reported % |
| Who defines lines | We do: generator suggests lines from the text, team reviews the xlsx once per region | Regions define lines; no lines at all |
| Status semantics | Derived, never chosen: `TaskStatus::aggregate()` weakest-link + deadline deferral, 3 states as tasks | Region picks a status from a dropdown |
| Cadence | Monthly report periods (`2026-09`), quarter (`2026-Q3`) accepted; history kept per period, page shows the latest | Quarterly only; no history |
| Page layout | B — card per measure, ring, visible lines, deadline countdown, district mini-bars in rail | A «Панель» rows (sectors-detail lineage); C master-detail |
| Template shape | A — document-shaped, row per indicator line, merged measure cell | B row per measure with side-by-side indicator blocks; C two sheets |
| Measure identity in files | Natural key `{region}-{section}-{districtCode|0}-{seq}` | DB id (changes on docx re-import) |

## Schema

Two new tables plus columns on `roadmap_measures`. Mirrors `tasks` → `task_progress`.

```text
roadmap_measure_lines                    -- indicator definitions (ours)
  id                     bigint pk
  roadmap_measure_id     bigint    FK roadmap_measures.id cascadeOnDelete
  line_no                smallint  1..n, order of the row inside the measure block in the template
  label                  text           «Насос агрегати таъмирланди» (was varchar(255), widened 2026-09-29)
  unit                   varchar(48) null   та | км | га | минг га | нафар | дона | тонна | м³ | млн сўм | млрд сўм | кВт | % | …
  plan_value             decimal(20,6) null
  timestamps
  unique (roadmap_measure_id, line_no)

roadmap_line_progress                    -- the region's fill, one row per line and period
  id                       bigint pk
  roadmap_measure_line_id  bigint    FK roadmap_measure_lines.id cascadeOnDelete
  report_period            varchar(16)   '2026-09' | '2026-Q3'
  period_type              varchar(8)    'month' | 'quarter'
  actual_value             decimal(20,6) null
  pct_of_plan              decimal(10,4) null   computed: actual / plan * 100; null when plan is null or 0
  note                     varchar(500) null    «Изоҳ» cell, trimmed
  reported_at              date null            import date
  timestamps
  unique (roadmap_measure_line_id, report_period)
  index  (roadmap_measure_line_id, report_period)

roadmap_measures  +=
  latest_period   varchar(16) null   max period (canonical month order) among the measure's progress rows
  status          varchar(16) not null default 'in_progress'   in_progress | done | open
  pct             decimal(6,2) null  mean of capped line percents for latest_period (see rules)
  lines_total     smallint not null default 0   lines with a non-null plan
  lines_done      smallint not null default 0   of those, pct_of_plan >= 100 in latest_period
```

Models: `RoadmapMeasureLine` (`measure()`, `progress()`), `RoadmapLineProgress`
(`line()`); `RoadmapMeasure` gains `lines()` (ordered by `line_no`) and the casts for
the new columns. The `Roadmap` model gains `latestPeriod(): ?string` = the max
`latest_period` across its measures.

### Period canonical order

`RoadmapPeriod::monthIndex(string $period): int` — `YYYY-MM` → `YYYY*12 + MM`,
`YYYY-Qn` → `YYYY*12 + n*3` (last month of the quarter). Used for "latest" and for the
deadline comparison. `RoadmapPeriod::label()` → «2026 йил сентябрь» / «2026 йил III чорак»
(delegated to `TaskPeriod::reportPeriodLabel`, so road maps and the tasks board read alike).
Validation regex for `--period`: `^\d{4}-(0[1-9]|1[0-2]|Q[1-4])$`.

### Deadline month

`RoadmapDeadline::month(?string $deadlineText, int $year): string` → `YYYY-MM`.
Rules, in order: take the last Cyrillic month name found in the text (январь … декабрь,
also the genitive/short forms `янв`, `фев`, `мар`, `апр`, `май`, `июн`, `июл`, `авг`,
`сен`, `окт`, `ноя`, `дек`) — so «2026 йил апрель-октябрь» → `2026-10`; a four-digit year
in the text overrides `$year`; `N-чорак` / `N чорак` → last month of that quarter;
nothing recognisable (including null, «йил давомида», «доимий») → `{year}-12`.

### Status and percent rules

Computed at import time and rebuilt by `roadmaps:recompute`; the page never computes
them. For one measure and its `latest_period` (null → everything below is null/zero and
status `in_progress`):

1. Per line: `pct_of_plan = actual / plan * 100` when both are non-null and plan ≠ 0;
   else null. Never read from the file.
2. `reported` = at least one line has `actual_value` not null and ≠ 0.
3. `lines_total` = lines with `plan_value` not null; `lines_done` = those with
   `pct_of_plan >= 100`.
4. `pct` = null when `!reported` or `lines_total = 0`; else the mean over planned lines
   of `min(100, pct_of_plan ?? 0)`, rounded to 2 decimals. (14/24 + 1/1 + 3/3 → 86,
   not the done-share 67.)
5. `status` = `TaskStatus::aggregate($lines)['status']` — `in_progress` when
   `!reported`; `done` when `lines_total > 0` and every planned line ≥ 100 %; else
   `open` — **then** the deadline deferral: `open` becomes `in_progress` while
   `RoadmapPeriod::monthIndex(latest_period) < monthIndex(RoadmapDeadline::month(deadline_text, roadmap.year))`.
   (A December promise running behind in September is «Бажарилмоқда»; in December or
   later it is «Бажарилмаган».)
6. Display cap: a measure that is not `done` never shows 100 % — the page shows
   `min(99, round(pct))` (same rule as `SectorDisplay::pshow`).

Roadmap-level aggregates (computed in the page, from measure columns):

- `hero pct` = mean of `pct ?? 0` over measures that have `lines_total > 0`; null when
  no measure has lines. Shown in the rail ring.
- `district pct` = same restricted to the district's measures.
- Counts: measures by status; lines done / lines total summed.
- Measures with `lines_total = 0` count as `in_progress` in the status counts and are
  reported in the hero card as «N тадбирда индикатор йўқ» when N > 0.

## Commands

All under `App\Console\Commands`, logic in `App\Services\Roadmaps\*`, PhpSpreadsheet
(`phpoffice/phpspreadsheet ^5.7`, already installed — this is the repo's first xlsx
*writer*).

### `roadmap:template`

```
php artisan roadmap:template --region=1733 --period=2026-09 [--out=path] [--domain=water] [--year=2026]
php artisan roadmap:template --all       --period=2026-09 [--out=path]
```

Writes an xlsx. `--region` → one workbook with one region sheet; `--all` → every
region that has a loaded road map (ordered by `regions.sort_order`), one sheet each.
Both add a final «Йўриқнома» sheet. Default `--out`:
`data/Сув хўжалиги бўйича йўл хариталар/мониторинг/{period}/{region name_full}.xlsx`
(or `…/{period}/Барча вилоятлар.xlsx` for `--all`); directories are created.
Region without a road map → error, exit 1.

**Sheet title:** `regions.name_full`, forbidden characters `[]:*?/\` replaced with a
space, truncated to 31 characters. The import ignores sheet titles.

**Sheet layout (template A):**

| Col | Header | Content | Locked | Fill |
| --- | --- | --- | --- | --- |
| A | Калит | natural key, **hidden column** | yes | grey text |
| B | № | `seq_no` | yes | — |
| C | Чора-тадбир | `body_raw` (full document text, lines separated by newline, wrap on) | yes | — |
| D | Индикатор | line label | yes | — |
| E | Ўлчов | unit | yes | — |
| F | Режа | plan value | yes | — |
| G | Амалда | empty (or the stored actual for `--period` when one exists) | **no** | yellow `FFF2CC` |
| H | Изоҳ | empty (or stored note) | **no** | yellow `FFF2CC` |
| I | Муддат | `deadline_text` | yes | — |
| J | Масъуллар | `responsible_text` | yes | — |

- Row 1: merged A1:J1 title «Сув хўжалиги йўл харитаси — {region name_full} — {year} ·
  Ҳисобот даври: {period}». The import reads the period back from this cell.
- Row 2: header row, dark blue fill `1F4E79`, white bold, wrapped; G and H headers
  orange fill `C65911`. Freeze panes at A3. Auto-filter off.
- Then rows in document order (`source_row`):
  - **Section header row:** merged A:J, fill `DBE5F1`, bold, «{Roman}. {section_title}».
  - **District header row** (inside the district section, once per district): merged
    A:J, fill `EAF1FB`, bold, «{n}. {district name_full} (масъул – {district_head_text})»;
    the parenthesis is omitted when `district_head_text` is null.
  - **Measure block:** one row per line (`line_no` order); a measure with no lines gets
    exactly one row with D/E/F empty (so the reviewer can type a line). When the block
    has more than one row, A, B, C, I, J are merged vertically over the block.
- Column widths: A 14 (hidden), B 5, C 60, D 40, E 9, F 10, G 10, H 28, I 16, J 30.
  Vertical alignment top everywhere.
- Data validation on every G cell: decimal ≥ 0, error message «Фақат рақам киритинг
  (ўлчов бирлигида, йил бошидан жами)». H: no validation.
- Sheet protection enabled with an empty password; G and H cells unlocked; the
  `formatColumns`/`formatRows` protection options left allowed so users can resize.
- Number format for F and G: `#,##0.##`.

**Natural key** (`RoadmapKey`): `{region_code}-{section_no}-{district code | 0}-{seq_no}`,
e.g. `1733-5-1733206-3` (Хоразм, section V, Гурлан тумани, measure 3) or `1733-1-0-2`
(region-level). `RoadmapKey::parse()` returns the four ints or throws; regex
`^\d{4}-\d{1,2}-\d+-\d{1,3}$`. District code is `districts.code` (SOATO), never the id.

**Line source per measure:** the stored `roadmap_measure_lines` when the measure has
any; otherwise the **suggestion heuristic** (`LineSuggester`, pure):

1. Candidate texts = `title` followed by each `details` line, in order.
2. In each text find every match of
   `(\d+(?:[,.]\d+)?)\s*(минг\s+)?(та|дона|нафар|км|га|гектар|тонна|т|м³|м3|кВт|млн|млрд)(\.?\s*(сўм|долл\.?|АҚШ доллари))?`
   (case-insensitive, Unicode). Unit normalisation: `гектар` → `га`; `минг га`/`минг та`
   keep the `минг ` prefix; `млн`/`млрд` followed by a currency → `млн сўм`/`млрд сўм`
   (dollar → `млн долл.`); bare `млн`/`млрд` with no currency → skipped (ambiguous);
   `м3` → `м³`; `т` → `тонна`.
3. Plan = the number with `,` as decimal separator converted to `.`.
4. Label = the candidate text with the matched quantity removed, whitespace collapsed,
   leading list markers (`1.`, `•`, `-`, `–`) and trailing `.`/`;`/`,` stripped, first
   letter upper-cased, truncated to 255. Empty label after stripping → «Ҳажм».
5. Several quantities in one text → one line each, same label plus « (n)» suffix for
   the 2nd and later.
6. No quantity anywhere in the measure → exactly one line: label «Бажарилиш даражаси»,
   unit `%`, plan 100.
7. Money lines are never suggested from `funding_text`.

The heuristic is a starting point; the team edits labels/units/plans, adds or removes
rows in the xlsx, and imports the reviewed file (next command) — from then on the DB
lines are the definition and the heuristic is not used again for that measure.

### `import:roadmap-progress`

```
php artisan import:roadmap-progress --file=path.xlsx [--period=2026-09] [--dry-run]
```

Reads **every** sheet except one titled «Йўриқнома». Period: from `--period`, else from
the title cell A1 of the first data sheet (`Ҳисобот даври:\s*(\S+)`); both present and
different → abort. No period at all → abort. Every data sheet whose A1 names a period
must name the resolved one (a `--all` workbook has one title row per sheet); a
mismatching sheet → abort naming the sheet.

Row classification, top to bottom, reading column A as PhpSpreadsheet reports it
(a merged range yields its value on the first row only, empty on the rest):

- A matches the key regex → **first row of a block**: the key becomes current, the
  row is a line row of it.
- A non-empty and not a key (title row, «Калит» header, section and district header
  rows — their text sits in A as the first cell of the merge) → **ends the current
  block**, row skipped.
- A empty and D, F, G, H all empty → **blank filler**, skipped, block continues.
- A empty and any of D, F, G, H non-empty → **continuation row** of the current block
  (the measure cell is merged); no current block → abort «line row without a key at
  {sheet}!{row}».

Per line row: `label` = D trimmed (required; stored whole, a cell over 4 000 characters
aborts naming it — see the as-built note), `unit` = E trimmed or null,
`plan` = F numeric or null, `actual` = G numeric or null, `note` = H trimmed ≤500 or
null. Numeric parsing accepts `7,8`, `7.8`, `1 240`, `1 240,5` (spaces and non-breaking
spaces removed, comma → dot); anything else non-empty → abort with sheet, row and
value. `line_no` = 1-based order inside the key's block. The same key appearing in two
separate blocks → abort.

Guards (all abort before writing): unknown key; key whose region has no road map for
the domain/year; a key whose block has zero valid rows when the measure already has
lines (protects against a truncated file wiping definitions — reported as «measure
{key} has {n} stored lines but no rows in the file»); duplicate key blocks; invalid
period.

Writes, inside one transaction, per measure present in the file:

1. **Lines:** upsert by `(measure, line_no)` updating label/unit/plan; delete stored
   lines with `line_no` greater than the block's row count (count reported per region:
   «{n} lines removed»). Their progress cascades.
2. **Progress:** upsert by `(line, period)` with `actual_value`, `pct_of_plan`, `note`,
   `period_type`, `reported_at = today`. An empty G still writes the row (actual null)
   so the note survives and the period is registered.
3. **Recompute** the touched measures (rules above) via the shared `MeasureRecomputer`.

Measures of that region **absent** from the file are untouched. Re-running the same
file is idempotent. `--dry-run` runs everything and rolls back, printing the same
summary. Summary per region: measures in file, lines upserted/removed, actuals
reported, status counts (done / in_progress / open), warnings.

Round-trip usage: (1) `roadmap:template` → (2) team reviews the suggested lines in
Excel → (3) `import:roadmap-progress` on the reviewed, still-empty file (stores the
definitions; every measure is `in_progress`) → (4) send the same file to the region →
(5) region returns it filled → (6) `import:roadmap-progress` again (actuals). Next
month: `roadmap:template --period=2026-10` now emits the stored lines (with stored
actuals for that period if any), no heuristics.

### `roadmaps:recompute`

`php artisan roadmaps:recompute [--region=1733]` — rebuilds `latest_period`, `pct`,
`status`, `lines_total`, `lines_done` for every measure (or one region) from
`roadmap_line_progress`, prints status counts. Same `MeasureRecomputer` the importer
uses. For a deadline rule or period change nothing is re-imported.

### `import:roadmap` change (existing command)

Today the write path does `measures()->delete()` then inserts — measure ids change on
every docx re-import, and with the new FKs that would cascade-delete lines and
progress. Change: upsert measures by `(roadmap_id, section_no, district_id, seq_no)`
(updating text columns and `source_row`), then delete only rows at positions no longer
present in the parse; print «{n} measures removed (their indicator lines and progress
with them)» when n > 0. `--dry-run` output unchanged. The partial unique index for
region-level rows stays; the upsert for `district_id IS NULL` rows must query with
`whereNull`, not `where('district_id', null)` semantics via `updateOrCreate` — use an
explicit lookup. The importer's existing tests keep passing; a new test asserts ids and
progress survive a re-run.

## Page (`/roadmaps`, layout B)

Livewire `RoadmapsPage` keeps its filters (`section`, `district`, `q`) and gains
`#[Url(as: 'holat', except: 'all')] string $status` ∈ `all|done|in_progress|open`
(same param name as the sectors detail page), combinable with the others. Rail counts
and the KPI strip describe the whole road map, unfiltered; the list is filtered.

Query: roadmap → measures ordered by `source_row` with `district`, `lines` (ordered)
and `lines.progress` eager-loaded; grouping, aggregates and history are computed in PHP
(≤ ~130 measures × a few lines × a few periods).

### Rail (sticky, self-scrolling, 272 px)

1. Search (existing).
2. **Hero card** `.wr-hero`: blue gradient (`#1f4f95 → #3a78c9`), white text; SVG ring
   (r 45, stroke 8, dasharray 282.7, animated dashoffset like `.sec-ring`) showing the
   hero pct («—» when null); right of it two stats: «{done}/{total} тадбир бажарилди»
   and «{lines_done}/{lines_total} индикатор»; when measures without lines exist a third
   muted line «{n} тадбирда индикатор йўқ». Period pill in the card corner
   («2026 · сентябрь» or «ҳисобот йўқ»).
3. **Status filter** `.wr-fbtns` (a `wr-kcard`): four buttons Барчаси / Бажарилди /
   Бажарилмоқда / Бажарилмаган with a colored dot (blue / green / violet / red) and a
   count badge; `aria-pressed`; active = blue-soft background like the existing rail
   buttons.
4. **Бўлимлар** — unchanged.
5. **Туманлар** — each row becomes: name (ellipsis) · mini progress bar (36 × 3 px,
   green fill = district pct) · «{pct}%» (or «—»); the measure count moves to the
   `title` attribute («6 та чора-тадбир»). Active row highlight unchanged.

### Main

- Header `.wr-head`: «Сув хўжалиги йўл харитаси · {region}» (unchanged).
- KPI strip: four tiles — жами (ink) / бажарилди (green number) / бажарилмоқда
  (violet) / бажарилмаган (red). Replaces the current вилоят/туман/туман-count tiles.
- Filter bar (shown / total, clear) — unchanged, now also resets `status`.
- Groups in document order. The group wrapper is no longer a white card: heading
  `.wr-gtitle` stays (Roman + section title, district name + head text as today), the
  measures under it are separate cards with 12 px gaps.

### Measure card `.wr-mcard`

White, 1 px `--line` border, 16 px radius, `--shadow-sm` at rest, on hover translateY
−1 px and a slightly larger shadow. Grid: `48px minmax(0,1fr)`.

- **Ring** (left column, `.wr-ring`): SVG circle r 18 (44 px box), stroke 4, track
  `--grey-soft`; fill color by status: done `--task-green`, in_progress `#7c3aed`, open
  `--task-red`; number inside = displayed pct (cap rule) or «—»; no lines → track only,
  «—», muted.
- **Head row**: `№` badge (existing `.no` style) · title (`.ttl`, wraps) · status chip
  `.wr-status` (pill, dot + label: «Бажарилди» green-soft, «Бажарилмоқда» violet-soft
  `#efe9fb`/`#7c3aed`, «Бажарилмаган» red-soft).
- **Indicator rows** `.wr-line` (always visible, first 4): label (ellipsis, full text
  in `title`) · bar 90 × 4 px with a tick at 83.33 % (120 % scale, fill = min(100,
  pct/120·100) %, color by line tier: ≥100 green, 50–99 amber `--task-amber`, <50 red,
  no actual → empty track) · «**{actual}** / {plan} {unit}» (tabular numbers,
  `SectorDisplay::fmt` formatting, «—» for null actual) · «{pct}%» colored by the same
  tier, «—» when null. More than 4 lines → an Alpine toggle «яна {n} индикатор» /
  «Ёпиш» reveals the rest. No lines → one muted row «Индикаторлар ҳали белгиланмаган».
- **Footer chips** `.wr-tag`: deadline chip first — status done → «✓ {deadline_text}»;
  otherwise months left = `monthIndex(deadline month) − monthIndex(today's YYYY-MM)`:
  ≥ 1 → «⏱ {month name}гача {n} ой» (amber-soft), 0 → «⏱ шу ой» (amber-soft), < 0 →
  «⏱ муддат ўтган» (red-soft). Then funding chip (existing `.wr-chip`, «Маблағ талаб
  этилмайди» rendered muted), then responsible chip (first 60 characters, full text
  in `title`).
- **«Батафсил» toggle** (existing Alpine pattern) reveals: the document detail lines
  (existing `.wr-details`), then «Изоҳ» entries for lines whose latest note is
  non-empty («{label}: {note}»), then — only when the measure has progress in ≥ 2
  periods — a sparkline (inline SVG 120 × 28, polyline of one value per period in
  canonical order: the mean over planned lines of `min(100, pct_of_plan ?? 0)` for that
  period, a line with no row in that period counting as 0; last point emphasised,
  period labels under the ends). The button label counts the document lines as today; when there are none but
  notes or history exist, the label is «Батафсил».

Card order inside a group stays document order (`seq_no`); no reported-first sorting
(the road-map structure is the point).

### Colors and CSS

Tasks-board status palette: done `--task-green`, in_progress violet `#7c3aed`
(the board's Бажарилмоқда color), open `--task-red`; line tiers reuse
`--task-green/--task-amber/--task-red`. All new rules are appended to the existing
`wr-` block in `public/css/portal.css` (hand-maintained; no build). New class names:
`.wr-hero`, `.wr-fbtns`, `.wr-drow` (district rail row), `.wr-mcard`, `.wr-ring`,
`.wr-status`, `.wr-line`, `.wr-tag`, `.wr-spark`, `.wr-notes`. Existing `.wr-card`
rules are removed with the old card markup. Breakpoint ≤ 1100 px: rail static (as
today), KPI 2 × 2, cards keep the 48 px ring column; ≤ 600 px: indicator row wraps the
numbers under the label.

### States

- No road map for the region → existing empty panel (unchanged).
- Road map, zero lines anywhere → hero «—», period «ҳисобот йўқ», every card the
  no-lines face, KPI tiles «жами N / 0 / N / 0».
- Filters match nothing → «Мос чора-тадбир топилмади.» (existing).
- A stale `holat` value in the URL falls back to `all` like the other filters.

## Testing

Pest, repo conventions (`uses(RefreshDatabase::class)` in feature tests, seeders run
explicitly, synthetic docx fixture builder from phase 1 reused to load a small road map).

- **Unit** (`tests/Unit/Roadmaps/`): `RoadmapDeadlineTest` (декабрь, апрель-октябрь,
  year in text, `3-чорак`, null/garbage → December); `RoadmapPeriodTest` (month index,
  labels, validation); `LineSuggesterTest` (та / км / минг га / нафар / м3 / bare млн
  skipped / no quantity → % line / label stripping / multi-quantity suffix);
  `MeasureRecomputerTest` (pct mean with cap, reported flag, status matrix incl.
  deadline deferral, lines_total/done, latest period across month and quarter);
  `RoadmapKeyTest`.
- **Feature — template** (`RoadmapTemplateTest`): workbook has a sheet per loaded
  region + Йўриқнома; title row carries the period; header row; section/district rows
  present in order; measure cells merged over multi-line blocks; column A hidden; G/H
  unlocked and yellow, others locked; stored lines preferred over heuristics; stored
  actuals for the period pre-filled; `--all` ordering; region without road map errors.
- **Feature — import** (`ImportRoadmapProgressTest`): round trip on the fixture
  (template → fill G/H in memory with PhpSpreadsheet → import) creates lines and
  progress, pct computed, statuses per rule; second import idempotent; removed row
  deletes the line with a notice; unknown key, non-numeric G, period mismatch, and
  truncated block all abort with nothing written; `--dry-run` writes nothing; period
  read from A1 when `--period` omitted; comma decimals parsed.
- **Feature — docx re-import** (in `ImportRoadmapTest`): running `import:roadmap`
  twice keeps measure ids and their lines/progress; a vanished position is removed.
- **Feature — recompute** (`RoadmapsRecomputeTest`): after editing a progress row
  directly, `roadmaps:recompute` restores consistent status/pct.
- **Feature — page** (`RoadmapsPageTest`, extended): status filter alone and combined
  with a district; rail counts, hero and KPI stay unfiltered; card renders ring value,
  status chip, indicator rows, «яна N индикатор» only above 4 lines, deadline chip
  variants (mock today), no-lines face; sparkline only with ≥ 2 periods; period pill;
  stale `holat` falls back.

## Runbook and docs

- New `backend/docs/roadmap-monitoring.md`: the six-step loop, file conventions
  (`data/…/мониторинг/{period}/`), review guidance for suggested lines (what to fix,
  when to add a money line), abort messages and fixes, recompute.
- `backend/docs/roadmap-import.md`: note the upsert behaviour and link the new runbook.
- `CLAUDE.md`: pipeline 4 gains the template/progress/recompute commands; the
  `/roadmaps` row mentions monitoring.
- `data/` stays out of git (templates and returned files live under `data/…/мониторинг/`).

## Out of scope (later phases)

- Sidebar navigation button and starter-page module for road maps.
- Period switcher on the page (history is stored and shown only as the sparkline).
- Importing the other 12 regions' road maps; Сурхондарё `.doc` conversion.
- Money-line suggestions from `funding_text`; parsing amounts.
- Editing lines or actuals inside the portal; authentication.
- Aggregating road-map execution into the starter page or the country map.
