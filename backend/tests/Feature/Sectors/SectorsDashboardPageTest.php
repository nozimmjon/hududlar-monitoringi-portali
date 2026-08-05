<?php

use App\Livewire\SectorsDashboard;
use App\Models\SectorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

function importPanelFixture(): void
{
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', '«Ўзбекнефтгаз» АЖ', [
            [1, 1, 'В1. Биринчи вазифа', 'Кўрсаткич А', 'та', '2026 йил III-чорак', 100, 120, null], // done
            [2, 2, 'В2. Иккинчи вазифа', 'Кўрсаткич Б', 'та', '2026 йил якуни', 100, 55, null],      // open
        ]],
        ['2. Ўзбекгидроэнерго', '«Ўзбекгидроэнерго» АЖ', [
            [1, 1, 'В1. Гидро вазифа', 'Кўрсаткич В', 'та', '2026 йил якуни', 100, 130, null],       // done
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);
}

test('/sectors renders all 17 sector cards in sheet order', function () {
    $this->seed();

    $response = $this->get('/sectors');

    $response->assertOk();
    $response->assertSeeInOrder([
        'Ўзбекнефтгаз', 'Ўзбекгидроэнерго', 'Иссиқлик электр станциялари', 'Ўзкимёсаноат', 'НКМК',
        'Навоийуран', 'Олмалиқ КМК', 'Ўзметкомбинат', 'ТМК', 'Ўзавтосаноат',
        'Ўзэлтехсаноат', 'Енгил саноат агентлиги', 'Ўзтўқимачиликсаноат', 'Ўзчармсаноат',
        'Ўзсаноатқурилишматериаллари', 'Фармацевтика агентлиги', 'Ўзбекзаргарсаноати',
    ]);
    $response->assertSee('Тармоқ корхоналари топшириқлари');
    $response->assertSee('Ижро рейтинги');
});

test('sectors without any reported actuals show the waiting card state', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);

    $html = $this->get('/sectors')->getContent();

    expect($html)->toContain('waitc');
    expect($html)->toContain('Ҳисобот кутилмоқда');
});

test('the rail hero shows indicator-level overall percent and fractions', function () {
    $this->seed();
    importPanelFixture();

    $html = $this->get('/sectors')->getContent();

    // 2 of 3 indicator lines done → 67%
    expect($html)->toContain('67%');
    expect($html)->toContain('Умумий ижро');
});

test('a sector card shows the 99-capped percent, counts, and earliest deadline bucket', function () {
    $this->seed();
    importPanelFixture();

    $html = $this->get('/sectors')->getContent();

    // Neftgaz: 1/2 lines done → 50%; q3 line present → III чорак on the card.
    expect($html)->toContain('50');
    expect($html)->toContain('III чорак');
    expect($html)->toContain('Батафсил');
});

test('the rating card ranks reporting sectors best-first', function () {
    $this->seed();
    importPanelFixture();

    // Гидро 100% > Нефтгаз 50%.
    $this->get('/sectors')->assertSeeInOrder(['Энг юқори', 'Ўзбекгидроэнерго', 'Энг паст']);
});

test('search narrows the card grid but not the rail', function () {
    $this->seed();

    Livewire::test(SectorsDashboard::class)
        ->assertSeeHtml('data-code="uzavtosanoat"')
        ->set('search', 'НКМК')
        ->assertSeeHtml('data-code="nkmk"')
        ->assertDontSeeHtml('data-code="uzavtosanoat"')
        ->set('search', 'зззйўқ')
        ->assertSee('Ҳеч нарса топилмади');
});

test('openSector renders the drawer with tasks; closeSector empties it', function () {
    $this->seed();
    importPanelFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('openSector', 'uzbekneftgaz')
        ->assertSet('open', 'uzbekneftgaz')
        ->assertSee('«Ўзбекнефтгаз» АЖ')
        ->assertSee('В1. Биринчи вазифа')
        ->call('closeSector')
        ->assertSet('open', null)
        ->assertDontSee('В1. Биринчи вазифа');
});

test('drawer tabs filter tasks and reset on reopen', function () {
    $this->seed();
    importPanelFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('openSector', 'uzbekneftgaz')
        ->call('setFilter', 'done')
        ->assertSee('В1. Биринчи вазифа')
        ->assertDontSee('В2. Иккинчи вазифа')
        ->call('closeSector')
        ->call('openSector', 'uzbekneftgaz')
        ->assertSet('filter', 'all')
        ->assertSee('В2. Иккинчи вазифа');
});

test('toggleTask expands a multi-line task inside the drawer', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'Углеводород қазиб чиқариш.', 'Табиий газ', 'млрд куб метр', '2026 йил якуни', 24.7, 13.2, null],
            [null, 2, null, 'Суюқ углеводородлар', 'минг тонна', '2026 йил якуни', 1188, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);
    $task = SectorTask::where('task_no', 1)->firstOrFail();

    Livewire::test(SectorsDashboard::class)
        ->call('openSector', 'uzbekneftgaz')
        ->assertDontSee('Суюқ углеводородлар')
        ->call('toggleTask', $task->id)
        ->assertSee('Суюқ углеводородлар');
});
