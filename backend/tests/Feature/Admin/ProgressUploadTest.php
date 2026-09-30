<?php

use App\Livewire\Admin\ProgressUpload;
use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('the panel opens for a region, refuses a non-xlsx, previews a dry run without writing, then applies and notifies', function () {
    AdminFixture::roadmap();
    $user = AdminFixture::user();
    $this->actingAs($user);
    $file = AdminFixture::template('2026-09', ['G8' => 7.8]);

    try {
        $c = Livewire::test(ProgressUpload::class, ['period' => '2026-09'])
            ->assertSet('open', false)
            ->assertDontSee('Excel’дан юклаш')
            ->dispatch('open-upload', code: 1733)
            ->assertSet('open', true)
            ->assertSet('code', 1733)
            ->assertSet('uploadPeriod', '2026-09')
            ->assertSee('Хоразм вилояти')
            ->set('file', UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))
            ->call('check')
            ->assertHasErrors(['file'])
            ->assertSee('Фақат .xlsx файл')
            // a plain UploadedFile has no $name — Livewire's test harness only accepts Testing\File (fake()) uploads
            ->set('file', UploadedFile::fake()->createWithContent('Хоразм.xlsx', file_get_contents($file)))
            ->call('check')
            ->assertHasNoErrors()
            ->assertSet('report.ok', true)
            ->assertSet('report.written', false)
            ->assertSee('4/4')
            ->assertSee('Қўллаш');
        expect(RoadmapLineProgress::count())->toBe(0);

        $c->call('apply')
            ->assertSet('report.written', true)
            ->assertSet('done', '4 та чора-тадбир 2026 йил сентябрь учун юкланди')
            ->assertDispatched('progress-imported')
            ->assertSet('file', null);
        expect(RoadmapLineProgress::count())->toBe(5);
        expect(RoadmapChangeLog::where('action', 'import')->value('user_id'))->toBe($user->id);
        expect(RoadmapChangeLog::where('action', 'import')->value('new_value'))->toBe('Хоразм.xlsx · 4 та чора-тадбир · 1 та амалда');

        $c->call('close')->assertSet('open', false)->assertSet('report', null)->assertSet('done', '');
    } finally {
        @unlink($file);
    }
});

test('a broken file shows the error line and apply is refused; the month select is validated', function () {
    AdminFixture::roadmap();
    $this->actingAs(AdminFixture::user());
    $bad = AdminFixture::template('2026-09', ['G8' => 'кўп']);

    try {
        $c = Livewire::test(ProgressUpload::class, ['period' => '2026-09'])
            ->dispatch('open-upload', code: 1733)
            ->set('file', UploadedFile::fake()->createWithContent('bad.xlsx', file_get_contents($bad)))
            ->set('uploadPeriod', '2026-Q3')
            ->call('check')
            ->assertHasErrors(['uploadPeriod'])
            ->set('uploadPeriod', '2026-09')
            ->call('check')
            ->assertSet('report.ok', false)
            ->assertSee('G8')
            ->assertDontSee('Қўллаш');
        $c->call('apply')->assertSet('report.written', false);
        expect(RoadmapLineProgress::count())->toBe(0);
    } finally {
        @unlink($bad);
    }
});

test('a guest gets 403', function () {
    Livewire::test(ProgressUpload::class, ['period' => '2026-09'])->dispatch('open-upload', code: 1733)->assertForbidden();
});
