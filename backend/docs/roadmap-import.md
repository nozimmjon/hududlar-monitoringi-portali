# Сув хўжалиги йўл хариталари — import runbook

Source: `data/Сув хўжалиги бўйича йўл хариталар/<N>. <Вилоят> … .docx`, one file per
region (N = the region's folder number, the same prefix as `regions.folder_name`). The
documents are the 2026 "ЙЎЛ ХАРИТАСИ" approved by the Ministry of Water Resources,
ТИҚХММИ and the regional hokim. They are a registry (no plan/actual numbers); the portal
shows them at `/roadmaps` for the session region (RegionSwitcher in the sidebar; there
is no sidebar button yet — open the URL directly). When the session region has no road
map yet, the page shows the first loaded region (by region sort order) with a notice
instead of an empty state.

## Import one region

```powershell
cd backend
php artisan migrate --force                          # first time only (.env says APP_ENV=production)
php artisan import:roadmap --region=1733 --dry-run   # parse + summary, no write
php artisan import:roadmap --region=1733             # write (replaces the region's measures)
```

Options: `--file=` (explicit path; needed when the default lookup finds 0 or 2+ files),
`--year=2026`, `--domain=water` (both validated; the page shows only water/2026).

The command is idempotent per (domain, region, year): it upserts the `roadmaps` row and
replaces all its `roadmap_measures` inside one transaction. Parsing happens before any
write, so a failed re-import leaves the previous import untouched.

## What the parser expects

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

## Known limitations / other regions (as of 2026-09-06)

- Сурхондарё's file is `.doc` — open in Word, *Save As* `.docx` first; the command
  says so when it finds only a `.doc`.
- Самарқанд: the docx spells `Қаттақўрғон тумани` with Қ, the district is seeded as
  `Каттақўрғон тумани` — add the Қ spelling to district 1718215 `alt_labels` before
  importing.
- Тошкент шаҳри (1726) has no road map file.
- All other regions (ҚҚР, Андижон, Бухоро, Жиззах, Қашқадарё, Навоий, Наманган,
  Сирдарё, Тошкент вилояти, Фарғона) parse cleanly in dry run.
- No status/progress yet. Adding it later = a `roadmap_measure_progress` table; the
  registry schema stays as is.
