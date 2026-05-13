<?php

namespace App\Services\Import;

use App\Models\ImportRun;
use App\Models\Region;

final readonly class ImportContext
{
    public function __construct(
        public ImportRun $run,
        public Region $region,
        public int $year,
        public ?string $dataPath,
    ) {}

    public function regionCode(): string
    {
        return $this->region->code;
    }
}
