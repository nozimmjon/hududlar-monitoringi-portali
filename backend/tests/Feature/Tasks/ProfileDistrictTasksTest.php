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

function profileTask(array $attrs = []): Task
{
    $task = Task::factory()->create(array_merge([
        'region_code' => 1703, 'module_code' => 'macro', 'indicator_code' => 'industry',
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => 6, 'status' => 'open', 'latest_period' => '2026-H1',
    ], $attrs));
    $districtId = DB::table('districts')->where('code', 1703401)->value('id');
    DB::table('task_districts')->insert(['task_id' => $task->id, 'district_id' => $districtId]);

    return $task;
}

test('a done task shows Режа/Амалда/Бажарилиш cells and the done chip', function () {
    profileTask([
        'title' => 'Йирик корхона топшириғи', 'status' => 'done',
        'headline_unit' => 'дона', 'headline_actual' => 6, 'headline_pct' => 100,
        'lines_total' => 1, 'lines_done' => 1,
    ]);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertSee('Йирик корхона топшириғи')
        ->assertSee('Режа')
        ->assertSee('Амалда')
        ->assertSee('Бажарилиш')
        ->assertSee('дона')
        ->assertSee('Бажарилди')
        ->assertSee('100%');
});

test('a task with no reported data reads Бажарилмоқда with an em-dash', function () {
    profileTask([
        'title' => 'Маълумотсиз туман топшириғи', 'status' => 'in_progress',
        'headline_unit' => 'дона', 'headline_actual' => null, 'headline_pct' => null,
        'lines_total' => 1, 'lines_done' => 0,
    ]);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertSee('Маълумотсиз туман топшириғи')
        ->assertSee('Бажарилмоқда')
        ->assertSee('маълумот кутилмоқда')
        ->assertSee('—');
});

test('a multi-indicator task shows line counts instead of line-0 numbers', function () {
    profileTask([
        'title' => 'Кўп индикаторли топшириқ', 'status' => 'open',
        'headline_plan' => 10, 'headline_actual' => 4, 'headline_pct' => 40,
        'lines_total' => 4, 'lines_done' => 2,
    ]);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertSee('Индикаторлар')
        ->assertSee('Бажарилмаган')
        ->assertSee('50%');   // 2 of 4 lines done
});

test('a no-plan task is hidden from the profile', function () {
    profileTask(['title' => 'Режали туман топшириғи']);
    profileTask(['title' => 'Режасиз туман топшириғи', 'headline_plan' => null]);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertSee('Режали туман топшириғи')
        ->assertDontSee('Режасиз туман топшириғи');
});

test('the period filter splits tasks by deadline bucket', function () {
    profileTask(['title' => 'Ярим йиллик топшириқ']);
    profileTask([
        'title' => 'Йил якуни топшириғи',
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
    ]);

    $c = Livewire::test(RegionProfile::class, ['districtCode' => '1703401']);
    $c->assertSee('Ярим йиллик топшириқ')->assertDontSee('Йил якуни топшириғи');

    $c->call('selectPeriod', 'year');
    $c->assertSee('Йил якуни топшириғи')->assertDontSee('Ярим йиллик топшириқ');
});

test('hero counters cover all planned district tasks regardless of period', function () {
    profileTask(['title' => 'Битта', 'status' => 'open']);
    profileTask(['title' => 'Иккита', 'status' => 'in_progress',
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан']);
    profileTask(['title' => 'Учта', 'status' => 'done']);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertViewHas('taskCounts', fn ($c) =>
            $c['total'] === 3 && $c['open'] === 1 && $c['in_progress'] === 1 && $c['done'] === 1);
});
