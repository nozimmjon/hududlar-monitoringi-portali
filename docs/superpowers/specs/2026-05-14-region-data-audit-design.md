# Region xlsx ↔ DB audit command

**Date:** 2026-05-14
**Status:** Approved
**Scope:** New diagnostic `data:audit-regions` artisan command. For each (region, module) pair, prints a table comparing xlsx data row count vs DB promoted district count. Read-only, no fixes.

---

## 1. Goal

Fergana foreign_invest shows zero numbers in UI, but the xlsx has 14 districts. Cause: workbook uses SOATO codes in col B instead of district names — current parser doesn't match. Other regions likely have similar surprises across other modules.

Need a one-shot audit that surfaces every gap so we can prioritize fixes instead of guessing region-by-region.

## 2. Non-goals

- No DB writes, no import re-run, no parser fix.
- No corrective action — diagnostic only.
- No CSV export — terminal table is enough.
- No xlsx structure deep-parse — heuristic row-count heuristic, not full data validation.

## 3. Strategy

Single artisan command:

```bash
php artisan data:audit-regions
```

Outputs a 5-column table: `region | module | xlsx_rows | db_districts | gap`.

For each (region, module):

1. **xlsx_rows**: walk all sheets of the module's workbook, count rows where col A is a sequence number 1..50 OR col B matches a 7-digit SOATO district code. Take `max` across sheets (largest sheet wins — typically the district-level rollup).
2. **db_districts**: distinct count of `district_code` in `indicator_facts` for the module's primary indicator code, year period.
3. **gap = xlsx - db**. Format: `0` (match), `-N ⚠` (data missing), `+N` (xlsx has extra rows — sub-categories).

## 4. Code

`backend/app/Console/Commands/AuditRegionsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AuditRegionsCommand extends Command
{
    protected $signature = 'data:audit-regions {--region=*}';
    protected $description = 'Compare xlsx data row counts to DB promoted facts per region+module.';

    private const MODULES = [
        'macro'          => ['indicator' => 'industry',          'pattern' => '/^1\.1-1\.[45].*макро.*\.xlsx$/u'],
        'inflation'      => ['indicator' => 'inflation',         'pattern' => '/^2\.1-2\.2.*инфляция.*\.xlsx$/u'],
        'budget'         => ['indicator' => 'budget',            'pattern' => '/^3-жадвал.*бюджет.*\.xlsx$/u'],
        'budget_invest'  => ['indicator' => 'budget_investment', 'pattern' => '/^4\.1.*бюджет.*инвест.*\.xlsx$/u'],
        'foreign_invest' => ['indicator' => 'investment',        'pattern' => '/^4\.2.*инвестиция.*\.xlsx$/u'],
        'export'         => ['indicator' => 'export',            'pattern' => '/^5\.1-5\.2.*экспорт.*\.xlsx$/u'],
        'employment'     => ['indicator' => 'poverty',           'pattern' => '/^6-жадвал.*бандлик.*\.xlsx$/u'],
    ];

    public function handle(): int
    {
        $only = (array) $this->option('region');
        $query = Region::query()->orderBy('sort_order');
        if (! empty($only)) $query->whereIn('name_latin', $only);
        $regions = $query->get();

        $rows = [];
        foreach ($regions as $r) {
            $folder = $this->resolveFolder($r);
            foreach (self::MODULES as $module => $cfg) {
                $xlsxPath = $folder ? $this->matchFile($folder, $cfg['pattern']) : null;
                $xlsx     = $xlsxPath ? $this->countXlsxDataRows($xlsxPath) : 0;
                $db       = $this->countDbDistricts($r->code, $cfg['indicator']);
                $gap      = $xlsx - $db;
                $rows[]   = [
                    $r->code . ' ' . $r->name_latin,
                    $module,
                    $xlsx,
                    $db,
                    $gap === 0 ? '0' : ($gap < 0 ? "{$gap} ⚠" : "+{$gap}"),
                ];
            }
        }

        $this->table(['region', 'module', 'xlsx', 'db', 'gap'], $rows);
        return self::SUCCESS;
    }

    private function resolveFolder(Region $r): ?string
    {
        $candidates = [
            base_path('..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . ($r->folder_name ?? sprintf('%d. %s', $r->sort_order, $r->name_short))),
        ];
        foreach ($candidates as $c) {
            if (is_dir($c)) return $c;
        }
        return null;
    }

    private function matchFile(string $folder, string $pattern): ?string
    {
        foreach (scandir($folder) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (preg_match($pattern, $entry)) {
                return $folder . DIRECTORY_SEPARATOR . $entry;
            }
        }
        return null;
    }

    private function countXlsxDataRows(string $filePath): int
    {
        $book = IOFactory::load($filePath);
        $count = 0;
        foreach ($book->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            $sheetCount = 0;
            for ($i = 0; $i < count($rows); $i++) {
                $colA = $rows[$i][0] ?? null;
                $colB = $rows[$i][1] ?? null;
                $aIsSeq = (is_int($colA) && $colA >= 1 && $colA <= 50)
                    || (is_string($colA) && ctype_digit(trim($colA)) && (int) $colA >= 1 && (int) $colA <= 50);
                $bIsCode = is_string($colB) && preg_match('/^17\d{5}$/', trim($colB));
                $bIsCodeInt = is_int($colB) && $colB >= 1700000 && $colB <= 1799999;
                if ($aIsSeq || $bIsCode || $bIsCodeInt) {
                    $sheetCount++;
                }
            }
            if ($sheetCount > $count) $count = $sheetCount;
        }
        return $count;
    }

    private function countDbDistricts(int $regionCode, string $indicatorCode): int
    {
        return (int) DB::table('indicator_facts')
            ->where('region_code', $regionCode)
            ->where('indicator_code', $indicatorCode)
            ->where('period', 'year')
            ->whereNotNull('district_code')
            ->distinct('district_code')
            ->count('district_code');
    }
}
```

## 5. Files

| File | Action |
|---|---|
| `backend/app/Console/Commands/AuditRegionsCommand.php` | new |

No tests (diagnostic command; output verified manually).

## 6. Operator smoke

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:all-regions 2026
php artisan data:audit-regions
```

Expected: table with 14×7=98 rows. Gap=0 for healthy pairs. `-N ⚠` flags missing data (e.g. fergana foreign_invest). `+N` flags xlsx with sub-rows (e.g. export with industrial+agri).

Use the report to triage real bugs in priority order.

## 7. Risks

- **Heuristic noise:** col A sequence numbers in non-district sheets (totals row in some workbooks) could inflate counts by 1-2. Acceptable noise.
- **Indicator-code mismatch:** some modules have multiple indicators (employment: poverty + unemployment); we use one as proxy. Gap interpretation requires operator judgment.
- **Performance:** ~98 xlsx loads, ~60s total. One-off command, acceptable.
- **No test coverage:** diagnostic-only; manual verify.
