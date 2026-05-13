# Bug A: workbook city-row patch

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Add ` ш.` suffix to ambiguous bare-city rows across all 14 regions' xlsx workbooks via a one-off + re-runnable artisan command, so `DistrictResolver` cannot collapse city + district to the same SOATO code.

---

## 1. Goal

`php artisan import:all-regions 2026` fails promote for kashkadarya (1710) + sirdarya (1724) with `SQLSTATE[21000]: Cardinality violation: ON CONFLICT DO UPDATE cannot update row twice`. Root cause: the xlsx for those regions includes a city row written as bare district name (e.g. row 8 of `1.5. Бозор хизматлари` says `'Қарши '`, not `'Қарши ш.'`). `DistrictResolver`'s normalized-bare fallback then maps that row to the district SOATO `1710224`, while another row genuinely labeled `'Қарши '` further down maps to the same SOATO. The promote `ON CONFLICT` batch hits two rows with identical `(region, district, year, indicator, period)` and Postgres refuses to upsert twice.

Fix direction: patch the workbooks themselves so each city row carries the ` ш.` suffix. Resolver code stays unchanged. After patch + re-import, all 14 regions promote cleanly.

## 2. Non-goals

- No change to `DistrictResolver`, `DistrictNameNormalizer`, or any module parser.
- No schema migration.
- No automatic re-run of import inside the script — operator runs `import:all-regions` afterward.
- No backup mechanism beyond what the operator already maintains. `data/` is gitignored; originals are recoverable.
- No edits to non-district sheets (rollup-only, header-only, charts).

## 3. Strategy

Single artisan command, `data:patch-city-rows`. For each region, fetch its cities from the `districts` table (`kind = 'city'`), derive the bare form (`'Қарши ш.'` → `'Қарши'`). For every xlsx in the region's `data/` folder, classify each sheet as a "district sheet" iff rows 1–6 contain `Туман` in col B (case-insensitive). For each district sheet, walk col B; if a cell's normalized-bare value matches a known city bare AND no other cell in the sheet already holds the canonical city full form, overwrite the topmost such cell with the canonical city `name_short` from the database.

`--dry-run` prints the report without saving. `--region=<slug>` (repeatable) restricts scope.

## 4. Command signature

```
php artisan data:patch-city-rows
    {--region=*}    # restrict to listed region slugs (andijan, bukhara, …); default = all 14
    {--dry-run}     # don't save xlsx, just print report
```

Output format (one line per patch):

```
1710 kashkadarya | 1.1-1.5-жадваллар (макро).xlsx | 1.5. Бозор хизматлари | row 8 | 'Қарши ' → 'Қарши ш.'
```

Summary line at end: `Patched N row(s) across M xlsx file(s) in R region(s).`

## 5. Algorithm

```php
foreach ($regions as $region) {
    $cities = District::query()
        ->where('region_code', $region->code)
        ->where('kind', 'city')
        ->get(['name_short']);

    if ($cities->isEmpty()) continue;

    // [['bare' => 'Қарши', 'full' => 'Қарши ш.', 'bareNorm' => 'қарши', 'fullNorm' => 'қарши ш.'], …]
    $cityForms = $cities->map(function ($c) {
        $full = trim($c->name_short);
        $bare = preg_replace('/ ш\.$/u', '', $full);
        return [
            'bare'     => $bare,
            'full'     => $full,
            'bareNorm' => DistrictNameNormalizer::normalize($bare),
            'fullNorm' => DistrictNameNormalizer::normalize($full),
        ];
    });

    $regionDir = $this->resolveRegionDataDir($region); // e.g. data/5. Қашқадарё
    foreach (glob("{$regionDir}/*.xlsx") as $file) {
        $book = IOFactory::load($file);
        $dirty = false;

        foreach ($book->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (! $this->isDistrictSheet($rows)) continue;

            // Build rowIndex (1-based) → normalized trimmed col B value
            $colBNorm = [];
            for ($i = 6; $i < count($rows); $i++) { // start from row 7
                $val = is_string($rows[$i][1] ?? null) ? trim($rows[$i][1]) : '';
                if ($val === '') continue;
                $colBNorm[$i + 1] = DistrictNameNormalizer::normalize($val);
            }

            foreach ($cityForms as $cf) {
                $bareRows = array_keys(array_filter($colBNorm, fn($v) => $v === $cf['bareNorm']));
                $fullRows = array_keys(array_filter($colBNorm, fn($v) => $v === $cf['fullNorm']));

                if (count($fullRows) > 0) continue; // already correctly marked
                if (count($bareRows) === 0)  continue; // city absent from this sheet

                $patchRow = min($bareRows); // topmost match
                $oldValue = $sheet->getCell([2, $patchRow])->getValue();
                $sheet->setCellValue([2, $patchRow], $cf['full']);
                $this->report($region, $file, $sheet->getTitle(), $patchRow, $oldValue, $cf['full']);
                $dirty = true;

                // Update colBNorm so subsequent cityForms in same sheet don't re-match this row
                $colBNorm[$patchRow] = $cf['fullNorm'];
            }
        }

        if ($dirty && ! $this->option('dry-run')) {
            (new XlsxWriter($book))->save($file);
        }
    }
}
```

`isDistrictSheet($rows)`:

```php
for ($i = 0; $i < min(6, count($rows)); $i++) {
    $b = is_string($rows[$i][1] ?? null) ? mb_strtolower($rows[$i][1]) : '';
    if (mb_stripos($b, 'туман') !== false) return true;
}
return false;
```

`resolveRegionDataDir(Region $r)`: read from a constant map keyed by region slug → folder name (`andijan → '2. Андижон'`, `kashkadarya → '5. Қашқадарё'`, etc.). Source: `SoatoSeeder::REGION_SORT` already encodes the numbering; mirror it.

## 6. Files

| File | Action |
|---|---|
| `backend/app/Console/Commands/PatchWorkbookCityRows.php` | new |
| `backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php` | new |

No service, no model, no migration, no view changes.

## 7. Tests

### 7.1 Unit / feature test

Builds an in-memory `Spreadsheet` with one district sheet:

- Row 1: empty
- Row 2: merged title (irrelevant)
- Row 4: A=`№`, B=`Туман/шаҳар номи`
- Row 7: A=null, B=`Қашқадарё вилояти`
- Row 8: A=`1`, B=`Қарши ` (bare, trailing space)
- Row 9: A=`2`, B=`Шахрисабз ш.` (already marked, orthography variant)
- Row 13: A=`6`, B=`Қарши ` (bare again)

Asserts after running the command's patch method:

- Row 8 col B == `Қарши ш.` (city short_name from seeder)
- Row 13 col B == `Қарши ` (untouched — second occurrence)
- Row 9 col B unchanged
- Report contains exactly one entry for this sheet

### 7.2 Dry-run smoke

Runs `php artisan data:patch-city-rows --region=kashkadarya --dry-run` against real `data/5. Қашқадарё/1.1-1.5-жадваллар (макро).xlsx`. Asserts report includes `1.5. Бозор хизматлари row 8`. Xlsx mtime unchanged.

### 7.3 End-to-end smoke

Operator-run, not automated:

```
php artisan data:patch-city-rows
php artisan migrate:fresh --seed
php artisan import:all-regions 2026
```

Expected: 14/14 promote ok, 14/14 tasks ok.

## 8. Risks

- **Risk:** PhpSpreadsheet save loses conditional formatting, charts, or comments. *Mitigation:* operator keeps source workbooks outside `data/`; `data/` is replaceable. Acceptable for prototype.
- **Risk:** Script touches a row that should remain bare. *Mitigation:* `--dry-run` first; only patches rows where normalized bare matches DB city `name_short` exactly. Topmost-row rule mirrors the workbook convention (cities listed first in district block).
- **Risk:** Bug recurs after data refresh. *Mitigation:* command is idempotent — second run finds the canonical form already present and skips. Operator includes it in their refresh playbook.
- **Risk:** Windows file lock on xlsx during save (Excel open). *Mitigation:* catch save error, print operator-actionable message: `"Close <file> in Excel and re-run."`
- **Risk:** Region folder naming drifts (`5. Қашқадарё` → `5. Кашкадарё`). *Mitigation:* `resolveRegionDataDir` returns `null` when folder missing; script logs `region X: data folder not found, skipping` and proceeds with others.
