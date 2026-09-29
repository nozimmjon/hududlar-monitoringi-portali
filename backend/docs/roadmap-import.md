# Сув хўжалиги йўл хариталари — import runbook

Source, in two generations, one file per region (N = the region's folder number, the
same prefix as `regions.folder_name`):

1. `data/Сув хўжалиги бўйича йўл хариталар/<N>. <Вилоят> … .docx` — the March documents.
2. `data/Сув хўжалиги бўйича йўл хариталар/Вилоятлар Йўл хариталари/<N>. <Вилоят>.xlsx` —
   what the regions returned in **our** template layout. These are the current source:
   the same registry **plus** each measure's indicator lines (Индикатор · Ўлчов · Режа).

`import:roadmap` picks the reader by extension, so the same command serves both. The
documents are the 2026 "ЙЎЛ ХАРИТАСИ" approved by the Ministry of Water Resources,
ТИҚХММИ and the regional hokim. They are a registry (no plan/actual numbers); the portal
shows them at `/roadmaps` for the session region (RegionSwitcher in the sidebar; there
is no sidebar button yet — open the URL directly). On direct entry with no region chosen
yet in the session, the route makes the first loaded region (by region sort order) the
active region; an explicitly chosen region without a road map shows its empty state.

## Import one region

```powershell
cd backend
php artisan migrate --force                          # first time only (.env says APP_ENV=production)
php artisan import:roadmap --region=1733 --dry-run   # parse + summary, no write
php artisan import:roadmap --region=1733             # write (upserts measures by position; dry run previews removals)
php artisan import:roadmap --region=1703 --period=2026-09    # …and import the «Амалда»/«Изоҳ» columns as that period
```

The default lookup scans both the docx folder and `Вилоятлар Йўл хариталари/` for a
`.docx`/`.xlsx` whose name starts with the region number. **One docx + one xlsx is the
normal state of the folder and the xlsx wins** — the command says
«Using 13. Хоразм вилояти.xlsx (newer layout); pass --file to import the docx instead».
Two files of the same kind are genuinely ambiguous and ask for `--file`; Excel lock files
(`~$…xlsx`) are never candidates.

Options:

| Option | Meaning |
| --- | --- |
| `--file=` | explicit path; needed only when the lookup finds 0 or 2+ of a kind |
| `--year=2026` `--domain=water` | validated; the page shows only water/2026 |
| `--period=YYYY-MM` / `YYYY-Qn` | xlsx only: import the «Амалда»/«Изоҳ» columns as that period |
| `--range=null\|lower\|upper` | xlsx only: what a plan written as «18-25» becomes (default: no plan) |
| `--no-shifted` | xlsx only: abort on a row typed one column to the left instead of reading it as a measure |
| `--dry-run` | parse, summarise, write nothing |

## The xlsx layout (current source)

Sheet 0 only («Йўриқнома» and anything else is ignored). Row 1 = the title
(«Сув хўжалиги йўл харитаси: <Вилоят>» → `roadmaps.title_text`), row 2 = the header
`Калит · № · Чора-тадбир · Индикатор · Ўлчов · Режа · Амалда · Изоҳ · Муддат ·
Масъуллар` (validated: C2/D2 must read «Чора-тадбир»/«Индикатор»), rows 3+ = the road map.

The layout carries **no ТАСДИҚЛАЙМАН block**, so an xlsx import never writes
`approvers_text` — a road map first imported from the March docx keeps the approvers it
got there (same for `title_text`, which is only written when the file actually has one).

- **Columns A and B are read but never obeyed.** Every returned file carries another
  region's keys in A (`1733-…` everywhere, `AND-…` in Андижон) and the № in B duplicates,
  skips and restarts. `seq_no` counts the measure rows inside (section, district) exactly
  as the docx path does; a № that disagrees is reported as
  «r{row}: № 6 in file, counted 1», never obeyed.
- Section and district headers work as in the docx, except the text may sit in **A or B**
  (Жиззах left a stale key in A above one district header, so both columns are tried),
  the district number is optional, and the parenthesis may be missing or never closed.
  A district header must end in `тумани`, `шаҳри` or `шаҳар` **not followed by another
  letter**, so «Боғот туманидаги …» is never mistaken for a header — the files say that
  inside `Чора-тадбир` (Фарғона r8/r9), where it is a measure like any other.
- A row with `Чора-тадбир` filled starts a measure; `Индикатор`/`Ўлчов`/`Режа` on that
  same row are its first indicator line, and the rows below it (C empty) continue the
  list. `Муддат` and `Масъуллар` are imported verbatim, as in the docx.
- **There is no funding column**, so `funding_text` is left alone: a measure imported
  from the March docx keeps the funding text the docx gave it.
- Units are normalised (`млн м3` / `млн м 3` → `млн м³`, `Га` → `га`); plans accept
  `7,8`, `7.8`, `1 240` (ordinary, no-break, narrow and thin spaces all count), `15848`.
  A plan written as a range («18-25», ҚҚР) is dropped with a warning —
  `--range=lower|upper` takes a bound instead.
- Numbers a spreadsheet can distort are refused, naming the cell, the same way
  `import:roadmap-progress` refuses them: `1,240` («ноаниқ» — write `1 240` or `1,24`),
  anything above 1e12, a TRUE/FALSE cell. With `--period` the workbook is read **with
  styles**, which additionally catches a percent-formatted cell («50% эмас, 50 деб
  ёзинг» — 0,5 meaning 50 would otherwise divide the report by 100), a date-formatted
  cell, and evaluates formulas.
- Сурхондарё r159 is typed one column to the left; it is read as a measure with a
  «Бажарилиш даражаси» % line and a warning. `--no-shifted` refuses it instead.
  Anything genuinely unclassifiable aborts with the row and its filled cells.
- Row numbers in xlsx messages are the **sheet rows** (1-based), not table indexes.
- **Blank rows do not close a measure block**, so content typed *below* the table (a
  stray «ЖАМИ» row, say) would be absorbed as another indicator line of the last
  measure rather than reported. None of the 13 files has any: each ends inside its last
  measure block. Check the tail of a file before importing a newly returned one.

### Indicator lines and «Амалда»

The indicator rows replace the measure's stored line set by row order (`line_no` 1..n);
lines beyond n are deleted and reported. Line identity is the position, so a row inserted
mid-block moves the following lines' history — the same rule `import:roadmap-progress`
follows. A measure whose indicator rows are all missing from the file keeps its stored
lines (a truncated file is likelier than a measure that stopped being measured).

`Амалда`/`Изоҳ` are only imported with `--period=2026-09` (or `2026-Q3`); without it the
command counts them and warns
«N «Амалда»/«Изоҳ» value(s) ignored — pass --period=YYYY-MM to import them». Statuses are
recomputed for every measure either way.

With `--period` the command prints the same three line-level notices
`import:roadmap-progress` prints, region-prefixed: lines whose label changed while
carrying reported history, previously reported «Амалда» values this file cleared, and
measures that advanced to the new period with nothing reported (status falls back to
`Бажарилмоқда`). Read them before calling an import done.

The command is idempotent per (domain, region, year): it upserts the `roadmaps` row and
its `roadmap_measures` **by position** (section · district · seq) inside one
transaction, so a re-import keeps measure ids — and with them the monitoring rows
(`roadmap_measure_lines` / `roadmap_line_progress`) hanging off them. Only positions
the new parse no longer uses are deleted, reported as
«N measure(s) removed — their indicator lines and progress with them.»; a measure whose
text changed while keeping indicator lines is reported as «N measure(s) with indicator
lines changed their title — a measure inserted or dropped mid-section shifts the
numbering; check…», because the lines follow the *position*, not the text. `--dry-run`
computes both counts without writing («Dry run: N measure(s) would be removed (M with
indicator lines); K measure(s) with indicator lines would change title.») — run it
first on any re-import of a region that already reports progress. Parsing happens
before any write, so a failed re-import leaves the previous import untouched.

## What the docx parser expects

- Table 1 = approvers; paragraphs after it = title; table 2 = the road map whose first
  row starts with `Т/р` (other column captions vary by region); table 3 = signatures
  (ignored).
- Section headers: one merged cell starting with a Roman numeral (`I.`, `IV.`, `VI.`),
  consecutive from I. Cyrillic look-alikes (І, Х) are accepted.
- District headers, only inside the section whose title contains «туман»:
  `N. <Туман номи> тумани (масъул – …)` or `N. <Шаҳар номи> шаҳар (…)`; the parenthesis
  is optional. The name is matched against `districts.name_full / name_short / alt_labels`
  of the region (normalised like the KPI importer) — an unknown name, or the same district
  twice in one section, aborts the import; add the spelling to `alt_labels`
  (`SoatoSeeder`) or fix the docx.
- Any other merged (single-cell) row aborts with its row index and text. A second
  `Т/р` header row is skipped.
- Measure rows: the `Т/р` cell is ignored (auto-numbered in some regions, typed in
  others); `seq_no` is counted per section/district. The text splits into `title`
  (first paragraph, or the part before «жумладан») and `details` (remaining paragraphs).
  Soft line breaks (Shift+Enter) are treated as spaces — in these documents they only
  wrap text to the column width.
- Funding / deadline / responsible cells are imported verbatim (lines joined with `, `
  after a line ending in `)` or `.`, otherwise a space). Typos in the source
  (`декабр`, `2026 декабрь` without «йил») come through as-is — not import bugs.
- Row numbers in error messages are 0-based indexes of table 2 (the header row is 0).

## Known limitations / other regions (xlsx dry run, 2026-09-29)

**All 13 files parse**, once district 1718215 carries the `Қаттақўрғон тумани` alias
(`SoatoSeeder`) that Самарқанд's spelling needs — the March docx needed it too.
Totals: **1 309 measures · 2 335 indicator lines · 161 districts**.

| Вилоят | Measures | Lines | Вилоят | Measures | Lines |
| --- | --- | --- | --- | --- | --- |
| ҚҚР | 141 | 180 | Сурхондарё | 96 | 200 |
| Андижон | 105 | 143 | Сирдарё | 85 | 156 |
| Бухоро | 105 | 213 | Тошкент вил. | 138 | 270 |
| Жиззах | 93 | 184 | Фарғона | 103 | 224 |
| Қашқадарё | 106 | 211 | Хоразм | 89 | 178 |
| Навоий | 61 | 78 | Самарқанд | 87 | 167 |
| Наманган | 100 | 131 | | | |

- Тошкент шаҳри (1726) has no road map file, in either generation.
- Only Андижон filled anything in yet: 3 «Амалда» and 11 «Изоҳ» values. **Its «Изоҳ»
  column holds funding text, not progress notes** («Республика бюджети маблағлари 20,3
  млрд сўм Бажарилмоқда…») — read them before importing that file with `--period`, or
  they land in `roadmap_line_progress.note`.
- **Фарғона r10–r12 file «минг гектар» amounts under the unit `га`** (40,7 minus its
  parts: 6.3 / 2.9 / 2.1 «га» where the measure text says минг гектар). The importer
  stores what the file says; the plans are a thousand times too small until the region
  fixes the unit or the numbers.
- Two plans out of 2 335 have no number: ҚҚР r23 («18-25», a range) and one Самарқанд
  row with an empty `Режа`. They count as informational lines — never as "behind plan".
- Сурхондарё's **docx** is `.doc` — irrelevant now that its xlsx exists; the hint only
  fires when a `.doc` is the only candidate.
- Status and progress are a separate pipeline on top of this registry — indicator
  lines, the monthly xlsx the regions fill and the derived statuses are documented in
  **`docs/roadmap-monitoring.md`** (`roadmap:template` → `import:roadmap-progress` →
  `roadmaps:recompute`).
