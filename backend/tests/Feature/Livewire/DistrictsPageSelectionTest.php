<?php

use App\Livewire\DistrictsPage;
use App\Models\District;
use App\Support\AndijanMapGeometry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('selectDistrict updates selectedDistrict when code matches', function () {
    $district = District::where('region_code', 1703)
        ->where('sort_order', '>', 1)
        ->orderBy('sort_order')
        ->first();
    expect($district)->not->toBeNull();

    $component = Livewire::test(DistrictsPage::class)
        ->call('selectDistrict', (string) $district->code);

    $selected = invade($component->instance())->selectedDistrict();
    expect($selected['district']->code)->toBe($district->code);
});

test('every map geometry cell name matches a District row', function () {
    foreach (AndijanMapGeometry::CELLS as $cell) {
        $match = District::where('region_code', 1703)
            ->where('name_full', $cell['name'])
            ->first();
        expect($match)->not->toBeNull("Missing district for map cell '{$cell['name']}'");
    }
});
