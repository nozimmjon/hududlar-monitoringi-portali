<?php

use App\Livewire\SectorsDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

function importSectorsUiFixture(): void
{
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            // Single-line task, done (104%): РЕЖА/АМАЛДА strip
            [1, 1, 'Товар маҳсулот ҳажмини етказиш.', 'Товар маҳсулот ҳажми', 'трлн сўм', '2026 йил якуни', 56.7, 58.9, null],
            // Multi-line task, open: ИНДИКАТОРЛАР/БАЖАРИЛДИ strip
            [2, 2, 'Углеводород қазиб чиқариш.', 'Табиий газ', 'млрд куб метр', '2026 йил якуни', 24.7, 13.2, null],
            [null, 3, null, 'Суюқ углеводородлар', 'минг тонна', '2026 йил якуни', 1188, 1210, null],
            // In-progress task (nothing reported)
            [3, 4, 'Инвестиция дастури.', 'Жами инвестициялар', 'млн доллар', '2026 йил якуни', 1232.1, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);
}

test('selecting a sector shows its task cards with status chips', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->assertSee('«Ўзбекнефтгаз» АЖ')
        ->assertSee('Товар маҳсулот ҳажмини етказиш.')
        ->assertSee('Бажарилди')       // done chip
        ->assertSee('Бажарилмаган')    // open chip (weakest link: газ 53%)
        ->assertSee('Бажарилмоқда')    // in_progress chip
        ->assertSee('Муддат:')
        ->assertSee('2026 йил якуни');
});

test('single-line task shows РЕЖА/АМАЛДА, multi-line shows indicator counts', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->assertSee('Режа')
        ->assertSee('Амалда')
        ->assertSee('Индикаторлар')
        ->assertSee('Бажарилиш');
});

test('selecting the same sector again closes the drilldown', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->assertSet('sector', 'uzbekneftgaz')
        ->call('selectSector', 'uzbekneftgaz')
        ->assertSet('sector', null);
});

test('sector deep link opens the drilldown from the URL', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::withQueryParams(['sector' => 'uzbekneftgaz'])
        ->test(SectorsDashboard::class)
        ->assertSee('«Ўзбекнефтгаз» АЖ');
});
