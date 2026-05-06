# Importer Tracer Bullet — Design

**Date:** 2026-05-06
**Status:** Approved through Sections 1-3 in brainstorming. Awaiting user spec review before implementation plan.
**Branch:** `v7-design-polish`
**Scope:** First end-to-end implementation of `php artisan import:region` covering the **macro** module only, against **Andijan** for **2026**, with a parity test asserting the staged rows reproduce the inlined `DATA` blob from `index.html`. Subsequent plans (3 onward) extend to other modules and regions.

**Predecessors:**
- Schema spec: `docs/superpowers/specs/2026-05-05-fact-tables-and-import-schema-design.md`
- Schema build-out plan: `docs/superpowers/plans/2026-05-05-schema-build-out.md` (committed at `5eb7faf`)

---

## 1. Context

The schema is built. 14 regions, 208 districts, 20 indicators, and the full set of staging + audit tables are migrated and seeded. Plan 1 verified end-to-end against a Postgres test DB (39 Pest tests, 211 assertions, all green) and the dev DB (`migrate:fresh --seed` clean, psql sanity checks match expected counts).

This plan delivers the first artisan command that reads real workbooks from `data/{region}/` and produces staging rows. The deliverable is intentionally narrow:

- **One module: macro.** The most regular of the 7 modules (5 sheets per region, sheets 1.1-1.5 with stable names within a region). Validates the architecture before committing to per-module parsers for the remaining 6 modules.
- **One region: Andijan.** Workbooks survey already shows Andijan's macro sheet names match expectations; sheet variance is minimal there.
- **Andijan parity assertion.** The inlined `DATA` blob in `index.html` (the v7 prototype's source of truth) becomes the test oracle. If the importer reproduces those values from the `.xlsx` source, the architecture is right.

Out of scope for Plan 2 (deferred to Plans 3+): inflation/budget/budget_invest/foreign_invest/export/employment modules; the other 12 regions; Filament admin UI for review/promote; the staging→production promote flow.

## 2. Constraints

- **PHP-side ingestion** via `phpoffice/phpspreadsheet` (Composer dependency added in this plan).
- **Postgres test DB** for the parity test (`hududlar_monitoringi_test`) — already configured in `phpunit.xml`.
- **Pest 3** for tests — Pest backward-compatible PHPUnit class syntax remains the primary style; new Plan 2 tests can use either Pest functional style or PHPUnit class style.
- **Navoi data import is deferred.** Importer recognizes `region_code='navoiy'`, prints a one-line skip notice, and exits 0 without touching staging.
- **Hybrid sheet resolution** — config-first with auto-detect fallback that writes back to the `region_workbook_sheets` cache.
- **Strict district matching** — exact match against `districts.alt_labels` jsonb. Unmatched strings raise `unknown_district` issues (severity=high), the row is skipped.

## 3. Architecture

Two-tier pipeline. Shared infrastructure services + one per-module parser. Plan 2 adds the macro parser; Plan 3 adds 6 more parsers and reuses everything else.

```
backend/app/
├── Console/Commands/
│   └── ImportRegionCommand.php           # artisan command, orchestrates one ImportRun
├── Services/Import/
│   ├── ImportContext.php                 # value object: ImportRun + Region + year + dataPath
│   ├── WorkbookLocator.php               # filesystem scan → module_code => filePath
│   ├── SheetResolver.php                 # logical_kind → Worksheet (cache + content match)
│   ├── HeaderDetector.php                # data start row, caches into region_workbook_sheets
│   ├── DistrictResolver.php              # workbook string → district_code via alt_labels
│   ├── StagingWriter.php                 # buffered DTOs → bulk insert per table
│   ├── IssueCollector.php                # buffered data_quality_issues → bulk insert
│   └── Modules/
│       ├── ModuleParser.php              # abstract base
│       └── MacroModuleParser.php         # parses 4 macro sheets (1.3 deferred)
├── Enums/
│   └── IssueKind.php                     # backed string enum: SheetMissing, HeaderNotFound, …
└── Support/Import/
    └── IndicatorFactDto.php              # plain readonly DTO

backend/tests/
├── Feature/Import/
│   ├── ImportRegionCommandTest.php       # CLI smoke + behavior
│   └── AndijanMacroParityTest.php        # the parity test
└── Helpers/
    └── IndexHtmlDataExtractor.php        # regex-extracts `const DATA = {...}` from ../index.html
```

**Configuration:**
- `IMPORT_DATA_PATH` env var, defaults to the project root's `../data` (relative to `backend/`). Override for environments where workbooks live elsewhere.

## 4. Data flow

```
1. INSERT import_runs (region_code, year, status='parsing', started_at=now())
       │ (no transaction — the row exists even if parsing fails)
       ▼
2. WorkbookLocator scans IMPORT_DATA_PATH/{region}/, returns module_code => filePath.
       For each file: hash sha256, INSERT import_files row.
       │
       ▼
3. For each module (Plan 2: 'macro' only):
       Resolve module-specific ModuleParser implementation from a registry.
       Call $parser->parse($context, $filePath).
       Inside parse():
         For each logical sheet:
            $sheet = SheetResolver->resolve($ctx, 'macro', 'rollup' | 'district_table' | …)
            $startRow = HeaderDetector->detect($sheet, $ctx, $regionWorkbookSheetId)
            For each data row:
              if col A is integer → district row, $code = DistrictResolver->resolve(col B)
              else if col B contains region keyword → region rollup, $code = NULL
              else → skip (silent)
              StagingWriter->buffer($table, IndicatorFactDto::from(...))
              raise IssueCollector entries as encountered
       │
       ▼
4. After all parsers complete:
       if IssueCollector->blockerCount() > 0:
           # Don't pollute staging.
           StagingWriter->discard()
           IssueCollector->flush()
           UPDATE import_runs (status='failed', failed_at=now)
       else:
           DB::transaction(fn() => StagingWriter->flush())
           IssueCollector->flush()
           UPDATE import_runs (status='awaiting_review', parsed_at=now,
                               files_processed, rows_staged, issues_open_count)
```

## 5. Component contracts

### `ImportContext` (value object)

```php
final readonly class ImportContext
{
    public function __construct(
        public ImportRun $run,
        public Region $region,
        public int $year,
        public string $dataPath,           // absolute path to data/{region}/
    ) {}

    public function regionCode(): string { return $this->region->code; }
}
```

### `WorkbookLocator`

```php
class WorkbookLocator
{
    /**
     * Scan the region's data directory and return a map of module_code => absolute file path.
     * Records each file as an import_files row (sha256, size, sheet_count).
     * Returns only modules whose file exists; missing files raise no issue (caller decides).
     */
    public function locate(ImportContext $ctx, ?string $moduleFilter = null): array;
}
```

Filename patterns from the survey (matches all 14 regions):

| module_code | regex |
| --- | --- |
| `macro` | `^1\.1-1\.[45].*макро.*\.xlsx$` |
| `inflation` | `^2\.1-2\.2.*инфляция.*\.xlsx$` |
| `budget` | `^3-жадвал.*бюджет.*\.xlsx$` |
| `budget_invest` | `^4\.1.*бюджет.*инвест.*\.xlsx$` |
| `foreign_invest` | `^4\.2.*инвестиция.*\.xlsx$` |
| `export` | `^5\.1-5\.2.*экспорт.*\.xlsx$` |
| `employment` | `^6-жадвал.*бандлик.*\.xlsx$` |

For tracer bullet, only `macro` is consumed; the rest are detected and registered in `region_workbooks` but unused.

### `SheetResolver`

```php
class SheetResolver
{
    /**
     * Returns the worksheet for the given logical_kind in this region+module.
     * Cache: query region_workbook_sheets row by (region_workbook_id, logical_kind).
     * Cache miss: scan all sheets, score against signature strings, pick highest scoring,
     *             write the detected sheet name back to region_workbook_sheets.
     * No match above threshold: raise SheetMissing blocker, return null.
     */
    public function resolve(
        ImportContext $ctx,
        Spreadsheet $book,
        int $regionWorkbookId,
        string $moduleCode,
        string $logicalKind,
    ): ?Worksheet;
}
```

Signature strings (initial set, expandable):

| logical_kind | signatures (any match in rows 1-5) |
| --- | --- |
| `rollup` (macro 1.1) | `"ЯҲМ"`, `"асосий иқтисодий кўрсаткич"` |
| `district_industry` (macro 1.2) | `"Саноат маҳсулотларини ишлаб чиқариш"` |
| `district_industry_detail` (macro 1.3) | `"Ҳудудий саноат"` |
| `district_agriculture` (macro 1.4) | `"Қишлоқ хўжалиги маҳсулотларини"` |
| `district_services` (macro 1.5) | `"Бозор хизматлари"` |

### `HeaderDetector`

```php
class HeaderDetector
{
    /**
     * Walk rows 1..15. The data start row is the first row where:
     *   - column A contains an integer (district numbering 1, 2, 3, …), AND
     *   - the row N rows above contains a unit signature like "ҳажми (млрд.сўм)".
     * Cache the detected row into region_workbook_sheets.header_row.
     * No match: raise HeaderNotFound blocker, return null.
     */
    public function detect(
        Worksheet $sheet,
        ImportContext $ctx,
        int $regionWorkbookSheetId,
    ): ?int;
}
```

### `DistrictResolver`

```php
class DistrictResolver
{
    private array $aliasToCode = [];   // built once per region

    public function __construct(
        private IssueCollector $issues,
    ) {}

    public function loadFor(string $regionCode): void
    {
        $this->aliasToCode = [];
        District::where('region_code', $regionCode)->each(function ($d) {
            foreach (json_decode($d->alt_labels ?? '[]', true) as $alias) {
                $this->aliasToCode[$alias] = $d->code;
            }
            // Also include the bare name_short / name_full for safety:
            $this->aliasToCode[$d->name_short] = $d->code;
            $this->aliasToCode[$d->name_full] = $d->code;
        });
    }

    public function resolve(string $workbookString, ImportContext $ctx, string $sourceLabel): ?string
    {
        $key = trim($workbookString);
        if (isset($this->aliasToCode[$key])) {
            return $this->aliasToCode[$key];
        }
        $this->issues->add(
            IssueKind::UnknownDistrict, IssueSeverity::High,
            detail: "District string '$key' did not match any alt_label in region {$ctx->regionCode()}",
            detectedValue: $key, sourceLabel: $sourceLabel,
        );
        return null;
    }
}
```

### `StagingWriter`

```php
class StagingWriter
{
    private array $buffers = [];   // table_name => array of row arrays

    public function buffer(string $table, array $row): void { $this->buffers[$table][] = $row; }

    public function bufferedCount(string $table): int { return count($this->buffers[$table] ?? []); }

    public function totalCount(): int { return array_sum(array_map('count', $this->buffers)); }

    public function discard(): void { $this->buffers = []; }

    /** Returns the total rows flushed across all tables. Wrap in DB::transaction at call site. */
    public function flush(): int
    {
        $count = 0;
        foreach ($this->buffers as $table => $rows) {
            // chunked insert to keep payload manageable
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($table)->insert($chunk);
                $count += count($chunk);
            }
        }
        $this->buffers = [];
        return $count;
    }
}
```

### `IssueCollector`

```php
class IssueCollector
{
    private array $issues = [];     // array of issue rows

    public function add(
        IssueKind $kind,
        IssueSeverity $severity,
        string $detail,
        ?string $regionCode = null,
        ?string $districtCode = null,
        ?string $indicatorCode = null,
        ?int $year = null,
        ?string $period = null,
        ?string $detectedValue = null,
        ?string $expectedValue = null,
        ?string $sourceLabel = null,
        ?int $importRunId = null,
    ): void;

    public function blockerCount(): int;
    public function flush(): int;   // bulk insert into data_quality_issues
}
```

### `IssueKind` enum

```php
enum IssueKind: string
{
    case SheetMissing       = 'sheet_missing';
    case HeaderNotFound     = 'header_not_found';
    case UnknownDistrict    = 'unknown_district';
    case CrossRegionData    = 'cross_region_data';
    case Sentinel           = 'sentinel';
    case SumMismatch        = 'sum_mismatch';
    case NegativeValue      = 'negative_value';
    case UnitMismatch       = 'unit_mismatch';
    case MissingRow         = 'missing_row';
    case Typo               = 'typo';
}
```

### `IndicatorFactDto`

```php
final readonly class IndicatorFactDto
{
    public function __construct(
        public string $regionCode,
        public ?string $districtCode,
        public int $year,
        public string $indicatorCode,
        public string $period,
        public ?float $planValue = null,
        public ?float $actualHokimyat = null,
        public ?float $growthPct = null,
        public string $unit = '',
        public string $sourceLabel = '',
        public bool $isSentinel = false,
        public ?string $sentinelLabel = null,
    ) {}

    public function toStagingRow(int $importRunId): array;
}
```

## 6. `MacroModuleParser`

Loads the workbook once via PhpSpreadsheet `setReadDataOnly(true)`. Parses 4 sheets in order; **`1.3 Ҳудудий саноат` is intentionally skipped** for the tracer bullet (special-zone breakdown adds 12+ extra columns and brings the localization indicator into scope — Plan 3's territory).

| Sheet | logical_kind | Output rows | indicator_code(s) | district_code |
| --- | --- | --- | --- | --- |
| `1.1 ЯҲМ` | `rollup` | 5 indicators × 4 periods = 20 | grp, industry, agriculture, construction, services | NULL |
| `1.2 Саноат` | `district_industry` | 16 districts × 4 periods = 64 | industry | from DistrictResolver |
| `1.4 ҚХ` | `district_agriculture` | 16 × 4 = 64 | agriculture | … |
| `1.5 Бозор хизматлари` | `district_services` | 16 × 4 = 64 | services | … |

**Total expected for Andijan macro: 212 rows.**

### Macro 1.1 row layout (from survey)

```
Row 4: header     "№" "Кўрсаткичлар" "январь-март (амалда)" … "2026 йил (прогноз)"
Row 5: sub-header "" "" "ҳажми (млрд.сўм)" "ўсиш суръати (%)" × 4 period blocks
Row 6: indicator 1, ЯҲМ, q1_value, q1_growth, h1_value, h1_growth, m9_value, m9_growth, year_value, year_growth
Row 7: indicator 2, Саноат, …
Row 8: indicator 3, Қишлоқ хўжалиги, …
Row 9: indicator 4, Қурилиш, …
Row 10: indicator 5, Бозор хизматлари, …
```

Indicator label → code mapping in the parser:

```php
private const INDICATOR_BY_LABEL = [
    'ЯҲМ'                            => 'grp',
    'Саноат маҳсулотлари'            => 'industry',
    'Қишлоқ хўжалиги маҳсулотлари'   => 'agriculture',
    'Қурилиш ишлари'                 => 'construction',
    'Бозор хизматлари'               => 'services',
];
```

Each row produces 4 `IndicatorFactDto`s — one per period. `plan_value` is set to the workbook's "value" column for that period; `actual_hokimyat` is set for `period='q1'` only (the workbook's "амалда" column is the hokimyat-reported actual). `growth_pct` is the corresponding growth column.

### Macro 1.2 / 1.4 / 1.5 row layout

```
Row 7: region rollup row — col A empty, col B "Андижон вилояти" (or typo "Анджижон вилояти")
Row 8: district 1, "Андижон шаҳри", q1_value, q1_growth, …
Row 9: district 2, "Хонобод шаҳри", …
…
Row 23: district 16, "Шаҳрихон тумани", …
```

The parser emits **one row** per district per period (16 × 4 = 64). The region rollup row in row 7 is **silently skipped** — the canonical rollup comes from sheet 1.1 already. If row 7's value disagrees with sheet 1.1's by more than ±1%, raise a `sum_mismatch` issue (medium severity).

### Distinguishing district rows from rollup rows

```php
$colA = $sheet->getCell([1, $row])->getValue();   // 1-indexed col A
$colB = $sheet->getCell([2, $row])->getValue();

if (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)))) {
    // district row — DistrictResolver handles col B
} elseif (is_string($colB) && str_contains($colB, 'вилояти')) {
    // region rollup row — skipped in 1.2/1.4/1.5; emitted in 1.1
} else {
    // blank / junk / footer — skip
}
```

## 7. CLI

```
php artisan import:region <region_code> <year>
   [--module=macro]    # default: all detected modules in data/{region}/. Tracer bullet uses 'macro'.
   [--dry-run]         # parse + collect issues but skip the writes (StagingWriter.discard() at the end)
   [-v|--verbose]      # log each parsed row + issue to stdout
```

**Validation:**
- `region_code` must exist in `regions.code`.
- `year` must exist in `reporting_years.year`.
- `region_code === 'navoiy'` → print warning, exit 0 (no `import_runs` row, nothing changes). Per-spec deferral.

**Output (success path):**

```
Importing region 'andijan' year 2026 (modules: macro)…
  ✓ macro: 4 sheets parsed, 212 rows staged, 0 blocker issues
  → import_run #1 status: awaiting_review
```

**Output (failure path):**

```
Importing region 'andijan' year 2026 (modules: macro)…
  ✗ macro: 1 blocker issue (header_not_found in '1.4. ҚХ')
  → import_run #2 status: failed
```

## 8. Parity test

`tests/Feature/Import/AndijanMacroParityTest.php`:

```php
test('Andijan macro import reproduces the inlined DATA blob', function () {
    $this->seed();

    Artisan::call('import:region', [
        'region_code' => 'andijan',
        'year'        => 2026,
        '--module'    => 'macro',
    ]);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $importRun = ImportRun::latest()->first();
    expect($importRun->status)->toBe(ImportRunStatus::AwaitingReview);
    expect($importRun->issues_blocker_count)->toBe(0);

    $rows = ImportStagingIndicatorFact::where('import_run_id', $importRun->id)->get();
    expect($rows)->toHaveCount(212);

    // Region rollup: 5 indicators × 4 periods = 20 rows
    foreach ($expected['regional']['macro'] as $i => $expectedRow) {
        $code = ['grp','industry','agriculture','construction','services'][$i];
        foreach (['q1','h1','m9','year'] as $period) {
            $actual = $rows->firstWhere(fn($r) =>
                $r->indicator_code === $code &&
                $r->district_code === null &&
                $r->period->value === $period
            );
            expect($actual)->not->toBeNull("missing rollup row $code/$period");
            expect((float) $actual->plan_value)->toBeNumericallyClose($expectedRow["{$period}_value"], 1e-6);
            expect((float) $actual->growth_pct)->toBeNumericallyClose($expectedRow["{$period}_growth"], 1e-4);
        }
    }

    // District rows: 16 districts × 3 indicators × 4 periods = 192 rows
    foreach ($expected['districts'] as $expectedDistrict) {
        $districtCode = resolveAndijanDistrictCode($expectedDistrict['name']);
        foreach (['industry','agriculture','services'] as $indicator) {
            $block = $expectedDistrict['data'][$indicator];
            foreach (['q1','h1','m9','year'] as $period) {
                $actual = $rows->firstWhere(fn($r) =>
                    $r->indicator_code === $indicator &&
                    $r->district_code === $districtCode &&
                    $r->period->value === $period
                );
                expect($actual)->not->toBeNull(
                    "missing district row $districtCode/$indicator/$period"
                );
                expect((float) $actual->plan_value)->toBeNumericallyClose($block["{$period}_value"], 1e-6);
                expect((float) $actual->growth_pct)->toBeNumericallyClose($block["{$period}_growth"], 1e-4);
            }
        }
    }
});
```

Helpers:

- `IndexHtmlDataExtractor::extract($path): array` — regex `/const DATA\s*=\s*(\{.*?\});\s*\n/s` on the html, `json_decode` the captured group.
- `expect()->toBeNumericallyClose($expected, $tolerance)` — custom Pest expectation defined in `tests/Pest.php` to wrap `abs($value - $expected) <= $tolerance`.
- `resolveAndijanDistrictCode(string $workbookName): string` — test helper that looks up the district code by `name_full` against the seed JSON, since the test asserts via the DATA blob's district names.

**Tolerance reasoning:** `decimal(20,6)` storage gives exact precision for the workbook values. `1e-6` matches that. `growth_pct` with `decimal(10,4)` allows `1e-4` tolerance for the trailing-decimal differences between the workbook's pre-computed % and the DATA blob's higher-precision values.

## 9. Out of scope (deferred)

- **Plans 3-8: remaining 6 modules** (one plan each: inflation/food_balance/warehouses, budget, budget_invest, foreign_invest, export, employment). Each adds one `ModuleParser` and reuses everything in this plan.
- **Plan 9: roll out to 12 more regions.** No code changes expected — the importer should "just work" against each region's data folder. Issues that surface go through Filament for triage.
- **Plan 10: Filament admin UI** for `import_runs` review with the staging-vs-production diff, plus the Promote/Reject actions that move rows from `import_staging_*` into the production fact tables.
- **Sentinel-aware tests** — once the `poverty` indicator imports (employment module), the `холи ҳудуд` sentinel becomes exercisable. Tracer bullet doesn't touch it.
- **Cross-region contamination detector** — when the importer is rolled out to other regions (Plan 9), add a guard that compares each parsed district name to the region being imported. If a district resolves to a different region's code (the Navoi case: parser sees `"Термиз шаҳри"` while importing `navoiy`), raise `cross_region_data` blocker. Skipped for tracer bullet because Andijan's macro 1.2 is clean.

## 10. Migration plan summary

Order of files this plan creates:

```
backend/app/Console/Commands/ImportRegionCommand.php
backend/app/Services/Import/ImportContext.php
backend/app/Services/Import/WorkbookLocator.php
backend/app/Services/Import/SheetResolver.php
backend/app/Services/Import/HeaderDetector.php
backend/app/Services/Import/DistrictResolver.php
backend/app/Services/Import/StagingWriter.php
backend/app/Services/Import/IssueCollector.php
backend/app/Services/Import/Modules/ModuleParser.php
backend/app/Services/Import/Modules/MacroModuleParser.php
backend/app/Enums/IssueKind.php
backend/app/Support/Import/IndicatorFactDto.php
backend/tests/Feature/Import/ImportRegionCommandTest.php
backend/tests/Feature/Import/AndijanMacroParityTest.php
backend/tests/Helpers/IndexHtmlDataExtractor.php
```

Plus:
- `composer require phpoffice/phpspreadsheet`
- One-line addition to `tests/Pest.php` for the `toBeNumericallyClose` expectation.
- New env var `IMPORT_DATA_PATH` documented in `.env.example`.

The implementation plan (next phase, via writing-plans skill) decomposes each component into TDD-style tasks, with the parity test as the final integration milestone.
