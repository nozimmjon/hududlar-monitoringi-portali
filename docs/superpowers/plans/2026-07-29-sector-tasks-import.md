# Sector (тармоқ) Guarantee-Letter Tasks Import — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Store the 17 sector enterprises' guarantee-letter tasks (119 tasks / 521 indicator lines from `Вазифалар_2026_тармоқлар_кесимида.xlsx`) with per-period plan/actual history and an idempotent import command.

**Architecture:** Three new tables (`sectors`, `sector_tasks`, `sector_task_progress`) mirroring the proven `tasks`/`task_progress` design, fully independent of the region pipeline. One parser service + one import command + one recompute command. Reuses `App\Support\TaskStatus::aggregate()` (weakest-link status) and `App\Support\TaskPeriod` (period type/sort) — do NOT reinvent those.

**Tech Stack:** Laravel 12, PostgreSQL, PhpSpreadsheet, Pest 3.

**Spec:** `docs/superpowers/specs/2026-07-29-sector-tasks-import-design.md`

**Test-run note:** Another session may run tests concurrently. Always run tests with a session-unique DB:
`PGPASSWORD=123 psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE hm_test_sectors"` once, then prefix every test run with `DB_DATABASE=hm_test_sectors` (bash). Drop the DB when the plan is done.

**Workbook layout (ground truth, verified):** row 1 title, row 2 org+signer, rows 3–4 headers (A:№ B:№ C:Кўрсаткич номи D:Индикатор номи E:Ўлчов бирлиги F:Муддати / G:Режа кўрсаткичи H:Амалда ижроси I:Бажарилиши фоизда), data from row 5, column A filled = new task, blank A = continuation line, sheet ends at "Изоҳлар:". H/I currently empty.

---

### Task 1: `sectors` table, model, seeder

**Files:**
- Create: `backend/database/migrations/2026_07_30_000001_create_sectors_table.php`
- Create: `backend/app/Models/Sector.php`
- Create: `backend/database/seeders/SectorSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Test: `backend/tests/Feature/Sectors/SectorSeederTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('sectors table exists with expected columns', function () {
    foreach (['id', 'code', 'name_short', 'org_full', 'signer_text', 'sort_order'] as $col) {
        expect(Schema::hasColumn('sectors', $col))->toBeTrue("missing column {$col}");
    }
});

test('seeder creates 17 sectors with stable codes in sheet order', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);

    expect(Sector::count())->toBe(17);
    expect(Sector::orderBy('sort_order')->pluck('code')->all())->toBe([
        'uzbekneftgaz', 'uzbekgidroenergo', 'ies', 'kimyo_sanoati', 'nkmk',
        'navoiyuran', 'olmaliq_kmk', 'uzmetkombinat', 'tmk', 'uzavtosanoat',
        'uzeltehsanoat', 'yengil_sanoat', 'uztoqimachiliksanoat', 'uzcharmsanoat',
        'qurilish_materiallari', 'farmatsevtika', 'uzbekzargarsanoati',
    ]);
    expect(Sector::where('code', 'nkmk')->first()->name_short)->toBe('НКМК');
});

test('seeder is idempotent', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);
    $this->seed(Database\Seeders\SectorSeeder::class);
    expect(Sector::count())->toBe(17);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=SectorSeederTest`
Expected: FAIL — `sectors` table does not exist / class `Sector` not found.

- [ ] **Step 3: Write migration, model, seeder**

Migration `2026_07_30_000001_create_sectors_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 48)->unique();          // 'uzbekneftgaz', 'nkmk', ...
            $table->string('name_short', 96);              // "Ўзбекнефтгаз" (sheet name)
            $table->string('org_full', 255);               // "«Ўзбекнефтгаз» АЖ"
            $table->string('signer_text', 255)->nullable();// "Бошқарув раиси А. Сангинов"
            $table->smallInteger('sort_order');            // sheet order 1–17
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
```

`app/Models/Sector.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = ['code', 'name_short', 'org_full', 'signer_text', 'sort_order'];

    public function tasks(): HasMany
    {
        return $this->hasMany(SectorTask::class);
    }
}
```

`database/seeders/SectorSeeder.php` — codes are hand-assigned and permanent; `updateOrCreate` keyed by `code` makes re-seeding safe:

```php
<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    /** sort_order => [code, name_short, org_full, signer_text] */
    public const SECTORS = [
        1  => ['uzbekneftgaz', 'Ўзбекнефтгаз', '«Ўзбекнефтгаз» АЖ', 'Бошқарув раиси А. Сангинов'],
        2  => ['uzbekgidroenergo', 'Ўзбекгидроэнерго', '«Ўзбекгидроэнерго» АЖ', 'Бошқаруви раиси И. Абдурахмонов'],
        3  => ['ies', 'ИЭС', '«Иссиқлик электр станциялари» АЖ', 'Бошқарув раиси Б. Жўраев'],
        4  => ['kimyo_sanoati', 'Кимё саноати', 'Кимё саноати тармоғи', 'Бошқарув раиси О. Темиров'],
        5  => ['nkmk', 'НКМК', '«Навоий кон-металлургия комбинати» АЖ', 'Бошқарув раиси-Бош директор Қ. Санақулов'],
        6  => ['navoiyuran', 'Навоийуран', '«Навоийуран» ДК', 'Бош директор Дж. Файзуллаев'],
        7  => ['olmaliq_kmk', 'Олмалиқ КМК', '«Олмалиқ КМК» АЖ', 'Бошқаруви раиси А. Хурсанов'],
        8  => ['uzmetkombinat', 'Ўзметкомбинат', '«Ўзметкомбинат» АЖ', 'Бошқарув раиси Б. Абдуллаев'],
        9  => ['tmk', 'ТМК', '«Ўзбекистон технологик металлар комбинати» АЖ', 'Бошқарув раиси Ф. Абдуллаев'],
        10 => ['uzavtosanoat', 'Ўзавтосаноат', '«Ўзавтосаноат» АЖ', 'Бошқаруви раиси У. Розуқулов'],
        11 => ['uzeltehsanoat', 'Ўзэлтехсаноат', '«Ўзэлтехсаноат» уюшмаси', 'Бошқарув раиси М. Юнусов'],
        12 => ['yengil_sanoat', 'Енгил саноат', 'Енгил саноат агентлиги', 'Директор Н. Холмуродов'],
        13 => ['uztoqimachiliksanoat', 'Ўзтўқимачиликсаноат', '«Ўзтўқимачиликсаноат» уюшмаси', 'Уюшма раиси М. Жуманиязов'],
        14 => ['uzcharmsanoat', 'Ўзчармсаноат', '«Ўзчармсаноат» уюшмаси', 'Уюшма раиси в.б. А. Латипов'],
        15 => ['qurilish_materiallari', 'Қурилиш материаллари', '«Ўзсаноатқурилишматериаллари» уюшмаси', 'Бошқарув раиси И.И. Раҳимов'],
        16 => ['farmatsevtika', 'Фармацевтика', 'Тиббиёт ва фармацевтика тармоғини ривожлантириш агентлиги', 'Директор А. Азизов'],
        17 => ['uzbekzargarsanoati', 'Ўзбекзаргарсаноати', '«Ўзбекзаргарсаноати» уюшмаси', 'Раис в.б. Н. Мирахмедов'],
    ];

    public function run(): void
    {
        foreach (self::SECTORS as $sortOrder => [$code, $nameShort, $orgFull, $signer]) {
            Sector::updateOrCreate(['code' => $code], [
                'name_short'  => $nameShort,
                'org_full'    => $orgFull,
                'signer_text' => $signer,
                'sort_order'  => $sortOrder,
            ]);
        }
    }
}
```

In `DatabaseSeeder.php` add `SectorSeeder::class,` to the `$this->call([...])` list (after `RegionIndicatorAvailabilitySeeder::class`).

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=SectorSeederTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_07_30_000001_create_sectors_table.php backend/app/Models/Sector.php backend/database/seeders/SectorSeeder.php backend/database/seeders/DatabaseSeeder.php backend/tests/Feature/Sectors/SectorSeederTest.php
git commit -m "feat(sectors): sectors reference table + seeder (17 enterprises)"
```

---

### Task 2: `sector_tasks` + `sector_task_progress` tables and models

**Files:**
- Create: `backend/database/migrations/2026_07_30_000002_create_sector_tasks_table.php`
- Create: `backend/database/migrations/2026_07_30_000003_create_sector_task_progress_table.php`
- Create: `backend/app/Models/SectorTask.php`
- Create: `backend/app/Models/SectorTaskProgress.php`
- Test: `backend/tests/Feature/Sectors/SectorTaskSchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\SectorTaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('sector task tables exist with expected columns', function () {
    foreach (['sector_id', 'task_no', 'title', 'status', 'lines_total', 'lines_done',
              'latest_period', 'headline_unit', 'headline_plan', 'headline_actual', 'headline_pct'] as $col) {
        expect(Schema::hasColumn('sector_tasks', $col))->toBeTrue("sector_tasks missing {$col}");
    }
    foreach (['sector_task_id', 'line_no', 'metric_label', 'unit', 'deadline_text', 'deadline_code',
              'report_period', 'period_type', 'plan_value', 'actual_value', 'pct_of_plan', 'reported_at'] as $col) {
        expect(Schema::hasColumn('sector_task_progress', $col))->toBeTrue("sector_task_progress missing {$col}");
    }
});

test('unique keys hold: task per sector, line per period', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);
    $sector = Sector::where('code', 'nkmk')->first();

    $task = SectorTask::create(['sector_id' => $sector->id, 'task_no' => 1, 'title' => 'Т', 'status' => 'in_progress']);
    expect(fn () => SectorTask::create(['sector_id' => $sector->id, 'task_no' => 1, 'title' => 'Д', 'status' => 'open']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

test('progress line unique per period, cascade delete with task', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);
    $sector = Sector::where('code', 'nkmk')->first();
    $task = SectorTask::create(['sector_id' => $sector->id, 'task_no' => 1, 'title' => 'Т', 'status' => 'in_progress']);

    SectorTaskProgress::create([
        'sector_task_id' => $task->id, 'line_no' => 1, 'metric_label' => 'Олтин', 'unit' => 'тонна',
        'deadline_text' => '2026 йил якуни', 'deadline_code' => 'year',
        'report_period' => '2026-H2', 'period_type' => 'half', 'plan_value' => 98.5,
    ]);
    expect(fn () => SectorTaskProgress::create([
        'sector_task_id' => $task->id, 'line_no' => 1, 'metric_label' => 'Олтин', 'unit' => 'тонна',
        'deadline_text' => '2026 йил якуни', 'deadline_code' => 'year',
        'report_period' => '2026-H2', 'period_type' => 'half', 'plan_value' => 99,
    ]))->toThrow(Illuminate\Database\QueryException::class);

    $task->delete();
    expect(SectorTaskProgress::count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=SectorTaskSchemaTest`
Expected: FAIL — tables missing.

- [ ] **Step 3: Write migrations and models**

`2026_07_30_000002_create_sector_tasks_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sector_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sector_id');
            $table->smallInteger('task_no');                       // column A
            $table->text('title');                                 // column C
            $table->string('status', 16)->default('in_progress');  // done | open | in_progress
            $table->integer('lines_total')->default(0);
            $table->integer('lines_done')->default(0);
            // Denormalized latest-period headline snapshot (same style as tasks)
            $table->string('latest_period', 16)->nullable();
            $table->string('headline_unit', 48)->nullable();
            $table->decimal('headline_plan', 20, 6)->nullable();
            $table->decimal('headline_actual', 20, 6)->nullable();
            $table->decimal('headline_pct', 10, 4)->nullable();
            $table->timestamps();

            $table->foreign('sector_id')->references('id')->on('sectors')->cascadeOnDelete();
            $table->unique(['sector_id', 'task_no'], 'uq_sector_tasks_sector_no');
            $table->index(['sector_id', 'status'], 'idx_sector_tasks_sector_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_tasks');
    }
};
```

`2026_07_30_000003_create_sector_task_progress_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sector_task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_task_id')->constrained('sector_tasks')->cascadeOnDelete();
            $table->smallInteger('line_no');                      // column B — sheet-global, stable key
            $table->string('metric_label', 255);                  // column D
            $table->string('unit', 48)->nullable();               // column E
            $table->string('deadline_text', 64)->nullable();      // column F raw
            $table->string('deadline_code', 8);                   // year | h2 | q3 | q4
            $table->string('report_period', 16);                  // '2026-H2' | '2026-Q3' | '2026-08'
            $table->string('period_type', 8);                     // half | quarter | month
            $table->decimal('plan_value', 20, 6)->nullable();
            $table->decimal('actual_value', 20, 6)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();    // recomputed, never from file
            $table->date('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['sector_task_id', 'line_no', 'report_period'], 'uq_stp_line_period');
            $table->index(['sector_task_id', 'report_period'], 'idx_stp_task_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_task_progress');
    }
};
```

`app/Models/SectorTask.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorTask extends Model
{
    protected $fillable = [
        'sector_id', 'task_no', 'title', 'status', 'lines_total', 'lines_done',
        'latest_period', 'headline_unit', 'headline_plan', 'headline_actual', 'headline_pct',
    ];

    protected $casts = [
        'headline_plan'   => 'decimal:6',
        'headline_actual' => 'decimal:6',
        'headline_pct'    => 'decimal:4',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(SectorTaskProgress::class);
    }
}
```

`app/Models/SectorTaskProgress.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorTaskProgress extends Model
{
    protected $table = 'sector_task_progress';

    protected $fillable = [
        'sector_task_id', 'line_no', 'metric_label', 'unit', 'deadline_text', 'deadline_code',
        'report_period', 'period_type', 'plan_value', 'actual_value', 'pct_of_plan', 'reported_at',
    ];

    protected $casts = [
        'plan_value'   => 'decimal:6',
        'actual_value' => 'decimal:6',
        'pct_of_plan'  => 'decimal:4',
        'reported_at'  => 'date',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(SectorTask::class, 'sector_task_id');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=SectorTaskSchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_07_30_000002_create_sector_tasks_table.php backend/database/migrations/2026_07_30_000003_create_sector_task_progress_table.php backend/app/Models/SectorTask.php backend/app/Models/SectorTaskProgress.php backend/tests/Feature/Sectors/SectorTaskSchemaTest.php
git commit -m "feat(sectors): sector_tasks + sector_task_progress schema and models"
```

---

### Task 3: Workbook fixture builder + `SectorWorkbookParser`

**Files:**
- Create: `backend/tests/Helpers/SectorWorkbookBuilder.php`
- Create: `backend/app/Services/Tasks/SectorWorkbookParser.php`
- Test: `backend/tests/Feature/Sectors/SectorWorkbookParserTest.php`

- [ ] **Step 1: Write the fixture builder** (test infrastructure, no TDD cycle of its own)

`tests/Helpers/SectorWorkbookBuilder.php`:

```php
<?php

namespace Tests\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SectorWorkbookBuilder
{
    /**
     * Build a workbook mirroring the real "Вазифалар_2026_тармоқлар_кесимида" layout.
     *
     * @param list<array{0:string,1:string,2:list<array>}> $sheets  [sheetTitle, orgLine, dataRows]
     *        dataRows: [A task_no|null, B line_no, C title|null, D label, E unit, F deadline, G plan, H actual, I pct]
     */
    public static function make(array $sheets, ?string $path = null): string
    {
        $wb = new Spreadsheet();
        $wb->removeSheetByIndex(0);
        foreach ($sheets as $i => [$title, $org, $rows]) {
            $sheet = $wb->createSheet($i);
            $sheet->setTitle($title);
            $sheet->setCellValue('A1', '2026 йил якунига қадар амалга ошириладиган долзарб вазифалар');
            $sheet->setCellValue('A2', $org);
            $sheet->fromArray(['№', '№', 'Кўрсаткич номи', 'Индикатор номи', 'Ўлчов бирлиги', 'Муддати', $org], null, 'A3');
            $sheet->fromArray([null, null, null, null, null, null, 'Режа кўрсаткичи', 'Амалда ижроси', 'Бажарилиши фоизда'], null, 'A4');
            $r = 5;
            foreach ($rows as $row) {
                $sheet->fromArray($row, null, 'A' . $r++);
            }
            $sheet->setCellValue('A' . $r, 'Изоҳлар:');
            $sheet->setCellValue('A' . ($r + 1), '1. Жадвалга фақат кафолат хатларининг «II бўлим» вазифалари киритилди.');
        }
        $path ??= tempnam(sys_get_temp_dir(), 'sector_wb_') . '.xlsx';
        (new Xlsx($wb))->save($path);

        return $path;
    }
}
```

- [ ] **Step 2: Write the failing parser tests**

`tests/Feature/Sectors/SectorWorkbookParserTest.php`:

```php
<?php

use App\Services\Tasks\SectorWorkbookParser;
use Tests\Helpers\SectorWorkbookBuilder;

test('parses tasks, continuation lines, units and deadlines', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', '«Ўзбекнефтгаз» АЖ — Бошқарув раиси А. Сангинов имзолаган кафолат хати', [
            [1, 1, 'Товар маҳсулот ҳажмини етказиш.', 'Товар маҳсулот ҳажми', 'трлн сўм', '2026 йил якуни', 56.7, null, null],
            [2, 2, 'Углеводород қазиб чиқариш.', 'Табиий газ', 'млрд куб метр', '2026 йил якуни', 24.7, null, null],
            [null, 3, null, 'Суюқ углеводородлар', 'минг тонна', '2026 йил 2-ярим йиллиги', 1188, null, null],
            [null, 4, null, 'Шундан: нефть', 'минг тонна', '2026 йил III-чорак', 62.9, null, null],
        ]],
    ]);

    $parsed = (new SectorWorkbookParser())->parse($file);

    expect($parsed['sheets'])->toHaveCount(1);
    $sheet = $parsed['sheets'][0];
    expect($sheet['sort_order'])->toBe(1);
    expect($sheet['tasks'])->toHaveCount(2);

    $t2 = $sheet['tasks'][1];
    expect($t2['task_no'])->toBe(2);
    expect($t2['title'])->toBe('Углеводород қазиб чиқариш.');
    expect($t2['lines'])->toHaveCount(3);
    expect($t2['lines'][1]['metric_label'])->toBe('Суюқ углеводородлар');
    expect($t2['lines'][1]['line_no'])->toBe(3);
    expect($t2['lines'][1]['deadline_code'])->toBe('h2');
    expect($t2['lines'][2]['deadline_code'])->toBe('q3');
    expect($t2['lines'][0]['plan'])->toEqualWithDelta(24.7, 0.001);
    expect($t2['lines'][0]['actual'])->toBeNull();
});

test('normalizes all deadline spellings', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null],
            [null, 2, null, 'б', 'та', '2026 йил 2-ярим йиллиги', 1, null, null],
            [null, 3, null, 'в', 'та', '2026 йил III-чорак', 1, null, null],
            [null, 4, null, 'г', 'та', '2026 йил III чорак', 1, null, null],
            [null, 5, null, 'д', 'та', '2026 йил IV чорак', 1, null, null],
            [null, 6, null, 'е', 'та', '2026 йил IV-чорак', 1, null, null],
        ]],
    ]);

    $lines = (new SectorWorkbookParser())->parse($file)['sheets'][0]['tasks'][0]['lines'];
    expect(array_column($lines, 'deadline_code'))->toBe(['year', 'h2', 'q3', 'q3', 'q4', 'q4']);
});

test('stops at Изоҳлар and keeps sheet-global line numbers', function () {
    $file = SectorWorkbookBuilder::make([
        ['16. Фармацевтика', 'орг', [
            [1, 1, 'В1', 'Ишлаб чиқариш', 'трлн сўм', '2026 йил якуни', 8.5, null, null],
        ]],
    ]);

    $parsed = (new SectorWorkbookParser())->parse($file);
    expect($parsed['sheets'][0]['sort_order'])->toBe(16);
    expect($parsed['sheets'][0]['tasks'])->toHaveCount(1);
});

test('rejects layout drift in header rows', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [[1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null]]],
    ]);
    // Sabotage the header: swap column D label
    $wb = PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $wb->getSheet(0)->setCellValue('D3', 'Бошқа устун');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($wb))->save($file);

    expect(fn () => (new SectorWorkbookParser())->parse($file))
        ->toThrow(RuntimeException::class, 'Индикатор номи');
});

test('rejects sheet title without a leading number', function () {
    $file = SectorWorkbookBuilder::make([
        ['Номаълум лист', 'орг', [[1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null]]],
    ]);

    expect(fn () => (new SectorWorkbookParser())->parse($file))
        ->toThrow(RuntimeException::class);
});

test('reads actual values and ignores the file pct column', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В', 'а', 'та', '2026 йил якуни', 100, 55, 999],
        ]],
    ]);

    $line = (new SectorWorkbookParser())->parse($file)['sheets'][0]['tasks'][0]['lines'][0];
    expect($line['plan'])->toEqualWithDelta(100.0, 0.001);
    expect($line['actual'])->toEqualWithDelta(55.0, 0.001);
    expect($line)->not->toHaveKey('pct'); // parser never surfaces column I
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=SectorWorkbookParserTest`
Expected: FAIL — class `SectorWorkbookParser` not found.

- [ ] **Step 4: Implement the parser**

`app/Services/Tasks/SectorWorkbookParser.php`:

```php
<?php

namespace App\Services\Tasks;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Parses the all-sectors guarantee-letter tasks workbook
 * ("Вазифалар_2026_тармоқлар_кесимида.xlsx", one sheet per enterprise).
 *
 * Layout per sheet: row 1 title, row 2 org+signer, rows 3–4 headers, data from
 * row 5 (col A filled = new task, blank = continuation), "Изоҳлар:" ends the sheet.
 * Column I (Бажарилиши фоизда) is deliberately never read — pct is recomputed.
 */
class SectorWorkbookParser
{
    /** Header needles verified per sheet: [cellRow, colIndex, needle] */
    private const HEADER_CHECKS = [
        [2, 2, 'кўрсаткич номи'],   // C3
        [2, 3, 'индикатор номи'],   // D3
        [2, 4, 'ўлчов бирлиги'],    // E3
        [2, 5, 'муддат'],           // F3
        [3, 6, 'режа'],             // G4
        [3, 7, 'амалда'],           // H4
        [3, 8, 'фоиз'],             // I4
    ];

    /**
     * @return array{
     *   sheets: list<array{sort_order: int, sheet_title: string, tasks: list<array{
     *     task_no: int, title: string, lines: list<array{
     *       line_no: int, metric_label: string, unit: ?string,
     *       deadline_text: ?string, deadline_code: string, plan: ?float, actual: ?float
     *     }>
     *   }>}>,
     *   warnings: list<string>,
     * }
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $wb = $reader->load($path);

        $out = ['sheets' => [], 'warnings' => []];
        foreach ($wb->getAllSheets() as $sheet) {
            $title = self::clean($sheet->getTitle());
            if (! preg_match('/^(\d{1,2})\s*\./u', $title, $m)) {
                throw new RuntimeException("Лист '{$title}': сарлавҳада тартиб рақами йўқ — тармоққа мослаб бўлмайди.");
            }

            $rows = $sheet->rangeToArray('A1:I' . $sheet->getHighestDataRow(), null, true, false);
            $this->verifyHeader($title, $rows);

            $out['sheets'][] = [
                'sort_order'  => (int) $m[1],
                'sheet_title' => $title,
                'tasks'       => $this->parseRows($title, $rows, $out['warnings']),
            ];
        }

        return $out;
    }

    private function verifyHeader(string $title, array $rows): void
    {
        foreach (self::HEADER_CHECKS as [$rowIdx, $colIdx, $needle]) {
            $cell = mb_strtolower(self::clean((string) ($rows[$rowIdx][$colIdx] ?? '')));
            if (mb_strpos($cell, $needle) === false) {
                $cellName = chr(65 + $colIdx) . ($rowIdx + 1);
                throw new RuntimeException(
                    "Лист '{$title}': {$cellName} катагида '{$needle}' кутилган эди — жадвал тузилмаси ўзгарган, импорт тўхтатилди."
                    . " (Кутилган устунлар: Кўрсаткич номи / Индикатор номи / Ўлчов бирлиги / Муддати / Режа / Амалда / фоизда)"
                );
            }
        }
    }

    /** @param list<string> $warnings */
    private function parseRows(string $title, array $rows, array &$warnings): array
    {
        $tasks = [];
        $current = null;

        foreach ($rows as $i => $row) {
            if ($i < 4) continue; // rows 1–4: title, org, headers

            $a = self::clean((string) ($row[0] ?? ''));
            if (mb_stripos($a, 'изоҳ') !== false) break;

            $b = self::clean((string) ($row[1] ?? ''));
            $d = self::clean((string) ($row[3] ?? ''));
            if ($b === '' || $d === '') continue; // spacer / malformed row

            if ($a !== '') {
                if ($current !== null) $tasks[] = $current;
                $c = self::clean((string) ($row[2] ?? ''));
                if ($c === '') {
                    $warnings[] = "Лист '{$title}', вазифа №{$a}: Кўрсаткич номи (C) бўш — индикатор номи ишлатилди.";
                    $c = $d;
                }
                $current = ['task_no' => (int) $a, 'title' => $c, 'lines' => []];
            } elseif ($current === null) {
                $warnings[] = "Лист '{$title}', қатор " . ($i + 1) . ": вазифа рақамисиз индикатор қатори ташлаб кетилди.";
                continue;
            }

            $deadlineText = self::clean((string) ($row[5] ?? ''));
            $current['lines'][] = [
                'line_no'       => (int) $b,
                'metric_label'  => $d,
                'unit'          => self::clean((string) ($row[4] ?? '')) ?: null,
                'deadline_text' => $deadlineText ?: null,
                'deadline_code' => $this->deadlineCode($deadlineText, $title, $warnings),
                'plan'          => self::num($row[6] ?? null),
                'actual'        => self::num($row[7] ?? null),
                // column I ($row[8]) deliberately ignored — pct is recomputed downstream
            ];
        }
        if ($current !== null) $tasks[] = $current;

        return $tasks;
    }

    /** @param list<string> $warnings */
    private function deadlineCode(string $text, string $title, array &$warnings): string
    {
        $t = mb_strtolower($text);
        if (mb_strpos($t, 'якун') !== false)     return 'year';
        if (mb_strpos($t, 'ярим йил') !== false) return 'h2';
        if (mb_strpos($t, 'iv') !== false)       return 'q4';
        if (mb_strpos($t, 'iii') !== false)      return 'q3';

        $warnings[] = "Лист '{$title}': номаълум муддат '{$text}' — 'year' деб олинди.";
        return 'year';
    }

    private static function clean(string $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $v)));
    }

    private static function num(mixed $v): ?float
    {
        if ($v === null) return null;
        if (is_int($v) || is_float($v)) return (float) $v;
        $s = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $v));
        return is_numeric($s) ? (float) $s : null;
    }
}
```

Note: the Roman-numeral check tests `iv` BEFORE `iii` — both are substring checks and 'IV' does not contain 'III', but keep this order anyway so a future "III-IV чорак" style value hits the earlier, more specific match deliberately.

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=SectorWorkbookParserTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/tests/Helpers/SectorWorkbookBuilder.php backend/app/Services/Tasks/SectorWorkbookParser.php backend/tests/Feature/Sectors/SectorWorkbookParserTest.php
git commit -m "feat(sectors): sector workbook parser with layout verification"
```

---

### Task 4: `import:sector-tasks` command

**Files:**
- Create: `backend/app/Console/Commands/ImportSectorTasks.php`
- Test: `backend/tests/Feature/Sectors/ImportSectorTasksTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Sectors/ImportSectorTasksTest.php`:

```php
<?php

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\SectorTaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

function sectorFixture(array $overrides = []): string
{
    $rows = $overrides ?: [
        [1, 1, 'Товар маҳсулот ҳажмини етказиш.', 'Товар маҳсулот ҳажми', 'трлн сўм', '2026 йил якуни', 56.7, null, null],
        [2, 2, 'Экспортни таъминлаш.', 'Экспорт ҳажми', 'млн доллар', '2026 йил якуни', 792, null, null],
        [null, 3, null, 'Шундан: тайёр маҳсулот', 'млн доллар', '2026 йил 2-ярим йиллиги', 400, null, null],
    ];

    return SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', '«Ўзбекнефтгаз» АЖ — Бошқарув раиси А. Сангинов имзолаган кафолат хати', $rows],
    ]);
}

test('imports tasks and progress lines for a period', function () {
    $this->seed();
    $file = sectorFixture();

    $exit = Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-H2']);

    expect($exit)->toBe(0);
    $sector = Sector::where('code', 'uzbekneftgaz')->first();
    expect(SectorTask::where('sector_id', $sector->id)->count())->toBe(2);

    $t2 = SectorTask::where('sector_id', $sector->id)->where('task_no', 2)->first();
    expect($t2->title)->toBe('Экспортни таъминлаш.');
    expect($t2->progress()->where('report_period', '2026-H2')->count())->toBe(2);
    expect($t2->status)->toBe('in_progress');        // no actuals reported yet
    expect($t2->lines_total)->toBe(2);               // both lines carry a plan
    expect($t2->lines_done)->toBe(0);
    expect($t2->latest_period)->toBe('2026-H2');
    expect((float) $t2->headline_plan)->toEqualWithDelta(792.0, 0.001);

    $line3 = $t2->progress()->where('line_no', 3)->first();
    expect($line3->deadline_code)->toBe('h2');
    expect($line3->period_type)->toBe('half');
});

test('re-import of the same period is idempotent and updates values', function () {
    $this->seed();

    Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => '2026-H2']);
    $updated = sectorFixture([
        [1, 1, 'Товар маҳсулот ҳажмини етказиш.', 'Товар маҳсулот ҳажми', 'трлн сўм', '2026 йил якуни', 60.0, null, null],
        [2, 2, 'Экспортни таъминлаш.', 'Экспорт ҳажми', 'млн доллар', '2026 йил якуни', 792, null, null],
        [null, 3, null, 'Шундан: тайёр маҳсулот', 'млн доллар', '2026 йил 2-ярим йиллиги', 400, null, null],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $updated, '--period' => '2026-H2']);

    expect(SectorTaskProgress::where('report_period', '2026-H2')->count())->toBe(3); // no dupes
    $t1 = SectorTask::where('task_no', 1)->first();
    expect((float) $t1->progress()->where('line_no', 1)->first()->plan_value)->toEqualWithDelta(60.0, 0.001);
});

test('actuals produce recomputed pct and statuses; file pct is ignored', function () {
    $this->seed();
    $file = sectorFixture([
        [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, 1.0],   // done (file pct lies: 1%)
        [2, 2, 'В2.', 'Кўрсаткич Б', 'та', '2026 йил якуни', 100, 55, 99.0],   // open
        [null, 3, null, 'Кўрсаткич В', 'та', '2026 йил якуни', 200, 250, null],
    ]);

    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-Q3']);

    $t1 = SectorTask::where('task_no', 1)->first();
    expect($t1->status)->toBe('done');
    expect((float) $t1->headline_pct)->toEqualWithDelta(120.0, 0.01);          // recomputed, not 1.0

    $t2 = SectorTask::where('task_no', 2)->first();
    expect($t2->status)->toBe('open');                                          // weakest link: line 2 at 55%
    expect($t2->lines_done)->toBe(1);                                           // line 3 is ≥100%
    expect($t2->progress()->where('line_no', 2)->first()->reported_at)->not->toBeNull();
});

test('a later period advances the snapshot, earlier period does not regress it', function () {
    $this->seed();
    Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => '2026-Q3']);
    Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => '2026-08']);

    // 2026-08 sorts before 2026-Q3 (Q3 closes at month 09) — snapshot stays on Q3.
    expect(SectorTask::where('task_no', 1)->first()->latest_period)->toBe('2026-Q3');
    // History keeps both periods.
    expect(SectorTaskProgress::where('line_no', 1)->count())->toBe(2);
});

test('dry-run writes nothing', function () {
    $this->seed();
    $exit = Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => '2026-H2', '--dry-run' => true]);

    expect($exit)->toBe(0);
    expect(SectorTask::count())->toBe(0);
    expect(SectorTaskProgress::count())->toBe(0);
});

test('unknown sheet number aborts the whole import', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['99. Номаълум', 'орг', [[1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null]]],
    ]);

    $exit = Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-H2']);
    expect($exit)->toBe(1);
    expect(SectorTask::count())->toBe(0);
});

test('sheet name mismatching the sector aborts', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Фармацевтика', 'орг', [[1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null]]], // number 1 is Ўзбекнефтгаз
    ]);

    $exit = Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-H2']);
    expect($exit)->toBe(1);
    expect(SectorTask::count())->toBe(0);
});

test('invalid period is rejected', function () {
    $this->seed();
    $exit = Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => 'H2-2026']);
    expect($exit)->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=ImportSectorTasksTest`
Expected: FAIL — command `import:sector-tasks` does not exist.

- [ ] **Step 3: Implement the command**

`app/Console/Commands/ImportSectorTasks.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\SectorTaskProgress;
use App\Services\Tasks\SectorWorkbookParser;
use App\Support\TaskPeriod;
use App\Support\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportSectorTasks extends Command
{
    protected $signature = 'import:sector-tasks
        {--file= : Path to the XLSX (defaults to data/sectors/Вазифалар_2026_тармоқлар_кесимида.xlsx)}
        {--period= : Report period this file represents, e.g. 2026-H2, 2026-Q3 or 2026-08}
        {--dry-run : Parse and report without writing}';

    protected $description = 'Import sector (тармоқ) guarantee-letter tasks plan+actual from the all-sectors XLSX.';

    public function handle(): int
    {
        $period = (string) $this->option('period');
        if ($period === '' || ! preg_match('/^\d{4}-(Q[1-4]|H[12]|\d{2})$/', $period)) {
            $this->error('Provide --period as YYYY-Q1..Q4, YYYY-H1/H2 or YYYY-MM (e.g. 2026-H2, 2026-Q3 or 2026-08).');
            return self::FAILURE;
        }
        $periodType = TaskPeriod::periodType($period);
        $year       = TaskPeriod::yearFromPeriod($period);

        if (! DB::table('reporting_years')->where('year', $year)->exists()) {
            $this->error("Reporting year {$year} is not configured (reporting_years table). Seed it before importing.");
            return self::FAILURE;
        }

        $file = $this->option('file')
            ?: base_path('../data/sectors/Вазифалар_2026_тармоқлар_кесимида.xlsx');
        if (! is_file($file)) {
            $this->error("Source workbook not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Parsing {$file} for period {$period}…");
        try {
            $parsed = (new SectorWorkbookParser())->parse($file);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        foreach ($parsed['warnings'] as $w) {
            $this->warn($w);
        }

        // Match every sheet to a sector BEFORE writing anything: leading number →
        // sort_order, then the sheet title must contain the sector's short name.
        $sectorsByOrder = Sector::all()->keyBy('sort_order');
        $matched = []; // [Sector, tasks[]]
        foreach ($parsed['sheets'] as $sheet) {
            $sector = $sectorsByOrder->get($sheet['sort_order']);
            if (! $sector) {
                $this->error("Лист '{$sheet['sheet_title']}': {$sheet['sort_order']}-тартибли тармоқ справочникда йўқ. Импорт тўхтатилди.");
                return self::FAILURE;
            }
            if (mb_stripos($sheet['sheet_title'], $sector->name_short) === false) {
                $this->error(
                    "Лист '{$sheet['sheet_title']}' номи {$sheet['sort_order']}-тармоқ '{$sector->name_short}' га мос эмас — "
                    . 'лист тартиби ўзгарган бўлиши мумкин. Импорт тўхтатилди.'
                );
                return self::FAILURE;
            }
            $matched[] = [$sector, $sheet['tasks']];
        }

        $missing = $sectorsByOrder->keys()->diff(array_column($parsed['sheets'], 'sort_order'));
        foreach ($missing as $order) {
            $this->warn("Тармоқ '{$sectorsByOrder[$order]->name_short}' учун лист файлда йўқ — ташлаб кетилди.");
        }

        $taskCount = $lineCount = 0;
        foreach ($matched as [, $tasks]) {
            $taskCount += count($tasks);
            foreach ($tasks as $t) $lineCount += count($t['lines']);
        }
        $this->info('Parsed ' . count($matched) . " sheet(s): {$taskCount} tasks, {$lineCount} metric lines.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($matched, $period, $periodType) {
            foreach ($matched as [$sector, $tasks]) {
                foreach ($tasks as $t) {
                    $task = SectorTask::firstOrNew(['sector_id' => $sector->id, 'task_no' => $t['task_no']]);
                    $task->title = $t['title'];
                    $task->status ??= 'in_progress';
                    $task->save();

                    // Replace this period's lines (idempotent re-import), then insert.
                    $task->progress()->where('report_period', $period)->delete();
                    $stored = [];
                    foreach ($t['lines'] as $line) {
                        $pct = ($line['plan'] !== null && $line['actual'] !== null && (float) $line['plan'] != 0.0)
                            ? round($line['actual'] / $line['plan'] * 100, 4)
                            : null;
                        SectorTaskProgress::create([
                            'sector_task_id' => $task->id,
                            'line_no'        => $line['line_no'],
                            'metric_label'   => $line['metric_label'],
                            'unit'           => $line['unit'],
                            'deadline_text'  => $line['deadline_text'],
                            'deadline_code'  => $line['deadline_code'],
                            'report_period'  => $period,
                            'period_type'    => $periodType,
                            'plan_value'     => $line['plan'],
                            'actual_value'   => $line['actual'],
                            'pct_of_plan'    => $pct,
                            'reported_at'    => $line['actual'] !== null ? now()->toDateString() : null,
                        ]);
                        $stored[] = ['line_no' => $line['line_no'], 'unit' => $line['unit'],
                                     'plan' => $line['plan'], 'actual' => $line['actual'], 'pct' => $pct];
                    }

                    // Weakest-link status over planned lines + headline snapshot from
                    // the task's first line. Only advance if this period is not older
                    // than what the task already shows.
                    $agg = TaskStatus::aggregate($stored);
                    $head = $stored[0] ?? null;
                    $shouldAdvance = $task->latest_period === null
                        || TaskPeriod::sortKey($period) >= TaskPeriod::sortKey($task->latest_period);
                    if ($shouldAdvance) {
                        $task->update([
                            'latest_period'   => $period,
                            'headline_unit'   => $head['unit'] ?? null,
                            'headline_plan'   => $head['plan'] ?? null,
                            'headline_actual' => $head['actual'] ?? null,
                            'headline_pct'    => $head['pct'] ?? null,
                            'lines_total'     => $agg['total'],
                            'lines_done'      => $agg['done'],
                            'status'          => $agg['status'],
                        ]);
                    }
                }
            }
        });

        $this->info("Wrote {$lineCount} progress rows for " . count($matched) . ' sector(s), period ' . $period . '.');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=ImportSectorTasksTest`
Expected: PASS (8 tests).

Note: the `$task->status ??= 'in_progress'` line relies on `status` being null for a
fresh model despite the column default — if the test for a brand-new task fails on
status, set `$task->status = $task->exists ? $task->status : 'in_progress'` instead.
The snapshot update below overwrites it anyway whenever the period advances.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/ImportSectorTasks.php backend/tests/Feature/Sectors/ImportSectorTasksTest.php
git commit -m "feat(sectors): import:sector-tasks command with idempotent per-period upserts"
```

---

### Task 5: `sector-tasks:recompute` command

**Files:**
- Create: `backend/app/Console/Commands/RecomputeSectorTaskStatus.php`
- Test: `backend/tests/Feature/Sectors/RecomputeSectorTaskStatusTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\SectorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

test('recompute rebuilds status and snapshot from stored progress', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-Q3']);

    // Corrupt the snapshot, then recompute.
    $task = SectorTask::where('task_no', 1)->first();
    $task->update(['status' => 'open', 'headline_pct' => null, 'lines_done' => 0, 'latest_period' => null]);

    $exit = Artisan::call('sector-tasks:recompute');

    expect($exit)->toBe(0);
    $task->refresh();
    expect($task->status)->toBe('done');
    expect($task->latest_period)->toBe('2026-Q3');
    expect((float) $task->headline_pct)->toEqualWithDelta(120.0, 0.01);
    expect($task->lines_done)->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=RecomputeSectorTaskStatusTest`
Expected: FAIL — command not found.

- [ ] **Step 3: Implement**

`app/Console/Commands/RecomputeSectorTaskStatus.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\SectorTask;
use App\Support\TaskPeriod;
use App\Support\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecomputeSectorTaskStatus extends Command
{
    protected $signature = 'sector-tasks:recompute';
    protected $description = 'Rebuild sector task statuses and headline snapshots from stored progress (no re-import).';

    public function handle(): int
    {
        $updated = 0;

        DB::transaction(function () use (&$updated) {
            foreach (SectorTask::with('progress')->get() as $task) {
                if ($task->progress->isEmpty()) continue;

                $latest = $task->progress
                    ->pluck('report_period')
                    ->unique()
                    ->sortBy(fn (string $p) => TaskPeriod::sortKey($p))
                    ->last();

                $lines = $task->progress
                    ->where('report_period', $latest)
                    ->sortBy('line_no')
                    ->map(fn ($r) => [
                        'line_no' => $r->line_no,
                        'unit'    => $r->unit,
                        'plan'    => $r->plan_value,
                        'actual'  => $r->actual_value,
                        'pct'     => $r->pct_of_plan,
                    ])
                    ->values();

                $agg  = TaskStatus::aggregate($lines);
                $head = $lines->first();
                $task->update([
                    'latest_period'   => $latest,
                    'headline_unit'   => $head['unit'] ?? null,
                    'headline_plan'   => $head['plan'] ?? null,
                    'headline_actual' => $head['actual'] ?? null,
                    'headline_pct'    => $head['pct'] ?? null,
                    'lines_total'     => $agg['total'],
                    'lines_done'      => $agg['done'],
                    'status'          => $agg['status'],
                ]);
                $updated++;
            }
        });

        $this->info("Recomputed {$updated} sector task(s).");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test --filter=RecomputeSectorTaskStatusTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/RecomputeSectorTaskStatus.php backend/tests/Feature/Sectors/RecomputeSectorTaskStatusTest.php
git commit -m "feat(sectors): sector-tasks:recompute command"
```

---

### Task 6: Real-file import, runbook, docs

**Files:**
- Create: `data/sectors/` (copy the workbook in; `data/` is gitignored)
- Create: `backend/docs/sector-task-import.md`
- Modify: `CLAUDE.md` (one line in "Data import pipelines")

- [ ] **Step 1: Run the full test suite**

Run: `cd backend && DB_DATABASE=hm_test_sectors php artisan test`
Expected: all green (~450 tests). Fix anything broken before proceeding.

- [ ] **Step 2: Copy the real workbook and dry-run**

```bash
mkdir -p data/sectors
cp "/c/Users/y.utepbergenov/Desktop/Вазифалар_2026_тармоқлар_кесимида.xlsx" data/sectors/
cd backend && php artisan migrate --force && php artisan db:seed --class=SectorSeeder
php artisan import:sector-tasks --period=2026-H2 --dry-run
```

Expected: `Parsed 17 sheet(s): 119 tasks, 521 metric lines.` and no warnings (or only explainable ones). Investigate any mismatch against the counts verified during design.

- [ ] **Step 3: Real import + verify**

```bash
php artisan import:sector-tasks --period=2026-H2
```

Then verify in psql:

```sql
SELECT count(*) FROM sector_tasks;          -- expect 119
SELECT count(*) FROM sector_task_progress;  -- expect 521
SELECT s.name_short, count(t.id) FROM sectors s JOIN sector_tasks t ON t.sector_id = s.id GROUP BY 1 ORDER BY min(s.sort_order);
-- expect per-sheet task counts: 8,6,8,8,7,6,6,6,12,6,7,8,6,9,6,5,5
SELECT status, count(*) FROM sector_tasks GROUP BY 1;  -- all in_progress (no actuals yet)
```

- [ ] **Step 4: Write the runbook** `backend/docs/sector-task-import.md`

Content requirements (write in the same operator-runbook style as `backend/docs/task-import.md`): what the file is, where it lives (`data/sectors/`), the two commands with examples (`import:sector-tasks --period=2026-H2 [--file=…] [--dry-run]`, `sector-tasks:recompute`), the baseline period convention (initial plans-only load = `2026-H2`; monthly update files = `2026-08`, `2026-09`, …; quarterly = `2026-Q3`/`2026-Q4`), idempotency (re-running a period replaces that period's rows), what aborts the import (layout drift, unknown/mismatched sheet), and the status model (in_progress = nothing reported; weakest-link done/open).

- [ ] **Step 5: Update CLAUDE.md**

In the "Data import pipelines" numbered list add:

```markdown
3. **Sector tasks (тармоқ корхоналари guarantee letters):** `import:sector-tasks --period=2026-H2` — reads the all-sectors workbook `data/sectors/Вазифалар_2026_тармоқлар_кесимида.xlsx` (17 sheets, one per enterprise). Runbook: `backend/docs/sector-task-import.md`. `sector-tasks:recompute` rebuilds statuses without re-import.
```

- [ ] **Step 6: Final full suite + commit**

```bash
cd backend && DB_DATABASE=hm_test_sectors php artisan test
git add backend/docs/sector-task-import.md CLAUDE.md
git commit -m "docs(sectors): import runbook + CLAUDE.md pipeline entry"
```

Drop the scratch test DB: `PGPASSWORD=123 psql -h 127.0.0.1 -U postgres -c "DROP DATABASE hm_test_sectors"`

---

## Self-review notes (already applied)

- Spec coverage: schema (Tasks 1–2), parser+verification (Task 3), import+idempotency+pct recompute (Task 4), recompute command (Task 5), real import+runbook (Task 6). Phase-2 UI intentionally absent.
- `TaskStatus::aggregate()` is used directly — sector tasks have no continuous/ongoing overrides, so `forTask()` (which needs region task numbers) is NOT used.
- `TaskPeriod::sortKey()` handles `H2` (→ `-12`) and quarters; the month-vs-quarter ordering test in Task 4 depends on it.
- Parser never reads column I; pct always recomputed in the command (honest-percent rule).
