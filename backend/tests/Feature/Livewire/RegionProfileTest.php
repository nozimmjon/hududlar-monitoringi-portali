<?php

use App\Livewire\RegionProfile;
use App\Models\Indicator;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('renders empty state when districtCode is missing', function () {
    Livewire::test(RegionProfile::class)
        ->assertSee('Туман танланмаган');
});

test('renders empty state when districtCode does not match any district', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '999999'])
        ->assertSee('Туман топилмади');
});

test('mounts with valid districtCode and kpi', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'industry'])
        ->assertViewHas('district', fn ($d) => $d !== null && $d->code === 1703401)
        ->assertViewHas('selectedIndicator', fn ($i) => $i !== null && $i->code === 'industry');
});

test('mount falls back to first available kpi when current kpi is unknown', function () {
    $component = Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'nonexistent_kpi']);
    expect($component->get('kpi'))->not->toBe('nonexistent_kpi');
    expect($component->get('kpi'))->toBeString()->not->toBeEmpty();
});

test('selectKpi action updates kpi state', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'industry'])
        ->call('selectKpi', 'grp')
        ->assertSet('kpi', 'grp');
});

test('taskCounts reflects tasks linked to district and KPI', function () {
    $task = Task::factory()->create([
        'region_code' => 1703, 'module_code' => 'macro',
        'indicator_code' => 'industry', 'status' => 'open',
    ]);
    $districtId = \DB::table('districts')->where('code', 1703401)->value('id');
    \DB::table('task_districts')->insert(['task_id' => $task->id, 'district_id' => $districtId]);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'industry'])
        ->assertViewHas('taskCounts', fn ($c) => $c['total'] === 1 && $c['unfinished'] === 1);
});

test('tasks panel renders empty state when no tasks match', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'grp'])
        ->assertSee('Бу KPI бўйича топшириқ топилмади');
});
