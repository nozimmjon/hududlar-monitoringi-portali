<?php

use App\Livewire\SectorDetail;
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

test('the sector detail page shows task cards with status chips', function () {
    $this->seed();
    importSectorsUiFixture();

    $response = $this->get('/sectors/uzbekneftgaz');

    $response->assertOk();
    $response->assertSee('«Ўзбекнефтгаз» АЖ');
    $response->assertSee('Товар маҳсулот ҳажмини етказиш.');
    $response->assertSee('Бажарилди');       // done chip
    $response->assertSee('Бажарилмаган');    // open chip (weakest link: газ 53%)
    $response->assertSee('Кутилмоқда');      // in_progress chip (renamed in the e-panel design)
    $response->assertSeeHtml('sec-chip wait');
    $response->assertSee('муддат');
    $response->assertSee('2026 йил якуни');
});

test('single-line task shows РЕЖА/ФАКТ, multi-line shows indicator counts', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::test(SectorDetail::class, ['code' => 'uzbekneftgaz'])
        ->assertSee('Режа')
        ->assertSee('Факт')
        ->assertSee('Индикаторлар')
        ->assertSee('Умумий ижро');
});

test('status filter narrows the task list', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::test(SectorDetail::class, ['code' => 'uzbekneftgaz'])
        ->assertSee('Товар маҳсулот ҳажмини етказиш.')
        ->assertSee('Инвестиция дастури.')
        ->call('setFilter', 'done')
        ->assertSee('Товар маҳсулот ҳажмини етказиш.')
        ->assertDontSee('Инвестиция дастури.')
        ->call('setFilter', 'in_progress')
        ->assertSee('Инвестиция дастури.')
        ->assertDontSee('Товар маҳсулот ҳажмини етказиш.');
});

test('the sector detail page stays reachable by direct URL', function () {
    $this->seed();
    importSectorsUiFixture();

    $this->get('/sectors/uzbekneftgaz')->assertOk()->assertSee('«Ўзбекнефтгаз» АЖ');
});

test('an unknown sector code returns 404', function () {
    $this->seed();

    $this->get('/sectors/nope_such')->assertNotFound();
});
