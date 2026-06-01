<?php

use App\Livewire\RegionProfile;
use App\Models\District;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('district panel shows task plan/actual/% and status chip', function () {
    session(['region_code' => 1703]); // RegionProfile::mount() reads CurrentRegion::code()
    $district = District::where('region_code', 1703)->first();

    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1', 'title' => 'Йирик корхона',
        'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'done',
        'headline_unit' => 'дона', 'headline_plan' => 6, 'headline_actual' => 6,
        'headline_pct' => 100, 'latest_period' => '2026-Q1',
    ]);
    $task->districts()->sync([$district->id]);

    Livewire::test(RegionProfile::class)
        ->set('districtCode', (string) $district->code)
        ->assertSee('Йирик корхона')
        ->assertSee('Режа')
        ->assertSee('дона')
        ->assertSeeHtml('Бажарилди · 100%')
        ->assertSee('Бажарилди');
});

test('district panel handles tasks without progress data', function () {
    session(['region_code' => 1703]);
    $district = District::where('region_code', 1703)->first();

    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2', 'title' => 'Маълумотсиз туман топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp',
        // plan set so the card is visible; actual/pct stay null -> still render as em-dash
        'headline_plan' => 6,
    ]);
    $task->districts()->sync([$district->id]);

    Livewire::test(RegionProfile::class)
        ->set('districtCode', (string) $district->code)
        ->assertSee('Маълумотсиз туман топшириғи')
        ->assertSee('Бажарилмаган')
        ->assertSee('—')
        ->assertDontSee('· %')
        ->assertDontSee('· 0%');
});

test('a no-plan task is hidden from the district panel', function () {
    session(['region_code' => 1703]);
    $district = District::where('region_code', 1703)->first();

    $planned = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '10', 'title' => 'Режали туман топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 6, 'headline_actual' => 3,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
    $noPlan = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '11', 'title' => 'Режасиз туман топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'open',
        'headline_plan' => null, 'latest_period' => '2026-Q1',
    ]);
    $planned->districts()->sync([$district->id]);
    $noPlan->districts()->sync([$district->id]);

    Livewire::test(RegionProfile::class)
        ->set('districtCode', (string) $district->code)
        ->assertSee('Режали туман топшириғи')      // planned -> visible
        ->assertDontSee('Режасиз туман топшириғи'); // no plan -> hidden
});

test('hasPlan scope is applied to the hero KPI panel counts and list', function () {
    // hero.blade.php line 25: {{ $taskCounts['unfinished'] }}/{{ $taskCounts['total'] }} T-топшириқ
    // $taskCounts comes from taskCounts() and $tasks comes from tasksForKpi(); both must apply hasPlan().
    // With only the planned task visible the chip must read "1/1 T-топшириқ".
    // If hasPlan() were removed from either method the chip would read "2/2 T-топшириқ", failing this test.
    session(['region_code' => 1703]);
    $district = District::where('region_code', 1703)->first();

    $planned = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '20', 'title' => 'Режали KPI топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 6, 'headline_actual' => 3,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
    $noPlan = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '21', 'title' => 'Режасиз KPI топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'open',
        'headline_plan' => null, 'latest_period' => '2026-Q1',
    ]);
    $planned->districts()->sync([$district->id]);
    $noPlan->districts()->sync([$district->id]);

    Livewire::test(RegionProfile::class)
        ->set('districtCode', (string) $district->code)
        ->set('kpi', 'grp')
        // Confirms taskCounts() applied hasPlan(): chip shows 1 planned task, not 2.
        // hero.blade.php:25  → <span class="chip ...">1/1 T-топшириқ</span>
        ->assertSeeHtml('1/1 T-топшириқ')
        // Confirms tasksForKpi() applied hasPlan(): planned task is in the list.
        ->assertSee('Режали KPI топшириғи')
        // The no-plan task must not appear (guarded by both the hero list and the bottom panel).
        ->assertDontSee('Режасиз KPI топшириғи');
});
