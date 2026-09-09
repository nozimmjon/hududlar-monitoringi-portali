<?php

use App\Livewire\RoadmapsPage;
use App\Models\RoadmapMeasure;
use App\Support\Roadmaps\MeasureDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

afterEach(fn () => Carbon::setTestNow());

test('GET /roadmaps shows the empty state when no road map is loaded at all', function () {
    Session::put('region_code', 1703);

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('Андижон вилояти учун сув хўжалиги йўл харитаси ҳали юкланмаган');
    $response->assertSee('import:roadmap --region=1703');
});

test('direct entry with no region chosen makes the first loaded region the active one', function () {
    roadmapImportKhorezmPage();
    expect(Session::has('region_code'))->toBeFalse();

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('· Хоразм вилояти');
    $response->assertSee('«Куловот»');
    $response->assertSee('Хоразм вилояти мониторинг платформаси');   // topbar follows the switched session
    expect(Session::get('region_code'))->toBe(1733);
});

test('direct entry with nothing loaded leaves the session alone and shows the default region empty state', function () {
    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('Андижон вилояти учун сув хўжалиги йўл харитаси ҳали юкланмаган');
    expect(Session::has('region_code'))->toBeFalse();
});

test('an explicitly chosen region without a road map keeps its own empty state', function () {
    Session::put('region_code', 1703);
    roadmapImportKhorezmPage();

    $response = $this->get('/roadmaps');

    $response->assertSee('Андижон вилояти учун сув хўжалиги йўл харитаси ҳали юкланмаган');
    $response->assertDontSee('«Куловот»');
    expect(Session::get('region_code'))->toBe(1703);
});

test('GET /roadmaps renders the rail, KPI strip and grouped cards for the session region', function () {
    Session::put('region_code', 1733);
    roadmapImportKhorezmPage();

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('Сув хўжалиги йўл харитаси');
    $response->assertSee('wr-mcard', false);
    $response->assertSeeInOrder(['«Куловот» каналини реконструкция қилиш.', 'Талабаларни амалиётга юбориш.', 'Суғориш тармоқларини бетонлаштириш', '52 млн м3 сувни иқтисод қилиш.']);
    $response->assertSeeInOrder(['<span class="dn">Боғот тумани</span>', '<span class="dn">Гурлан тумани</span>'], false);
    $response->assertSee('<span class="hd">туман ҳокими Ж.Назаров</span>', false);
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
        ->assertSet('section', 'all')
        ->assertSeeHtml('class="on" title="Туманларда амалга ошириладиган лойиҳалар"')
        ->assertDontSeeHtml('class="on" title="Вилоятда амалга ошириладиган йирик лойиҳалар"');
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

test('a stale district or section in the URL falls back to the full list', function () {
    Session::put('region_code', 1733);
    roadmapImportKhorezmPage();

    Livewire::withQueryParams(['district' => '999999'])->test(RoadmapsPage::class)
        ->assertSet('district', 'all')
        ->assertSee('«Куловот»')
        ->assertDontSee('Кўрсатилмоқда:');
    Livewire::withQueryParams(['section' => '99'])->test(RoadmapsPage::class)
        ->assertSet('section', 'all')
        ->assertSee('«Куловот»');
    Livewire::withQueryParams(['section' => '02'])->test(RoadmapsPage::class)
        ->assertSet('section', 'all')
        ->assertSee('«Куловот»');
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

/**
 * Monitoring rows on top of roadmapImportKhorezmPage(). Today is pinned to 2026-11-15.
 *  Куловот (I/1)    5 lines, 8 % reported in 2026-09          → in_progress, «яна 1 индикатор», neutral «2026 йил декабрь» chip
 *  484,5 (I/2)      1 line done                                → done, green «2026 йил декабрь» chip
 *  Талабалар (II/1) 1 line 30 % reported in 2026-11, Oct deadline → open, red «2026 йил апрель-октябрь» chip
 *  Боғот (III/1)    2 lines, Aug + Sep history                 → in_progress 80 %, sparkline
 *  Гурлан (III/2)   no lines                                   → «Индикаторлар ҳали белгиланмаган»
 */
function roadmapPageMonitoring(): void
{
    Carbon::setTestNow('2026-11-15');
    roadmapImportKhorezmPage();
    $find = fn (string $needle) => RoadmapMeasure::where('title', 'like', "%{$needle}%")->firstOrFail();

    $k = $find('Куловот');
    $k->lines()->create(['line_no' => 1, 'label' => 'Лойиҳа босқичи', 'unit' => '%', 'plan_value' => 100])
        ->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 40, 'pct_of_plan' => 40]);
    foreach (['Насос', 'Затвор', 'Дамба', 'Кўприк'] as $i => $label) {
        $k->lines()->create(['line_no' => $i + 2, 'label' => $label, 'unit' => 'та', 'plan_value' => 10])
            ->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 0, 'pct_of_plan' => 0]);
    }

    $find('484,5')->lines()->create(['line_no' => 1, 'label' => 'Сув иқтисоди', 'unit' => 'млн м³', 'plan_value' => 484.5])
        ->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 484.5, 'pct_of_plan' => 100]);

    $find('Талабаларни')->lines()->create(['line_no' => 1, 'label' => 'Амалиётга юборилди', 'unit' => '%', 'plan_value' => 100])
        ->progress()->create(['report_period' => '2026-11', 'period_type' => 'month', 'actual_value' => 30, 'pct_of_plan' => 30]);

    $b  = $find('бетонлаштириш');
    $l1 = $b->lines()->create(['line_no' => 1, 'label' => 'Хўжаликлараро канал', 'unit' => 'км', 'plan_value' => 7.8]);
    $l2 = $b->lines()->create(['line_no' => 2, 'label' => 'Ички канал', 'unit' => 'км', 'plan_value' => 33]);
    $l1->progress()->create(['report_period' => '2026-08', 'period_type' => 'month', 'actual_value' => 3, 'pct_of_plan' => 38.46]);
    $l1->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 7.8, 'pct_of_plan' => 100]);
    $l2->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 20, 'pct_of_plan' => 60.61, 'note' => 'Ёмғир сабабли кечикди']);

    Artisan::call('roadmaps:recompute');
}

test('cards carry ring, status chip, indicator rows, deadline chips, notes and the period pill', function () {
    Session::put('region_code', 1733);
    roadmapPageMonitoring();

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('wr-mcard', false);
    $response->assertSee('2026 йил ноябрь');                                  // latest period across the road map
    $response->assertSeeInOrder(['Бажарилди', 'Бажарилмоқда', 'Бажарилмаган']);
    $response->assertSee('Хўжаликлараро канал');
    $response->assertSee('Ички канал');
    $response->assertSee('яна 1 индикатор');
    $response->assertSee('Индикаторлар ҳали белгиланмаган');
    $response->assertSeeHtml('<span class="wr-tag due">2026 йил декабрь</span>');
    $response->assertSeeHtml('<span class="wr-tag done">2026 йил декабрь</span>');
    $response->assertSeeHtml('<span class="wr-tag over">2026 йил апрель-октябрь</span>');
    $response->assertSee('wr-spark', false);                                   // Боғот has two periods
    $response->assertSee('wr-notes', false);
    $response->assertSee('Ёмғир сабабли кечикди');                            // the reporter's note under «Батафсил»
    $response->assertSee('1 тадбирда индикатор йўқ');
    $response->assertSee('бажарилмаган');                                     // KPI tile label

    $bogot = RoadmapMeasure::where('title', 'like', '%бетонлаштириш%')->firstOrFail()->load('lines.progress');
    expect(MeasureDisplay::history($bogot))->toBe([
        ['period' => '2026-08', 'pct' => 19.2],   // (38.46 + 0) / 2 — line 2 has no August row
        ['period' => '2026-09', 'pct' => 80.3],   // (100 + 60.61) / 2
    ]);
    $response->assertSeeHtml('<span class="p tnum">80%</span>');                       // Боғот district row
    $response->assertSeeHtml('<span class="wr-chip period" title="Охирги ҳисобот даври">📅 2026 йил сентябрь</span>');   // a card older than the road map's latest period
    // Куловот 8 · 484,5 100 · Талабалар 30 · Боғот 80,31 → mean 54,5775 → 55; Гурлан has no lines and is out.
    $response->assertSeeHtml('<div class="cv"><b class="tnum">55%</b></div>');
});

test('status filter narrows the list and combines with a district; rail counts, hero and KPI stay whole', function () {
    Session::put('region_code', 1733);
    roadmapPageMonitoring();

    Livewire::test(RoadmapsPage::class)
        ->call('selectStatus', 'done')
        ->assertSet('status', 'done')
        ->assertSee('484,5 млн м3')
        ->assertDontSee('«Куловот»')
        ->assertDontSee('Талабаларни амалиётга')
        ->assertSeeHtml('Барчаси<span class="n tnum">5</span>')
        ->assertSee('Кўрсатилмоқда:')
        ->call('selectDistrict', '1733204')
        ->assertSet('status', 'done')
        ->assertSee('Мос чора-тадбир топилмади')
        ->call('selectStatus', 'in_progress')
        ->assertSee('Суғориш тармоқларини бетонлаштириш')
        ->call('clearFilters')
        ->assertSet('status', 'all')
        ->assertSee('«Куловот»');

    Livewire::withQueryParams(['holat' => 'zzz'])->test(RoadmapsPage::class)
        ->assertSet('status', 'all')
        ->assertSee('«Куловот»');
});

test('a road map without any indicator lines renders the registry face everywhere', function () {
    Session::put('region_code', 1733);
    roadmapImportKhorezmPage();

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('ҳисобот йўқ');
    $response->assertSee('Индикаторлар ҳали белгиланмаган');
    $response->assertDontSee('wr-spark', false);
});
