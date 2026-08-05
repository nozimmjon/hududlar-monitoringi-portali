<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the map page links back to the starter page, not to sectors', function () {
    $this->seed();

    $response = $this->get('/regions');

    $response->assertOk();
    $response->assertSee('href="' . route('home') . '"', escape: false);
    $response->assertSee('Бош саҳифа');
    $response->assertDontSee('href="' . route('sectors') . '"', escape: false);
});
