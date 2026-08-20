<?php

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Cache::forget('starter.regions');
    Cache::forget('starter.sectors');
});

test('GET / renders the starter page with both module cards', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Ҳудудлар');
    $response->assertSee('Тармоқлар');
    $response->assertSee('href="' . route('regions') . '"', escape: false);
    $response->assertSee('href="' . route('sectors') . '"', escape: false);
    $response->assertSee('Ҳудудлар мониторинги платформаси');
    $response->assertDontSee('side-brand'); // standalone shell — no app-layout chrome
});

test('the regions card shows strict done counts and percent', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1',
        'status' => 'done', 'headline_plan' => 10,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2',
        'status' => 'in_progress', 'headline_plan' => 5,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3',
        'status' => 'open', 'headline_plan' => 7,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
    ]);
    // Plan-less task counts nowhere.
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '4',
        'status' => 'done', 'headline_plan' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);

    $html = $this->get('/')->getContent();

    // 3 planned tasks, 1 strictly done → 33%.
    expect($html)->toContain('data-count="3"');
    expect($html)->toContain('data-count="1"');
    expect($html)->toContain('data-count="33"');
});

test('the sectors card shows the waiting state while nothing is reported', function () {
    // Seeded sectors exist; no SectorTask rows at all → no report.
    $html = $this->get('/')->getContent();

    expect($html)->toContain('Ҳисобот кутилмоқда');
    expect($html)->toContain('data-count="17"'); // 17 seeded sectors
});

test('the sectors card shows the indicator percent once a report exists', function () {
    $sector = Sector::where('code', 'uzbekneftgaz')->firstOrFail();
    SectorTask::create([
        'sector_id' => $sector->id, 'task_no' => 1, 'title' => 'Т1',
        'status' => 'done', 'lines_total' => 2, 'lines_done' => 1,
    ]);

    $html = $this->get('/')->getContent();

    expect($html)->not->toContain('Ҳисобот кутилмоқда');
    expect($html)->toContain('data-count="50"'); // 1/2 indicator lines → 50%
});

test('aggregates are cached under the starter keys', function () {
    $this->get('/')->assertOk();

    expect(Cache::has('starter.regions'))->toBeTrue();
    expect(Cache::has('starter.sectors'))->toBeTrue();
});

test('a failing aggregate degrades to a card without numbers, not a 500', function () {
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('db down'));

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Ҳудудлар');
    $response->assertSee('Тармоқлар');
    $response->assertDontSee('data-count', escape: false);
});
