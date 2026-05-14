<?php

use App\Support\RegionMapGeometry;

test('Andijan map has at least 14 cells with non-empty paths', function () {
    $geo = RegionMapGeometry::forRegion(1703);
    expect($geo['viewBox'])->toBe('0 0 600 500');
    expect(count($geo['cells']))->toBeGreaterThanOrEqual(14);
    foreach ($geo['cells'] as $cell) {
        expect($cell['path'])->not->toBe('');
        expect($cell['path'])->toMatch('/^M [0-9.]+ [0-9.]+/');
    }
});

test('Andijan cell codes all start with 1703', function () {
    $geo = RegionMapGeometry::forRegion(1703);
    foreach ($geo['cells'] as $cell) {
        if ($cell['code'] !== null) {
            expect((string) $cell['code'])->toStartWith('1703');
        }
    }
});

test('Kashkadarya returns multiple cells with 1710 prefix', function () {
    $geo = RegionMapGeometry::forRegion(1710);
    expect(count($geo['cells']))->toBeGreaterThanOrEqual(10);
    foreach ($geo['cells'] as $cell) {
        if ($cell['code'] !== null) {
            expect((string) $cell['code'])->toStartWith('1710');
        }
    }
});

test('every region returns at least one cell', function () {
    $codes = [1735, 1703, 1706, 1708, 1710, 1712, 1714, 1718, 1722, 1724, 1726, 1727, 1730, 1733];
    foreach ($codes as $code) {
        $geo = RegionMapGeometry::forRegion($code);
        expect(count($geo['cells']))->toBeGreaterThan(0, "region {$code} has zero cells");
    }
});

test('unknown region returns empty cells', function () {
    $geo = RegionMapGeometry::forRegion(9999);
    expect($geo['cells'])->toBe([]);
});
