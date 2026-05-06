<?php

use Tests\Helpers\IndexHtmlDataExtractor;

test('extract returns regional and districts keys from the v7 prototype', function () {
    $path = base_path('../index.html');
    if (! file_exists($path)) {
        $this->markTestSkipped('index.html not present');
    }

    $data = (new IndexHtmlDataExtractor())->extract($path);

    expect($data)->toHaveKey('regional');
    expect($data)->toHaveKey('districts');
    expect($data['meta']['region'])->toBe('Андижон вилояти');
});

test('extract surfaces 5 macro indicators in regional', function () {
    $path = base_path('../index.html');
    if (! file_exists($path)) {
        $this->markTestSkipped('index.html not present');
    }

    $data = (new IndexHtmlDataExtractor())->extract($path);

    expect($data['regional']['macro'])->toHaveCount(5);
    expect($data['regional']['macro'][0]['indicator'])->toBe('ЯҲМ');
});

test('extract surfaces 16 districts each with industry/agriculture/services blocks', function () {
    $path = base_path('../index.html');
    if (! file_exists($path)) {
        $this->markTestSkipped('index.html not present');
    }

    $data = (new IndexHtmlDataExtractor())->extract($path);

    expect($data['districts'])->toHaveCount(16);
    expect($data['districts'][0]['data'])->toHaveKey('industry');
    expect($data['districts'][0]['data'])->toHaveKey('agriculture');
    expect($data['districts'][0]['data'])->toHaveKey('services');
});
