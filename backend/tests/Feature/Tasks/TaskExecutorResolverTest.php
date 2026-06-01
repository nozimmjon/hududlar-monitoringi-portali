<?php

use App\Models\District;
use App\Support\TaskExecutorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(); // full seed: regions + districts for all 14 regions
});

test('resolves district hokimliklar, skips region rows', function () {
    $districts = District::where('region_code', 1703)->get();
    $unmatched = [];

    $executor = "Андижон вилояти ҳокимлиги,\nШахрихон тумани ҳокимлиги,\nХонобод шаҳри ҳокимлиги";
    $ids = TaskExecutorResolver::districtIds($executor, $districts, $unmatched);

    expect($ids)->toHaveCount(2);                 // region row skipped, 2 districts matched
    expect($unmatched)->toBe([]);

    $names = District::whereIn('id', $ids)->pluck('name_full')->all();
    expect($names)->toContain('Шахрихон тумани');
    expect($names)->toContain('Хонобод шаҳри');
});

test('collects unmatched tokens', function () {
    $districts = District::where('region_code', 1703)->get();
    $unmatched = [];
    TaskExecutorResolver::districtIds('Несуществующий тумани ҳокимлиги', $districts, $unmatched);
    expect($unmatched)->toContain('Несуществующий тумани');
});
