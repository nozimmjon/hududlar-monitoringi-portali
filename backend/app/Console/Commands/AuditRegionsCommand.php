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
