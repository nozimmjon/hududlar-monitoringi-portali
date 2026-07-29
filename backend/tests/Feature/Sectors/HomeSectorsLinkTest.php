<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home page links to the sectors dashboard', function () {
    $this->seed();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('href="' . route('sectors') . '"', escape: false);
    $response->assertSee('Тармоқ корхоналари');
});
