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

test('the overview shows one card per region with the month\'s saved / carried / empty counts, the status split and the last change', function () {
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
        ->assertSee('4 та чора-тадбир · 5 та индикатор')
        ->assertSeeHtml('Бажарилди <b class="tnum">1</b>')
        ->assertSee('Админ · 15.09.2026 15:00')                       // Tashkent time
        ->assertSee('йўл харитаси йўқ')                                // the regions without a road map
        ->assertSee('Тошкент шаҳри')
        ->call('selectPeriod', '2026-08')
        ->assertSet('period', '2026-08')
        ->assertSeeHtml('data-region="1733" data-saved="1" data-carry="0" data-empty="3"')
        ->call('selectPeriod', 'nonsense')
        ->assertSet('period', '2026-09');

    $this->get('/admin/roadmaps')->assertOk()->assertSee('Хоразм вилояти')->assertSee('Чиқиш');
    $this->get('/admin/roadmaps?davr=2026-07')->assertOk()->assertSee('2026 йил июль');
});

test('a guest gets 403 from the component', function () {
    Livewire::test(RoadmapOverview::class)->assertForbidden();
});
