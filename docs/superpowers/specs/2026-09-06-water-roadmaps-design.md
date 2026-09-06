# Water-management road maps (Сув хўжалиги йўл хариталари) — registry page design

**Date:** 2026-09-06
**Status:** implemented (2026-09-06) — Хоразм imported; other regions pending
**Phase:** 1 of N (registry + district filter; status/monitoring form, sidebar entry and the other 13 regions are later phases)

> **As-built deviations** (accepted during implementation, all found by auditing the
> 12 real region files): parser classes live in `App\Services\Roadmaps\`
> (`DocxTableReader` + `RoadmapParser`) matching the existing `App\Services\Tasks`
> layout, not `App\Support\Roadmap`; **soft line breaks (`w:br`) are spaces, only
> paragraph boundaries make lines** — in the documents `w:br` is a layout wrap (224 of
> 235 fell mid-sentence), the original rule truncated 179 of 1213 titles; a measure row
> is any multi-cell row whose second cell is non-empty (empty second cell aborts),
> fully empty rows are skipped, a repeated `Т/р` header row is skipped; district
> headers also accept `N. <name> шаҳар (…)` (Фарғона: Қувасой), `N)` numbering and a
> trailing `.`/`;`, the parenthesis is optional; the same district twice in one section
> aborts; funding/deadline/responsible lines join with `, ` after a line ending in `)`
> or `.` and with a space otherwise; `funding_text`/`responsible_text` are `text`
> nullable and `deadline_text` `varchar(128)`; the unique index is complemented by a
> partial index for region-level rows (Postgres treats NULL district_id as distinct)
> and the district FK is `restrictOnDelete`; `import:roadmap` validates `--year`/
> `--domain` and detects a legacy `.doc`; on the page the «Туманлар» rail is in
> document order (mirrors the list), choosing a district leaves `section=all` in the
> URL and the rail highlights the district section, stale URL filters fall back to
> the full list, measures are ordered by `source_row`, the district chip on cards was
> dropped (the group heading names the district), and the sticky rail scrolls when
> taller than the viewport. Also: the layout has no topbar title slot, so the page
> heading «Сув хўжалиги йўл харитаси» + the document title sit in `.wr-head` instead
> of the topbar; `deadline_text` is nullable like the other two text columns; the
> «district section must reference ≥ 1 district» guard is not implemented as such —
> a measure before any district header aborts, a district section with no rows
> imports empty; tests live in `tests/{Unit,Feature}/Roadmaps/` (6 files:
> DocxTableReaderTest, RoadmapParserRulesTest, RoadmapSchemaTest, RoadmapParserTest,
> ImportRoadmapTest, RoadmapsPageTest), not the three paths named below. Later the
> same day: the document-title subtitle was dropped, the heading carries the region
> name, and a session region without a road map falls back to the first loaded region
> (by `regions.sort_order`) with an amber notice — the empty state appears only when
> nothing is loaded at all.

## Background

For every region the Ministry of Water Resources, the "ТИҚХММИ" National Research
University and the regional hokim approved a 2026 "ЙЎЛ ХАРИТАСИ" — a road map of
water-management measures with their scientific support. The documents live in
`data/Сув хўжалиги бўйича йўл хариталар/` (14 files, one per region; 13 are `.docx`,
Сурхондарё is a legacy `.doc` that must be re-saved as `.docx` in Word before import).

The portal needs a page listing these measures (чора-тадбирлар) per region, filterable
by district, as the base for later monitoring. Хоразм is the first region loaded; the
importer must work unchanged for the others.

Document facts (verified on the Хоразм file, 2026-09-06):

- Table 1: three "ТАСДИҚЛАЙМАН" approver cells. Then three title paragraphs
  ("2026 йилда Хоразм вилоятида … / тадбирларнинг илмий ечимларига қаратилган /
  “ЙЎЛ ХАРИТАСИ”"). Table 3 at the end: two signers (skipped).
- Table 2 is the road map: 5 columns `Т/р | Чора-тадбир номи | Лойиҳанинг молиялаштириш
  манбаси | Муддати | Масъуллар`, 106 rows.
  - Section header rows are a single merged cell starting with a Roman numeral:
    `I. Вилоятда амалга ошириладиган йирик лойиҳалар`, `II. Халқаро молия институтлари
    маблағлари ҳисобидан …`, `III. Дуал таълимни ташкил қилиш`, `IV. Вилоятнинг
    хусусиятидан келиб чиқиб …`, `V. Туманларда амалга ошириладиган лойиҳалар`.
  - Inside section V, district header rows are a single merged cell
    `1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)`; 11 districts, all names
    match `districts.name_full` for region 1733.
  - Measure rows have 5 cells. The `Т/р` cell is empty text — Word auto-numbering
    (`w:numPr`), so the sequence must be computed by the importer.
  - Totals: I=5, II=2, III=4, IV=12, V=66 (6 per district) → 89 measures.
  - Deadline (`Муддати`) has two variants: `2026 йил декабрь` (85), `2026 йил
    апрель-октябрь` (4). Some cells break the text across paragraphs (`2026 йил /
    декабрь`) — whitespace is normalised.
  - Measure text often has an embedded list: `… ишлари, жумладан: / 24 та насос
    агрегатларини таъмирлаш. / 1 та … / … бўйича илмий тавсиялар ишлаб чиқиш.`
    Lines are separated either by paragraph boundaries (`w:p`) or soft breaks (`w:br`).
- Other regions vary: Андижон has the district section as **VI** (its IV and V share a
  title) and some cells contain literal `1.` numbers. The parser therefore keys on
  header text, never on section index or the `Т/р` cell.

## Decisions

- **Registry only (approach C for monitoring).** No status, no progress, no data-entry
  form in this phase. Nothing in the schema blocks adding a `roadmap_measure_progress`
  table later.
- **URL-only access.** Route `/roadmaps`, no sidebar button yet. The page uses the
  normal app layout; region comes from `CurrentRegion` (session, switchable with the
  existing `RegionSwitcher`). Adding the sidebar link later is a one-line change.
- **Dedicated tables** (`roadmaps`, `roadmap_measures`), not `tasks` with a new kind —
  `tasks` carries plan/actual/status semantics and drives the Топшириқлар board; mixing
  would pollute its filters and aggregates.
- **PHP importer** reading `.docx` directly (ZipArchive + DOMDocument over
  `word/document.xml`; PhpWord is installed but its table-text API is not needed).
  One toolchain, repeatable for the remaining regions.
- **Naming:** tables and classes are `Roadmap*` with a `domain` column defaulting to
  `water`, so a second road-map family later does not need a rename. The route name is
  `roadmaps`.
- **Layout B + card B** (chosen in the visual companion): left rail with section and
  district filters, KPI strip, grouped card list; each card shows the title, a
  collapsed «Батафсил» block for the embedded list, chips, responsibles and deadline.

## Schema

```text
roadmaps
  id                 bigint pk
  domain             varchar(24)   default 'water'
  region_code        integer       FK regions.code
  year               smallint      2026
  title_text         text          the three title paragraphs joined with ' '
  approvers_text     text null     table-1 cells joined with ' | '
  source_file        varchar(255)  basename of the imported docx
  imported_at        timestamp
  timestamps
  unique (domain, region_code, year)

roadmap_measures
  id                 bigint pk
  roadmap_id         bigint        FK roadmaps.id cascadeOnDelete
  section_no         smallint      1..n, Roman numeral of the section header
  section_title      varchar(255)  header text after the numeral, e.g. "Вилоятда амалга ошириладиган йирик лойиҳалар"
  district_id        bigint null   FK districts.id (only inside the district section)
  district_head_text varchar(255) null   "туман ҳокими Ж.Назаров" (text inside the parentheses, after "масъул –")
  seq_no             smallint      1-based order inside (section, district)
  title              text          first paragraph, or the text before "жумладан:" (see split rule)
  details            text null     remaining lines, '\n'-separated
  body_raw           text          full cell text, lines '\n'-separated
  funding_text       varchar(255)  column 3, whitespace-normalised
  deadline_text      varchar(64)   column 4, whitespace-normalised
  responsible_text   text          column 5, lines joined with ', '
  source_row         smallint      0-based row index in the docx table (debugging)
  timestamps
  unique (roadmap_id, section_no, district_id, seq_no)   -- Postgres treats NULLs as distinct; the importer guarantees uniqueness, the index is a guard for the non-null case
  index  (roadmap_id, district_id)
```

Models: `App\Models\Roadmap` (`region()`, `measures()`), `App\Models\RoadmapMeasure`
(`roadmap()`, `district()`, scope `districtLevel()` = `whereNotNull('district_id')`,
scope `regionLevel()` = `whereNull('district_id')`).

## Importer

Command: `php artisan import:roadmap --region=1733 --file="data/Сув хўжалиги бўйича йўл хариталар/13. Хоразм вилояти якуний.docx" [--year=2026] [--domain=water] [--dry-run]`

Parser (`App\Support\Roadmap\DocxRoadmapParser`, pure, unit-testable) returns a plain
array `{title_text, approvers_text, measures: [...]}` from a docx path:

1. Open the docx with `ZipArchive`, load `word/document.xml`, iterate `w:tbl` in body
   order. Table 1 = approvers (all cell texts). Body paragraphs between table 1 and
   table 2 = title. Table 2 = road map. Later tables ignored.
2. For each `w:tr` collect cell texts. A cell's text is its `w:p` paragraphs joined with
   `\n`, with every `w:br` also becoming `\n`; each line is whitespace-collapsed and
   trimmed; empty lines dropped.
3. Row classification, in order:
   - first data row whose cell 1 is `Т/р` → header, skipped;
   - one non-empty cell (merged) whose text matches `^([IVX]+)\.\s*(.+)$` → section
     header; `section_no` = Roman→int, `section_title` = group 2. Section numbering is
     validated to be consecutive; a gap aborts the import.
   - one non-empty cell matching `^(\d+)\.\s*(.+?)\s*\((.*)\)\s*$` where group 2 ends
     with `тумани` or `шаҳри` → district header. Only valid inside the section whose
     title contains `туман` ("Туманларда амалга ошириладиган лойиҳалар"); elsewhere it
     is an error. District resolved against `districts` of the region by `name_full`,
     then `alt_labels`; unresolved → abort with the offending text.
     `district_head_text` = group 3 with a leading `масъул\s*[–-]\s*` stripped.
   - ≥ 2 non-empty cells among cells 2–5 → measure. `seq_no` increments per
     (section, district) and resets on every header. Cell-1 text is ignored (Word
     numbering; some regions put literal `1.` there).
   - anything else → abort with row index and text (no silent skipping).
4. Title/details split for the measure cell (lines `L1..Ln`):
   - if `L1` contains `жумладан` → `title` = the part of `L1` before `жумладан`,
     `details` = `L2..Ln`;
   - else if n > 1 → `title` = `L1`, `details` = `L2..Ln`;
   - else `title` = `L1`, `details` = null.
   In every case trailing `,`, `:` and whitespace are stripped from `title`.
   `funding_text`, `deadline_text` = cell lines joined with a single space;
   `responsible_text` = lines joined with `, ` (a line already ending in `,` is
   joined with a space instead).
   `body_raw` always keeps all lines. Leading list markers in detail lines
   (`1.`, `2.`, `•`, `-`, `–`) are kept as-is — the page renders details as
   pre-wrapped lines, not as an HTML list.
5. The command wraps the write in a transaction: `updateOrCreate` the `roadmaps` row,
   delete its measures, insert the parsed ones, set `imported_at`. It prints a summary
   (sections with counts, districts with counts, total) and with `--dry-run` prints the
   summary without writing. Re-running is idempotent.
6. Guard rails: the region must exist; the parsed district section must reference at
   least one district; total must be > 0.

## Page

Route: `Route::view('/roadmaps', 'pages.roadmaps')->name('roadmaps')` mounting Livewire
`App\Livewire\RoadmapsPage` inside `layouts.app` (sidebar + topbar as every other page).

State (URL-synced via `#[Url]`): `section` (int|null), `district` (district code
int|null), `q` (string). Choosing a district implies the district section: the section
filter is set to that section and region-level sections are hidden from the list;
clearing the district restores «Барчаси». Choosing a region-level section clears the
district.

Layout (chosen mock B, `wr-` CSS namespace in `public/css/portal.css`, reusing the
Тармоқлар (`sec-`) look: soft cards, pills, hairlines):

- **Topbar title:** «Сув хўжалиги йўл харитаси · {Region} · 2026».
- **Left rail** (sticky): search input; «Бўлимлар» list — «Барчаси» + one row per
  section with count; «Туманлар» list — one row per district with count (only
  districts present in the road map, in `districts.sort_order`). Active row
  highlighted.
- **KPI strip** (4 tiles): жами чора-тадбир · вилоят даражаси (I–IV) · туман
  лойиҳалари · туманлар сони. Tiles reflect the whole road map, not the filter.
- **List:** groups in document order. Group heading = «{Roman}. {section_title}»; inside
  the district section a sub-heading per district «{name_full} · {district_head_text}».
  Card = `seq_no` · `title` · «Батафсил (n банд)» toggle (Alpine `x-data`, shows
  `details` lines) · chips (district name when present; `funding_text`) ·
  «Масъуллар» column · «Муддат» column.
- **Search** filters cards by case-insensitive substring over `title`, `details`,
  `responsible_text`, `funding_text`; group headings with zero matching cards are hidden;
  counts in the rail always show unfiltered totals.
- **Empty states:** no `roadmaps` row for the current region → single panel «{Region}
  учун сув хўжалиги йўл харитаси ҳали юкланмаган»; filters that match nothing → «Мос
  чора-тадбир топилмади».

Queries: one `Roadmap` lookup + one `RoadmapMeasure` query with `district` eager-loaded
per request; grouping and counting happen in PHP (≤ ~130 rows per region).

## Testing

Pest, following repo conventions (`uses(RefreshDatabase::class)` in feature tests,
seeders run explicitly).

- **Unit — parser rules** (`tests/Unit/Roadmap/DocxRoadmapParserTest.php`): Roman
  numeral conversion; section/district header regexes; title/details split for the
  three cases (`жумладан`, multi-line, single line); `масъул –` stripping.
- **Feature — parser on a fixture** (`tests/Feature/Roadmap/ImportRoadmapTest.php`): a
  small synthetic docx fixture (built by the test with `ZipArchive`, 2 sections, 2
  districts, 5 measures — the real Хоразм file stays out of git) → correct counts,
  district resolution via `alt_labels`, unresolved district aborts, running the
  command twice leaves the same row count, `--dry-run` writes nothing.
- **Feature — page** (`tests/Feature/Livewire/RoadmapsPageTest.php`): empty state for a
  region without a road map; district filter shows only that district's cards and
  hides region-level groups; section filter; search hides non-matching cards; rail
  counts stay unfiltered.

## Out of scope (later phases)

- Status / progress tracking and any data-entry form.
- Sidebar navigation button and starter-page module.
- Importing the other 13 regions (command is ready; Сурхондарё needs `.doc`→`.docx`
  conversion first; region-specific parser surprises are handled when they appear).
- Parsing amounts out of `funding_text` or dates out of `deadline_text`.
