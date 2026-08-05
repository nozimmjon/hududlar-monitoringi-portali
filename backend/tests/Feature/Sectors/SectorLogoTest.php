<?php

use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('logoPath returns the bundled svg asset when one exists', function () {
    $this->seed();

    $sector = Sector::where('code', 'nkmk')->firstOrFail();

    expect($sector->logoPath())->toBe('img/sectors/nkmk.svg');
});

test('logoPath falls back to png when there is no svg', function () {
    $this->seed();

    $sector = Sector::where('code', 'uzavtosanoat')->firstOrFail();

    expect($sector->logoPath())->toBe('img/sectors/uzavtosanoat.png');
});

test('logoPath is null when no logo file is bundled', function () {
    $sector = Sector::create([
        'code'       => 'no_logo_sector',
        'name_short' => 'Тест',
        'org_full'   => 'Тест ташкилоти',
        'sort_order' => 99,
    ]);

    expect($sector->logoPath())->toBeNull();
});

test('every seeded sector card renders its logo image', function () {
    $this->seed();

    $html = $this->get('/sectors')->getContent();

    expect($html)->toContain('img/sectors/nkmk.svg');
    expect($html)->toContain('img/sectors/uzavtosanoat.png');
    expect($html)->toContain('img/sectors/kimyo_sanoati.png');
    expect($html)->toContain('img/sectors/farmatsevtika.svg');
    // All 17 have a bundled logo now — no monogram fallback in the page.
    expect($html)->not->toContain('sec-mono');
});
