# Macro module sheet + header detection fix

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Replace iterator-based cell reads in `SheetResolver::scoreSheet` and `HeaderDetector::detect` with `Worksheet::toArray()` so merged title/unit cells are not silently skipped. Macro module currently blocks Andijan import with `sheet_missing` + `header_not_found` issues caused by this exact bug.

---

## 1. Goal

`php artisan import:region andijan 2026` produces 4 blocker issues from the macro module:

- `sheet_missing` for `logical_kind = rollup`
- `header_not_found` on `1.2. Саноат`, `1.4. ҚХ`, `1.5. Бозор хизматлари`

Root cause: PhpSpreadsheet's `getRowIterator + getCellIterator` returns empty rows when iterating multiple rows in a loop where some rows have merged cells. The macro xlsx has merged title row (row 2) and merged unit row (row 5/6). `$sheet->toArray(null, true, true, false)` reads correctly. The two affected services are `SheetResolver::scoreSheet` (scans rows 1-5 for signatures) and `HeaderDetector::detect` (scans rows 1-15 for unit marker + digit start). MacroModuleParser uses `$sheet->getCell([col, row])->getValue()` for direct cell access — those calls work because cell coordinates are unambiguous outside the merge range.

After the fix, all 14 regions can complete a full xlsx import pass.

## 2. Non-goals

- No change to MacroModuleParser data-parsing loop (uses direct `getCell` access; works fine for non-merged data rows).
- No change to other module parsers (InflationModuleParser, BudgetModuleParser, etc.) — they use direct cell access too.
- No change to `RegionWorkbookSheet` cache schema or behaviour.
- No change to signature definitions in `SheetResolver::SIGNATURES`.
- No performance refactor — the affected sheets are tiny (≤ 30 rows × ~15 cols).
- No fuzzy substring matching beyond `mb_stripos`.

## 3. Strategy

Two files modified. Both replace the `getRowIterator + getCellIterator` loop with a single up-front `Worksheet::toArray(null, true, true, false)` call, then iterate the resulting 2D PHP array.

- `null` — fill value for empty cells (we filter them out via `is_string`).
- `true` (calculateFormulas) — keep computed values for formula cells.
- `true` (formatData) — apply number-format strings; numbers come back as formatted strings but signature matching is text-based, so this is irrelevant.
- `false` (returnCellRef) — numeric 0-indexed arrays instead of `['A' => ..., 'B' => ...]`.

`HeaderDetector` additionally extends the unit-marker check to read column index 0 (zero-indexed PHP array) which corresponds to spreadsheet column A. The returned `header_row` stays 1-indexed (consumers expect 1-indexed row numbers everywhere downstream).

`SheetResolver::scoreSheet` widens its scan range from 5 to 8 rows so the 1.1. ЯҲМ sheet's `ЯҲМ` token (which lives on row 6) contributes to the rollup signature match. The existing `асосий иқтисодий кўрсаткич` token still hits in row 2.

## 4. `SheetResolver::scoreSheet` rewrite

```php
private function scoreSheet(Worksheet $sheet, array $signatures): int
{
    $rows = $sheet->toArray(null, true, true, false);
    $score = 0;
    $limit = min(8, count($rows));

    for ($i = 0; $i < $limit; $i++) {
        $rowText = '';
        foreach ($rows[$i] as $cell) {
            if (is_string($cell)) {
                $rowText .= ' ' . $cell;
            }
        }
        foreach ($signatures as $sig) {
            if (mb_stripos($rowText, $sig) !== false) {
                $score++;
            }
        }
    }
    return $score;
}
```

Differences vs current:

- `getRowIterator + getCellIterator` → `toArray`-once.
- Scan range 5 → 8.

The `resolve()` method body, the cache lookup, and the cache write-back are unchanged.

## 5. `HeaderDetector::detect` rewrite

```php
public function detect(Worksheet $sheet, ImportContext $ctx, int $regionWorkbookSheetId): ?int
{
    $cached = RegionWorkbookSheet::find($regionWorkbookSheetId);
    if ($cached && $cached->header_row) {
        return $cached->header_row;
    }

    $allRows = $sheet->toArray(null, true, true, false);
    $hasUnitAbove = false;

    $limit = min(15, count($allRows));
    for ($i = 0; $i < $limit; $i++) {
        $rowText = '';
        foreach ($allRows[$i] as $cell) {
            if (is_string($cell)) {
                $rowText .= ' ' . $cell;
            }
        }

        if (mb_stripos($rowText, 'ҳажм') !== false || mb_stripos($rowText, 'млрд.сўм') !== false) {
            $hasUnitAbove = true;
        }

        $colA = $allRows[$i][0] ?? null;
        if ($hasUnitAbove && (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA))))) {
            $rowNumber = $i + 1;
            if ($cached) {
                $cached->update(['header_row' => $rowNumber]);
            }
            return $rowNumber;
        }
    }

    $this->issues->add(
        kind: IssueKind::HeaderNotFound,
        severity: IssueSeverity::Blocker,
        detail: "Could not locate data start row in sheet '{$sheet->getTitle()}'",
        regionCode: $ctx->regionCode(),
        sourceLabel: $sheet->getTitle(),
        importRunId: $ctx->run->id,
    );
    return null;
}
```

Differences vs current:

- `getRowIterator + getCellIterator` loop → `toArray`-once + 0-indexed array iteration.
- `$colA = $sheet->getCell([1, $row])->getValue()` → `$colA = $allRows[$i][0] ?? null`.
- Return value is `$i + 1` instead of `$row` (toArray is 0-indexed; spreadsheet rows are 1-indexed; downstream consumers including MacroModuleParser's `$row = $headerRow; …; $sheet->getCell([col, $row])` expect 1-indexed values).

## 6. Tests

### 6.1 `tests/Feature/Import/SheetResolverMergedCellsTest.php` (new)

Builds an in-memory `Spreadsheet` with one sheet that has:

- Row 1: a single-cell value (`'unrelated'`).
- Row 2: a merged range `A2:J2` whose value contains a signature substring.
- Row 3 onward: empty.

Asserts that `SheetResolver::scoreSheet` (via the public `resolve` entry point, with a stubbed cache miss) returns the sheet and the issue collector has no entries.

```php
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

test('scoreSheet finds signature in merged row 2', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('test_sheet');
    $sheet->setCellValue('A1', 'unrelated');
    $sheet->setCellValue('A2', '2026 йил асосий иқтисодий кўрсаткичларнинг прогнози');
    $sheet->mergeCells('A2:J2');
    $sheet->setCellValue('A3', 'whatever');

    // Use a small reflection helper to call private scoreSheet, OR seed
    // a region_workbook row and call resolve() and assert result.
    // (Test stub — concrete shape TBD by implementer based on existing test patterns.)
});
```

### 6.2 `tests/Feature/Import/HeaderDetectorMergedCellsTest.php` (new)

Builds an in-memory `Spreadsheet` mirroring the macro xlsx's structure:

- Row 1: `'1.2-жадвал'` in J1 (any non-header text).
- Row 2: merged `A2:J2` with title.
- Row 4: column header row.
- Row 5: merged unit row (`A5:J5`) containing `ҳажми (млрд.сўм)`.
- Row 6: first data row with `'1'` (string) in column A.

Asserts `HeaderDetector::detect` returns `6`.

### 6.3 Implementer notes

Existing tests `tests/Feature/Import/MacroModuleParserTest.php`, `tests/Feature/Import/AndijanMacroParityTest.php` will start to pass after the fix; they were failing pre-T8 (per prior SOATO smoke notes). Re-running the full Pest suite after the fix is part of the smoke task.

## 7. Files touched

| File | Action |
|---|---|
| `backend/app/Services/Import/SheetResolver.php` | modify `scoreSheet` |
| `backend/app/Services/Import/HeaderDetector.php` | modify `detect` |
| `backend/tests/Feature/Import/SheetResolverMergedCellsTest.php` | new |
| `backend/tests/Feature/Import/HeaderDetectorMergedCellsTest.php` | new |

No migration, no model, no view, no Livewire changes.

## 8. Operator smoke

After the implementation:

```bash
cd backend && php artisan migrate:fresh --seed
php artisan import:region andijan 2026
```

Expected: `Run #N: <rows> rows staged, <N> issues. Status: awaiting_review.` Macro module produces ~50 rows (rollup + 16 districts × 3 sub-sectors). Then `php artisan import:promote {run_id}` succeeds, and `php artisan import:all-regions 2026` finishes all 14 regions cleanly.

## 9. Risks

- **Risk:** `toArray` reads the entire sheet into memory. *Mitigation:* affected sheets are tiny (≤ 30 rows × ~15 cols). Total memory negligible.
- **Risk:** `toArray(formatData=true)` formats numbers using the workbook's number-format strings; signature matching is text-only so irrelevant. *Mitigation:* documented; tests cover number formatting via the digit-col-A check.
- **Risk:** Some workbook in a non-Andijan region might place its title differently. *Mitigation:* the fix removes the merged-cell bug entirely; signature matching is now consistent across all workbooks.
- **Risk:** `RegionWorkbookSheet::header_row` cache may already contain stale values from prior failed runs. *Mitigation:* `migrate:fresh` rebuilds the table; cache starts empty.
- **Risk:** Scan range widening from 5 → 8 in `scoreSheet` could cause false signature matches in workbooks with very different layouts. *Mitigation:* widening is small; existing signatures (`ЯҲМ`, `асосий иқтисодий кўрсаткич`) are domain-specific. If new false-positives surface in other regions, narrow per-signature or add per-module overrides as a follow-up.
