<?php

use App\Enums\IssueSeverity;
use App\Models\ImportRun;
use App\Models\Region;
use App\Services\Import\DistrictResolver;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAndijanCtxForDistrictResolver(): ImportContext
{
    return new ImportContext(
        run: ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: Region::where('code', 1703)->first(),
        year: 2026, dataPath: base_path('../data'),
    );
}

test('DistrictResolver returns code for known full name', function () {
    $this->seed();
    $issues = new IssueCollector();
    $resolver = new DistrictResolver($issues);
    $resolver->loadFor(1703);

    $code = $resolver->resolve('Андижон шаҳри', makeAndijanCtxForDistrictResolver(), 'fixture');

    expect($code)->toBe('1703401');
    expect($issues->bufferedCount())->toBe(0);
});

test('DistrictResolver raises UnknownDistrict issue and returns null on miss', function () {
    $this->seed();
    $issues = new IssueCollector();
    $resolver = new DistrictResolver($issues);
    $resolver->loadFor(1703);

    $code = $resolver->resolve('Совершенно неизвестный район', makeAndijanCtxForDistrictResolver(), 'fixture-row-99');

    expect($code)->toBeNull();
    expect($issues->bufferedCount())->toBe(1);

    $reflection = new ReflectionClass($issues);
    $bufferProp = $reflection->getProperty('buffer');
    $bufferProp->setAccessible(true);
    $buffer = $bufferProp->getValue($issues);
    expect($buffer[0]['issue_kind'])->toBe('unknown_district');
    expect($buffer[0]['severity'])->toBe('high');
});

test('DistrictResolver trims whitespace before lookup', function () {
    $this->seed();
    $issues = new IssueCollector();
    $resolver = new DistrictResolver($issues);
    $resolver->loadFor(1703);

    $code = $resolver->resolve("  Андижон шаҳри  \n", makeAndijanCtxForDistrictResolver(), 'fixture');

    expect($code)->toBe('1703401');
});
