<?php

use App\Support\AndijanMapGeometry;

test('VIEWBOX is the prototype viewBox', function () {
    expect(AndijanMapGeometry::VIEWBOX)->toBe('0 0 600 328');
});

test('CELLS has exactly 16 entries', function () {
    expect(AndijanMapGeometry::CELLS)->toHaveCount(16);
});

test('every cell has required fields with correct types', function () {
    foreach (AndijanMapGeometry::CELLS as $cell) {
        expect($cell)
            ->toHaveKeys(['name', 'short', 'cx', 'cy', 'path']);
        expect($cell['name'])->toBeString()->not->toBe('');
        expect($cell['short'])->toBeString()->not->toBe('');
        expect($cell['path'])->toBeString()->toStartWith('M');
        expect($cell['cx'])->toBeFloat();
        expect($cell['cy'])->toBeFloat();
    }
});

test('cell names include the two Andijan cities and the 14 districts', function () {
    $names = array_column(AndijanMapGeometry::CELLS, 'name');
    expect($names)->toContain('Андижон шаҳри', 'Хонобод шаҳри');
    expect(array_filter($names, fn ($n) => str_ends_with($n, 'тумани')))->toHaveCount(14);
});
