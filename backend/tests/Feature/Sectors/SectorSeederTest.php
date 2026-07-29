<?php

use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('sectors table exists with expected columns', function () {
    foreach (['id', 'code', 'name_short', 'display_name', 'org_full', 'signer_text', 'sort_order'] as $col) {
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

test('display_name overrides generic sheet labels with real organisation names', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);

    // Sheet-matching name_short stays untouched (import relies on it);
    // the card shows display_name when the label is not the real org name.
    expect(Sector::where('code', 'yengil_sanoat')->first()->display_name)->toBe('Енгил саноат агентлиги');
    expect(Sector::where('code', 'qurilish_materiallari')->first()->display_name)->toBe('Ўзсаноатқурилишматериаллари');
    expect(Sector::where('code', 'farmatsevtika')->first()->display_name)->toBe('Фармацевтика агентлиги');
    expect(Sector::where('code', 'ies')->first()->display_name)->toBe('Иссиқлик электр станциялари');
    // Codes whose short label already is the real name keep display_name null.
    expect(Sector::where('code', 'nkmk')->first()->display_name)->toBeNull();
});

test('seeder is idempotent', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);
    $this->seed(Database\Seeders\SectorSeeder::class);
    expect(Sector::count())->toBe(17);
});
