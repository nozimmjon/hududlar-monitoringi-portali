<?php

use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('sectors table exists with expected columns', function () {
    foreach (['id', 'code', 'name_short', 'org_full', 'signer_text', 'sort_order'] as $col) {
        expect(Schema::hasColumn('sectors', $col))->toBeTrue("missing column {$col}");
    }
});

test('seeder creates 17 sectors with stable codes in sheet order', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);

    expect(Sector::count())->toBe(17);
    expect(Sector::orderBy('sort_order')->pluck('code')->all())->toBe([
        'uzbekneftgaz', 'uzbekgidroenergo', 'ies', 'kimyo_sanoati', 'nkmk',
        'navoiyuran', 'olmaliq_kmk', 'uzmetkombinat', 'tmk', 'uzavtosanoat',
        'uzeltehsanoat', 'yengil_sanoat', 'uztoqimachiliksanoat', 'uzcharmsanoat',
        'qurilish_materiallari', 'farmatsevtika', 'uzbekzargarsanoati',
    ]);
    expect(Sector::where('code', 'nkmk')->first()->name_short)->toBe('НКМК');
});

test('seeder is idempotent', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);
    $this->seed(Database\Seeders\SectorSeeder::class);
    expect(Sector::count())->toBe(17);
});
