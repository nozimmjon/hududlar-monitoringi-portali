<?php

use App\Livewire\Admin\RoadmapOverview;
use App\Services\Roadmaps\ProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());
afterEach(fn () => Carbon::setTestNow());

test('the overview shows one card per region with the month\'s saved / carried / empty counts, the completion ring and the last change', function () {
    Carbon::setTestNow('2026-09-15 10:00:00');
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    AdminFixture::report(AdminFixture::measure(2, 1, 1733204), '2026-08', [1 => 7.8, 2 => 20.0]);  // one line below plan: only Гурлан closes as done
    ProgressEntry::save($roadmap, '2026-09', [AdminFixture::line(AdminFixture::measure(2, 1, 1733208), 1)->id => ['actual' => '24']], $user);
    $this->actingAs($user);

    Livewire::test(RoadmapOverview::class)
        ->assertSet('period', '2026-09')
        ->assertSee('Йўл хариталари · мониторинг')
        ->assertSee('2026 йил сентябрь')
        ->assertSee('Хоразм вилояти')
        ->assertSeeHtml('data-region="1733" data-saved="1" data-carry="1" data-empty="2"')
        ->assertSeeHtml('/admin/roadmaps/1733?davr=2026-09"')         // the card carries the browsed month into the entry page
        ->assertSee('4 та чора-тадбир · 5 та индикатор')
        ->assertSee('1 / 4 тўлдирилди · 5 та индикатор')              // the card's own numbers
        ->assertSeeHtml('<i class="ad-num">25%</i>')                   // and its ring
        ->assertSee('Админ · 15.09.2026 15:00')                       // Tashkent time
        ->assertDontSee('йўл харитаси йўқ')                            // regions without one are not listed at all
        ->assertDontSee('Тошкент шаҳри')
        ->call('selectPeriod', '2026-08')
        ->assertSet('period', '2026-08')
        ->assertSeeHtml('data-region="1733" data-saved="1" data-carry="0" data-empty="3"')
        ->call('selectPeriod', 'nonsense')
        ->assertSet('period', '2026-09')
        ->dispatch('progress-imported')
        ->assertSeeHtml('data-region="1733" data-saved="1" data-carry="1" data-empty="2"')
        ->assertSeeHtml('x-on:click="$dispatch(\'open-upload\', { code: 1733 })"')   // browser-side: opening the panel must not re-render 13 sheets
        ->assertSee('Тўлдирилди')                                      // the KPI tiles carry the same three totals the strip used to
        ->assertSee('4 тадан · 25%')
        ->assertSeeHtml('<b class="v ad-num">1</b>')                   // «Ўтган ойдан» = 1
        ->assertSee('Тегилмаган вилоят');

    $this->get('/admin/roadmaps')->assertOk()->assertSee('Хоразм вилояти')->assertSee('Чиқиш');
    $this->get('/admin/roadmaps?davr=2026-07')->assertOk()->assertSee('2026 йил июль');
});

test('cards carry the overdue count, and the regions that need attention come first', function () {
    // Pinned: «кечиккан» is judged against the real current month, and the Хоразм fixture's
    // own «2026 йил июнь» measure would be late from July on — the clock, not the fixture,
    // would then decide what the test asserts.
    Carbon::setTestNow('2026-05-15 10:00:00');
    AdminFixture::roadmap();                                   // Хоразм 1733, four measures, none overdue
    AdminFixture::report(AdminFixture::measure(2, 1, 1733208), '2026-05', [1 => 24.0]);   // one measure filled → not an attention case
    AdminFixture::bareRoadmap(1703, [                          // Андижон: one measure past its deadline
        [1, 'Андижон чора-тадбири', '2026 йил март', 'in_progress'],
        [2, 'Иккинчи чора-тадбир', '2026 йил декабрь', 'in_progress'],
    ]);
    AdminFixture::bareRoadmap(1735, [                          // Қорақалпоғистон leads on sort_order, but nothing of its own is late
        [1, 'Қорақалпоғистон чора-тадбири', '2026 йил декабрь', 'in_progress'],
        [2, 'Иккинчи чора-тадбир', '2026 йил ноябрь', 'in_progress'],
    ]);
    $this->actingAs(AdminFixture::user());

    $html = Livewire::test(RoadmapOverview::class)->assertOk()->html();

    expect($html)->toContain('Диққат талаб қилади')
        ->and($html)->toContain('Қолганлари')                                                      // Хоразм has something saved and nothing late
        ->and(strpos($html, 'Андижон вилояти'))->toBeLessThan(strpos($html, 'Хоразм вилояти'))
        ->and(strpos($html, 'Андижон вилояти'))->toBeLessThan(strpos($html, 'Қорақалпоғистон Республикаси'));

    Livewire::test(RoadmapOverview::class)
        ->assertSeeHtml('data-region="1703" data-saved="0" data-carry="0" data-empty="2" data-overdue="1"')
        ->assertSeeHtml('data-overdue="0"')
        ->assertSee('1 кечиккан')
        ->assertSee('Кечиккан');                                // the KPI tile
});

test('a guest gets 403 from the component', function () {
    Livewire::test(RoadmapOverview::class)->assertForbidden();
});
