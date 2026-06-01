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

test('normalizes real-world token variants before matching', function () {
    // Region 1735 (Karakalpakstan) — "Кегейли тумани" is seeded.
    $kk = District::where('region_code', 1735)->get();
    // Region 1712 (Navoi) — "Навоий шаҳри" and "Навбаҳор тумани" are seeded.
    $navoi = District::where('region_code', 1712)->get();
    // Region 1703 (Andijan) — "Бўстон тумани" is seeded.
    $andijan = District::where('region_code', 1703)->get();
    $unmatched = [];

    // 1. Truncated 'туман' suffix (real DB name ends 'тумани')
    $kegeyli = $kk->first(fn ($d) => str_starts_with($d->name_full, 'Кегейли'));
    expect($kegeyli)->not->toBeNull('Кегейли тумани must be seeded');
    $ids = TaskExecutorResolver::districtIds('Кегейли туман ҳокимлиги', $kk, $unmatched);
    expect($ids)->toContain($kegeyli->id);

    // 2. 'шаҳар' instead of 'шаҳри'
    $navoiCity = $navoi->first(fn ($d) => str_starts_with($d->name_full, 'Навоий шаҳ'));
    expect($navoiCity)->not->toBeNull('Навоий шаҳри must be seeded');
    $ids = TaskExecutorResolver::districtIds('Навоий шаҳар ҳокимлиги', $navoi, $unmatched);
    expect($ids)->toContain($navoiCity->id);

    // 3. Double spaces
    $boston = $andijan->first(fn ($d) => str_starts_with($d->name_full, 'Бўстон'));
    expect($boston)->not->toBeNull('Бўстон тумани must be seeded');
    $ids = TaskExecutorResolver::districtIds('Бўстон  тумани ҳокимлиги', $andijan, $unmatched);
    expect($ids)->toContain($boston->id);

    // 4. Truncated 'ҳокимлиг' (typo — missing final и)
    $ids = TaskExecutorResolver::districtIds($boston->name_full . ' ҳокимлиг', $andijan, $unmatched);
    expect($ids)->toContain($boston->id);

    // 5. Latin lookalike letters (Latin 'o' U+006F in place of Cyrillic 'о' U+043E)
    $navbaxor = $navoi->first(fn ($d) => str_starts_with($d->name_full, 'Навбаҳор'));
    expect($navbaxor)->not->toBeNull('Навбаҳор тумани must be seeded');
    // Inject Latin 'o' in "Навбаҳoр" (3rd char from end)
    $ids = TaskExecutorResolver::districtIds("Навбаҳoр тумани ҳокимлиги", $navoi, $unmatched);
    expect($ids)->toContain($navbaxor->id);

    // 6. Missing space: "Навбаҳортумани" → should match "Навбаҳор тумани"
    // (This pattern is handled via missing-space normalization)
    // Skipped for now — patterns 1-5 cover the real unmatched tokens from the workbook.

    expect($unmatched)->toBe([]);
});
