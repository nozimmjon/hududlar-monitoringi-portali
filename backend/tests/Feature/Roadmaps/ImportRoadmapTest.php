<?php

use App\Models\District;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\RoadmapDocxBuilder;

uses(RefreshDatabase::class);

function roadmapKhorezmFixture(?array $rows = null): string
{
    return RoadmapDocxBuilder::make($rows ?? [
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['«Куловот» каналини реконструкция қилиш.'], ['Республика бюджети маблағлари,', '32,0 млрд сўм'], ['2026 йил', 'декабрь'], ['Сув хўжалиги вазирлиги (Ў.Шералиев),', 'Вилоят ҳокимлиги (Ў.Машарипов)']],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['Чапқирғоқ-Амударё', 'ИТҲБ (Э.Нурметов)']],
        ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['Суғориш тармоқларини бетонлаштириш, жумладан:', '1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.'], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Тупроққала тумани (масъул – туман ҳокими А.Жималязов)'],   // alt_labels spelling
        ['measure', ['1 280 гектар ерда сув тежовчи технологиялар.'], ['Банк кредити'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
    ]);
}

test('imports a regional road map: header row, measures, district links', function () {
    $this->seed();

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapKhorezmFixture()]);

    expect($exit)->toBe(0);
    $roadmap = Roadmap::where('region_code', 1733)->firstOrFail();
    expect($roadmap->domain)->toBe('water');
    expect($roadmap->year)->toBe(2026);
    expect($roadmap->title_text)->toContain('“ЙЎЛ ХАРИТАСИ”');
    expect($roadmap->approvers_text)->toContain('Вилоят ҳокими');
    expect($roadmap->source_file)->toEndWith('.docx');
    expect($roadmap->imported_at)->not->toBeNull();

    expect($roadmap->measures()->count())->toBe(4);
    $bogot = District::where('code', 1733204)->firstOrFail();
    $tq    = District::where('code', 1733221)->firstOrFail();
    expect($roadmap->measures()->where('district_id', $bogot->id)->count())->toBe(1);
    expect($roadmap->measures()->where('district_id', $tq->id)->count())->toBe(1);       // resolved via alt_labels

    $m = $roadmap->measures()->where('district_id', $bogot->id)->first();
    expect($m->title)->toBe('Суғориш тармоқларини бетонлаштириш');
    expect($m->detailLines())->toBe(['1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.']);
    expect($m->district_head_text)->toBe('туман ҳокими Ж.Назаров');
    expect($m->section_no)->toBe(2);
    expect($m->seq_no)->toBe(1);

    $first = $roadmap->measures()->orderBy('id')->first();
    expect($first->funding_text)->toBe('Республика бюджети маблағлари, 32,0 млрд сўм');
    expect($first->deadline_text)->toBe('2026 йил декабрь');
    expect($first->responsible_text)->toBe('Сув хўжалиги вазирлиги (Ў.Шералиев), Вилоят ҳокимлиги (Ў.Машарипов)');

    expect(Artisan::output())->toContain('Total: 4 measures, 2 districts');
});

test('re-import replaces the measures instead of duplicating them', function () {
    $this->seed();
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapKhorezmFixture()]);
    $firstId = Roadmap::where('region_code', 1733)->value('id');

    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapKhorezmFixture()]);

    expect(Roadmap::count())->toBe(1);
    expect(Roadmap::where('region_code', 1733)->value('id'))->toBe($firstId);
    expect(RoadmapMeasure::count())->toBe(4);
});

test('dry run parses and reports but writes nothing', function () {
    $this->seed();

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapKhorezmFixture(), '--dry-run' => true]);

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('Dry run');
    expect(Roadmap::count())->toBe(0);
});

test('an unknown district aborts the whole import', function () {
    $this->seed();
    $file = roadmapKhorezmFixture([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Йўқтуман тумани (масъул – туман ҳокими X)'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
    ]);

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $file]);

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('Йўқтуман тумани');
    expect(Roadmap::count())->toBe(0);
});

test('a district of another region is not resolved (resolver is region-scoped)', function () {
    $this->seed();
    $file = roadmapKhorezmFixture([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Андижон тумани (масъул – туман ҳокими X)'],   // exists, but in region 1703
        ['measure', ['x'], ['y'], ['z'], ['w']],
    ]);

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $file]);

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('Андижон тумани');
    expect(RoadmapMeasure::count())->toBe(0);
});

test('a missing or unknown region is rejected before reading the file', function () {
    $this->seed();

    expect(Artisan::call('import:roadmap', ['--file' => roadmapKhorezmFixture()]))->toBe(1);
    expect(Artisan::call('import:roadmap', ['--region' => 9999, '--file' => roadmapKhorezmFixture()]))->toBe(1);
    expect(Artisan::output())->toContain('--region');
});

test('a missing file is reported', function () {
    $this->seed();

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => 'C:/nope/missing.docx']);

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('топилмади');
});

test('a failing re-import keeps the previous measures (parse happens before any write)', function () {
    $this->seed();
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapKhorezmFixture()]);
    $broken = roadmapKhorezmFixture([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Йўқтуман тумани (масъул – туман ҳокими X)'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
    ]);

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $broken]);

    expect($exit)->toBe(1);
    expect(RoadmapMeasure::count())->toBe(4);
    expect(Roadmap::where('region_code', 1733)->value('source_file'))->not->toBe(basename($broken));
});

test('an invalid year or domain is rejected before reading the file', function () {
    $this->seed();

    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => 'C:/nope.docx', '--year' => 'abc']))->toBe(1);
    expect(Artisan::output())->toContain('--year');
    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => 'C:/nope.docx', '--domain' => 'gas']))->toBe(1);
    expect(Artisan::output())->toContain('--domain');
});

test('an empty title is imported with a warning', function () {
    $this->seed();
    $file = RoadmapDocxBuilder::make(
        [['section', 'I. Йирик лойиҳалар'], ['measure', ['Ягона тадбир.'], ['b'], ['c'], ['d']]],
        null,
        [],
    );

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $file]);

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('сарлавҳа');
    expect(Roadmap::where('region_code', 1733)->value('title_text'))->toBe('');
});
