<?php

namespace App\Services\Import;

use App\Models\ImportFile;

class WorkbookLocator
{
    private const PATTERNS = [
        'macro'          => '/^1\.1-1\.[45].*макро.*\.xlsx$/u',
        'inflation'      => '/^2\.1-2\.2.*инфляция.*\.xlsx$/u',
        'budget'         => '/^3-жадвал.*бюджет.*\.xlsx$/u',
        'budget_invest'  => '/^4\.1.*бюджет.*инвест.*\.xlsx$/u',
        'foreign_invest' => '/^4\.2.*инвестиция.*\.xlsx$/u',
        'export'         => '/^5\.1-5\.2.*экспорт.*\.xlsx$/u',
        'employment'     => '/^6-жадвал.*бандлик.*\.xlsx$/u',
    ];

    public function locate(ImportContext $ctx, ?string $moduleFilter = null): array
    {
        $regionFolder = $this->resolveRegionFolder($ctx);
        if (! is_dir($regionFolder)) {
            return [];
        }

        $found = [];
        foreach (scandir($regionFolder) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            foreach (self::PATTERNS as $module => $pattern) {
                if ($moduleFilter !== null && $module !== $moduleFilter) {
                    continue;
                }
                if (preg_match($pattern, $entry)) {
                    $absolute = $regionFolder . DIRECTORY_SEPARATOR . $entry;
                    $found[$module] = $absolute;
                    $this->recordImportFile($ctx, $module, $entry, $absolute);
                    break;
                }
            }
        }

        return $found;
    }

    private function resolveRegionFolder(ImportContext $ctx): string
    {
        $region = $ctx->region;
        if ($region->folder_name) {
            return rtrim($ctx->dataPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $region->folder_name;
        }
        return rtrim($ctx->dataPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . sprintf('%d. %s', $region->sort_order, $region->name_short);
    }

    private function recordImportFile(ImportContext $ctx, string $moduleCode, string $fileName, string $absolutePath): void
    {
        $size = filesize($absolutePath);
        $sha = hash_file('sha256', $absolutePath);

        ImportFile::create([
            'import_run_id' => $ctx->run->id,
            'module_code'   => $moduleCode,
            'file_name'     => $fileName,
            'file_path'     => $absolutePath,
            'sha256'        => $sha,
            'size_bytes'    => $size,
            'sheet_count'   => null,
            'parsed_ok'     => false,
        ]);
    }
}
