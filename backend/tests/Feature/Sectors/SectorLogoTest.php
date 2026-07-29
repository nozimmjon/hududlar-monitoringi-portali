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
    $this->seed();

    $sector = Sector::where('code', 'kimyo_sanoati')->firstOrFail();

    expect($sector->logoPath())->toBeNull();
});

test('sector cards render the logo image, with a monogram fallback', function () {
    $this->seed();

    $html = $this->get('/sectors')->getContent();

    expect($html)->toContain('img/sectors/nkmk.svg');
    expect($html)->toContain('img/sectors/uzavtosanoat.png');
    // Sectors without a bundled logo (кимё саноати) show a letter monogram instead.
    expect($html)->toContain('sector-card-mono');
});
