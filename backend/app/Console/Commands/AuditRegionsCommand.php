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
        'macro'          => ['pattern' => '/^1\.1-1\.[45].*макро.*\.xlsx$/u',         'indicators' => ['industry'],                         'sentinel' => false],
        'inflation'      => ['pattern' => '/^2\.1-2\.2.*инфляция.*\.xlsx$/u',         'indicators' => [],                                   'sentinel' => false, 'table' => 'warehouses'],
        'budget'         => ['pattern' => '/^3-жадвал.*бюджет.*\.xlsx$/u',            'indicators' => ['budget'],                           'sentinel' => true],
        'budget_invest'  => ['pattern' => '/^4\.1.*бюджет.*инвест.*\.xlsx$/u',        'indicators' => ['budget_investment'],                'sentinel' => false],
        'foreign_invest' => ['pattern' => '/^4\.2.*инвестиция.*\.xlsx$/u',            'indicators' => ['investment'],                       'sentinel' => false],
        'export'         => ['pattern' => '/^5\.1-5\.2.*экспорт.*\.xlsx$/u',          'indicators' => ['export'],                           'sentinel' => false, 'subrows' => true],
        'employment'     => ['pattern' => '/^6-жадвал.*бандлик.*\.xlsx$/u',           'indicators' => ['poverty', 'unemployment', 'jobs'],  'sentinel' => false],
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
                $xlsxRaw  = $xlsxPath ? $this->countXlsxDistricts($xlsxPath, $cfg['subrows'] ?? false) : 0;
                $xlsx     = $xlsxRaw - (($cfg['sentinel'] ?? false) && $xlsxRaw > 0 ? 1 : 0);
                $db       = $this->countDb($r->code, $cfg);
                $gap      = $xlsx - $db;
                $note     = $this->noteFor($r->code, $module, $gap);
                $rows[]   = [
                    $r->code . ' ' . $r->name_latin,
                    $module,
                    $xlsx,
                    $db,
                    $gap === 0 ? '✓' : ($gap < 0 ? "{$gap} ⚠" : "+{$gap}"),
                    $note,
                ];
            }
        }

        $this->table(['region', 'module', 'xlsx', 'db', 'gap', 'note'], $rows);
        return self::SUCCESS;
    }

    private function resolveFolder(Region $r): ?string
    {
        $folderName = $r->folder_name ?: sprintf('%d. %s', $r->sort_order, $r->name_short);
        $candidate = base_path('..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $folderName);
        return is_dir($candidate) ? $candidate : null;
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

    /**
     * Counts distinct districts referenced in the xlsx.
     * Uses unique SOATO district codes when col B holds them, or unique row labels otherwise.
     * For sub-row layouts (export industrial+agri), counts unique col B labels to avoid double-counting.
     */
    private function countXlsxDistricts(string $filePath, bool $subrows): int
    {
        $book = IOFactory::load($filePath);
        $best = 0;
        foreach ($book->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            $codes = [];
            $names = [];
            for ($i = 0; $i < count($rows); $i++) {
                $colA = $rows[$i][0] ?? null;
                $colB = $rows[$i][1] ?? null;
                $aIsSeq = (is_int($colA) && $colA >= 1 && $colA <= 50)
                    || (is_string($colA) && ctype_digit(trim($colA)) && (int) $colA >= 1 && (int) $colA <= 50);
                if (! $aIsSeq) continue;

                $bStr = is_string($colB) ? trim($colB) : (is_int($colB) ? (string) $colB : '');
                if ($bStr === '') continue;
                if (ctype_digit($bStr) && (int) $bStr >= 1700000 && (int) $bStr <= 1799999) {
                    $codes[$bStr] = true;
                } else {
                    $names[$bStr] = true;
                }
            }
            $sheetCount = max(count($codes), count($names));
            if ($sheetCount > $best) $best = $sheetCount;
        }
        return $best;
    }

    private function countDb(int $regionCode, array $cfg): int
    {
        if (($cfg['table'] ?? null) === 'warehouses') {
            return (int) DB::table('warehouses')
                ->where('region_code', $regionCode)
                ->whereNotNull('district_code')
                ->distinct('district_code')
                ->count('district_code');
        }

        return (int) DB::table('indicator_facts')
            ->where('region_code', $regionCode)
            ->whereIn('indicator_code', $cfg['indicators'])
            ->whereNotNull('district_code')
            ->distinct('district_code')
            ->count('district_code');
    }

    private function noteFor(int $regionCode, string $module, int $gap): string
    {
        if ($gap === 0) return '';
        if ($regionCode === 1714 && in_array($module, ['macro', 'foreign_invest'], true) && $gap === 2) {
            return 'xlsx missing Янги Наманган + Давлатобод (operator)';
        }
        if ($regionCode === 1726 && $module === 'macro' && $gap === 1) {
            return 'xlsx 1.4 ҚХ sheet has Khorezm data (operator)';
        }
        if ($module === 'inflation' && $gap > 0) {
            return 'warehouses table partial — only districts with reserve data';
        }
        if ($gap > 0) {
            return 'inspect: xlsx > db';
        }
        return 'inspect: db > xlsx';
    }
}
