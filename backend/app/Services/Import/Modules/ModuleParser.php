<?php

namespace App\Services\Import\Modules;

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;

abstract class ModuleParser
{
    public function __construct(
        protected SheetResolver $sheetResolver,
        protected HeaderDetector $headerDetector,
        protected DistrictResolver $districtResolver,
        protected StagingWriter $stagingWriter,
        protected IssueCollector $issueCollector,
    ) {}

    abstract public function moduleCode(): string;

    abstract public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int;
}
