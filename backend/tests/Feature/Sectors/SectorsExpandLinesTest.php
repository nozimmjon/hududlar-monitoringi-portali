<?php

use App\Livewire\SectorDetail;
use App\Models\SectorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

function importExpandFixture(string $period, float $gasActual): void
{
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'Углеводород қазиб чиқариш.', 'Табиий газ', 'млрд куб метр', '2026 йил якуни', 24.7, $gasActual, null],
            [null, 2, null, 'Суюқ углеводородлар', 'минг тонна', '2026 йил якуни', 1188, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => $period]);
}

test('expanding a multi-line task reveals its indicator rows', function () {
    $this->seed();
    importExpandFixture('2026-07', 13.2);
    $task = SectorTask::where('task_no', 1)->firstOrFail();

    Livewire::test(SectorDetail::class, ['code' => 'uzbekneftgaz'])
        ->assertDontSee('Суюқ углеводородлар')
        ->call('toggleTask', $task->id)
        ->assertSee('Табиий газ')
        ->assertSee('Суюқ углеводородлар')
        ->call('toggleTask', $task->id)
        ->assertDontSee('Суюқ углеводородлар');
});

test('expanded rows show only the latest period values', function () {
    $this->seed();
    importExpandFixture('2026-07', 10.5);
    importExpandFixture('2026-08', 13.2); // later period wins
    $task = SectorTask::where('task_no', 1)->firstOrFail();

    Livewire::test(SectorDetail::class, ['code' => 'uzbekneftgaz'])
        ->call('toggleTask', $task->id)
        ->assertSee('13,2')       // latest actual, formatted with comma decimal
        ->assertDontSee('10,5');  // older period's actual must not render
});

test('rows without actuals are shown dimmed with a dash', function () {
    $this->seed();
    importExpandFixture('2026-07', 13.2);
    $task = SectorTask::where('task_no', 1)->firstOrFail();

    $component = Livewire::test(SectorDetail::class, ['code' => 'uzbekneftgaz'])
        ->call('toggleTask', $task->id);

    $component->assertSee('Суюқ углеводородлар');
    $component->assertSeeHtml('sdp-line dim');
});
