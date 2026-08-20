<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('web responses allow framing only from self and du.egov.uz', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertHeader(
        'Content-Security-Policy',
        "frame-ancestors 'self' https://du.egov.uz",
    );
});
