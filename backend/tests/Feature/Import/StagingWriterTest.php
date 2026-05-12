<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Services\Import\StagingWriter;
use App\Support\Import\IndicatorFactDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeStagingWriterRun(): ImportRun
{
    return ImportRun::create([
        'region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli',
        'status' => 'parsing', 'started_at' => now(),
    ]);
}

test('StagingWriter buffers and flushes DTOs to staging table', function () {
    $this->seed();
    $run = makeStagingWriterRun();

    $writer = new StagingWriter();
    $dto = new IndicatorFactDto(
        regionCode: 1703, districtCode: null, year: 2026,
        indicatorCode: 'grp', period: 'h1',
        planValue: 52100.81, growthPct: 107.16,
        unit: 'млрд сўм', sourceLabel: 'fixture',
    );

    $writer->buffer('import_staging_indicator_facts', $dto->toStagingRow($run->id));
    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(1);

    DB::transaction(fn() => $writer->flush());

    expect(ImportStagingIndicatorFact::count())->toBe(1);
    expect((float) ImportStagingIndicatorFact::first()->plan_value)->toBe(52100.81);
});

test('StagingWriter discard() empties the buffer without writing', function () {
    $this->seed();
    $run = makeStagingWriterRun();

    $writer = new StagingWriter();
    $dto = new IndicatorFactDto(
        regionCode: 1703, districtCode: null, year: 2026,
        indicatorCode: 'grp', period: 'h1',
        unit: 'млрд сўм', sourceLabel: 'fixture',
    );
    $writer->buffer('import_staging_indicator_facts', $dto->toStagingRow($run->id));
    $writer->discard();

    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(0);
    expect(ImportStagingIndicatorFact::count())->toBe(0);
});

test('StagingWriter flushes 250 rows in chunks', function () {
    $this->seed();
    $run = makeStagingWriterRun();

    $andijanDistrictCodes = [
        1703401, 1703408, 1703202, 1703203, 1703206, 1703209, 1703210,
        1703211, 1703214, 1703217, 1703220, 1703224, 1703227, 1703230,
        1703232, 1703236,
    ];
    $writer = new StagingWriter();
    for ($i = 1; $i <= 250; $i++) {
        $dto = new IndicatorFactDto(
            regionCode: 1703,
            districtCode: $andijanDistrictCodes[(($i - 1) % 16)],
            year: 2026,
            indicatorCode: 'industry', period: 'h1',
            planValue: $i, unit: 'млрд сўм', sourceLabel: "row $i",
        );
        $writer->buffer('import_staging_indicator_facts', $dto->toStagingRow($run->id));
    }
    DB::transaction(fn() => $writer->flush());

    expect(ImportStagingIndicatorFact::count())->toBe(250);
});
