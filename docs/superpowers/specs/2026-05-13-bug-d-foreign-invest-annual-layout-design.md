# Bug D: foreign_invest annual-only layout (karakalpak)

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Teach `SheetResolver` to recognize karakalpak's `4.2-жадвал (инвестициялар).xlsx` and teach `ForeignInvestModuleParser` to fall back to a single-column annual-only parser branch when no `'I чорак'` header is present. Eliminates `sheet_missing` blocker for karakalpak.

---

## 1. Goal

`php artisan import:region karakalpak 2026` fails with one blocker:

```
sheet_missing | No sheet matched signatures for logical_kind 'foreign_invest' in module 'foreign_invest'
```

The workbook `data/1. Қорақалпоғистон Республикаси/4.2-жадвал (инвестициялар).xlsx` has a single sheet `4.2. Хорижий инвестициялар` with this structure:

| Row | A                  | B                                            |
|----:|--------------------|----------------------------------------------|
| 2   | (merged title)     |                                              |
| 4   | `Ҳудудлар`         | `Хорижий инвестициялар прогнози (ВМҚ-86)`   |
| 6   |                    | `млн долл.`                                  |
| 7   | `Жами`             | `633`                                        |
| 8   | `Нукус шаҳри`      | `129`                                        |
| 9   | `Амударё тумани`   | `18`                                         |
| 10  | `Бўзатов тумани`   | `2`                                          |
| ...                |                                              |

Two divergences from the standard layout that 13 other regions use:

1. **Headers:** col A row 4 = `Ҳудудлар` (not `Шаҳар ва туманлар номи`). No `I чорак` anywhere on the sheet. `SheetResolver`'s `foreign_invest` signatures (`'Шаҳар ва туманлар номи'`, `'I чорак'`) score 0 → blocker.

2. **Columns:** only col A (district name) + col B (annual forecast). No quarterly or half-year columns. `ForeignInvestModuleParser` expects a 30-column matrix and would crash even if the sheet matched.

3. **Rollup label:** `Жами` (sum) instead of `<X> вилояти`. Karakalpak is a Republic, not a region. `findRollupRow` looks for the `вилояти` suffix and would skip the rollup row.

After fix: karakalpak `import:region` succeeds with 14 districts + 1 republic-rollup foreign_invest rows.

## 2. Non-goals

- No change to the standard parser path for the 13 other regions.
- No change to the schema, migrations, or `IndicatorFactDto`.
- No change to UI / dashboards.
- No change for other karakalpak modules — only foreign_invest is broken.
- No support for hypothetical H1-only or Q1-only future variants. YAGNI.

## 3. Strategy

Two additive changes:

1. Extend `SheetResolver::SIGNATURES['foreign_invest']` with `'Ҳудудлар'` and `'ВМҚ-86'` so the karakalpak sheet scores ≥1. Existing standard regions also score because their sheets still contain `'Шаҳар ва туманлар номи'` and `'I чорак'` — additive, no regression.

2. In `ForeignInvestModuleParser`, after resolving the sheet, detect the layout: if the first 10 rows contain `'I чорак'` somewhere, use the existing quarterly-layout code. Otherwise, route to a new `parseAnnualOnly` method that emits a single `year`-period DTO per district with `plan_value = col B`.

## 4. SheetResolver change

```php
private const SIGNATURES = [
    // …
    'foreign_invest' => ['Шаҳар ва туманлар номи', 'I чорак', 'Ҳудудлар', 'ВМҚ-86'],
    // …
];
```

The signatures are tested with `mb_stripos` (case-insensitive substring); any one hit increments the score by 1. The karakalpak sheet matches `Ҳудудлар` (row 4 col A) and `ВМҚ-86` (row 4 col B) → score 2. Standard sheets keep matching `Шаҳар ва туманлар номи` and `I чорак` → score ≥ 2. Both layouts win against unrelated sheets which score 0.

## 5. ForeignInvestModuleParser changes

### 5.1 Layout-detection helper

```php
private function isAnnualOnlyLayout(Worksheet $sheet): bool
{
    $rows = $sheet->toArray(null, true, true, false);
    $limit = min(10, count($rows));
    for ($i = 0; $i < $limit; $i++) {
        foreach ($rows[$i] as $cell) {
            if (is_string($cell) && mb_stripos($cell, 'I чорак') !== false) {
                return false;
            }
        }
    }
    return true;
}
```

Conservative: defaults to *annual-only* only when there is no `'I чорак'` at all in the first 10 rows. Standard regions always have it, so they go through the existing path.

### 5.2 Rollup detection extension

`findRollupRow` currently looks for col A short string ending with `'вилояти'`. Karakalpak rollup says `Жами`. Extend the helper:

```php
private function isRollupCell(mixed $value): bool
{
    if (! is_string($value)) return false;
    $trimmed = trim($value);
    if (strlen($trimmed) > 40) return false;
    return str_ends_with($trimmed, 'вилояти')
        || str_ends_with($trimmed, 'Республикаси')
        || $trimmed === 'Жами';
}
```

`Республикаси` covers karakalpak's full-region title row if it ever appears as a label; `Жами` covers the rollup-totals row used in the annual-only sheet.

### 5.3 parseAnnualOnly method

```php
private function parseAnnualOnly(ImportContext $ctx, Worksheet $sheet, string $filePath): int
{
    $rollupRow = $this->findRollupRow($sheet);
    if ($rollupRow === null) return 0;

    $count = 0;
    $count += $this->emitAnnualRow($ctx, $sheet, $rollupRow, null, $filePath);

    for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
        $colA = $sheet->getCell([1, $row])->getCalculatedValue();
        $colB = $sheet->getCell([2, $row])->getCalculatedValue();
        if (! is_string($colA) || trim($colA) === '') continue;
        if (! is_numeric($colB)) continue;

        $districtCode = $this->districtResolver->resolve(
            trim($colA), $ctx,
            basename($filePath) . " · {$sheet->getTitle()} · row $row",
        );
        if ($districtCode === null) continue;

        $count += $this->emitAnnualRow($ctx, $sheet, $row, $districtCode, $filePath);
    }
    return $count;
}

private function emitAnnualRow(
    ImportContext $ctx,
    Worksheet $sheet,
    int $row,
    ?string $districtCode,
    string $filePath,
): int {
    $value = $this->numericOrNull($sheet->getCell([2, $row])->getCalculatedValue());
    if ($value === null) return 0;

    $dto = new IndicatorFactDto(
        regionCode:     $ctx->regionCode(),
        districtCode:   $districtCode,
        year:           $ctx->year,
        indicatorCode:  'investment',
        period:         'year',
        planValue:      $value,
        expectedValue:  null,
        actualHokimyat: null,
        pctOfPlan:      null,
        countExtra:     null,
        countExtra2:    null,
        unit:           'млн доллар',
        sourceLabel:    basename($filePath) . " · {$sheet->getTitle()} · row $row",
    );
    $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
    return 1;
}
```

### 5.4 parse() entry-point

```php
public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
{
    $reader = IOFactory::createReaderForFile($filePath);
    $reader->setReadDataOnly(false);
    $book = $reader->load($filePath);

    $this->districtResolver->loadFor($ctx->regionCode());

    $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'foreign_invest', 'foreign_invest');
    if ($sheet === null) return 0;

    if ($this->isAnnualOnlyLayout($sheet)) {
        return $this->parseAnnualOnly($ctx, $sheet, $filePath);
    }

    // existing standard-layout body stays unchanged below
    $rollupRow = $this->findRollupRow($sheet);
    if ($rollupRow === null) return 0;

    $count = 0;
    $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);
    for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
        // … unchanged
    }
    return $count;
}
```

## 6. Files

| File | Action |
|---|---|
| `backend/app/Services/Import/SheetResolver.php` | modify: extend `SIGNATURES['foreign_invest']` |
| `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php` | modify: add `isAnnualOnlyLayout`, `parseAnnualOnly`, `emitAnnualRow`; extend `isRollupCell`; branch in `parse` |
| `backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php` | new |
| `backend/tests/Feature/Import/SheetResolverForeignInvestTest.php` | extend: add karakalpak signature case |

No migration, no model, no view changes.

## 7. Tests

### 7.1 Layout-detection unit

`tests/Feature/Import/ForeignInvestAnnualOnlyTest.php`:

```php
test('isAnnualOnlyLayout returns true when no I чорак in first 10 rows', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A4', 'Ҳудудлар');
    $sheet->setCellValue('B4', 'Хорижий инвестициялар прогнози (ВМҚ-86)');

    $parser = $this->app->make(ForeignInvestModuleParser::class);
    expect(invade($parser)->isAnnualOnlyLayout($sheet))->toBeTrue();
});

test('isAnnualOnlyLayout returns false when I чорак present', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A4', 'Шаҳар ва туманлар номи');
    $sheet->setCellValue('I4', 'I чорак');

    $parser = $this->app->make(ForeignInvestModuleParser::class);
    expect(invade($parser)->isAnnualOnlyLayout($sheet))->toBeFalse();
});
```

### 7.2 End-to-end annual-only parse

Build an in-memory `Spreadsheet` mirroring karakalpak structure:

```php
test('parseAnnualOnly emits one year-period row per district + rollup', function () {
    // Seed region 1735 + 14 karakalpak districts.
    // Build sheet with A4=Ҳудудлар, B4=Хорижий инвестициялар, A7=Жами, B7=633,
    // A8=Нукус шаҳри, B8=129, A9=Амударё тумани, B9=18.
    // Save to tmp xlsx, call parser->parse(ctx, $path, $regionWorkbookId).
    // Assert: 3 staging rows (1 rollup + 2 districts), all period='year',
    // plan_value = 633/129/18 respectively, expected_value/actual = null.
});
```

(Concrete fixture body to be written by implementer following existing
`tests/Feature/Import/ForeignInvestModuleParserTest.php` patterns.)

### 7.3 SheetResolver signature test

Extend `tests/Feature/Import/SheetResolverForeignInvestTest.php` with a case where the sheet has `Ҳудудлар` + `ВМҚ-86` in row 4 → expect `resolve()` returns the sheet.

### 7.4 Operator smoke

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php artisan import:region karakalpak 2026
```

Expected: `foreign_invest: 15 rows buffered` (or whatever the actual count is — 14 districts + 1 rollup). No `sheet_missing` blocker. Run status `awaiting_review`.

Then `php artisan import:promote {run_id}` succeeds.

## 8. Risks

- **Risk:** Other regions' foreign_invest sheets gain `Ҳудудлар` text in the future and route to annual-only by accident. *Mitigation:* the layout switch hinges on absence of `'I чорак'`, not on presence of `'Ҳудудлар'`. Standard sheets all carry `'I чорак'` → stay on the standard path.
- **Risk:** Karakalpak district names contain orthography variants (`Қорақалпоғистон`/`Каракалпакстан`). *Mitigation:* `DistrictResolver` already runs through `DistrictNameNormalizer`; covered by Bug-1 (district name normalizer) fixes.
- **Risk:** `findRollupRow`'s extended predicate accidentally matches a non-rollup cell containing `'Жами'`. *Mitigation:* the predicate caps length at 40 chars and matches only exact `'Жами'` (no substring), so titles like `'Жами ҳосил, тонна'` are excluded.
- **Risk:** `parseAnnualOnly` skips a district when col B holds a numeric-as-string with whitespace. *Mitigation:* `is_numeric` handles `'  18'` correctly (leading whitespace allowed) and `$this->numericOrNull` handles trailing whitespace via PHP's float cast. Inspect during smoke; if a real karakalpak district shows zero-row staging, widen the check.
- **Risk:** Future Bug-D-like layout in budget / budget_invest / export modules. *Mitigation:* out of scope; pattern is reusable when needed.
