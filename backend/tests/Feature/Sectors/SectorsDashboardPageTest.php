<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

test('/sectors renders all 17 sector cards in sheet order', function () {
    $this->seed();

    $response = $this->get('/sectors');

    $response->assertOk();
    $response->assertSeeInOrder([
        'Ўзбекнефтгаз', 'Ўзбекгидроэнерго', 'Иссиқлик электр станциялари', 'Кимё саноати', 'НКМК',
        'Навоийуран', 'Олмалиқ КМК', 'Ўзметкомбинат', 'ТМК', 'Ўзавтосаноат',
        'Ўзэлтехсаноат', 'Енгил саноат агентлиги', 'Ўзтўқимачиликсаноат', 'Ўзчармсаноат',
        'Ўзсаноатқурилишматериаллари', 'Фармацевтика агентлиги', 'Ўзбекзаргарсаноати',
    ]);
    $response->assertSee('Тармоқ корхоналари топшириқлари');
});

test('sectors without any reported actuals show the waiting state', function () {
    $this->seed();
    // Plans-only import: statuses stay in_progress → card is dimmed, no percent.
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);

    $response = $this->get('/sectors');

    $response->assertOk();
    $response->assertSee('Маълумот кутилмоқда');
    // The card is actually dimmed, not just labeled.
    expect($response->getContent())->toContain('nodata');
});

test('summary strip counts tasks by status across all sectors', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, null], // done
            [2, 2, 'В2.', 'Кўрсаткич Б', 'та', '2026 йил якуни', 100, 55, null],  // open
            [3, 3, 'В3.', 'Кўрсаткич В', 'та', '2026 йил якуни', 100, null, null], // in_progress
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);

    $html = $this->get('/sectors')->getContent();

    // dp-fact summary: 17 корхона, 3 топшириқдан 1 бажарилди, 1 бажарилмаган / 1 кутилмоқда
    expect($html)->toContain('дан 1 таси бажарилди');
    expect($html)->toContain('<span class="warn">1</span>/1'); // open/waiting fact cell
});

test('a sector card shows counts and indicator-level percent', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, null], // done line
            [2, 2, 'В2.', 'Кўрсаткич Б', 'та', '2026 йил якуни', 100, 55, null],  // open line
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);

    $html = $this->get('/sectors')->getContent();

    // lines_done/lines_total = 1/2 → 50%
    expect($html)->toContain('50%');
    expect($html)->toContain('2 топшириқ');
});
