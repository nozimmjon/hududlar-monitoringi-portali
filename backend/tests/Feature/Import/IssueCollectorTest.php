<?php

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\DataQualityIssue;
use App\Models\ImportRun;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('IssueCollector buffers and flushes issues to data_quality_issues', function () {
    $this->seed();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);

    $collector = new IssueCollector();
    $collector->add(
        kind: IssueKind::UnknownDistrict, severity: IssueSeverity::High,
        detail: 'unknown district X', regionCode: 'andijan',
        detectedValue: 'Some unknown', importRunId: $run->id,
    );
    $collector->add(
        kind: IssueKind::Sentinel, severity: IssueSeverity::Medium,
        detail: 'холи ҳудуд in poverty.year', regionCode: 'andijan',
        importRunId: $run->id,
    );

    expect($collector->blockerCount())->toBe(0);
    $written = $collector->flush();
    expect($written)->toBe(2);
    expect(DataQualityIssue::count())->toBe(2);
});

test('IssueCollector counts blocker severity issues', function () {
    $collector = new IssueCollector();
    $collector->add(IssueKind::HeaderNotFound, IssueSeverity::Blocker, 'detail', regionCode: 'andijan');
    $collector->add(IssueKind::SheetMissing, IssueSeverity::Blocker, 'detail', regionCode: 'andijan');
    $collector->add(IssueKind::Typo, IssueSeverity::Low, 'detail', regionCode: 'andijan');

    expect($collector->blockerCount())->toBe(2);
});
