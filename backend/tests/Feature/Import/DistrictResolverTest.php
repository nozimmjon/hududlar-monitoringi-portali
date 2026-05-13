<?php

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Region;
use App\Services\Import\DistrictResolver;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('reporting_years')->insert([
        'year' => 2026, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('regions')->where('code', 1703)->value('id');

    $rows = [
        ['code' => 1703202, 'name_short' => 'Олтинкўл т.', 'name_full' => 'Олтинкўл тумани',  'name_latin' => 'oltinkol_district',     'kind' => 'district', 'sort_order' => 1],
        ['code' => 1703203, 'name_short' => 'Андижон т.',  'name_full' => 'Андижон тумани',   'name_latin' => 'andijan_district',      'kind' => 'district', 'sort_order' => 2],
        ['code' => 1703209, 'name_short' => 'Бўстон т.',   'name_full' => 'Бўстон тумани',    'name_latin' => 'boston_district',       'kind' => 'district', 'sort_order' => 3],
        ['code' => 1703217, 'name_short' => 'Улуғнор т.',  'name_full' => 'Улуғнор тумани',   'name_latin' => 'ulugnor_district',      'kind' => 'district', 'sort_order' => 4],
        ['code' => 1703230, 'name_short' => 'Шахрихон т.', 'name_full' => 'Шахрихон тумани',  'name_latin' => 'shakhrikhan_district',  'kind' => 'district', 'sort_order' => 5],
        ['code' => 1703401, 'name_short' => 'Андижон ш.',  'name_full' => 'Андижон шаҳри',    'name_latin' => 'andijan_city',          'kind' => 'city',     'sort_order' => 6],
        ['code' => 1703408, 'name_short' => 'Хонобод ш.',  'name_full' => 'Хонобод шаҳри',    'name_latin' => 'khonobod_city',         'kind' => 'city',     'sort_order' => 7],
    ];
    foreach ($rows as $r) {
        DB::table('districts')->insert(array_merge($r, [
            'region_id' => $regionId, 'region_code' => 1703,
            'alt_labels' => null, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }
});

function makeDistrictResolver(): array
{
    $issues = new IssueCollector();
    $resolver = new DistrictResolver($issues);
    $resolver->loadFor(1703);

    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => ImportRunStatus::Parsing,
        'started_at'   => now(),
    ]);
    $ctx = new ImportContext(run: $run, region: $region, year: 2026, dataPath: null);

    return [$resolver, $ctx, $issues];
}

test('exact name_full match still works', function () {
    [$r, $ctx] = makeDistrictResolver();
    expect($r->resolve('Олтинкўл тумани', $ctx, 'src'))->toBe(1703202);
});

test('bare workbook name resolves to district', function () {
    [$r, $ctx] = makeDistrictResolver();
    expect($r->resolve('Олтинкўл', $ctx, 'src'))->toBe(1703202);
});

test('ў to у variant resolves', function () {
    [$r, $ctx] = makeDistrictResolver();
    expect($r->resolve('Бустон', $ctx, 'src'))->toBe(1703209);
});

test('ҳ to х variant resolves', function () {
    [$r, $ctx] = makeDistrictResolver();
    expect($r->resolve('Шаҳриҳон', $ctx, 'src'))->toBe(1703230);
});

test('Latin-p typo resolves', function () {
    [$r, $ctx] = makeDistrictResolver();
    expect($r->resolve('Улуғноp', $ctx, 'src'))->toBe(1703217);
});

test('city resolves via bare-name fallback', function () {
    [$r, $ctx] = makeDistrictResolver();
    expect($r->resolve('Хонобод', $ctx, 'src'))->toBe(1703408);
});

test('shared bare name resolves to district first', function () {
    [$r, $ctx] = makeDistrictResolver();
    expect($r->resolve('Андижон', $ctx, 'src'))->toBe(1703203);
});

test('city resolves via explicit suffix', function () {
    [$r, $ctx] = makeDistrictResolver();
    expect($r->resolve('Андижон шаҳри', $ctx, 'src'))->toBe(1703401);
    expect($r->resolve('Андижон ш.', $ctx, 'src'))->toBe(1703401);
});

test('non-district string returns null and emits issue', function () {
    [$r, $ctx, $issues] = makeDistrictResolver();
    expect($r->resolve('ДСБ солиқ тўловчилари', $ctx, 'src'))->toBeNull();
    expect($issues->bufferedCount())->toBeGreaterThan(0);
});
