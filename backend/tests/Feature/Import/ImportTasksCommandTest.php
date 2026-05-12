<?php

use App\Models\Task;
use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('regions')->where('code', 1703)->value('id');

    DB::table('modules')->insert([
        ['code' => 'macro',             'label' => 'Макро',     'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'inflation',         'label' => 'Инфляция',  'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'budget',            'label' => 'Бюджет',    'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'budget_invest',     'label' => 'Инвест.',   'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'foreign_invest',    'label' => 'Хор. инв.', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'export',            'label' => 'Экспорт',   'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'employment',        'label' => 'Бандлик',   'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // indicators table requires scope and default_unit (non-nullable without default)
    DB::table('indicators')->insert([
        ['code' => 'grp',          'label_full' => 'ЯҲМ',       'label_short' => 'ЯҲМ',       'scope' => 'region', 'default_unit' => 'trln', 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'industry',     'label_full' => 'Саноат',    'label_short' => 'Саноат',    'scope' => 'region', 'default_unit' => 'trln', 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'agriculture',  'label_full' => 'Қишлоқ',    'label_short' => 'Қ. ҳ.',     'scope' => 'region', 'default_unit' => 'trln', 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'construction', 'label_full' => 'Қурилиш',   'label_short' => 'Қурилиш',   'scope' => 'region', 'default_unit' => 'trln', 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'services',     'label_full' => 'Хизматлар', 'label_short' => 'Хизмат',    'scope' => 'region', 'default_unit' => 'trln', 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'unemployment', 'label_full' => 'Ишсизлик',  'label_short' => 'Ишсизлик',  'scope' => 'region', 'default_unit' => 'pct',  'created_at' => now(), 'updated_at' => now()],
        ['code' => 'poverty',      'label_full' => 'Камбағ.',   'label_short' => 'Камбағ.',   'scope' => 'region', 'default_unit' => 'pct',  'created_at' => now(), 'updated_at' => now()],
    ]);

    // Seed enough Andijan districts for the multi-district rows in the docx.
    $districts = [
        ['code' => 1703401, 'name_short' => 'Андижон ш.',    'name_full' => 'Андижон шаҳри'],
        ['code' => 1703408, 'name_short' => 'Хонобод ш.',    'name_full' => 'Хонобод шаҳри'],
        ['code' => 1703224, 'name_short' => 'Асака т.',      'name_full' => 'Асака тумани'],
        ['code' => 1703203, 'name_short' => 'Андижон т.',    'name_full' => 'Андижон тумани'],
        ['code' => 1703230, 'name_short' => 'Шаҳрихон т.',  'name_full' => 'Шаҳрихон тумани'],
        ['code' => 1703209, 'name_short' => 'Бўстон т.',     'name_full' => 'Бўстон тумани'],
        ['code' => 1703217, 'name_short' => 'Улуғнор т.',    'name_full' => 'Улуғнор тумани'],
        ['code' => 1703232, 'name_short' => 'Пахтаобод т.', 'name_full' => 'Пахтаобод тумани'],
    ];
    foreach ($districts as $i => $d) {
        DB::table('districts')->insert(array_merge($d, [
            'region_id'   => $regionId,
            'region_code' => 1703,
            'kind'        => str_contains($d['name_full'], 'шаҳри') ? 'city' : 'district',
            'sort_order'  => $i + 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]));
    }
});

test('import:tasks andijan loads at least 80 tasks from docx', function () {
    Artisan::call('import:tasks', ['region' => 'andijan']);

    expect(Task::where('region_code', 1703)->count())->toBeGreaterThanOrEqual(80);
});

test('imported tasks have module + indicator codes derived from sections', function () {
    Artisan::call('import:tasks', ['region' => 'andijan']);

    // Task number 3 is in section I.1.2 (Саноат), h1 deadline.
    $task = Task::where('region_code', 1703)->where('task_number', '3')->first();
    expect($task)->not->toBeNull();
    expect($task->module_code)->toBe('macro');
    expect($task->indicator_code)->toBe('industry');
    expect($task->kind)->toBe('kpi');
    expect($task->section_path)->toBe('I.1.2');
    expect($task->period_code)->toBe('h1');
});

test('multi-district executor parses to pivot rows', function () {
    Artisan::call('import:tasks', ['region' => 'andijan']);

    // Task 80 in the docx has executor listing exactly:
    //   Андижон вилояти ҳокимлиги (skipped — contains "вилояти"),
    //   Бўстон тумани ҳокимлиги, Улуғнор тумани ҳокимлиги, Пахтаобод тумани ҳокимлиги
    // → 3 matched districts. Task 75 also contains those three names but
    //   lists all 8 seeded districts so we target task_number='80' explicitly.
    $task = Task::where('region_code', 1703)
        ->where('task_number', '80')
        ->first();

    expect($task)->not->toBeNull();
    expect($task->executor_text)->toContain('Бўстон');
    expect($task->executor_text)->toContain('Улуғнор');
    expect($task->executor_text)->toContain('Пахтаобод');
    expect($task->districts()->count())->toBe(3);
});

test('rerun is idempotent', function () {
    Artisan::call('import:tasks', ['region' => 'andijan']);
    $first = Task::where('region_code', 1703)->count();

    Artisan::call('import:tasks', ['region' => 'andijan']);
    $second = Task::where('region_code', 1703)->count();

    expect($second)->toBe($first);
});
