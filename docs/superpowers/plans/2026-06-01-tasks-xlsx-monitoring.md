# XLSX-Driven Task Monitoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the DOCX task importer with the monthly all-regions XLSX (`Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx`) as the sole task source: store plan/actual/% with per-period history, derive a binary done/open status (import-only), and surface progress on the tasks board and district drilldown — for all 14 regions.

**Architecture:** A new `task_progress` table (metric-line × report-period) plus altered `tasks` (cadence, source metadata, denormalized headline snapshot). A pure `TaskWorkbookParser` service turns the workbook into structured task/region/metric rows; an `import:task-progress` Artisan command upserts tasks, syncs districts from the per-region Ижрочи, writes per-period progress, and recomputes headline + status — wrapped in per-region `ImportRun` records for traceability. UI changes are limited to the `TasksBoard` card and the `profile/bottom` district panel; the districts comparison grid already renders done/total chips from `status`.

**Tech Stack:** Laravel 12, Livewire 3, Pest 3 (PHPUnit 11), PostgreSQL, `phpoffice/phpspreadsheet ^5.7`.

**Spec:** `docs/superpowers/specs/2026-06-01-tasks-xlsx-monitoring-design.md`

---

## Conventions for every step

- **Working directory:** all `php artisan`, `composer`, and `./vendor/bin/pest` commands run from `backend/` (the Laravel root). On Windows use PowerShell; `php artisan test --filter=X` works cross-platform.
- **Test DB:** PostgreSQL `hududlar_monitoringi_test` must be reachable (see `backend/phpunit.xml`). `RefreshDatabase` migrates automatically; seeders are explicit via `$this->seed()`.
- **Test style:** Pest closures. Feature tests that touch the DB start with `uses(RefreshDatabase::class);`. Unit tests are pure `expect()` assertions (no DB).
- **Commits:** Conventional Commits. End every commit body with the trailer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`
- **Run a single test file:** `php artisan test --filter=<TestFileOrName>`.

---

## File structure

**Create:**
- `backend/database/migrations/2026_06_01_000001_alter_tasks_add_progress_fields.php`
- `backend/database/migrations/2026_06_01_000002_create_task_progress_table.php`
- `backend/app/Models/TaskProgress.php`
- `backend/app/Support/TaskStatus.php`
- `backend/app/Support/TaskPeriod.php`
- `backend/app/Support/TaskExecutorResolver.php`
- `backend/app/Services/Tasks/TaskWorkbookParser.php`
- `backend/app/Console/Commands/ImportTaskProgress.php`
- `backend/tests/Helpers/TaskWorkbookFixture.php`
- `backend/tests/Unit/TaskStatusTest.php`
- `backend/tests/Unit/TaskPeriodTest.php`
- `backend/tests/Feature/Tasks/TaskExecutorResolverTest.php`
- `backend/tests/Feature/Tasks/TaskWorkbookParserTest.php`
- `backend/tests/Feature/Tasks/ImportTaskProgressTest.php`
- `backend/tests/Feature/Schema/TaskProgressTableTest.php`

**Modify:**
- `backend/app/Models/Task.php` (fillable, casts, `progress()` relation, `headlineProgress()` helper)
- `backend/app/Support/TasksTaxonomy.php` (add `REGION_BLOCKS`)
- `backend/database/factories/TaskFactory.php` (add new nullable fields default null — optional, only if tests need them)
- `backend/resources/views/livewire/tasks-board.blade.php` (enrich card)
- `backend/resources/views/livewire/profile/bottom.blade.php` (enrich district task rows)
- `backend/app/Console/Commands/ImportAllRegionsCommand.php` (rewire to new command)

**Delete (retire legacy DOCX importer):**
- `backend/app/Console/Commands/ImportTasks.php`
- `backend/tests/Feature/Import/ImportTasksCommandTest.php`

---

## Task 1: Schema — alter `tasks` + create `task_progress`

**Files:**
- Create: `backend/database/migrations/2026_06_01_000001_alter_tasks_add_progress_fields.php`
- Create: `backend/database/migrations/2026_06_01_000002_create_task_progress_table.php`
- Test: `backend/tests/Feature/Schema/TaskProgressTableTest.php`

- [ ] **Step 1: Write the failing schema test**

```php
<?php
// backend/tests/Feature/Schema/TaskProgressTableTest.php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('tasks table gains progress + metadata columns', function () {
    foreach ([
        'cadence', 'data_source', 'report_schedule_text', 'integration_status',
        'mechanism_text', 'latest_period', 'headline_unit', 'headline_plan',
        'headline_actual', 'headline_pct',
    ] as $col) {
        expect(Schema::hasColumn('tasks', $col))->toBeTrue("tasks.$col missing");
    }
});

test('task_progress table exists with expected columns', function () {
    expect(Schema::hasTable('task_progress'))->toBeTrue();
    foreach ([
        'id', 'task_id', 'line_no', 'metric_label', 'unit', 'report_period',
        'period_type', 'plan_value', 'actual_value', 'pct_of_plan',
        'reported_at', 'import_run_id', 'created_at', 'updated_at',
    ] as $col) {
        expect(Schema::hasColumn('task_progress', $col))->toBeTrue("task_progress.$col missing");
    }
});
```

- [ ] **Step 2: Run it — verify it fails**

Run: `php artisan test --filter=TaskProgressTableTest`
Expected: FAIL (`task_progress` table / new columns do not exist).

- [ ] **Step 3: Write the `tasks` alter migration**

```php
<?php
// backend/database/migrations/2026_06_01_000001_alter_tasks_add_progress_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('cadence', 16)->nullable()->after('period_code');          // monthly | quarterly
            $table->text('data_source')->nullable()->after('cadence');                // col I
            $table->text('report_schedule_text')->nullable()->after('data_source');   // col J (raw)
            $table->string('integration_status', 64)->nullable()->after('report_schedule_text'); // col L
            $table->text('mechanism_text')->nullable()->after('integration_status');  // col K
            // Denormalized latest-period headline snapshot (recomputed on every import)
            $table->string('latest_period', 16)->nullable()->after('mechanism_text');
            $table->string('headline_unit', 48)->nullable()->after('latest_period');
            $table->decimal('headline_plan', 20, 6)->nullable()->after('headline_unit');
            $table->decimal('headline_actual', 20, 6)->nullable()->after('headline_plan');
            $table->decimal('headline_pct', 10, 4)->nullable()->after('headline_actual');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'cadence', 'data_source', 'report_schedule_text', 'integration_status',
                'mechanism_text', 'latest_period', 'headline_unit', 'headline_plan',
                'headline_actual', 'headline_pct',
            ]);
        });
    }
};
```

- [ ] **Step 4: Write the `task_progress` create migration**

```php
<?php
// backend/database/migrations/2026_06_01_000002_create_task_progress_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->smallInteger('line_no')->default(0);          // 0 = headline metric, 1+ = sub-metrics
            $table->string('metric_label', 255)->nullable();      // col D
            $table->string('unit', 48)->nullable();               // col E
            $table->string('report_period', 16);                  // '2026-03' | '2026-Q1'
            $table->string('period_type', 8);                     // 'month' | 'quarter'
            $table->decimal('plan_value', 20, 6)->nullable();
            $table->decimal('actual_value', 20, 6)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();
            $table->date('reported_at')->nullable();
            $table->foreignId('import_run_id')->nullable()->constrained('import_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'line_no', 'report_period'], 'uq_task_progress_line_period');
            $table->index(['task_id', 'report_period'], 'idx_task_progress_task_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_progress');
    }
};
```

- [ ] **Step 5: Run the test — verify it passes**

Run: `php artisan test --filter=TaskProgressTableTest`
Expected: PASS (both tests green).

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_06_01_000001_alter_tasks_add_progress_fields.php \
        backend/database/migrations/2026_06_01_000002_create_task_progress_table.php \
        backend/tests/Feature/Schema/TaskProgressTableTest.php
git commit -m "feat(tasks): schema for task progress history + headline snapshot"
```

---

## Task 2: Models — `TaskProgress` + `Task` additions

**Files:**
- Create: `backend/app/Models/TaskProgress.php`
- Modify: `backend/app/Models/Task.php`
- Test: `backend/tests/Feature/Tasks/TaskWorkbookParserTest.php` is later; add model test inline here as `backend/tests/Feature/Schema/TaskProgressTableTest.php` already exists — add a relation test to a new file.
- Test: `backend/tests/Feature/Tasks/TaskModelTest.php`

- [ ] **Step 1: Write the failing model test**

```php
<?php
// backend/tests/Feature/Tasks/TaskModelTest.php

use App\Models\Task;
use App\Models\TaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // tasks.region_code FK -> regions.code; seed a minimal region.
    DB::table('regions')->insert([
        'id' => 1, 'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'folder_name' => '2. Андижон', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

test('task has many progress rows ordered access works', function () {
    $task = Task::factory()->create(['region_code' => 1703]);

    $task->progress()->create([
        'line_no' => 0, 'metric_label' => 'ЯҲМ', 'unit' => 'фоиз',
        'report_period' => '2026-Q1', 'period_type' => 'quarter',
        'plan_value' => 7.2, 'actual_value' => null, 'pct_of_plan' => null,
    ]);

    expect($task->progress()->count())->toBe(1);
    expect($task->headlineProgress('2026-Q1')->metric_label)->toBe('ЯҲМ');
});

test('headlineProgress returns null when no rows for period', function () {
    $task = Task::factory()->create(['region_code' => 1703]);
    expect($task->headlineProgress('2026-Q1'))->toBeNull();
});
```

- [ ] **Step 2: Run it — verify it fails**

Run: `php artisan test --filter=TaskModelTest`
Expected: FAIL (`Call to undefined method ...progress()` / `TaskProgress` class missing).

- [ ] **Step 3: Create the `TaskProgress` model**

```php
<?php
// backend/app/Models/TaskProgress.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskProgress extends Model
{
    protected $table = 'task_progress';

    protected $fillable = [
        'task_id', 'line_no', 'metric_label', 'unit', 'report_period', 'period_type',
        'plan_value', 'actual_value', 'pct_of_plan', 'reported_at', 'import_run_id',
    ];

    protected $casts = [
        'line_no'     => 'integer',
        'reported_at' => 'date',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }
}
```

- [ ] **Step 4: Add relation + helper + fillable to `Task`**

In `backend/app/Models/Task.php`, extend the `$fillable` array to include the new columns, and add the relation + helper. Replace the existing `$fillable` block with:

```php
    protected $fillable = [
        'region_code', 'guarantee_letter_id', 'task_number', 'title',
        'deadline_text', 'period_code', 'executor_text', 'kind',
        'module_code', 'indicator_code', 'section_path', 'section_label',
        'source_paragraph_index', 'status',
        // XLSX progress fields
        'cadence', 'data_source', 'report_schedule_text', 'integration_status',
        'mechanism_text', 'latest_period', 'headline_unit', 'headline_plan',
        'headline_actual', 'headline_pct',
    ];
```

Add these methods inside the `Task` class (e.g. after the `districts()` relation):

```php
    public function progress(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaskProgress::class);
    }

    /** Headline (line_no 0) progress row for a given report period. */
    public function headlineProgress(?string $period = null): ?TaskProgress
    {
        $period ??= $this->latest_period;
        if ($period === null) return null;

        return $this->progress()
            ->where('report_period', $period)
            ->where('line_no', 0)
            ->first();
    }
```

Add the import at the top of `Task.php` if not present:

```php
use App\Models\TaskProgress;
```

- [ ] **Step 5: Run the test — verify it passes**

Run: `php artisan test --filter=TaskModelTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Models/TaskProgress.php backend/app/Models/Task.php \
        backend/tests/Feature/Tasks/TaskModelTest.php
git commit -m "feat(tasks): TaskProgress model + Task progress relation"
```

---

## Task 3: Support — `TaskStatus` + `TaskPeriod`

**Files:**
- Create: `backend/app/Support/TaskStatus.php`
- Create: `backend/app/Support/TaskPeriod.php`
- Test: `backend/tests/Unit/TaskStatusTest.php`
- Test: `backend/tests/Unit/TaskPeriodTest.php`

- [ ] **Step 1: Write the failing unit tests**

```php
<?php
// backend/tests/Unit/TaskStatusTest.php

use App\Support\TaskStatus;

test('status is done at or above 100 percent', function () {
    expect(TaskStatus::statusFor(100.0))->toBe('done');
    expect(TaskStatus::statusFor(150.0))->toBe('done');
});

test('status is open below 100 percent or when missing', function () {
    expect(TaskStatus::statusFor(99.99))->toBe('open');
    expect(TaskStatus::statusFor(0.0))->toBe('open');
    expect(TaskStatus::statusFor(null))->toBe('open');
});
```

```php
<?php
// backend/tests/Unit/TaskPeriodTest.php

use App\Support\TaskPeriod;

test('cadence detects quarterly before monthly', function () {
    // Contains both "чорак" and "ой" -> must resolve quarterly.
    expect(TaskPeriod::cadenceFor('Ҳар чорак якуни билан кейинги ойнинг 25 санаси'))->toBe('quarterly');
    expect(TaskPeriod::cadenceFor('Ҳар ой якуни билан кейинги ойнинг 25 санаси'))->toBe('monthly');
    expect(TaskPeriod::cadenceFor('Ҳар ой'))->toBe('monthly');
    expect(TaskPeriod::cadenceFor('Ҳар ойда'))->toBe('monthly');
    expect(TaskPeriod::cadenceFor(''))->toBe('quarterly'); // default
});

test('period type parses quarter vs month', function () {
    expect(TaskPeriod::periodType('2026-Q1'))->toBe('quarter');
    expect(TaskPeriod::periodType('2026-Q4'))->toBe('quarter');
    expect(TaskPeriod::periodType('2026-03'))->toBe('month');
});

test('year is parsed from report period', function () {
    expect(TaskPeriod::yearFromPeriod('2026-Q1'))->toBe(2026);
    expect(TaskPeriod::yearFromPeriod('2026-11'))->toBe(2026);
});

test('deadline text maps to period code', function () {
    expect(TaskPeriod::deadlineToPeriodCode("2026 йил\nI ярим йиллик"))->toBe('h1');
    expect(TaskPeriod::deadlineToPeriodCode('2026 йил якуни билан'))->toBe('year');
    expect(TaskPeriod::deadlineToPeriodCode('2026 йил давомида'))->toBe('ongoing');
    expect(TaskPeriod::deadlineToPeriodCode('2026 йил май ойи'))->toBe('month');
    expect(TaskPeriod::deadlineToPeriodCode(null))->toBeNull();
});
```

- [ ] **Step 2: Run them — verify they fail**

Run: `php artisan test --filter="TaskStatusTest|TaskPeriodTest"`
Expected: FAIL (classes missing).

- [ ] **Step 3: Implement `TaskStatus`**

```php
<?php
// backend/app/Support/TaskStatus.php

namespace App\Support;

class TaskStatus
{
    /** Binary done/open from a percent-of-plan value. */
    public static function statusFor(?float $pct): string
    {
        return $pct !== null && $pct >= 100.0 ? 'done' : 'open';
    }
}
```

- [ ] **Step 4: Implement `TaskPeriod`**

```php
<?php
// backend/app/Support/TaskPeriod.php

namespace App\Support;

class TaskPeriod
{
    /** Reporting cadence from the col J schedule text. "чорак" wins over "ой". */
    public static function cadenceFor(?string $scheduleText): string
    {
        $text = (string) $scheduleText;
        if (mb_strpos($text, 'чорак') !== false) return 'quarterly';
        if (mb_strpos($text, 'ой') !== false)    return 'monthly';
        return 'quarterly';
    }

    /** 'quarter' for "2026-Q1", else 'month'. */
    public static function periodType(string $reportPeriod): string
    {
        return preg_match('/-Q[1-4]$/', $reportPeriod) ? 'quarter' : 'month';
    }

    public static function yearFromPeriod(string $reportPeriod): int
    {
        return (int) substr($reportPeriod, 0, 4);
    }

    /** Normalize deadline text (col F) to a coarse period_code. */
    public static function deadlineToPeriodCode(?string $deadline): ?string
    {
        if ($deadline === null) return null;
        $t = preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $deadline));
        $t = trim((string) $t);
        if ($t === '') return null;

        if (mb_strpos($t, 'ярим йиллик') !== false) return 'h1';
        if (mb_strpos($t, 'якуни') !== false)       return 'year';
        if (mb_strpos($t, 'давомида') !== false)     return 'ongoing';
        if (preg_match('/(январ|феврал|март|апрел|май|июн|июл|август|сентябр|октябр|ноябр|декабр)/u', $t)) {
            return 'month';
        }
        return null;
    }
}
```

- [ ] **Step 5: Run the tests — verify they pass**

Run: `php artisan test --filter="TaskStatusTest|TaskPeriodTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Support/TaskStatus.php backend/app/Support/TaskPeriod.php \
        backend/tests/Unit/TaskStatusTest.php backend/tests/Unit/TaskPeriodTest.php
git commit -m "feat(tasks): TaskStatus + TaskPeriod support helpers"
```

---

## Task 4: Support — `TaskExecutorResolver` (executor text → district IDs)

This extracts the proven matching logic from the legacy `ImportTasks::resolveDistricts()` into a reusable, tested helper.

**Files:**
- Create: `backend/app/Support/TaskExecutorResolver.php`
- Test: `backend/tests/Feature/Tasks/TaskExecutorResolverTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Tasks/TaskExecutorResolverTest.php

use App\Models\District;
use App\Support\TaskExecutorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(); // full seed: regions + districts for all 14 regions
});

test('resolves district hokimliklar, skips region rows', function () {
    $districts = District::where('region_code', 1703)->get();
    $unmatched = [];

    $executor = "Андижон вилояти ҳокимлиги,\nШаҳрихон тумани ҳокимлиги,\nХонобод шаҳри ҳокимлиги";
    $ids = TaskExecutorResolver::districtIds($executor, $districts, $unmatched);

    expect($ids)->toHaveCount(2);                 // region row skipped, 2 districts matched
    expect($unmatched)->toBe([]);

    $names = District::whereIn('id', $ids)->pluck('name_full')->all();
    expect($names)->toContain('Шаҳрихон тумани');
    expect($names)->toContain('Хонобод шаҳри');
});

test('collects unmatched tokens', function () {
    $districts = District::where('region_code', 1703)->get();
    $unmatched = [];
    TaskExecutorResolver::districtIds('Несуществующий тумани ҳокимлиги', $districts, $unmatched);
    expect($unmatched)->toContain('Несуществующий тумани');
});
```

- [ ] **Step 2: Run it — verify it fails**

Run: `php artisan test --filter=TaskExecutorResolverTest`
Expected: FAIL (class missing).

- [ ] **Step 3: Implement `TaskExecutorResolver`**

```php
<?php
// backend/app/Support/TaskExecutorResolver.php

namespace App\Support;

class TaskExecutorResolver
{
    /**
     * Resolve a per-region "Ижрочи" cell to a list of district IDs.
     * Region-level rows (containing "вилояти") are skipped.
     *
     * @param  iterable  $districts  District models for the region (need id, name_full, name_short, alt_labels)
     * @param  list<string>  $unmatched  collects clean tokens that matched nothing
     * @return list<int>
     */
    public static function districtIds(string $executor, iterable $districts, array &$unmatched): array
    {
        $tokens = preg_split('/[,\n]+/u', $executor) ?: [];
        $ids = [];

        foreach ($tokens as $token) {
            $clean = trim($token);
            $clean = preg_replace('/\s+ҳокимлиги$/u', '', $clean);
            $clean = preg_replace('/\s+ҳокимияти$/u', '', $clean);
            $clean = trim((string) $clean);
            // Skip region-level executors: вилоят hokimliklar AND the Karakalpak
            // "Республикаси Вазирлар Кенгаши" (which has no "вилояти" token).
            if ($clean === ''
                || str_contains($clean, 'вилояти')
                || str_contains($clean, 'Республикаси')
                || str_contains($clean, 'Вазирлар')) {
                continue;
            }

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

        return array_values(array_unique($ids));
    }
}
```

- [ ] **Step 4: Run the test — verify it passes**

Run: `php artisan test --filter=TaskExecutorResolverTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/TaskExecutorResolver.php \
        backend/tests/Feature/Tasks/TaskExecutorResolverTest.php
git commit -m "feat(tasks): extract executor->district resolver"
```

---

## Task 5: Test helper — `TaskWorkbookFixture` (synthetic XLSX builder)

A committed, hermetic fixture so parser/importer tests do not depend on the gitignored real workbook. It mirrors the real layout: descriptor cols A–L, region blocks at fixed columns (M=13 Qoraqalpoq, Q=17 Andijan), section rows, a single-metric task, and a multi-metric task.

**Files:**
- Create: `backend/tests/Helpers/TaskWorkbookFixture.php`

- [ ] **Step 1: Implement the fixture builder (no test yet — it is exercised by Task 6/7)**

```php
<?php
// backend/tests/Helpers/TaskWorkbookFixture.php

namespace Tests\Helpers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TaskWorkbookFixture
{
    /**
     * Build a small workbook matching the real structure and save to a temp .xlsx.
     * @return string absolute path to the temp file (caller deletes if desired)
     */
    public static function make(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();

        $set = function (int $col, int $row, $val) use ($sheet) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, $val);
        };

        // Row 3: descriptor headers + two region block headers (cols 13, 17).
        $set(1, 3, '№');
        $set(3, 3, 'Кўрсаткич номи');
        $set(4, 3, 'Индикатор номи');
        $set(5, 3, 'Ўлчов бирлиги');
        $set(6, 3, 'Муддати');
        $set(7, 3, 'Ижрочи');
        $set(8, 3, 'Топшириқ тури');
        $set(9, 3, 'Маълумот манбаи');
        $set(10, 3, 'Ҳисобот шакилланадиган сана');
        $set(11, 3, 'Амалга ошириш механизми');
        $set(12, 3, 'Интеграция ҳолати');
        $set(13, 3, 'Қорақалпоғистон Республикаси');
        $set(17, 3, 'Андижон вилояти');

        // Row 4: per-block sub-headers (fidelity only; parser ignores them).
        foreach ([13, 17] as $b) {
            $set($b + 0, 4, 'Ижрочи');
            $set($b + 1, 4, 'Режа кўрсаткичи');
            $set($b + 2, 4, 'Амалда ижроси');
            $set($b + 3, 4, 'Бажарилиши фоизда');
        }

        // Row 5: module section (roman I -> macro).
        $set(1, 5, 'I. Макроиқтисодий кўрсаткичлар');
        // Row 6: indicator subsection (1.1 -> grp).
        $set(1, 6, '1.1. Ялпи ҳудудий маҳсулот бўйича мақсадлар');

        // Row 7: TASK 1 (KPI, quarterly, h1, single metric).
        $set(1, 7, 1);
        $set(3, 7, 'ЯҲМ ўсишини таъминлаш');
        $set(4, 7, 'ЯҲМ ўсиш суръати');
        $set(5, 7, 'фоиз');
        $set(6, 7, "2026 йил\nI ярим йиллик");
        $set(8, 7, 'KPI');
        $set(9, 7, 'Статистика агентлиги');
        $set(10, 7, 'Ҳар чорак якуни билан кейинги ойнинг 25 санаси');
        // Qoraqalpoq block (13): executor + plan only
        $set(13, 7, 'Қорақалпоғистон Республикаси Вазирлар Кенгаши');
        $set(14, 7, 10.2);
        // Andijan block (17): executor + plan only (no actual yet)
        $set(17, 7, 'Андижон вилояти ҳокимлиги');
        $set(18, 7, 7.2);

        // Row 8: TASK 2 (Чора-тадбир, monthly, year, multi-metric, district executor).
        $set(1, 8, 2);
        $set(3, 8, 'Йирик корхоналарни ишга тушириш');
        $set(4, 8, 'йирик корхона сони');
        $set(5, 8, 'дона');
        $set(6, 8, '2026 йил якуни билан');
        $set(8, 8, 'Чора-тадбирлар');
        $set(9, 8, 'Ҳокимлик');
        $set(10, 8, 'Ҳар ой');
        $set(17, 8, "Андижон вилояти ҳокимлиги,\nШаҳрихон тумани ҳокимлиги");
        $set(18, 8, 6);   // plan
        $set(19, 8, 3);   // actual
        $set(20, 8, 50);  // pct

        // Row 9: continuation metric line for TASK 2 (col A empty, col D present).
        $set(4, 9, 'қайта тикланадиган ишлаб чиқариш ҳажми');
        $set(5, 9, 'млрд сўм');
        $set(18, 9, 55);   // plan
        $set(19, 9, 55);   // actual
        $set(20, 9, 100);  // pct (done)

        $path = tempnam(sys_get_temp_dir(), 'taskwb_') . '.xlsx';
        (IOFactory::createWriter($book, 'Xlsx'))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add backend/tests/Helpers/TaskWorkbookFixture.php
git commit -m "test(tasks): synthetic XLSX fixture builder"
```

---

## Task 6: `TaskWorkbookParser` (workbook → structured rows)

Pure parser: reads the workbook, walks rows, emits one structured array per numbered task with per-region executor + metric lines. No DB.

**Files:**
- Create: `backend/app/Services/Tasks/TaskWorkbookParser.php`
- Modify: `backend/app/Support/TasksTaxonomy.php` (add `REGION_BLOCKS`)
- Test: `backend/tests/Feature/Tasks/TaskWorkbookParserTest.php`

- [ ] **Step 1: Write the failing parser test**

```php
<?php
// backend/tests/Feature/Tasks/TaskWorkbookParserTest.php

use App\Services\Tasks\TaskWorkbookParser;
use Tests\Helpers\TaskWorkbookFixture;

test('parses tasks, sections, regions and multi-metric lines', function () {
    $path = TaskWorkbookFixture::make();
    $tasks = (new TaskWorkbookParser())->parse($path);
    @unlink($path);

    expect($tasks)->toHaveCount(2);

    $t1 = $tasks[0];
    expect($t1['task_number'])->toBe('1');
    expect($t1['title'])->toBe('ЯҲМ ўсишини таъминлаш');
    expect($t1['kind'])->toBe('kpi');
    expect($t1['module_code'])->toBe('macro');
    expect($t1['indicator_code'])->toBe('grp');
    expect($t1['cadence'])->toBe('quarterly');
    expect($t1['period_code'])->toBe('h1');
    expect($t1['regions'])->toHaveKeys([1735, 1703]);
    expect($t1['regions'][1703]['executor_text'])->toBe('Андижон вилояти ҳокимлиги');
    expect($t1['regions'][1703]['metrics'][0]['plan'])->toBeNumericallyClose(7.2);
    expect($t1['regions'][1703]['metrics'][0]['actual'])->toBeNull();

    $t2 = $tasks[1];
    expect($t2['task_number'])->toBe('2');
    expect($t2['kind'])->toBe('measure');
    expect($t2['cadence'])->toBe('monthly');
    expect($t2['period_code'])->toBe('year');
    expect($t2['regions'][1703]['metrics'])->toHaveCount(2);
    expect($t2['regions'][1703]['metrics'][0]['plan'])->toBeNumericallyClose(6);
    expect($t2['regions'][1703]['metrics'][0]['pct'])->toBeNumericallyClose(50);
    expect($t2['regions'][1703]['metrics'][1]['metric_label'])->toContain('қайта тикланадиган');
    expect($t2['regions'][1703]['metrics'][1]['plan'])->toBeNumericallyClose(55);
    expect($t2['regions'][1703]['metrics'][1]['pct'])->toBeNumericallyClose(100);
    // Qoraqalpoq has no data for task 2 -> region absent.
    expect($t2['regions'])->not->toHaveKey(1735);
});
```

- [ ] **Step 2: Run it — verify it fails**

Run: `php artisan test --filter=TaskWorkbookParserTest`
Expected: FAIL (parser missing).

- [ ] **Step 3: Add `REGION_BLOCKS` to `TasksTaxonomy`**

Append this constant inside `backend/app/Support/TasksTaxonomy.php` (alongside the existing constants):

```php
    /**
     * 1-based start column index of each region's 4-col block (Ижрочи/Режа/Амалда/Фоиз)
     * => SOATO region code. Order matches the real workbook header row 3.
     */
    public const REGION_BLOCKS = [
        13 => 1735, // Қорақалпоғистон
        17 => 1703, // Андижон
        21 => 1706, // Бухоро
        25 => 1708, // Жиззах
        29 => 1710, // Қашқадарё
        33 => 1712, // Навоий
        37 => 1714, // Наманган
        41 => 1718, // Самарқанд
        45 => 1724, // Сирдарё
        49 => 1722, // Сурхондарё
        53 => 1727, // Тошкент вилояти
        57 => 1730, // Фарғона
        61 => 1733, // Хоразм
        65 => 1726, // Тошкент шаҳри
    ];
```

- [ ] **Step 4: Implement `TaskWorkbookParser`**

```php
<?php
// backend/app/Services/Tasks/TaskWorkbookParser.php

namespace App\Services\Tasks;

use App\Support\TaskPeriod;
use App\Support\TasksTaxonomy;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TaskWorkbookParser
{
    private const FIRST_DATA_ROW = 7;

    /**
     * @return list<array{
     *   task_number:string, title:string, deadline_text:?string, period_code:?string,
     *   kind:string, data_source:?string, report_schedule_text:?string, cadence:string,
     *   mechanism_text:?string, integration_status:?string, module_code:?string,
     *   indicator_code:?string, section_path:string, section_label:string, source_row:int,
     *   regions: array<int, array{executor_text:string, metrics: list<array{
     *     line_no:int, metric_label:?string, unit:?string, plan:?float, actual:?float, pct:?float
     *   }>}>
     * }>
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $sheet = $book->getActiveSheet();

        $this->assertLayout($sheet);

        $maxRow = $sheet->getHighestDataRow();
        $tasks = [];
        $current = null;          // reference to the in-progress task array
        $module = null; $indicator = null; $path0 = ''; $label = '';

        $romanRe   = '/^(VII|VI|IV|V|III|II|I)\./u';
        $numericRe = '/^(\d+)\.(\d+)\./u';

        for ($row = self::FIRST_DATA_ROW; $row <= $maxRow; $row++) {
            $a = $this->str($sheet, 1, $row);
            $c = $this->str($sheet, 3, $row);
            $d = $this->str($sheet, 4, $row);

            // Section header rows (col A carries a marker, col C empty).
            if ($a !== '' && $c === '' && ! $this->isIntToken($a)) {
                if (preg_match($romanRe, $a, $m)) {
                    $module    = TasksTaxonomy::ROMAN_TO_MODULE[$m[1]] ?? null;
                    $indicator = null;
                    $path0     = $m[1];
                    $label     = $a;
                } elseif (preg_match($numericRe, $a, $m)) {
                    $key       = $m[1] . '.' . $m[2];
                    $indicator = TasksTaxonomy::NUMERIC_TO_INDICATOR[$key] ?? null;
                    $path0     = ($path0 !== '' ? explode('.', $path0)[0] : '') . '.' . $key;
                    $label     = $a;
                } else {
                    $label = $a; // free-text sub-label, keep module/indicator
                }
                continue;
            }

            // New task row: integer col A + non-empty title.
            if ($this->isIntToken($a) && $c !== '') {
                $current = [
                    'task_number'          => (string) (int) (float) $a,
                    'title'                => $c,
                    'deadline_text'        => $this->normWs($this->str($sheet, 6, $row)) ?: null,
                    'period_code'          => TaskPeriod::deadlineToPeriodCode($this->str($sheet, 6, $row)),
                    'kind'                 => str_starts_with($this->str($sheet, 8, $row), 'KPI') ? 'kpi' : 'measure',
                    'data_source'          => $this->str($sheet, 9, $row) ?: null,
                    'report_schedule_text' => $this->str($sheet, 10, $row) ?: null,
                    'cadence'              => TaskPeriod::cadenceFor($this->str($sheet, 10, $row)),
                    'mechanism_text'       => $this->str($sheet, 11, $row) ?: null,
                    'integration_status'   => $this->str($sheet, 12, $row) ?: null,
                    'module_code'          => $module,
                    'indicator_code'       => $indicator,
                    'section_path'         => $path0,
                    'section_label'        => $label,
                    'source_row'           => $row,
                    'regions'              => [],
                ];
                $this->captureRegions($sheet, $row, $current, lineNo: 0, isTaskRow: true);
                $tasks[] = $current;
                $current = &$tasks[count($tasks) - 1];
                continue;
            }

            // Continuation metric line (col A empty, col D present) for the current task.
            if ($a === '' && $d !== '' && $current !== null) {
                $nextLine = $this->maxLineNo($current) + 1;
                $this->captureRegions($sheet, $row, $current, lineNo: $nextLine, isTaskRow: false);
                continue;
            }
        }

        $book->disconnectWorksheets();
        return array_values($tasks);
    }

    /** Read each region block's metric (+ executor on task rows) into $task['regions']. */
    private function captureRegions(Worksheet $sheet, int $row, array &$task, int $lineNo, bool $isTaskRow): void
    {
        $metricLabel = $this->str($sheet, 4, $row) ?: null; // col D
        $unit        = $this->str($sheet, 5, $row) ?: null; // col E

        foreach (TasksTaxonomy::REGION_BLOCKS as $col => $code) {
            $executor = $this->str($sheet, $col + 0, $row);
            $plan     = $this->num($sheet, $col + 1, $row);
            $actual   = $this->num($sheet, $col + 2, $row);
            $pctCell  = $this->num($sheet, $col + 3, $row);

            $hasExecutor = $isTaskRow && $executor !== '' && ! $this->isSentinel($executor);
            $hasValue    = $plan !== null || $actual !== null || $pctCell !== null;
            if (! $hasExecutor && ! $hasValue) continue;

            $pct = $pctCell;
            if ($pct === null && $plan !== null && $actual !== null && $plan != 0.0) {
                $pct = $actual / $plan * 100.0;
            }

            if (! isset($task['regions'][$code])) {
                $task['regions'][$code] = ['executor_text' => '', 'metrics' => []];
            }
            if ($hasExecutor && $task['regions'][$code]['executor_text'] === '') {
                $task['regions'][$code]['executor_text'] = $executor;
            }
            $task['regions'][$code]['metrics'][] = [
                'line_no'      => $lineNo,
                'metric_label' => $metricLabel,
                'unit'         => $unit,
                'plan'         => $plan,
                'actual'       => $actual,
                'pct'          => $pct,
            ];
        }
    }

    private function maxLineNo(array $task): int
    {
        $max = 0;
        foreach ($task['regions'] as $r) {
            foreach ($r['metrics'] as $m) {
                $max = max($max, $m['line_no']);
            }
        }
        return $max;
    }

    /** Anchor sanity check: two known block headers (cols 13, 17) must match. */
    private function assertLayout(Worksheet $sheet): void
    {
        $anchors = [13 => 'Қорақалпоғистон', 17 => 'Андижон'];
        foreach ($anchors as $col => $needle) {
            $h = $this->str($sheet, $col, 3);
            if (mb_strpos($h, $needle) === false) {
                throw new \RuntimeException(
                    "Unexpected workbook layout: column {$col} header is '{$h}', expected to contain '{$needle}'. ".
                    'The region block columns may have shifted.'
                );
            }
        }
    }

    private function str(Worksheet $sheet, int $col, int $row): string
    {
        $v = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue();
        return $v === null ? '' : trim((string) $v);
    }

    private function num(Worksheet $sheet, int $col, int $row): ?float
    {
        $v = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue();
        if ($v === null) return null;
        if (is_int($v) || is_float($v)) return (float) $v;
        $s = trim((string) $v);
        if ($s === '' || $this->isSentinel($s)) return null;
        $s = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $s);
        return is_numeric($s) ? (float) $s : null;
    }

    private function isSentinel(string $s): bool
    {
        $t = mb_strtolower(trim($s));
        return in_array($t, ['х', 'x', '-', '—', '–'], true);
    }

    private function isIntToken(string $s): bool
    {
        return $s !== '' && preg_match('/^\d+(\.0+)?$/', $s) === 1;
    }

    private function normWs(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $s)));
    }
}
```

- [ ] **Step 5: Run the test — verify it passes**

Run: `php artisan test --filter=TaskWorkbookParserTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/Tasks/TaskWorkbookParser.php \
        backend/app/Support/TasksTaxonomy.php \
        backend/tests/Feature/Tasks/TaskWorkbookParserTest.php
git commit -m "feat(tasks): XLSX workbook parser with region blocks + multi-metric"
```

---

## Task 7: `import:task-progress` Artisan command

Orchestrates parse → upsert tasks + districts → write per-period progress → recompute headline + status, per-region `ImportRun` for traceability. Idempotent per `(region, period)`; never bulk-deletes tasks.

**Files:**
- Create: `backend/app/Console/Commands/ImportTaskProgress.php`
- Test: `backend/tests/Feature/Tasks/ImportTaskProgressTest.php`

- [ ] **Step 1: Write the failing command test**

```php
<?php
// backend/tests/Feature/Tasks/ImportTaskProgressTest.php

use App\Models\ImportRun;
use App\Models\Task;
use App\Models\TaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\TaskWorkbookFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(); // regions, districts, modules, indicators, reporting_years(2026)
    $this->fixture = TaskWorkbookFixture::make();
});

afterEach(function () {
    @unlink($this->fixture);
});

test('imports tasks, progress, districts and status for a period', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1'])
        ->assertSuccessful();

    // Andijan task 1
    $t1 = Task::where('region_code', 1703)->where('task_number', '1')->first();
    expect($t1)->not->toBeNull();
    expect($t1->title)->toBe('ЯҲМ ўсишини таъминлаш');
    expect($t1->kind)->toBe('kpi');
    expect($t1->cadence)->toBe('quarterly');
    expect($t1->module_code)->toBe('macro');
    expect($t1->indicator_code)->toBe('grp');
    expect((float) $t1->headline_plan)->toBeNumericallyClose(7.2);
    expect($t1->status)->toBe('open'); // no actual yet

    // Andijan task 2 (multi-metric, district executor, headline pct 50)
    $t2 = Task::where('region_code', 1703)->where('task_number', '2')->first();
    expect($t2->kind)->toBe('measure');
    expect($t2->status)->toBe('open');
    expect((float) $t2->headline_pct)->toBeNumericallyClose(50);
    expect($t2->progress()->where('report_period', '2026-Q1')->count())->toBe(2);
    expect($t2->districts->pluck('name_full')->all())->toContain('Шаҳрихон тумани');

    // Qoraqalpoq task 1 also imported
    expect(Task::where('region_code', 1735)->where('task_number', '1')->exists())->toBeTrue();

    // ImportRun recorded
    expect(ImportRun::where('region_code', 1703)->where('year', 2026)->exists())->toBeTrue();
});

test('re-importing the same period is idempotent', function () {
    $args = ['--file' => $this->fixture, '--period' => '2026-Q1'];
    $this->artisan('import:task-progress', $args)->assertSuccessful();
    $this->artisan('import:task-progress', $args)->assertSuccessful();

    $t2 = Task::where('region_code', 1703)->where('task_number', '2')->first();
    expect($t2->progress()->where('report_period', '2026-Q1')->count())->toBe(2); // not 4
    expect(Task::where('region_code', 1703)->count())->toBe(2); // no duplicate tasks
});

test('a later period appends history without losing the old one', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1'])->assertSuccessful();
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-04'])->assertSuccessful();

    $t2 = Task::where('region_code', 1703)->where('task_number', '2')->first();
    expect($t2->progress()->where('report_period', '2026-Q1')->count())->toBe(2);
    expect($t2->progress()->where('report_period', '2026-04')->count())->toBe(2);
    expect($t2->latest_period)->toBe('2026-04');
});
```

- [ ] **Step 2: Run it — verify it fails**

Run: `php artisan test --filter=ImportTaskProgressTest`
Expected: FAIL (command missing).

- [ ] **Step 3: Implement the command**

```php
<?php
// backend/app/Console/Commands/ImportTaskProgress.php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\District;
use App\Models\ImportRun;
use App\Models\Task;
use App\Models\TaskProgress;
use App\Services\Tasks\TaskWorkbookParser;
use App\Support\TaskExecutorResolver;
use App\Support\TaskPeriod;
use App\Support\TaskStatus;
use Database\Seeders\SoatoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTaskProgress extends Command
{
    protected $signature = 'import:task-progress
        {--file= : Path to the XLSX (defaults to data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx)}
        {--period= : Report period this file represents, e.g. 2026-Q1 or 2026-04}
        {--region=all : Region slug/code to import, or "all"}
        {--dry-run : Parse and report without writing}';

    protected $description = 'Import monthly/quarterly task plan+actual+% from the all-regions monitoring XLSX.';

    public function handle(): int
    {
        $period = (string) $this->option('period');
        if ($period === '' || ! preg_match('/^\d{4}-(Q[1-4]|\d{2})$/', $period)) {
            $this->error('Provide --period as YYYY-Q1..Q4 or YYYY-MM (e.g. 2026-Q1 or 2026-04).');
            return self::FAILURE;
        }
        $periodType = TaskPeriod::periodType($period);
        $year       = TaskPeriod::yearFromPeriod($period);

        $file = $this->option('file')
            ?: base_path('../data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx');
        if (! is_file($file)) {
            $this->error("Source workbook not found: {$file}");
            return self::FAILURE;
        }

        // Optional region filter -> SOATO code.
        $regionFilter = null;
        $regionOpt = (string) $this->option('region');
        if ($regionOpt !== '' && $regionOpt !== 'all') {
            $regionFilter = ctype_digit($regionOpt)
                ? (int) $regionOpt
                : array_search($regionOpt, SoatoSeeder::REGION_LATIN, true);
            if ($regionFilter === false) {
                $this->error("Unknown region: {$regionOpt}");
                return self::FAILURE;
            }
        }

        $this->info("Parsing {$file} for period {$period}…");
        $tasks = (new TaskWorkbookParser())->parse($file);
        $this->info('Parsed ' . count($tasks) . ' task definitions.');

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
            return self::SUCCESS;
        }

        // Districts per region for executor resolution.
        $districtsByRegion = District::all()->groupBy('region_code');
        $unmatched = [];
        $runByRegion = [];
        $written = 0;

        DB::transaction(function () use (
            $tasks, $period, $periodType, $year, $regionFilter,
            $districtsByRegion, &$unmatched, &$runByRegion, &$written
        ) {
            foreach ($tasks as $t) {
                foreach ($t['regions'] as $code => $regionData) {
                    if ($regionFilter !== null && $code !== $regionFilter) continue;

                    $run = $runByRegion[$code] ??= ImportRun::create([
                        'region_code'  => $code,
                        'year'         => $year,
                        'trigger_kind' => 'cli',
                        'status'       => ImportRunStatus::Promoting,
                        'started_at'   => now(),
                    ]);

                    $task = Task::updateOrCreate(
                        ['region_code' => $code, 'task_number' => $t['task_number']],
                        [
                            'title'                => $t['title'],
                            'deadline_text'        => $t['deadline_text'],
                            'period_code'          => $t['period_code'],
                            'executor_text'        => $regionData['executor_text'],
                            'kind'                 => $t['kind'],
                            'cadence'              => $t['cadence'],
                            'data_source'          => $t['data_source'],
                            'report_schedule_text' => $t['report_schedule_text'],
                            'integration_status'   => $t['integration_status'],
                            'mechanism_text'       => $t['mechanism_text'],
                            'module_code'          => $t['module_code'],
                            'indicator_code'       => $t['indicator_code'],
                            'section_path'         => $t['section_path'],
                            'section_label'        => $t['section_label'],
                            'source_paragraph_index' => $t['source_row'],
                        ]
                    );

                    // Re-sync districts from this file's executor list.
                    $ids = TaskExecutorResolver::districtIds(
                        $regionData['executor_text'],
                        $districtsByRegion->get($code, collect()),
                        $unmatched
                    );
                    $task->districts()->sync($ids);

                    // Replace this period's progress rows (idempotent), then insert.
                    $task->progress()->where('report_period', $period)->delete();
                    foreach ($regionData['metrics'] as $m) {
                        TaskProgress::create([
                            'task_id'       => $task->id,
                            'line_no'       => $m['line_no'],
                            'metric_label'  => $m['metric_label'],
                            'unit'          => $m['unit'],
                            'report_period' => $period,
                            'period_type'   => $periodType,
                            'plan_value'    => $m['plan'],
                            'actual_value'  => $m['actual'],
                            'pct_of_plan'   => $m['pct'],
                            'import_run_id' => $run->id,
                        ]);
                        $written++;
                    }

                    // Recompute headline snapshot + binary status from line_no 0.
                    $head = collect($regionData['metrics'])->firstWhere('line_no', 0)
                        ?? ($regionData['metrics'][0] ?? null);
                    $task->update([
                        'latest_period'   => $period,
                        'headline_unit'   => $head['unit'] ?? null,
                        'headline_plan'   => $head['plan'] ?? null,
                        'headline_actual' => $head['actual'] ?? null,
                        'headline_pct'    => $head['pct'] ?? null,
                        'status'          => TaskStatus::statusFor($head['pct'] ?? null),
                    ]);
                }
            }

            foreach ($runByRegion as $run) {
                $run->update([
                    'status'        => ImportRunStatus::Promoted,
                    'promoted_at'   => now(),
                    'files_processed' => 1,
                    'rows_promoted' => $written,
                ]);
            }
        });

        $this->info("Wrote {$written} progress rows across " . count($runByRegion) . ' region(s).');
        if (! empty($unmatched)) {
            $this->warn('Unmatched executor tokens: ' . implode(' | ', array_unique($unmatched)));
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test — verify it passes**

Run: `php artisan test --filter=ImportTaskProgressTest`
Expected: PASS (all three tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/ImportTaskProgress.php \
        backend/tests/Feature/Tasks/ImportTaskProgressTest.php
git commit -m "feat(tasks): import:task-progress command with history + idempotency"
```

---

## Task 8: Tasks board UI — show plan/actual/% + status + cadence

The `TasksBoard` query already eager-loads `module`, `indicator`, `districts` and filters by `status`. Headline values live on the task row, so the component needs no query change; only the card template is enriched.

**Files:**
- Modify: `backend/resources/views/livewire/tasks-board.blade.php`
- Test: `backend/tests/Feature/Tasks/TasksBoardProgressTest.php`

- [ ] **Step 1: Write the failing Livewire test**

```php
<?php
// backend/tests/Feature/Tasks/TasksBoardProgressTest.php

use App\Livewire\TasksBoard;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'id' => 1, 'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'folder_name' => '2. Андижон', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'code' => 'macro', 'label' => 'Макро иқтисодиёт', 'sort_order' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1', 'title' => 'ЯҲМ ўсиши',
        'module_code' => 'macro', 'cadence' => 'quarterly', 'status' => 'open',
        'headline_unit' => 'фоиз', 'headline_plan' => 7.2, 'headline_actual' => 3.6,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
});

test('board card shows plan, actual, percent, cadence and last period', function () {
    session(['region_code' => 1703]); // TasksBoard::mount() reads CurrentRegion::code()
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('7.2')      // plan
        ->assertSee('3.6')      // actual
        ->assertSee('50')       // pct
        ->assertSee('Чорак')    // cadence label (quarterly)
        ->assertSee('2026-Q1'); // last period
});
```

> Note: `TasksBoard::mount()` reads `CurrentRegion::code()`. Passing `['regionCode' => 1703]` to `Livewire::test` sets the public property before mount runs; if mount overrides it, set the session instead: `session(['region_code' => 1703]);` in `beforeEach`. Use whichever the existing `tests/Feature/Http/TasksPageTest.php` uses for consistency — inspect it and match.

- [ ] **Step 2: Run it — verify it fails**

Run: `php artisan test --filter=TasksBoardProgressTest`
Expected: FAIL (card does not render these values yet).

- [ ] **Step 3: Enrich the task card**

In `backend/resources/views/livewire/tasks-board.blade.php`, replace the `<article class="task-card" ...>` block (the `@forelse` body) with this version. It adds a status badge, cadence chip, a plan→actual→% line, and a reused `.progress` bar (no new CSS):

```blade
                    @forelse($tasks as $task)
                        @php
                            $pct = $task->headline_pct !== null ? (float) $task->headline_pct : null;
                            $statusChip = $task->status === 'done' ? 'green' : 'grey';
                            $statusLabel = $task->status === 'done' ? 'Бажарилди' : 'Бажарилмаган';
                            $cadenceLabel = $task->cadence === 'monthly' ? 'Ойлик' : ($task->cadence === 'quarterly' ? 'Чорак' : '');
                            $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ' '), '0'), '.');
                        @endphp
                        <article class="task-card" data-task-id="{{ $task->id }}">
                            <span class="task-num">{{ $loop->iteration }}</span>
                            <div class="task-body">
                                <strong>{{ $task->title }}</strong>
                                <div class="task-meta">
                                    <span>{{ $task->deadline_text }}</span>
                                    <span>{{ $task->districts->count() ? $task->districts->count() . ' туман/шаҳар' : 'вилоят' }}</span>
                                    <span>{{ $task->module?->label ?? $task->section_label }}</span>
                                    @if($cadenceLabel)<span>{{ $cadenceLabel }}</span>@endif
                                    @if($task->latest_period)<span>Сўнгги: {{ $task->latest_period }}</span>@endif
                                </div>
                                <div class="task-meta">
                                    <span>Режа: <b>{{ $fmt($task->headline_plan) }}</b> {{ $task->headline_unit }}</span>
                                    <span>Амалда: <b>{{ $fmt($task->headline_actual) }}</b> {{ $task->headline_unit }}</span>
                                    <span>Бажарилиши: <b>{{ $pct === null ? '—' : round($pct) . '%' }}</b></span>
                                </div>
                                @if($pct !== null)
                                    <div class="progress"><i style="--w:{{ max(0, min(100, $pct)) }}%;--c:var(--task-{{ $task->status === 'done' ? 'green' : 'blue' }})"></i></div>
                                @endif
                            </div>
                            <div class="task-chips">
                                <span class="chip {{ $statusChip }}">{{ $statusLabel }}</span>
                                <span class="chip grey">{{ $task->kind === 'kpi' ? 'KPI' : 'Чора-тадбир' }}</span>
                                @if($task->indicator)
                                    <span class="chip blue">{{ $task->indicator->label_short }}</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="muted">Бу филтр бўйича топшириқ топилмади.</p>
                    @endforelse
```

- [ ] **Step 4: Run the test — verify it passes**

Run: `php artisan test --filter=TasksBoardProgressTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/resources/views/livewire/tasks-board.blade.php \
        backend/tests/Feature/Tasks/TasksBoardProgressTest.php
git commit -m "feat(tasks): board cards show plan/actual/% + status + cadence"
```

---

## Task 9: District drilldown — enrich "Туман топшириқлари" panel

`RegionProfile::tasksForDistrict()` already returns the district's tasks (via `task_districts`) with headline columns on each model. Only `profile/bottom.blade.php` needs enriching.

**Files:**
- Modify: `backend/resources/views/livewire/profile/bottom.blade.php`
- Test: `backend/tests/Feature/Tasks/ProfileDistrictTasksTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Tasks/ProfileDistrictTasksTest.php

use App\Livewire\RegionProfile;
use App\Models\District;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('district panel shows task plan/actual/% and status chip', function () {
    session(['region_code' => 1703]); // RegionProfile::mount() reads CurrentRegion::code()
    $district = District::where('region_code', 1703)->first();

    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1', 'title' => 'Йирик корхона',
        'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'done',
        'headline_unit' => 'дона', 'headline_plan' => 6, 'headline_actual' => 6,
        'headline_pct' => 100, 'latest_period' => '2026-Q1',
    ]);
    $task->districts()->sync([$district->id]);

    Livewire::test(RegionProfile::class)
        ->set('districtCode', (string) $district->code)
        ->assertSee('Йирик корхона')
        ->assertSee('Режа')
        ->assertSee('100%')
        ->assertSee('Бажарилди');
});
```

> Match how `tests/Feature/Livewire/RegionProfileTest.php` sets region/district context (session vs property). Inspect it and mirror in `beforeEach` if needed.

- [ ] **Step 2: Run it — verify it fails**

Run: `php artisan test --filter=ProfileDistrictTasksTest`
Expected: FAIL (panel does not render plan/%/status yet).

- [ ] **Step 3: Enrich `profile/bottom.blade.php`**

Replace the `@forelse($tasks as $task) ... @endforelse` body in `backend/resources/views/livewire/profile/bottom.blade.php` with:

```blade
            @forelse($tasks as $task)
                @php
                    $pct = $task->headline_pct !== null ? (float) $task->headline_pct : null;
                    $tStatusChip = $task->status === 'done' ? 'green' : 'grey';
                    $tStatusLabel = $task->status === 'done' ? 'Бажарилди' : 'Бажарилмаган';
                    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ' '), '0'), '.');
                @endphp
                <article class="profile-task">
                    <p class="profile-task-title">{{ $task->title }}</p>
                    <div class="task-meta">
                        <span>Режа: <b>{{ $fmt($task->headline_plan) }}</b> {{ $task->headline_unit }}</span>
                        <span>Амалда: <b>{{ $fmt($task->headline_actual) }}</b> {{ $task->headline_unit }}</span>
                        <span class="chip {{ $tStatusChip }}">{{ $tStatusLabel }}{{ $pct !== null ? ' · ' . round($pct) . '%' : '' }}</span>
                    </div>
                    @if($task->deadline_text)
                        <span class="profile-task-deadline">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            {{ $task->deadline_text }}
                        </span>
                    @endif
                </article>
            @empty
                <p class="muted">Бу туман бўйича топшириқ топилмади.</p>
            @endforelse
```

- [ ] **Step 4: Run the test — verify it passes**

Run: `php artisan test --filter=ProfileDistrictTasksTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/resources/views/livewire/profile/bottom.blade.php \
        backend/tests/Feature/Tasks/ProfileDistrictTasksTest.php
git commit -m "feat(tasks): district panel shows task plan/actual/% + status"
```

---

## Task 10: Retire legacy DOCX importer + rewire orchestrator + full suite

**Files:**
- Delete: `backend/app/Console/Commands/ImportTasks.php`
- Delete: `backend/tests/Feature/Import/ImportTasksCommandTest.php`
- Modify: `backend/app/Console/Commands/ImportAllRegionsCommand.php`

- [ ] **Step 1: Rewire the orchestrator to the new command**

In `backend/app/Console/Commands/ImportAllRegionsCommand.php`:

1. Add a period option to `$signature` (next to `--no-tasks`):

```php
        {--period= : Report period (e.g. 2026-Q1) for import:task-progress; required to import tasks}
```

2. Replace the task-import block (the lines around the `Artisan::call('import:tasks', ['region' => $slug])` call, currently ~136–151). Replace from `if ($this->option('no-tasks')) {` through its closing `}` with:

```php
        $period = (string) $this->option('period');
        if ($this->option('no-tasks') || $period === '') {
            $row['tasks'] = 'skipped';
        } else {
            try {
                $tasksExit = Artisan::call('import:task-progress', [
                    '--region' => $slug,
                    '--period' => $period,
                ]);
                if ($tasksExit === 0) {
                    $row['tasks'] = 'ok';
                    $row['tasks_count'] = Task::where('region_code', $regionCode)->count();
                } else {
                    $row['tasks'] = 'fail';
                    $row['note'] = trim($row['note'] . " import:task-progress exit={$tasksExit}");
                }
            } catch (\Throwable $e) {
                $row['tasks'] = 'error';
                $row['note'] = trim($row['note'] . ' tasks: ' . $this->truncate($e->getMessage(), 80));
            }
        }
```

3. Update `$description` to read `Run import:region + import:promote + import:task-progress for every region.`

- [ ] **Step 2: Delete the legacy command + its test**

```bash
git rm backend/app/Console/Commands/ImportTasks.php \
       backend/tests/Feature/Import/ImportTasksCommandTest.php
```

> `TasksTaxonomy::REGION_FILENAMES` was only used by `ImportTasks`; leave the constant in place (harmless) unless a later cleanup removes it.

- [ ] **Step 3: Run the full suite**

Run: `php artisan test`
Expected: PASS — no references to `import:tasks`/`ImportTasks` remain; all new task tests green; previously-passing tests still green.

If any pre-existing test referenced `import:tasks` or expected docx-built tasks, update it to the new command (search: `grep -rn "import:tasks\|ImportTasks" backend/tests backend/app`).

- [ ] **Step 4: Commit**

```bash
git add backend/app/Console/Commands/ImportAllRegionsCommand.php
git commit -m "refactor(tasks): retire DOCX import:tasks, route orchestrator to import:task-progress"
```

---

## Task 11: End-to-end smoke against the real workbook (manual)

Not a unit test — a manual verification that the parser handles the real file's full messiness (merged cells, whitespace variants, sentinels, 97 tasks × 14 regions).

- [ ] **Step 1: Dry-run parse the real file**

Run (from `backend/`):
```bash
php artisan import:task-progress --period=2026-Q1 --dry-run
```
Expected: `Parsed N task definitions.` with N in the ~90–97 range, exit 0, no layout exception.

- [ ] **Step 2: Import into the dev DB and spot-check**

Run:
```bash
php artisan migrate
php artisan db:seed   # if not already seeded (regions/districts/modules/indicators/2026)
php artisan import:task-progress --period=2026-Q1
php artisan tinker --execute="echo App\Models\Task::count().' tasks; '.App\Models\TaskProgress::count().' progress rows';"
```
Expected: tasks across all 14 regions (~90×14 minus skipped 'х' regions), progress rows ≥ tasks. Unmatched-executor warnings, if any, are acceptable (logged, non-fatal) — note them for a future district alt-label fix.

- [ ] **Step 3: Visual check**

Run `composer dev` (or `php artisan serve` + `npm run dev`), open `/tasks`, switch regions via the region switcher, confirm cards show plan/actual/%/status; open `/districts`, pick a district → `/profile`, confirm the "Туман топшириқлари" panel lists the district's tasks with progress.

- [ ] **Step 4: (no commit — verification only).** Record findings; if the parser mis-handles a real-file pattern, write a failing test reproducing it (extend `TaskWorkbookFixture` or add a targeted case) and fix before closing out.

---

## Self-review notes (already reconciled)

- **Spec coverage:** schema (Task 1), models (Task 2), status/period logic (Task 3), executor→district (Task 4), importer parse (Tasks 5–6) + command with history/idempotency/ImportRun (Task 7), board UI (Task 8), district panel (Task 9), DOCX retirement (Task 10), real-file smoke (Task 11). Districts comparison grid needs no task — it already renders done/total from `status` (verified in recon).
- **No per-district numbers:** by design the district panel shows the *region* task's headline values; the district is linked via `task_districts` only. Surfaced honestly per spec §6b.
- **Headline/latest_period:** the importer always sets `latest_period` to the imported period (latest import wins). Monthly files must be imported in chronological order — documented limitation, acceptable under the binary model.
- **Type consistency:** `TaskStatus::statusFor(?float): string`, `TaskPeriod::{cadenceFor,periodType,yearFromPeriod,deadlineToPeriodCode}`, `TaskExecutorResolver::districtIds(string, iterable, array&): array`, `TaskWorkbookParser::parse(string): array` (shape fixed in Task 6 docblock and consumed verbatim in Task 7), `TaskProgress` fillable matches the migration columns, `Task` headline_* columns match migration + blades.
