<?php
// backend/tests/Feature/Http/HomePageTest.php

use App\Models\Task;
use App\Support\CountryMapGeometry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('GET / renders the entry map page with per-region task stats', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1',
        'status' => 'done', 'headline_plan' => 10,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2',
        'status' => 'open', 'headline_plan' => 5,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
    ]);
    // Plan-less task must NOT count anywhere.
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3',
        'status' => 'open', 'headline_plan' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Ҳудудий топшириқлар ижроси');
    $response->assertSee('Тошкент ш.');           // disambiguated short label
    $response->assertSee('I ярим йиллик');         // deadline filter control
    $response->assertSee('Барча топшириқлар');
    // "all" filter: both planned tasks; "h1" filter: only the half-year one.
    $response->assertSee('"total":2', false);
    $response->assertSee('"total":1', false);
    $response->assertSee('"done":1', false);
    $response->assertDontSee('layouts.app');       // standalone page, no app shell
});

test('the entry map counts Бажарилмоқда tasks on the done side', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1',
        'status' => 'done', 'headline_plan' => 10,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2',
        'status' => 'in_progress', 'headline_plan' => 5,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3',
        'status' => 'open', 'headline_plan' => 7,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);

    $response = $this->get('/');

    // 3 planned tasks: done + in_progress on the done side, only 'open' left over.
    $response->assertOk();
    $response->assertSee('"total":3,"done":2,"open":1', false);
});

test('GET / works with no tasks at all', function () {
    $this->get('/')->assertOk()->assertSee('Ҳудудий топшириқлар ижроси');
});

test('clicking a region switches the session region and opens the dashboard', function () {
    $response = $this->get('/region/1714');

    $response->assertRedirect(route('dashboard'));
    expect(session('region_code'))->toBe(1714);
});

test('an unknown region code keeps the current session region', function () {
    session(['region_code' => 1703]);

    $this->get('/region/9999')->assertRedirect(route('dashboard'));
    expect(session('region_code'))->toBe(1703);
});

test('country map geometry contains all 14 regions with drawable paths', function () {
    Cache::forget('country_map_geometry_v1');
    $regions = CountryMapGeometry::regions();

    expect($regions)->toHaveCount(14);
    $codes = array_column($regions, 'code');
    foreach ([1703, 1706, 1708, 1710, 1712, 1714, 1718, 1722, 1724, 1726, 1727, 1730, 1733, 1735] as $code) {
        expect($codes)->toContain($code);
    }
    foreach ($regions as $r) {
        expect($r['d'])->toStartWith('M');
        expect(strlen($r['d']))->toBeGreaterThan(100);
        expect($r['cx'])->toBeGreaterThan(0)->toBeLessThan(1000);
        expect($r['cy'])->toBeGreaterThan(0)->toBeLessThan(640);
    }
});
