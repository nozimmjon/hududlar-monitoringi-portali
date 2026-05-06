<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\InflationModuleParser;
use App\Services\Import\Modules\MacroModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use App\Services\Import\WorkbookLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRegionCommand extends Command
{
    protected $signature = 'import:region {region_code} {year} {--module=} {--dry-run}';
    protected $description = 'Import workbook data for one region+year into staging tables.';

    public function handle(): int
    {
        $regionCode = (string) $this->argument('region_code');
        $year = (int) $this->argument('year');

        $region = Region::where('code', $regionCode)->first();
        if (! $region) {
            $this->error("Unknown region code: $regionCode");
            return 1;
        }

        if ($regionCode === 'navoiy') {
            $this->warn("Skipped 'navoiy' — see data_quality_issues for upstream macro 1.2 contamination.");
            return 0;
        }

        $run = ImportRun::create([
            'region_code'   => $regionCode,
            'year'          => $year,
            'trigger_kind'  => 'cli',
            'status'        => ImportRunStatus::Parsing,
            'started_at'    => now(),
        ]);

        $ctx = new ImportContext(
            run: $run,
            region: $region,
            year: $year,
            dataPath: config('import.data_path'),
        );

        $issues = new IssueCollector();
        $writer = new StagingWriter();
        $sheetResolver = new SheetResolver($issues);
        $headerDetector = new HeaderDetector($issues);
        $districtResolver = new DistrictResolver($issues);

        $locator = new WorkbookLocator();
        $files = $locator->locate($ctx, $this->option('module'));

        $parsers = [
            'macro'     => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
            'inflation' => new InflationModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
        ];

        $this->info("Importing region '$regionCode' year $year (modules: " . implode(', ', array_keys($files)) . ")…");

        $yearId = DB::table('reporting_years')->where('year', $year)->value('id');

        $filesProcessed = 0;
        foreach ($files as $module => $filePath) {
            if (! isset($parsers[$module])) {
                $this->line("  · $module: no parser implemented yet, skipping");
                continue;
            }
            $moduleId = DB::table('modules')->where('code', $module)->value('id');
            $rwb = RegionWorkbook::firstOrCreate(
                ['region_id' => $region->id, 'reporting_year_id' => $yearId, 'module_id' => $moduleId],
                ['file_name' => basename($filePath), 'file_path' => $filePath, 'last_seen_at' => now()],
            );
            $count = $parsers[$module]->parse($ctx, $filePath, $rwb->id);
            $this->line("  · $module: $count rows buffered");
            $filesProcessed++;
        }

        $blockerCount = $issues->blockerCount();

        if ($blockerCount > 0) {
            $writer->discard();
            $issues->flush();
            $run->update([
                'status' => ImportRunStatus::Failed, 'failed_at' => now(),
                'files_processed' => $filesProcessed,
                'issues_open_count' => 0, 'issues_blocker_count' => $blockerCount,
            ]);
            $this->error("Run #{$run->id} failed: $blockerCount blocker issue(s).");
            return 1;
        }

        if ($this->option('dry-run')) {
            $rows = $writer->totalCount();
            $writer->discard();
            $issues->flush();
            $run->update([
                'status' => ImportRunStatus::AwaitingReview, 'parsed_at' => now(),
                'files_processed' => $filesProcessed,
                'rows_staged' => 0,
                'issues_open_count' => $issues->bufferedCount(),
                'issues_blocker_count' => 0,
                'notes' => "Dry run: $rows rows would have been staged.",
            ]);
            $this->info("Dry run complete. Would have staged $rows rows.");
            return 0;
        }

        DB::transaction(fn() => $writer->flush());
        $issuesWritten = $issues->flush();

        $rowsStaged = DB::table('import_staging_indicator_facts')->where('import_run_id', $run->id)->count();
        $run->update([
            'status' => ImportRunStatus::AwaitingReview,
            'parsed_at' => now(),
            'files_processed' => $filesProcessed,
            'rows_staged' => $rowsStaged,
            'issues_open_count' => $issuesWritten,
            'issues_blocker_count' => 0,
        ]);

        $this->info("Run #{$run->id}: $rowsStaged rows staged, $issuesWritten issues. Status: awaiting_review.");
        return 0;
    }
}
