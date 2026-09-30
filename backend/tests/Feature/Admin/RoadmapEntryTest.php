<?php

use App\Livewire\Admin\RoadmapEntry;
use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasureLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Carbon::setTestNow('2026-09-15 10:00:00');
});
afterEach(fn () => Carbon::setTestNow());

test('the grid renders groups, measure rows with their state, and cells carrying the data the JS reads', function () {
    AdminFixture::roadmap();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 3.0, 2 => 10.0], [1 => 'август']);
    $l1 = AdminFixture::line($bogot, 1);
    $this->actingAs(AdminFixture::user());

    Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->assertSet('period', '2026-09')
        ->assertSee('Амалда киритиш')
        ->assertSee('Хоразм вилояти')
        ->assertSeeInOrder(['Вилоятда амалга ошириладиган йирик лойиҳалар', '«Куловот» каналини реконструкция қилиш.', 'Туманларда амалга ошириладиган лойиҳалар', 'Боғот тумани', 'Хўжаликлараро каналлар', 'Ички каналлар', 'Гурлан тумани', 'Насос агрегатлари'])
        ->assertSee('туман ҳокими Ж.Назаров')
        ->assertSeeHtml('data-measure="' . $bogot->id . '" data-state="carry" data-deadline="0"')
        ->assertSeeHtml('data-line="' . $l1->id . '" data-measure="' . $bogot->id . '" data-field="actual" data-plan="7.8" data-loaded="3" data-loaded-text="3" data-state="carry"')
        ->assertSeeHtml('value="август"')
        ->assertSee('2026 йил августдан кўчирилган')
        ->assertSeeHtml('data-measure="' . AdminFixture::measure(1, 1)->id . '" data-state="empty"')
        ->assertSee('Батафсил (2 банд)')
        ->assertSee('2026 йил декабрь')
        ->assertSee('Қолганини ўзгаришсиз тасдиқлаш')
        // the arm text names what the confirm actually does: only 1 of the 4 carries anything over
        ->assertSee('4 та чора-тадбир 2026 йил сентябрь учун ҳозирги кўринишда сақланади — 1 таси ўтган ой қийматлари билан, 3 таси бўш.')
        ->assertSee('Фильтрга мос чора-тадбир йўқ');

    $this->get('/admin/roadmaps/1733?davr=2026-08')->assertOk()->assertSee('2026 йил август')->assertSee('/js/ad-entry.js', false);
});

test('save applies the buffer, re-renders with saved cells and reports; an invalid cell is refused with its message', function () {
    AdminFixture::roadmap();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    $l1    = AdminFixture::line($bogot, 1);
    $l2    = AdminFixture::line($bogot, 2);
    $this->actingAs(AdminFixture::user());

    $c = Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->call('save', [$l1->id => ['actual' => '7,8', 'note' => 'тайёр'], $l2->id => ['actual' => '20']])
        ->assertReturned(['ok' => true, 'measures' => 1, 'changes' => 3])
        ->assertSet('flash', '1 та чора-тадбир сақланди')
        ->assertSet('version', 1)
        ->assertDispatched('ad-refresh')
        ->assertSeeHtml('data-measure="' . $bogot->id . '" data-state="saved"')
        ->assertSeeHtml('data-loaded="7.8" data-loaded-text="7,8" data-state="saved"');
    expect((float) RoadmapLineProgress::where('roadmap_measure_line_id', $l2->id)->where('report_period', '2026-09')->value('actual_value'))->toBe(20.0);
    expect($bogot->fresh()->status)->toBe('in_progress');

    $c->call('save', [$l2->id => ['actual' => 'x']])
        ->assertReturned(['ok' => false, 'message' => '«Ички каналлар»: рақам эмас'])
        ->assertSet('error', '«Ички каналлар»: рақам эмас')
        ->assertSee('«Ички каналлар»: рақам эмас')
        // an import re-keys the cells like a save, but with `ad-imported`: the grid drops the unsaved
        // buffer instead of re-typing it over the values the file just brought in
        ->dispatch('progress-imported')
        ->assertSet('flash', 'Excel файли юкланди')
        ->assertSet('error', '')
        ->assertSet('version', 2)
        ->assertDispatched('ad-imported')
        ->assertNotDispatched('ad-refresh');
    expect(RoadmapChangeLog::count())->toBe(3);
});

test('confirmRest, period switching and the history drawer', function () {
    AdminFixture::roadmap();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 7.8, 2 => 33.0]);
    $this->actingAs(AdminFixture::user());

    $c = Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->call('confirmRest')
        ->assertSet('flash', '4 та чора-тадбир ўзгаришсиз тасдиқланди')
        ->assertDispatched('ad-refresh')
        ->assertSeeHtml('data-measure="' . $bogot->id . '" data-state="saved"');
    expect($bogot->fresh()->status)->toBe('done');

    $c->call('selectPeriod', '2026-08')
        ->assertSet('period', '2026-08')
        ->assertSet('flash', '')
        ->assertSee('2026 йил август')
        ->call('selectPeriod', '2026-Q3')
        ->assertSet('period', '2026-09');

    $c->call('openHistory', $bogot->id)
        ->assertSet('historyFor', $bogot->id)
        ->assertSee('Ўзгаришлар тарихи')
        ->assertSee('Ўзгаришсиз тасдиқланди')
        ->assertSee('2 та қатор')
        ->call('closeHistory')
        ->assertSet('historyFor', null);
});

test('the correction panel loads the lines, edits them in memory, saves through ProgressEntry::correct and shows its errors', function () {
    AdminFixture::roadmap();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    $l1    = AdminFixture::line($bogot, 1);
    $l2    = AdminFixture::line($bogot, 2);
    AdminFixture::report($bogot, '2026-08', [1 => 3.9]);
    $this->actingAs(AdminFixture::user());

    $c = Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->call('openPanel', $bogot->id)
        ->assertSet('panelFor', $bogot->id)
        ->call('panelMove', 5, 1)                        // out of range: a no-op, the list below is unchanged
        ->assertSet('panelLines', [
            ['id' => $l1->id, 'label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => '7,8', 'months' => 1],
            ['id' => $l2->id, 'label' => 'Ички каналлар', 'unit' => 'км', 'plan' => '33', 'months' => 0],
        ])
        ->assertSee('Индикаторларни тузатиш')
        ->assertSee('1 ойлик ҳисобот ҳам ўчирилади')
        ->call('panelAdd')
        ->set('panelLines.2.label', 'Гидропостлар')
        ->set('panelLines.2.unit', 'та')
        ->set('panelLines.2.plan', '4')
        ->call('panelMove', 2, -1)                       // [l1, new, l2]
        ->call('panelRemove', 2)                         // drops Ички каналлар
        ->call('panelSave')
        ->assertSet('panelFor', null)
        ->assertSet('flash', 'Индикаторлар янгиланди')
        ->assertSet('error', '')
        ->assertDispatched('ad-refresh');
    expect($bogot->fresh()->lines->map(fn ($l) => [$l->line_no, $l->label])->all())->toBe([[1, 'Хўжаликлараро каналлар'], [2, 'Гидропостлар']]);
    expect(RoadmapMeasureLine::find($l2->id))->toBeNull();

    $c->call('openPanel', $bogot->id)
        ->set('panelLines.0.label', '')
        ->call('panelSave')
        ->assertSet('panelFor', $bogot->id)
        ->assertSet('error', '1-қатор: индикатор номи бўш.')
        ->call('panelClose')
        ->assertSet('panelFor', null)
        ->assertSet('panelLines', []);
});

test('region switching redirects; an unknown region is 404; a guest is 403', function () {
    AdminFixture::roadmap();
    $user = AdminFixture::user();

    $this->actingAs($user);
    Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->call('switchRegion', '1733')
        ->assertRedirect(route('admin.roadmaps.entry', ['code' => 1733, 'davr' => '2026-09']));
    Livewire::test(RoadmapEntry::class, ['code' => 1703])->assertNotFound();
    $this->get('/admin/roadmaps/1703')->assertNotFound()->assertSee('Бу вилоят учун йўл харитаси юкланмаган')->assertSee('Чиқиш');

    // a measure of another road map (here: none at all) is never reachable through the panel or the drawer
    Livewire::test(RoadmapEntry::class, ['code' => 1733])->call('openPanel', 999999)->assertNotFound();
    Livewire::test(RoadmapEntry::class, ['code' => 1733])->call('openHistory', 999999)->assertNotFound();

    auth()->logout();
    Livewire::test(RoadmapEntry::class, ['code' => 1733])->assertForbidden();
    $this->get('/no-such-page')->assertNotFound()->assertSee('Саҳифа топилмади');
});
