<?php

use App\Livewire\RoadmapsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\Helpers\RoadmapDocxBuilder;

uses(RefreshDatabase::class);

function roadmapImportKhorezmPage(): void
{
    $file = RoadmapDocxBuilder::make([
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['«Куловот» каналини реконструкция қилиш.'], ['Республика бюджети, 32,0 млрд сўм'], ['2026 йил декабрь'], ['СХВ (Ў.Шералиев)']],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['section', 'II. Дуал таълимни ташкил қилиш'],
        ['measure', ['Талабаларни амалиётга юбориш.'], ['Университет маблағлари'], ['2026 йил апрель-октябрь'], ['Университет (Б.Мирзаев)']],
        ['section', 'III. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['Суғориш тармоқларини бетонлаштириш, жумладан:', '1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.'], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
        ['measure', ['52 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
    ]);
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $file]);
}

beforeEach(function () {
    $this->seed();
});

test('GET /roadmaps shows the empty state for a region without a road map', function () {
    Session::put('region_code', 1703);

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('Андижон вилояти учун сув хўжалиги йўл харитаси ҳали юкланмаган');
    $response->assertSee('import:roadmap --region=1703');
});

test('GET /roadmaps renders the rail, KPI strip and grouped cards for the session region', function () {
    Session::put('region_code', 1733);
    roadmapImportKhorezmPage();

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('Сув хўжалиги йўл харитаси');
    $response->assertSee('wr-card', false);
    $response->assertSeeInOrder(['I.', 'Вилоятда амалга ошириладиган йирик лойиҳалар', 'II.', 'Дуал таълимни ташкил қилиш', 'III.', 'Боғот тумани', 'Гурлан тумани']);
    $response->assertSee('туман ҳокими Ж.Назаров');
    $response->assertSee('Батафсил (2 банд)');
    $response->assertSee('7,8 км хўжаликлараро каналлар');
    $response->assertSee('жами чора-тадбир');
});

test('component reads the region from the session', function () {
    Session::put('region_code', 1733);

    Livewire::test(RoadmapsPage::class)->assertSet('regionCode', 1733);
});

test('district filter shows only that district and hides region-level groups; rail counts stay total', function () {
    Session::put('region_code', 1733);
    roadmapImportKhorezmPage();

    Livewire::test(RoadmapsPage::class)
        ->call('selectDistrict', '1733204')
        ->assertSee('Суғориш тармоқларини бетонлаштириш')
        ->assertDontSee('«Куловот»')
        ->assertDontSee('52 млн м3')
        ->assertSeeHtml('Барчаси<span class="n tnum">5</span>')
        ->assertSee('Кўрсатилмоқда:')
        ->assertSet('section', 'all');
});

test('section filter shows one section and clears the district', function () {
    Session::put('region_code', 1733);
    roadmapImportKhorezmPage();

    Livewire::test(RoadmapsPage::class)
        ->call('selectDistrict', '1733204')
        ->call('selectSection', '1')
        ->assertSet('district', 'all')
        ->assertSee('«Куловот»')
        ->assertSee('484,5 млн м3')
        ->assertDontSee('Талабаларни амалиётга')
        ->assertDontSee('Суғориш тармоқларини бетонлаштириш');
});

test('search narrows the cards and reports no match', function () {
    Session::put('region_code', 1733);
    roadmapImportKhorezmPage();

    // Section/district names also live in the rail, so only measure text is asserted absent.
    Livewire::test(RoadmapsPage::class)
        ->set('q', 'куловот')
        ->assertSee('«Куловот»')
        ->assertDontSee('484,5 млн м3')
        ->assertDontSee('Талабаларни амалиётга')
        ->set('q', 'зззйўқ')
        ->assertSee('Мос чора-тадбир топилмади')
        ->call('clearFilters')
        ->assertSet('q', '')
        ->assertSee('484,5 млн м3');
});
