<?php
// backend/tests/Feature/Livewire/InflationLimitsTest.php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

function inflationTask(string $num, string $title, ?float $plan, ?float $actual): Task
{
    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => $num, 'title' => $title,
        'module_code' => 'inflation', 'indicator_code' => 'inflation',
        'headline_plan' => $plan, 'latest_period' => '2026-H1',
    ]);
    $task->progress()->create([
        'line_no' => 0, 'report_period' => '2026-H1', 'period_type' => 'half',
        'metric_label' => 'Инфляция даражаси', 'unit' => 'фоиз',
        'plan_value' => $plan, 'actual_value' => $actual,
    ]);

    return $task;
}

test('the H1 ceiling card shows the reported rate next to the plan', function () {
    inflationTask('68', 'Биринчи ярим йилликда инфляция даражаси прогнозидан ошмаслиги', 2.9, 3.3);

    $html = Livewire::test(KpiWorkspaceCard::class, [
        'module' => 'inflation', 'kpi' => 'inflation', 'period' => 'h1',
    ])->html();

    expect($html)->toContain('3,3%')            // амалда leads the card
        ->toContain('режа ≤2,9%')                // the cap stays visible
        ->toContain('is-over')                   // above the cap -> red
        ->not->toContain('Амалдаги инфляция маълумоти киритилмаган');
});

test('a rate at or below the cap reads as on target', function () {
    inflationTask('68', 'Биринчи ярим йилликда инфляция даражаси прогнозидан ошмаслиги', 2.9, 2.4);

    $html = Livewire::test(KpiWorkspaceCard::class, [
        'module' => 'inflation', 'kpi' => 'inflation', 'period' => 'h1',
    ])->html();

    expect($html)->toContain('2,4%')->toContain('is-ok')->not->toContain('is-over');
});

test('without reported data the card keeps the cap and the note', function () {
    inflationTask('68', 'Биринчи ярим йилликда инфляция даражаси прогнозидан ошмаслиги', 2.9, null);

    $html = Livewire::test(KpiWorkspaceCard::class, [
        'module' => 'inflation', 'kpi' => 'inflation', 'period' => 'h1',
    ])->html();

    expect($html)->toContain('≤2,9%')
        ->toContain('Амалдаги инфляция маълумоти киритилмаган')
        ->not->toContain('is-ok');
});

test('a renumbered task cannot feed the ceiling card', function () {
    // Same number, unrelated indicator -> the title guard rejects it.
    inflationTask('68', 'Мутлақо бошқа топшириқ', 2.9, 3.3);

    $html = Livewire::test(KpiWorkspaceCard::class, [
        'module' => 'inflation', 'kpi' => 'inflation', 'period' => 'h1',
    ])->html();

    expect($html)->toContain('≤2,9%')->not->toContain('3,3%');
});
