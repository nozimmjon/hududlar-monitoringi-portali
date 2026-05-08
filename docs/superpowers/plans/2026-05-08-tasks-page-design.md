# Tasks Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement Andijan-region tasks page in the Laravel app: schema (`tasks` + `task_districts` pivot), `Task` model, `import:tasks` artisan command parsing the Andijan docx, Livewire `TasksBoard` with filterable list, and Blade markup matching `index.html#tasksPage` 1:1.

**Architecture:** Standard Laravel + Livewire 3. Schema is 14-region-ready (every task has `region_code`) but only Andijan is ingested. Importer reads `data/tasks/00_Чора_тадбир_Андижон.docx` via PhpOffice/PhpWord (new composer dep). UI mirrors the static prototype's classes verbatim so existing CSS in `backend/public/css/portal.css` covers the visual.

**Tech Stack:** PHP 8 + Laravel 11 + Livewire 3 + Pest 3 + PostgreSQL. PhpOffice/PhpWord for docx parsing.

**Spec:** `docs/superpowers/specs/2026-05-08-tasks-page-design.md`

**Working directory:** `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali` (project root). All `php artisan`, `composer`, `vendor/bin/pest` commands run from inside `backend/` unless noted.

---

## File Structure

| File | Responsibility |
|---|---|
| `backend/database/migrations/2026_05_08_000001_create_tasks_table.php` | Create `tasks` table with FKs and indexes per spec §4.1. |
| `backend/database/migrations/2026_05_08_000002_create_task_districts_table.php` | Create `task_districts` pivot per spec §4.2. |
| `backend/app/Models/Task.php` | Eloquent model: relations, scopes, fillable. |
| `backend/app/Models/District.php` | Add `tasks()` belongsToMany. |
| `backend/app/Support/TasksTaxonomy.php` | Constant arrays mapping Roman numerals → module codes and numeric subsections → indicator codes. Single source of truth so the importer and tests can share it. |
| `backend/app/Console/Commands/ImportTasks.php` | Artisan command parsing the docx and populating `tasks` + `task_districts`. |
| `backend/app/Livewire/TasksBoard.php` | Livewire component with URL-synced filters and computed properties. |
| `backend/resources/views/livewire/tasks-board.blade.php` | Blade markup mirroring `index.html#tasksPage`. |
| `backend/tests/Feature/Schema/TasksTableTest.php` | Schema test verifying `tasks` columns + FKs exist. |
| `backend/tests/Feature/Schema/TaskDistrictsTableTest.php` | Schema test verifying pivot table. |
| `backend/tests/Unit/TaskScopeTest.php` | Quick scope-chain unit tests. |
| `backend/tests/Feature/Import/ImportTasksCommandTest.php` | End-to-end importer test against the Andijan docx. |
| `backend/tests/Feature/Http/TasksPageTest.php` | Route + Livewire interaction test. |

No CSS or JS files are created or modified. The `pages/tasks.blade.php` view is unchanged (already mounts `<livewire:tasks-board />`).

---

## Conventions for this plan

- All `composer`, `php artisan`, `vendor/bin/pest` commands run from inside `backend/`. The plan uses absolute working-dir prefixes only when ambiguity matters.
- All migrations use the timestamp prefix shown in the file paths above. If a migration runs out of order due to a new file added in the same batch, prefer numeric tie-breaking (`...000001`, `...000002`).
- All test files use Pest's functional style (`test('...', function () { ... });`), matching the existing `tests/Feature/Schema/*Test.php` pattern.
- Database for tests runs against the `RefreshDatabase` trait. Apply via `pest()->extend(...)->use(RefreshDatabase::class)` in `tests/Pest.php` if not already, OR `uses(RefreshDatabase::class);` at the top of each file. The current `tests/Pest.php` shows `RefreshDatabase` is commented out at the suite level, so individual test files must opt in via `uses()`.
- All model methods follow Laravel naming: `forRegion`, `tasks`, etc. — match the spec's casing.

---

### Task 1: Add PhpWord composer dependency

**Files:**
- Modify: `backend/composer.json` (via `composer require`)
- Modify: `backend/composer.lock` (via `composer require`)

- [ ] **Step 1: Add the dependency**

Run from `backend/`:

```bash
composer require phpoffice/phpword
```

Expected: PhpWord and its transitive deps (laminas-* libraries) install. `composer.json` `require` section gains `"phpoffice/phpword": "^1.x"` (whatever current major is).

- [ ] **Step 2: Verify load**

```bash
php -r "require 'vendor/autoload.php'; echo class_exists(\PhpOffice\PhpWord\IOFactory::class) ? 'OK' : 'MISSING';"
```

Expected output: `OK`.

- [ ] **Step 3: Commit**

```bash
git add backend/composer.json backend/composer.lock
git commit -m "chore: add phpoffice/phpword for tasks docx parsing"
```

---

### Task 2: TasksTaxonomy constants

**Files:**
- Create: `backend/app/Support/TasksTaxonomy.php`
- Create: `backend/tests/Unit/TasksTaxonomyTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/TasksTaxonomyTest.php`:

```php
<?php

use App\Support\TasksTaxonomy;

test('Roman map covers I through VII', function () {
    expect(TasksTaxonomy::ROMAN_TO_MODULE)
        ->toHaveKeys(['I', 'II', 'III', 'IV', 'V', 'VI', 'VII']);
    expect(TasksTaxonomy::ROMAN_TO_MODULE['I'])->toBe('macro');
    expect(TasksTaxonomy::ROMAN_TO_MODULE['VII'])->toBe('employment');
});

test('numeric subsection map maps to indicator codes', function () {
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.1'])->toBe('grp');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.2'])->toBe('industry');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.3'])->toBe('services');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.4'])->toBe('agri');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.5'])->toBe('build');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['7.1'])->toBe('unemployment');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['7.2'])->toBe('poverty');
});

test('region filename map covers Andijan', function () {
    expect(TasksTaxonomy::REGION_FILENAMES['andijan'])
        ->toBe('00_Чора_тадбир_Андижон.docx');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend && vendor/bin/pest tests/Unit/TasksTaxonomyTest.php
```

Expected: FAIL — `App\Support\TasksTaxonomy` not found.

- [ ] **Step 3: Implement TasksTaxonomy**

Create `backend/app/Support/TasksTaxonomy.php`:

```php
<?php

namespace App\Support;

class TasksTaxonomy
{
    public const ROMAN_TO_MODULE = [
        'I'   => 'macro',
        'II'  => 'inflation',
        'III' => 'budget',
        'IV'  => 'budget_investment',
        'V'   => 'foreign_invest',
        'VI'  => 'export',
        'VII' => 'employment',
    ];

    public const NUMERIC_TO_INDICATOR = [
        '1.1' => 'grp',
        '1.2' => 'industry',
        '1.3' => 'services',
        '1.4' => 'agri',
        '1.5' => 'build',
        '7.1' => 'unemployment',
        '7.2' => 'poverty',
    ];

    public const REGION_FILENAMES = [
        'andijan'      => '00_Чора_тадбир_Андижон.docx',
        'bukhara'      => '00_Чора_тадбир_Бухоро.docx',
        'fergana'      => '00_Чора тадбир _Фарғона.docx',
        'jizzakh'      => '00_Чора_тадбир_Жиззах.docx',
        'kashkadarya'  => '00_Чора_тадбир_Қашқадарё.docx',
        'karakalpak'   => '00_Чора Тадбир_ҚР.docx',
        'khorezm'      => '00_Чора_тадбир_Хоразм.docx',
        'namangan'     => '00_Чора_тадбир_Наманган.docx',
        'navoi'        => '00_Чора_тадбир_Навоий.docx',
        'samarkand'    => '00_Чора_тадбир_Самарқанд.docx',
        'sirdarya'     => '00_Чора_тадбир_Сирдарё.docx',
        'surkhandarya' => '00_Чора_тадбир_Сурхондарё.docx',
        'tashkent'     => '00_Чора_тадбир_Тошкент_вилояти.docx',
        'tashkent_city'=> '00_Чора_тадбир_Тошкент_шаҳри.docx',
    ];
}
```

> The Karakalpakstan filename uses a single-word abbreviation `ҚР` and varies the spacing — copy literally from `data/tasks/` directory listing.

- [ ] **Step 4: Run test to verify it passes**

```bash
cd backend && vendor/bin/pest tests/Unit/TasksTaxonomyTest.php
```

Expected: PASS — 3 tests, all green.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/TasksTaxonomy.php backend/tests/Unit/TasksTaxonomyTest.php
git commit -m "feat: TasksTaxonomy with Roman/numeric/filename maps"
```

---

### Task 3: Tasks table migration + schema test

**Files:**
- Create: `backend/database/migrations/2026_05_08_000001_create_tasks_table.php`
- Create: `backend/tests/Feature/Schema/TasksTableTest.php`

- [ ] **Step 1: Write the failing schema test**

Create `backend/tests/Feature/Schema/TasksTableTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('tasks table exists with all expected columns', function () {
    expect(Schema::hasTable('tasks'))->toBeTrue();

    $expected = [
        'id', 'region_code', 'guarantee_letter_id', 'task_number',
        'title', 'deadline_text', 'period_code', 'executor_text',
        'kind', 'module_code', 'indicator_code', 'section_path',
        'section_label', 'source_paragraph_index', 'status',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('tasks', $column))
            ->toBeTrue("column {$column} missing on tasks table");
    }
});

test('tasks table enforces unique (region_code, task_number)', function () {
    \DB::table('regions')->insert([
        'code' => 'andijan', 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    \DB::table('tasks')->insert([
        'region_code' => 'andijan', 'task_number' => '1', 'title' => 'a',
        'executor_text' => 'x', 'kind' => 'kpi', 'section_path' => 'I',
        'section_label' => 'I', 'source_paragraph_index' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => \DB::table('tasks')->insert([
        'region_code' => 'andijan', 'task_number' => '1', 'title' => 'b',
        'executor_text' => 'x', 'kind' => 'kpi', 'section_path' => 'I',
        'section_label' => 'I', 'source_paragraph_index' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Schema/TasksTableTest.php
```

Expected: FAIL — table `tasks` does not exist.

- [ ] **Step 3: Implement the migration**

Create `backend/database/migrations/2026_05_08_000001_create_tasks_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->foreignId('guarantee_letter_id')->nullable()->constrained('guarantee_letters')->nullOnDelete();
            $table->string('task_number', 16);
            $table->text('title');
            $table->string('deadline_text', 128)->nullable();
            $table->string('period_code', 16)->nullable();
            $table->text('executor_text');
            $table->string('kind', 16);
            $table->string('module_code', 32)->nullable();
            $table->string('indicator_code', 48)->nullable();
            $table->string('section_path', 16);
            $table->string('section_label', 255);
            $table->integer('source_paragraph_index');
            // Reserved for future status-tracking spec. Default 'open'.
            $table->string('status', 16)->default('open');
            $table->timestamps();

            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('module_code')->references('code')->on('modules')->nullOnDelete();
            $table->foreign('indicator_code')->references('code')->on('indicators')->nullOnDelete();

            $table->unique(['region_code', 'task_number'], 'uq_tasks_region_number');
            $table->index(['region_code', 'module_code'], 'idx_tasks_region_module');
            $table->index(['region_code', 'indicator_code'], 'idx_tasks_region_indicator');
            $table->index(['region_code', 'kind'], 'idx_tasks_region_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd backend && vendor/bin/pest tests/Feature/Schema/TasksTableTest.php
```

Expected: PASS — both tests green.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_05_08_000001_create_tasks_table.php backend/tests/Feature/Schema/TasksTableTest.php
git commit -m "feat: tasks table migration + schema test"
```

---

### Task 4: task_districts pivot migration + schema test

**Files:**
- Create: `backend/database/migrations/2026_05_08_000002_create_task_districts_table.php`
- Create: `backend/tests/Feature/Schema/TaskDistrictsTableTest.php`

- [ ] **Step 1: Write the failing schema test**

Create `backend/tests/Feature/Schema/TaskDistrictsTableTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('task_districts pivot table exists with task_id and district_id', function () {
    expect(Schema::hasTable('task_districts'))->toBeTrue();
    expect(Schema::hasColumns('task_districts', ['task_id', 'district_id']))->toBeTrue();
});

test('task_districts has composite primary key', function () {
    \DB::table('regions')->insert([
        'code' => 'andijan', 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = \DB::table('regions')->where('code', 'andijan')->value('id');

    $districtId = \DB::table('districts')->insertGetId([
        'region_id' => $regionId, 'region_code' => 'andijan',
        'code' => 'andijan_city', 'name_short' => 'Андижон ш.',
        'name_full' => 'Андижон шаҳри', 'kind' => 'city', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $taskId = \DB::table('tasks')->insertGetId([
        'region_code' => 'andijan', 'task_number' => '1', 'title' => 'x',
        'executor_text' => 'x', 'kind' => 'kpi', 'section_path' => 'I',
        'section_label' => 'I', 'source_paragraph_index' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    \DB::table('task_districts')->insert(['task_id' => $taskId, 'district_id' => $districtId]);

    expect(fn () => \DB::table('task_districts')->insert([
        'task_id' => $taskId, 'district_id' => $districtId,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Schema/TaskDistrictsTableTest.php
```

Expected: FAIL — `task_districts` does not exist.

- [ ] **Step 3: Implement the migration**

Create `backend/database/migrations/2026_05_08_000002_create_task_districts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_districts', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();

            $table->primary(['task_id', 'district_id']);
            $table->index('district_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_districts');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd backend && vendor/bin/pest tests/Feature/Schema/TaskDistrictsTableTest.php
```

Expected: PASS — both tests green.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_05_08_000002_create_task_districts_table.php backend/tests/Feature/Schema/TaskDistrictsTableTest.php
git commit -m "feat: task_districts pivot migration + schema test"
```

---

### Task 5: Task model + District relationship + scope unit test

**Files:**
- Create: `backend/app/Models/Task.php`
- Modify: `backend/app/Models/District.php`
- Create: `backend/tests/Unit/TaskScopeTest.php`

- [ ] **Step 1: Write the failing scope test**

Create `backend/tests/Unit/TaskScopeTest.php`:

```php
<?php

use App\Models\Task;
use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \DB::table('regions')->insert([
        ['code' => 'andijan',  'label' => 'A', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'bukhara',  'label' => 'B', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);
    \DB::table('modules')->insert([
        ['code' => 'macro',    'label' => 'M', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'export',   'label' => 'E', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $regionId = \DB::table('regions')->where('code', 'andijan')->value('id');
    $this->districtId = \DB::table('districts')->insertGetId([
        'region_id' => $regionId, 'region_code' => 'andijan',
        'code' => 'boston', 'name_short' => 'Бўстон т.',
        'name_full' => 'Бўстон тумани', 'kind' => 'district', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Task::create([
        'region_code' => 'andijan', 'task_number' => '1',
        'title' => 'macro task', 'executor_text' => 'хокимлик',
        'kind' => 'kpi', 'module_code' => 'macro',
        'period_code' => 'h1', 'section_path' => 'I.1.1',
        'section_label' => '1.1', 'source_paragraph_index' => 1,
    ]);
    $exportTask = Task::create([
        'region_code' => 'andijan', 'task_number' => '2',
        'title' => 'export task', 'executor_text' => 'Бўстон',
        'kind' => 'measure', 'module_code' => 'export',
        'period_code' => 'year', 'section_path' => 'VI',
        'section_label' => 'VI', 'source_paragraph_index' => 2,
    ]);
    Task::create([
        'region_code' => 'bukhara', 'task_number' => '1',
        'title' => 'other region', 'executor_text' => 'x',
        'kind' => 'kpi', 'module_code' => 'macro',
        'section_path' => 'I', 'section_label' => 'I',
        'source_paragraph_index' => 1,
    ]);

    $exportTask->districts()->attach($this->districtId);
});

test('forRegion narrows to one region', function () {
    expect(Task::forRegion('andijan')->count())->toBe(2);
    expect(Task::forRegion('bukhara')->count())->toBe(1);
});

test('forModule narrows to one module', function () {
    expect(Task::forRegion('andijan')->forModule('macro')->count())->toBe(1);
});

test('ofKind filters by kind', function () {
    expect(Task::forRegion('andijan')->ofKind('measure')->count())->toBe(1);
});

test('forPeriod filters by period_code', function () {
    expect(Task::forRegion('andijan')->forPeriod('year')->count())->toBe(1);
});

test('forDistrict joins through pivot', function () {
    expect(Task::forRegion('andijan')->forDistrict($this->districtId)->count())->toBe(1);
});

test('search matches title (ILIKE)', function () {
    expect(Task::forRegion('andijan')->search('export')->count())->toBe(1);
});

test('District has tasks() relationship', function () {
    $district = District::find($this->districtId);
    expect($district->tasks()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend && vendor/bin/pest tests/Unit/TaskScopeTest.php
```

Expected: FAIL — `App\Models\Task` not found.

- [ ] **Step 3: Implement Task model**

Create `backend/app/Models/Task.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    protected $fillable = [
        'region_code', 'guarantee_letter_id', 'task_number', 'title',
        'deadline_text', 'period_code', 'executor_text', 'kind',
        'module_code', 'indicator_code', 'section_path', 'section_label',
        'source_paragraph_index', 'status',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function guaranteeLetter(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLetter::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }

    public function districts(): BelongsToMany
    {
        return $this->belongsToMany(District::class, 'task_districts');
    }

    public function scopeForRegion(Builder $q, string $code): Builder
    {
        return $q->where('region_code', $code);
    }

    public function scopeForModule(Builder $q, string $code): Builder
    {
        return $q->where('module_code', $code);
    }

    public function scopeForIndicator(Builder $q, string $code): Builder
    {
        return $q->where('indicator_code', $code);
    }

    public function scopeForDistrict(Builder $q, int $districtId): Builder
    {
        return $q->whereHas('districts', fn ($d) => $d->where('districts.id', $districtId));
    }

    public function scopeForPeriod(Builder $q, string $code): Builder
    {
        return $q->where('period_code', $code);
    }

    public function scopeOfKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $like = '%' . $term . '%';
        return $q->where(function ($w) use ($like) {
            $w->where('title', 'ILIKE', $like)
              ->orWhere('executor_text', 'ILIKE', $like)
              ->orWhere('section_label', 'ILIKE', $like);
        });
    }
}
```

> If `Module` or `Region` model classes don't exist yet, omit those `belongsTo` methods. Verify by `ls backend/app/Models/` before adding.

- [ ] **Step 4: Verify Module and Region model existence**

```bash
ls backend/app/Models/
```

If `Region.php` or `Module.php` is missing, remove the corresponding `region()` / `module()` method from `Task.php`. Spec design assumes both exist; the existing migration list suggests they do (`regions`, `modules` tables exist), but model files may not have been created. If a model is missing, add a minimal one in a single file (5 lines: namespace + class extends Model with `protected $fillable = ['code', 'label', 'sort_order'];`).

- [ ] **Step 5: Add tasks() to District model**

Modify `backend/app/Models/District.php`. Current contents:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = [
        'region_id', 'region_code', 'code', 'name_short', 'name_full',
        'name_latin', 'alt_labels', 'kind', 'sort_order',
    ];
}
```

Replace with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class District extends Model
{
    protected $fillable = [
        'region_id', 'region_code', 'code', 'name_short', 'name_full',
        'name_latin', 'alt_labels', 'kind', 'sort_order',
    ];

    protected $casts = [
        'alt_labels' => 'array',
    ];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_districts');
    }
}
```

(`alt_labels` cast added because the importer in Task 7 reads it as an array; safer to set it now.)

- [ ] **Step 6: Run test to verify it passes**

```bash
cd backend && vendor/bin/pest tests/Unit/TaskScopeTest.php
```

Expected: PASS — 7 tests green.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Models/Task.php backend/app/Models/District.php backend/tests/Unit/TaskScopeTest.php
git commit -m "feat: Task model with scopes + District tasks() relation"
```

---

### Task 6: ImportTasks command skeleton

**Files:**
- Create: `backend/app/Console/Commands/ImportTasks.php`

- [ ] **Step 1: Implement the skeleton (no test yet — full e2e test added in Task 7)**

Create `backend/app/Console/Commands/ImportTasks.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\District;
use App\Support\TasksTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\IOFactory;

class ImportTasks extends Command
{
    protected $signature = 'import:tasks {region=andijan} {--file=}';

    protected $description = 'Import tasks (chora-tadbirlar) from a regional guarantee-letter docx into tasks + task_districts.';

    public function handle(): int
    {
        $regionCode = (string) $this->argument('region');
        $file       = $this->option('file') ?: $this->resolveFile($regionCode);

        if (! is_file($file)) {
            $this->error("Source docx not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Reading {$file}");

        $rows = $this->parseDocxRows($file);
        $tasks = $this->extractTasks($rows, $regionCode);
        $districts = District::where('region_code', $regionCode)->get();

        $unmatched = [];

        DB::transaction(function () use ($tasks, $regionCode, $districts, &$unmatched) {
            Task::where('region_code', $regionCode)->delete();

            foreach ($tasks as $row) {
                $task = Task::create($row['attrs']);
                $ids  = $this->resolveDistricts($row['executor_text'], $districts, $unmatched);
                if (! empty($ids)) {
                    $task->districts()->sync($ids);
                }
            }
        });

        $this->info("Imported " . count($tasks) . " tasks for region '{$regionCode}'.");
        if (! empty($unmatched)) {
            $this->warn("Unmatched executor tokens: " . implode(' | ', array_unique($unmatched)));
        }

        return self::SUCCESS;
    }

    private function resolveFile(string $regionCode): string
    {
        $name = TasksTaxonomy::REGION_FILENAMES[$regionCode] ?? null;
        if ($name === null) {
            return base_path('../data/tasks/00_Чора_тадбир_Андижон.docx');
        }
        return base_path('../data/tasks/' . $name);
    }

    /** @return list<list<string>> matrix of row → cell texts */
    private function parseDocxRows(string $file): array
    {
        $doc = IOFactory::load($file);
        $rows = [];

        foreach ($doc->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (! $element instanceof \PhpOffice\PhpWord\Element\Table) continue;
                foreach ($element->getRows() as $row) {
                    $cells = [];
                    foreach ($row->getCells() as $cell) {
                        $cells[] = $this->cellText($cell);
                    }
                    $rows[] = $cells;
                }
                return $rows; // first table only
            }
        }

        return $rows;
    }

    private function cellText(\PhpOffice\PhpWord\Element\Cell $cell): string
    {
        $buf = '';
        foreach ($cell->getElements() as $el) {
            if ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
                foreach ($el->getElements() as $sub) {
                    if (method_exists($sub, 'getText')) {
                        $buf .= $sub->getText();
                    }
                }
                $buf .= "\n";
            } elseif ($el instanceof \PhpOffice\PhpWord\Element\Text) {
                $buf .= $el->getText() . "\n";
            }
        }
        return trim($buf);
    }

    /** @return list<array{attrs: array, executor_text: string}> */
    private function extractTasks(array $rows, string $regionCode): array
    {
        $tasks = [];
        $currentModule  = null;
        $currentRoman   = null;
        $currentIndicator = null;
        $currentPath    = null;
        $currentLabel   = null;

        $romanRe   = '/^(VII|VI|IV|V|III|II|I)\.\s/u';
        $numericRe = '/^(\d+)\.(\d+)\.\s/u';

        foreach ($rows as $i => $cells) {
            // Header row check: all cells identical
            if (count(array_unique($cells)) === 1) {
                $text = $cells[0] ?? '';
                if (preg_match($romanRe, $text, $m)) {
                    $currentRoman   = $m[1];
                    $currentModule  = TasksTaxonomy::ROMAN_TO_MODULE[$currentRoman] ?? null;
                    $currentIndicator = null;
                    $currentPath    = $currentRoman;
                    $currentLabel   = $text;
                } elseif (preg_match($numericRe, $text, $m)) {
                    $key = $m[1] . '.' . $m[2];
                    $currentIndicator = TasksTaxonomy::NUMERIC_TO_INDICATOR[$key] ?? null;
                    $currentPath    = ($currentRoman ?? '') . '.' . $key;
                    $currentLabel   = $text;
                }
                continue;
            }

            // Skip header row (literal column titles "№", "Топшириқ номи", ...)
            if ($i === 0) continue;

            // Data row
            $taskNumber = trim($cells[0] ?? '');
            if ($taskNumber === '') continue;

            $title    = trim($cells[1] ?? '');
            $deadline = trim($cells[2] ?? '');
            $executor = trim($cells[3] ?? '');
            $kindRaw  = trim($cells[4] ?? '');

            $kind = str_starts_with($kindRaw, 'KPI') ? 'kpi' : 'measure';

            $period = null;
            if (str_contains($deadline, 'I ярим йиллик')) $period = 'h1';
            elseif (str_contains($deadline, 'якуни')) $period = 'year';

            $tasks[] = [
                'attrs' => [
                    'region_code'            => $regionCode,
                    'task_number'            => $taskNumber,
                    'title'                  => $title,
                    'deadline_text'          => $deadline ?: null,
                    'period_code'            => $period,
                    'executor_text'          => $executor,
                    'kind'                   => $kind,
                    'module_code'            => $currentModule,
                    'indicator_code'         => $currentIndicator,
                    'section_path'           => $currentPath ?? '',
                    'section_label'          => $currentLabel ?? '',
                    'source_paragraph_index' => $i,
                ],
                'executor_text' => $executor,
            ];
        }

        return $tasks;
    }

    /**
     * Resolve executor text to a list of district IDs.
     *
     * @return list<int>
     */
    private function resolveDistricts(string $executor, $districts, array &$unmatched): array
    {
        $tokens = preg_split('/[,\n]+/u', $executor) ?: [];
        $ids    = [];

        foreach ($tokens as $token) {
            $clean = trim($token);
            $clean = preg_replace('/\s+ҳокимлиги$/u', '', $clean);
            $clean = preg_replace('/\s+ҳокимияти$/u', '', $clean);
            $clean = trim($clean);
            if ($clean === '' || str_contains($clean, 'вилояти')) continue;

            $matched = false;
            foreach ($districts as $d) {
                if ($d->name_full === $clean || $d->name_short === $clean) {
                    $ids[] = $d->id;
                    $matched = true;
                    break;
                }
                $alt = $d->alt_labels ?? [];
                if (is_array($alt)) {
                    foreach ($alt as $label) {
                        if (mb_strtolower($label) === mb_strtolower($clean)) {
                            $ids[] = $d->id;
                            $matched = true;
                            break 2;
                        }
                    }
                }
            }

            if (! $matched) {
                $unmatched[] = $clean;
            }
        }

        return array_unique($ids);
    }
}
```

> Laravel auto-discovers commands in `app/Console/Commands/` (Laravel 11 default). No registration step needed.

- [ ] **Step 2: Verify command registers**

```bash
cd backend && php artisan list | grep import:tasks
```

Expected output: `import:tasks ...` line listed.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Console/Commands/ImportTasks.php
git commit -m "feat: import:tasks command (parser + district resolver)"
```

---

### Task 7: ImportTasks end-to-end test

**Files:**
- Create: `backend/tests/Feature/Import/ImportTasksCommandTest.php`

Source data fixture: `data/tasks/00_Чора_тадбир_Андижон.docx`. Tests run from `backend/`, so relative path is `../data/tasks/00_Чора_тадбир_Андижон.docx`.

- [ ] **Step 1: Write the failing end-to-end test**

Create `backend/tests/Feature/Import/ImportTasksCommandTest.php`:

```php
<?php

use App\Models\Task;
use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 'andijan', 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('regions')->where('code', 'andijan')->value('id');

    DB::table('modules')->insert([
        ['code' => 'macro',             'label' => 'Макро',     'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'inflation',         'label' => 'Инфляция',  'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'budget',            'label' => 'Бюджет',    'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'budget_investment', 'label' => 'Инвест.',   'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'foreign_invest',    'label' => 'Хор. инв.', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'export',            'label' => 'Экспорт',   'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'employment',        'label' => 'Бандлик',   'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('indicators')->insert([
        ['code' => 'grp',          'label_full' => 'ЯҲМ',      'label_short' => 'ЯҲМ',      'created_at' => now(), 'updated_at' => now()],
        ['code' => 'industry',     'label_full' => 'Саноат',   'label_short' => 'Саноат',   'created_at' => now(), 'updated_at' => now()],
        ['code' => 'agri',         'label_full' => 'Қишлоқ',   'label_short' => 'Қ. ҳ.',    'created_at' => now(), 'updated_at' => now()],
        ['code' => 'build',        'label_full' => 'Қурилиш',  'label_short' => 'Қурилиш',  'created_at' => now(), 'updated_at' => now()],
        ['code' => 'services',     'label_full' => 'Хизматлар','label_short' => 'Хизмат',   'created_at' => now(), 'updated_at' => now()],
        ['code' => 'unemployment', 'label_full' => 'Ишсизлик', 'label_short' => 'Ишсизлик', 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'poverty',      'label_full' => 'Камбағ.',  'label_short' => 'Камбағ.',  'created_at' => now(), 'updated_at' => now()],
    ]);

    // Seed enough Andijan districts for the multi-district rows in the docx.
    $districts = [
        ['code' => 'andijan_city',     'name_short' => 'Андижон ш.',    'name_full' => 'Андижон шаҳри'],
        ['code' => 'khonobod_city',    'name_short' => 'Хонобод ш.',    'name_full' => 'Хонобод шаҳри'],
        ['code' => 'asaka_district',   'name_short' => 'Асака т.',      'name_full' => 'Асака тумани'],
        ['code' => 'andijan_district', 'name_short' => 'Андижон т.',    'name_full' => 'Андижон тумани'],
        ['code' => 'shahrikhan_district', 'name_short' => 'Шаҳрихон т.', 'name_full' => 'Шаҳрихон тумани'],
        ['code' => 'boston_district',  'name_short' => 'Бўстон т.',     'name_full' => 'Бўстон тумани'],
        ['code' => 'ulugnor_district', 'name_short' => 'Улуғнор т.',    'name_full' => 'Улуғнор тумани'],
        ['code' => 'pakhtaobod_district', 'name_short' => 'Пахтаобод т.', 'name_full' => 'Пахтаобод тумани'],
    ];
    foreach ($districts as $i => $d) {
        DB::table('districts')->insert(array_merge($d, [
            'region_id' => $regionId, 'region_code' => 'andijan',
            'kind' => str_contains($d['name_full'], 'шаҳри') ? 'city' : 'district',
            'sort_order' => $i + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }
});

test('import:tasks andijan loads at least 80 tasks from docx', function () {
    Artisan::call('import:tasks', ['region' => 'andijan']);

    expect(Task::where('region_code', 'andijan')->count())->toBeGreaterThanOrEqual(80);
});

test('imported tasks have module + indicator codes derived from sections', function () {
    Artisan::call('import:tasks', ['region' => 'andijan']);

    // Row 3 in source: industry KPI, h1 deadline.
    $task = Task::where('region_code', 'andijan')->where('task_number', '3')->first();
    expect($task)->not->toBeNull();
    expect($task->module_code)->toBe('macro');
    expect($task->indicator_code)->toBe('industry');
    expect($task->kind)->toBe('kpi');
    expect($task->section_path)->toBe('I.1.2');
    expect($task->period_code)->toBe('h1');
});

test('multi-district executor parses to pivot rows', function () {
    Artisan::call('import:tasks', ['region' => 'andijan']);

    // The docx has rows mentioning Бўстон/Улуғнор/Пахтаобод. Find by content.
    $task = Task::where('region_code', 'andijan')
        ->where('executor_text', 'ILIKE', '%Бўстон%')
        ->where('executor_text', 'ILIKE', '%Улуғнор%')
        ->where('executor_text', 'ILIKE', '%Пахтаобод%')
        ->first();

    expect($task)->not->toBeNull();
    expect($task->districts()->count())->toBe(3);
});

test('rerun is idempotent', function () {
    Artisan::call('import:tasks', ['region' => 'andijan']);
    $first = Task::where('region_code', 'andijan')->count();

    Artisan::call('import:tasks', ['region' => 'andijan']);
    $second = Task::where('region_code', 'andijan')->count();

    expect($second)->toBe($first);
});
```

- [ ] **Step 2: Run test to verify it fails (or passes)**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ImportTasksCommandTest.php
```

Expected at this point: PASS, because Task 6 implementation is already complete. If FAILS, fix the importer (likely a parser-edge case) before proceeding.

If the test fails on the "section_path => I.1.2" assertion, inspect actual via:

```bash
cd backend && php artisan tinker --execute "echo \App\Models\Task::where('region_code', 'andijan')->where('task_number', '3')->first()?->section_path;"
```

If the test fails on the multi-district assertion because no row contains all three names, query for the actual multi-district executor strings:

```bash
cd backend && php artisan tinker --execute "echo \App\Models\Task::where('region_code', 'andijan')->where('executor_text', 'ILIKE', '%тумани%')->where('executor_text', 'ILIKE', '%вилояти%')->pluck('task_number', 'executor_text');"
```

Adjust the test or the parser to match actual data shape.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Import/ImportTasksCommandTest.php
git commit -m "test: import:tasks end-to-end against Andijan docx"
```

---

### Task 8: Livewire TasksBoard component

**Files:**
- Modify (replace): `backend/app/Livewire/TasksBoard.php`

- [ ] **Step 1: Replace the Livewire component**

Open `backend/app/Livewire/TasksBoard.php` (currently a stub) and replace the entire file with:

```php
<?php

namespace App\Livewire;

use App\Models\Task;
use App\Models\Module;
use App\Models\Indicator;
use App\Models\District;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class TasksBoard extends Component
{
    #[Url]
    public string $module = 'all';

    #[Url]
    public string $indicator = 'all';

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $period = 'all';

    #[Url]
    public string $district = 'all';

    #[Url]
    public string $search = '';

    public string $regionCode = 'andijan';

    public function selectModule(string $code): void
    {
        $this->module = $code;
        $this->indicator = 'all';
    }

    public function selectIndicator(string $code): void
    {
        $this->indicator = $code;
    }

    public function selectStatus(string $code): void
    {
        $this->status = $code;
    }

    public function selectPeriod(string $code): void
    {
        $this->period = $code;
    }

    public function selectDistrict(string $code): void
    {
        $this->district = $code;
    }

    public function clearFilters(): void
    {
        $this->module = 'all';
        $this->indicator = 'all';
        $this->status = 'open';
        $this->period = 'all';
        $this->district = 'all';
        $this->search = '';
    }

    #[Computed]
    public function tasks()
    {
        $q = Task::with(['module', 'indicator', 'districts'])
            ->forRegion($this->regionCode);

        if ($this->module !== 'all')   $q->forModule($this->module);
        if ($this->indicator !== 'all') $q->forIndicator($this->indicator);
        if ($this->period !== 'all')   $q->forPeriod($this->period);
        if ($this->district !== 'all') $q->forDistrict((int) $this->district);
        if ($this->search !== '')      $q->search($this->search);

        // Status: 'open' shows non-done; 'done' shows done; 'all' shows all.
        if ($this->status === 'open') $q->where('status', '!=', 'done');
        if ($this->status === 'done') $q->where('status', 'done');

        return $q->orderBy('source_paragraph_index')->get();
    }

    #[Computed]
    public function moduleOptions()
    {
        $codes = Task::forRegion($this->regionCode)
            ->whereNotNull('module_code')
            ->distinct()
            ->pluck('module_code');

        return Module::whereIn('code', $codes)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function indicatorOptions()
    {
        $q = Task::forRegion($this->regionCode)->whereNotNull('indicator_code');
        if ($this->module !== 'all') $q->forModule($this->module);
        $codes = $q->distinct()->pluck('indicator_code');

        return Indicator::whereIn('code', $codes)->orderBy('label_short')->get();
    }

    #[Computed]
    public function districtOptions()
    {
        $taskIds = Task::forRegion($this->regionCode)->pluck('id');

        return District::whereHas('tasks', fn ($q) => $q->whereIn('tasks.id', $taskIds))
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function totals(): array
    {
        $base = Task::forRegion($this->regionCode);
        if ($this->module !== 'all')    $base->forModule($this->module);
        if ($this->indicator !== 'all') $base->forIndicator($this->indicator);
        if ($this->period !== 'all')    $base->forPeriod($this->period);
        if ($this->district !== 'all')  $base->forDistrict((int) $this->district);
        if ($this->search !== '')       $base->search($this->search);

        $total = $base->count();
        $done  = (clone $base)->where('status', 'done')->count();

        return [
            'total' => $total,
            'done'  => $done,
            'open'  => $total - $done,
            'pct'   => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    public function render()
    {
        return view('livewire.tasks-board');
    }
}
```

> If `Module` or `Indicator` model classes don't exist yet, add minimal classes alongside (single file each, 5 lines, namespace + class extending Model). Verify before running tests.

- [ ] **Step 2: Sanity check Livewire registration**

```bash
cd backend && php artisan livewire:discover
```

Expected: no errors. The component name `tasks-board` is auto-derived.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Livewire/TasksBoard.php
git commit -m "feat: TasksBoard Livewire with URL-synced filters"
```

---

### Task 9: tasks-board Blade view

**Files:**
- Modify (replace): `backend/resources/views/livewire/tasks-board.blade.php`

- [ ] **Step 1: Replace the stub view**

Open `backend/resources/views/livewire/tasks-board.blade.php` and replace the entire file with:

```blade
@php
    $totals = $this->totals;
    $tasks = $this->tasks;
    $moduleOptions = $this->moduleOptions;
    $indicatorOptions = $this->indicatorOptions;
    $districtOptions = $this->districtOptions;
    $shownScope = $status === 'done' ? 'Бажарилган' : ($status === 'open' ? 'Бажарилмаган' : 'Барчаси');
@endphp

<div>
    <div class="task-filter report-filter">
        <label>Йўналиш / жадвал
            <select wire:model.live="module">
                <option value="all">Барча 7 йўналиш</option>
                @foreach($moduleOptions as $m)
                    <option value="{{ $m->code }}">{{ $m->label }}</option>
                @endforeach
            </select>
        </label>
        <label>KPI / топшириқ йўналиши
            <select wire:model.live="indicator">
                <option value="all">Барча KPI</option>
                @foreach($indicatorOptions as $i)
                    <option value="{{ $i->code }}">{{ $i->label_short }} — {{ $i->label_full }}</option>
                @endforeach
            </select>
        </label>
        <label>Ҳолат
            <select wire:model.live="status">
                <option value="open">Бажарилмаган</option>
                <option value="all">Барчаси</option>
                <option value="done">Бажарилган</option>
            </select>
        </label>
        <label>Қидириш
            <input wire:model.live.debounce.300ms="search" placeholder="Топшириқ, масъул ёки ҳудуд">
        </label>
    </div>

    <details class="task-advanced-filters" @if($period !== 'all' || $district !== 'all') open @endif>
        <summary>Қўшимча фильтрлар</summary>
        <div class="task-advanced-grid">
            <label>Муддат
                <select wire:model.live="period">
                    <option value="all">Барча муддатлар</option>
                    <option value="h1">II чорак / I ярим йиллик</option>
                    <option value="year">Йил якуни / давомида</option>
                </select>
            </label>
            <label>Туман/шаҳар
                <select wire:model.live="district">
                    <option value="all">Барча ҳудудлар</option>
                    @foreach($districtOptions as $d)
                        <option value="{{ $d->id }}">{{ $d->name_full }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </details>

    <div class="task-summary-strip execution-overview">
        <div class="task-summary-copy">
            <span>Ижро ҳолати</span>
            <strong>{{ $totals['total'] }} та топшириқ</strong>
            <small>{{ $shownScope }} топшириқлар кўрсатилмоқда.</small>
        </div>
        <div class="exec-status-grid">
            <button class="exec-status-pill {{ $status === 'all' ? 'active' : '' }}" type="button" wire:click="selectStatus('all')">
                <span>Жами</span>
                <strong>{{ $totals['total'] }}</strong>
            </button>
            <button class="exec-status-pill green {{ $status === 'done' ? 'active' : '' }}" type="button" wire:click="selectStatus('done')">
                <span>Бажарилди</span>
                <strong>{{ $totals['done'] }}</strong>
            </button>
            <button class="exec-status-pill red {{ $status === 'open' ? 'active' : '' }}" type="button" wire:click="selectStatus('open')">
                <span>Бажарилмади</span>
                <strong>{{ $totals['open'] }}</strong>
            </button>
            <button class="exec-status-pill blue" type="button" disabled>
                <span>Ҳисобот киритилган</span>
                <strong>0</strong>
            </button>
        </div>
        <div class="exec-progress-box">
            <div class="exec-donut" style="--pct:{{ $totals['pct'] }}"><strong>{{ $totals['pct'] }}%</strong></div>
            <small>бажарилиш</small>
        </div>
        <div class="score-actions">
            <a class="score-action primary" href="{{ route('dashboard') }}">KPI экрани</a>
            <a class="score-action" href="{{ route('execution') }}">Ижро журнали</a>
        </div>
    </div>

    <div class="task-workspace">
        <div class="task-groups">
            <section class="task-group">
                <div class="task-group-head">
                    <h3>{{ $shownScope }} топшириқлар</h3>
                    <span class="chip grey">{{ $tasks->count() }} та</span>
                </div>
                <div class="task-list">
                    @forelse($tasks as $task)
                        <article class="task-card compact" data-task-id="{{ $task->id }}">
                            <header>
                                <span class="task-code">{{ $task->task_number }}</span>
                                <strong>{{ $task->title }}</strong>
                                <div class="task-meta">
                                    <span>{{ $task->deadline_text }}</span>
                                    <span>{{ $task->districts->count() ? $task->districts->count() . ' туман/шаҳар' : 'вилоят' }}</span>
                                    <span>{{ $task->module?->label ?? $task->section_label }}</span>
                                </div>
                            </header>
                            <div class="task-actions">
                                <span class="chip grey">{{ $task->kind === 'kpi' ? 'KPI' : 'Чора-тадбир' }}</span>
                                @if($task->indicator)
                                    <span class="chip blue">{{ $task->indicator->label_short }}</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="muted">Бу филтр бўйича топшириқ топилмади.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <aside class="task-focus">
            <div class="eyebrow">Топшириқлар</div>
            <h3>KPI → топшириқ → ҳисобот</h3>
            <p>Бу экран KPI карточкасида кўринган ижро ҳолатини номма-ном топшириқларга очиб беради.</p>
            <div class="task-side-stack">
                <div class="task-side-row">
                    <div><strong>Танланган йўналиш</strong><span>{{ $module === 'all' ? 'Барча 7 йўналиш' : ($moduleOptions->firstWhere('code', $module)?->label ?? $module) }}</span></div>
                    <span class="chip blue">{{ $totals['total'] }} та</span>
                </div>
                <div class="task-side-row">
                    <div><strong>Танланган KPI</strong><span>{{ $indicator === 'all' ? 'Барча KPI' : ($indicatorOptions->firstWhere('code', $indicator)?->label_full ?? $indicator) }}</span></div>
                    <span class="chip blue">{{ $indicator === 'all' ? 'ҳаммаси' : $indicator }}</span>
                </div>
                <div class="task-side-row">
                    <div><strong>Ҳисобот киритилган</strong><span>Киритилган ҳисоботлар ижро журналида текширилади.</span></div>
                    <span class="chip grey">0/{{ $totals['total'] }}</span>
                </div>
            </div>
        </aside>
    </div>
</div>
```

- [ ] **Step 2: Render check (manual)**

Run from `backend/`:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

In another terminal, run `import:tasks` first (ensures DB has data):

```bash
cd backend && php artisan import:tasks andijan
```

Open `http://127.0.0.1:8001/tasks` in a browser. Confirm: filter row shows, advanced filter `<details>` collapsed by default, summary strip with 4 pills + donut, task list with cards. Stop the server (Ctrl+C).

- [ ] **Step 3: Commit**

```bash
git add backend/resources/views/livewire/tasks-board.blade.php
git commit -m "feat: tasks-board Blade mirroring index.html prototype markup"
```

---

### Task 10: TasksPage HTTP + Livewire interaction tests

**Files:**
- Create: `backend/tests/Feature/Http/TasksPageTest.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Http/TasksPageTest.php`:

```php
<?php

use App\Livewire\TasksBoard;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 'andijan', 'name_short' => 'A', 'name_full' => 'Andijan',
        'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        ['code' => 'macro',  'label' => 'M', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'export', 'label' => 'E', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    Task::create(['region_code'=>'andijan','task_number'=>'1','title'=>'macro one','executor_text'=>'хокимлик','kind'=>'kpi','module_code'=>'macro','section_path'=>'I','section_label'=>'I','source_paragraph_index'=>1]);
    Task::create(['region_code'=>'andijan','task_number'=>'2','title'=>'export two','executor_text'=>'хокимлик','kind'=>'measure','module_code'=>'export','section_path'=>'VI','section_label'=>'VI','source_paragraph_index'=>2]);
});

test('GET /tasks returns 200 and contains task-card markup', function () {
    $response = $this->get('/tasks');

    $response->assertOk();
    $response->assertSee('task-filter', false);
    $response->assertSee('task-summary-strip', false);
    $response->assertSee('task-card', false);
    $response->assertSee('macro one', false);
    $response->assertSee('export two', false);
});

test('selectModule filter narrows the task list', function () {
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->call('selectModule', 'macro')
        ->assertSee('macro one')
        ->assertDontSee('export two');
});

test('search filters by title via ILIKE', function () {
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->set('search', 'export')
        ->assertSee('export two')
        ->assertDontSee('macro one');
});

test('selectModule resets indicator to all', function () {
    Livewire::test(TasksBoard::class)
        ->set('indicator', 'industry')
        ->call('selectModule', 'export')
        ->assertSet('indicator', 'all');
});
```

- [ ] **Step 2: Run tests to verify**

```bash
cd backend && vendor/bin/pest tests/Feature/Http/TasksPageTest.php
```

Expected: PASS, all four tests green. If route 200 fails, verify `/tasks` is registered in `routes/web.php` (it should be — the existing `pages/tasks.blade.php` is already wired up).

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Http/TasksPageTest.php
git commit -m "test: tasks page HTTP + Livewire interaction"
```

---

### Task 11: Run full test suite + smoke-test in browser

**Files:** none (verification only)

- [ ] **Step 1: Run full Pest suite**

```bash
cd backend && vendor/bin/pest
```

Expected: all tests pass. Take note of any pre-existing failures unrelated to this work.

- [ ] **Step 2: Run the importer once for real**

```bash
cd backend && php artisan import:tasks andijan
```

Expected: `Imported N tasks for region 'andijan'.` where N is the actual count (≥80). May print warnings about unmatched executor tokens — that's informational.

- [ ] **Step 3: Smoke-test in browser**

```bash
cd backend && php artisan serve --host=127.0.0.1 --port=8001
```

In a browser, visit `http://127.0.0.1:8001/tasks`. Verify:

- Page loads w/ no console errors.
- Filter selects show real options (modules, KPIs, districts).
- Selecting a module filters the list and the URL gains `?module=macro`.
- Selecting a status pill changes the count display.
- Search input filters by title text.
- Each task card shows code, title, deadline, district count, module label, kind chip.

Stop the server (Ctrl+C).

- [ ] **Step 4: Final commit (only if any touch-ups were needed)**

If anything was tweaked during smoke-test:

```bash
cd backend && git add -A
git commit -m "fix: tasks page smoke-test touch-ups"
```

If no changes were needed, skip this step.

---

## Self-Review

**Spec coverage map**

| Spec section | Plan task |
|---|---|
| §3 Strategy | All tasks — single-pass through schema → models → importer → UI |
| §4.1 `tasks` migration | Task 3 |
| §4.2 `task_districts` pivot | Task 4 |
| §5.1 `Task` model + scopes | Task 5 |
| §5.2 `District::tasks()` | Task 5 |
| §6.1 ImportTasks command | Tasks 6 (skeleton + parsing) |
| §6.2 importer tests | Task 7 |
| §7.1 TasksBoard Livewire | Task 8 |
| §7.2 Blade view mirroring `index.html` | Task 9 |
| §8 Routing (no change) | Implicit — verified in Task 11 |
| §9 Tests | Tasks 3, 4, 5, 7, 10 |
| §10 Files touched | Each task lists its own files |
| §11 Risks & mitigations | Importer transactional+idempotent (Task 6); unmatched tokens warned not errored (Task 6); status default 'open' documented in migration comment (Task 3) |

**Placeholder scan**: no TBD/TODO/handwave language. Every step has either complete code or an exact command with expected output.

**Type/name consistency**: Method/scope names (`forRegion`, `forModule`, `forIndicator`, `forDistrict`, `forPeriod`, `ofKind`, `search`, `tasks()`) match between spec, model definition (Task 5), and consumers (Tasks 7, 8, 10). Task model's fillable list matches schema columns from Task 3. Pivot table name `task_districts` consistent across migration (Task 4), Task model relationship (Task 5), District model relationship (Task 5), and importer sync call (Task 6).

**Outstanding gaps**: none. The plan covers schema → models → importer → tests → Livewire → Blade → integration test → manual smoke-test.
