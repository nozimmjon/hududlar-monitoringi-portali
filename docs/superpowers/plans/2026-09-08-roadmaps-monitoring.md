# Road-map monitoring (indicator lines, xlsx round trip, layout-B page) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every water road-map measure indicator lines (plan/actual/%) filled by the regions through an xlsx we generate, derive a status per measure, and redesign `/roadmaps` as a card-per-measure monitoring page (layout B).

**Architecture:** Two new tables (`roadmap_measure_lines`, `roadmap_line_progress`) hang off `roadmap_measures`, which gains denormalised `status/pct/latest_period/lines_*` columns rebuilt by one `MeasureRecomputer`. Three Artisan commands form the loop: `roadmap:template` writes the document-shaped xlsx (stored lines, else heuristic suggestions), `import:roadmap-progress` reads it back (line definitions **and** actuals, idempotent), `roadmaps:recompute` rebuilds the denormalised columns. The Livewire page reads only stored columns plus the lines/progress rows and renders the new `wr-` card design.

**Tech Stack:** Laravel 12, Livewire 3 + Alpine, PostgreSQL, PhpSpreadsheet 5.9 (first xlsx *writer* in the repo), Pest 3. Spec: `docs/superpowers/specs/2026-09-08-roadmaps-monitoring-design.md`.

**Conventions you must follow:**
- Run everything from `backend/`. Tests: `php artisan test --filter=<Name>` (PostgreSQL must be running; the suite uses `hududlar_monitoringi_test` from `phpunit.xml`). If another `php artisan test` may be running concurrently, prefix with `$env:DB_DATABASE='hududlar_test_<something-unique>'` in PowerShell.
- Feature tests start with `uses(RefreshDatabase::class);` and call `$this->seed();` explicitly. Unit tests under `tests/Unit/Roadmaps/` are pure `expect()` closures (no TestCase binding needed).
- UI strings are Cyrillic Uzbek. Commit messages: Conventional Commits, scope `roadmaps`, and end every commit message with the two attribution lines shown in Task 1 Step 6.
- `public/css/portal.css` is hand-maintained (no build step); append to the existing `wr-` block.
- Never commit anything under `data/`.

---

## File map

| File | Responsibility |
| --- | --- |
| `database/migrations/2026_09_08_000001_create_roadmap_measure_lines_table.php` | indicator definitions table |
| `database/migrations/2026_09_08_000002_create_roadmap_line_progress_table.php` | per-period actuals table |
| `database/migrations/2026_09_08_000003_add_monitoring_columns_to_roadmap_measures_table.php` | `latest_period/status/pct/lines_total/lines_done` |
| `app/Models/RoadmapMeasureLine.php`, `app/Models/RoadmapLineProgress.php` | models; `RoadmapMeasure::lines()` added |
| `app/Support/Roadmaps/RoadmapPeriod.php` | period regex, month index, type, label, latest |
| `app/Support/Roadmaps/RoadmapDeadline.php` | deadline text → `YYYY-MM`, reached?, months-left label |
| `app/Support/Roadmaps/RoadmapKey.php`, `app/Support/Roadmaps/Roman.php` | natural key make/parse/canonical; Roman numerals for section headings |
| `app/Support/Roadmaps/LineSuggester.php` | quantity heuristic → suggested lines (pure) |
| `app/Services/Roadmaps/MeasureRecomputer.php` | pure `aggregate()` + persisting `recompute()` |
| `app/Services/Roadmaps/RoadmapTemplateWriter.php` | builds the xlsx `Spreadsheet` |
| `app/Services/Roadmaps/RoadmapProgressReader.php` | xlsx → blocks of line rows (pure over a loaded workbook) |
| `app/Console/Commands/RoadmapTemplate.php` | `roadmap:template` |
| `app/Console/Commands/ImportRoadmapProgress.php` | `import:roadmap-progress` |
| `app/Console/Commands/RecomputeRoadmapMeasures.php` | `roadmaps:recompute` |
| `app/Console/Commands/ImportRoadmap.php` | changed: upsert measures by position |
| `app/Support/Roadmaps/MeasureDisplay.php` | page view helpers (percent cap, tiers, chips, sparkline points) |
| `app/Livewire/RoadmapsPage.php`, `resources/views/livewire/roadmaps-page.blade.php` | status filter, aggregates, layout-B markup |
| `public/css/portal.css` (`wr-` block) | new card/rail styles |
| `docs/roadmap-monitoring.md`, `docs/roadmap-import.md`, `../CLAUDE.md` | runbooks |

---

### Task 1: Schema and models

**Files:**
- Create: `database/migrations/2026_09_08_000001_create_roadmap_measure_lines_table.php`
- Create: `database/migrations/2026_09_08_000002_create_roadmap_line_progress_table.php`
- Create: `database/migrations/2026_09_08_000003_add_monitoring_columns_to_roadmap_measures_table.php`
- Create: `app/Models/RoadmapMeasureLine.php`, `app/Models/RoadmapLineProgress.php`
- Modify: `app/Models/RoadmapMeasure.php`
- Test: `tests/Feature/Roadmaps/RoadmapSchemaTest.php` (append)

- [ ] **Step 1: Append failing schema tests**

Append to the end of `tests/Feature/Roadmaps/RoadmapSchemaTest.php`:

```php
test('monitoring tables and columns exist', function () {
    expect(Schema::hasColumns('roadmap_measure_lines', ['id', 'roadmap_measure_id', 'line_no', 'label', 'unit', 'plan_value']))->toBeTrue();
    expect(Schema::hasColumns('roadmap_line_progress', [
        'id', 'roadmap_measure_line_id', 'report_period', 'period_type', 'actual_value', 'pct_of_plan', 'note', 'reported_at',
    ]))->toBeTrue();
    expect(Schema::hasColumns('roadmap_measures', ['latest_period', 'status', 'pct', 'lines_total', 'lines_done']))->toBeTrue();
});

test('a measure owns ordered lines, a line owns progress, and deletes cascade down', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $m = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1]);
    expect($m->status)->toBe('in_progress');
    expect((int) $m->lines_total)->toBe(0);

    $m->lines()->create(['line_no' => 2, 'label' => 'Ички канал', 'unit' => 'км', 'plan_value' => 33]);
    $l1 = $m->lines()->create(['line_no' => 1, 'label' => 'Хўжаликлараро канал', 'unit' => 'км', 'plan_value' => 7.8]);
    $l1->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 7.8, 'pct_of_plan' => 100, 'note' => 'тайёр']);

    $m->refresh();
    expect($m->lines->pluck('line_no')->all())->toBe([1, 2]);
    expect($m->lines->first()->progress->first()->note)->toBe('тайёр');
    expect(App\Models\RoadmapLineProgress::first()->line->label)->toBe('Хўжаликлараро канал');

    $m->delete();
    expect(App\Models\RoadmapMeasureLine::count())->toBe(0);
    expect(App\Models\RoadmapLineProgress::count())->toBe(0);
});

test('a line position and a progress period are unique', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $m = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1]);
    $line = DB::transaction(fn () => $m->lines()->create(['line_no' => 1, 'label' => 'a']));
    expect(fn () => DB::transaction(fn () => $m->lines()->create(['line_no' => 1, 'label' => 'b'])))->toThrow(QueryException::class);

    DB::transaction(fn () => $line->progress()->create(['report_period' => '2026-09', 'period_type' => 'month']));
    expect(fn () => DB::transaction(fn () => $line->progress()->create(['report_period' => '2026-09', 'period_type' => 'month'])))->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=RoadmapSchemaTest`
Expected: the three new tests FAIL (`hasColumns` false / undefined method `lines()`).

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_09_08_000001_create_roadmap_measure_lines_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roadmap_measure_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_measure_id')->constrained('roadmap_measures')->cascadeOnDelete();
            $table->smallInteger('line_no');                       // 1..n, row order inside the measure block of the template
            $table->string('label', 255);                          // «Насос агрегати таъмирланди»
            $table->string('unit', 48)->nullable();                // та | км | минг га | % | млрд сўм …
            $table->decimal('plan_value', 20, 6)->nullable();
            $table->timestamps();

            $table->unique(['roadmap_measure_id', 'line_no'], 'uq_roadmap_measure_lines_pos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_measure_lines');
    }
};
```

`database/migrations/2026_09_08_000002_create_roadmap_line_progress_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roadmap_line_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_measure_line_id')->constrained('roadmap_measure_lines')->cascadeOnDelete();
            $table->string('report_period', 16);                   // '2026-09' | '2026-Q3'
            $table->string('period_type', 8);                      // month | quarter
            $table->decimal('actual_value', 20, 6)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();     // computed at import, never read from the file
            $table->string('note', 500)->nullable();               // «Изоҳ»
            $table->date('reported_at')->nullable();
            $table->timestamps();

            // Also serves the (line, period) lookups — no separate index needed.
            $table->unique(['roadmap_measure_line_id', 'report_period'], 'uq_roadmap_line_progress_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_line_progress');
    }
};
```

`database/migrations/2026_09_08_000003_add_monitoring_columns_to_roadmap_measures_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roadmap_measures', function (Blueprint $table) {
            $table->string('latest_period', 16)->nullable();                 // max period among the measure's progress rows
            $table->string('status', 16)->default('in_progress');           // in_progress | done | open
            $table->decimal('pct', 6, 2)->nullable();                        // mean of capped line percents, latest period
            $table->smallInteger('lines_total')->default(0);                 // lines with a plan
            $table->smallInteger('lines_done')->default(0);                  // of those, ≥100 % in the latest period
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_measures', function (Blueprint $table) {
            $table->dropColumn(['latest_period', 'status', 'pct', 'lines_total', 'lines_done']);
        });
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/RoadmapMeasureLine.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoadmapMeasureLine extends Model
{
    protected $fillable = ['roadmap_measure_id', 'line_no', 'label', 'unit', 'plan_value'];

    protected $casts = [
        'roadmap_measure_id' => 'integer',
        'line_no'            => 'integer',
    ];

    public function measure(): BelongsTo
    {
        return $this->belongsTo(RoadmapMeasure::class, 'roadmap_measure_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(RoadmapLineProgress::class, 'roadmap_measure_line_id');
    }
}
```

`app/Models/RoadmapLineProgress.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadmapLineProgress extends Model
{
    protected $table = 'roadmap_line_progress';

    protected $fillable = [
        'roadmap_measure_line_id', 'report_period', 'period_type', 'actual_value', 'pct_of_plan', 'note', 'reported_at',
    ];

    protected $casts = [
        'roadmap_measure_line_id' => 'integer',
        'reported_at'             => 'date',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(RoadmapMeasureLine::class, 'roadmap_measure_line_id');
    }
}
```

In `app/Models/RoadmapMeasure.php` add `HasMany` import, the five columns to `$fillable`, casts and the relation:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
// $fillable: append
        'latest_period', 'status', 'pct', 'lines_total', 'lines_done',
// $casts: append
        'lines_total' => 'integer',
        'lines_done'  => 'integer',
// after district():
    /** Indicator definitions in template order. */
    public function lines(): HasMany
    {
        return $this->hasMany(RoadmapMeasureLine::class)->orderBy('line_no');
    }
```

- [ ] **Step 5: Migrate the dev DB and run the tests**

Run: `php artisan migrate --force` (dev `.env` says `APP_ENV=production`), then `php artisan test --filter=RoadmapSchemaTest`
Expected: all RoadmapSchemaTest tests PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_08_00000*_*.php app/Models/RoadmapMeasureLine.php app/Models/RoadmapLineProgress.php app/Models/RoadmapMeasure.php tests/Feature/Roadmaps/RoadmapSchemaTest.php
git commit -m "feat(roadmaps): indicator lines + line progress schema, monitoring columns on measures

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 2: `RoadmapPeriod` and `RoadmapDeadline`

**Files:**
- Create: `app/Support/Roadmaps/RoadmapPeriod.php`, `app/Support/Roadmaps/RoadmapDeadline.php`
- Test: `tests/Unit/Roadmaps/RoadmapPeriodTest.php`, `tests/Unit/Roadmaps/RoadmapDeadlineTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Roadmaps/RoadmapPeriodTest.php`:

```php
<?php

use App\Support\Roadmaps\RoadmapPeriod;

test('validates month and quarter periods only', function () {
    expect(RoadmapPeriod::isValid('2026-09'))->toBeTrue();
    expect(RoadmapPeriod::isValid('2026-Q3'))->toBeTrue();
    expect(RoadmapPeriod::isValid('2026-13'))->toBeFalse();
    expect(RoadmapPeriod::isValid('2026-H1'))->toBeFalse();
    expect(RoadmapPeriod::isValid('2026-9'))->toBeFalse();
});

test('month index orders months and quarters on one axis', function () {
    expect(RoadmapPeriod::monthIndex('2026-09'))->toBe(2026 * 12 + 9);
    expect(RoadmapPeriod::monthIndex('2026-Q3'))->toBe(2026 * 12 + 9);
    expect(RoadmapPeriod::monthIndex('2027-01'))->toBeGreaterThan(RoadmapPeriod::monthIndex('2026-12'));
    expect(fn () => RoadmapPeriod::monthIndex('nope'))->toThrow(InvalidArgumentException::class);
});

test('type, label, latest and fromYearMonth', function () {
    expect(RoadmapPeriod::type('2026-09'))->toBe('month');
    expect(RoadmapPeriod::type('2026-Q3'))->toBe('quarter');
    expect(RoadmapPeriod::label('2026-09'))->toBe('2026 йил сентябрь');
    expect(RoadmapPeriod::label('2026-Q3'))->toBe('2026 йил III чорак');
    expect(RoadmapPeriod::label(null))->toBe('ҳисобот йўқ');
    expect(RoadmapPeriod::latest(['2026-07', '2026-09', '2026-08']))->toBe('2026-09');
    expect(RoadmapPeriod::latest([]))->toBeNull();
    expect(RoadmapPeriod::fromYearMonth(2026, 4))->toBe('2026-04');
});
```

`tests/Unit/Roadmaps/RoadmapDeadlineTest.php`:

```php
<?php

use App\Support\Roadmaps\RoadmapDeadline;

test('deadline month is parsed from the text, last month of a range wins', function () {
    expect(RoadmapDeadline::month('2026 йил декабрь', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('2026 йил апрель-октябрь', 2026))->toBe('2026-10');
    expect(RoadmapDeadline::month('2026 йил', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('июн', 2026))->toBe('2026-06');
    expect(RoadmapDeadline::month('2027 йил март', 2026))->toBe('2027-03');   // year in text overrides
    expect(RoadmapDeadline::month('3-чорак', 2026))->toBe('2026-09');
    expect(RoadmapDeadline::month('йил давомида', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month(null, 2026))->toBe('2026-12');
});

test('reached compares the report period with the deadline month', function () {
    expect(RoadmapDeadline::reached('2026-09', '2026 йил декабрь', 2026))->toBeFalse();
    expect(RoadmapDeadline::reached('2026-12', '2026 йил декабрь', 2026))->toBeTrue();
    expect(RoadmapDeadline::reached('2026-Q4', '2026 йил декабрь', 2026))->toBeTrue();
    expect(RoadmapDeadline::reached('2026-11', '2026 йил апрель-октябрь', 2026))->toBeTrue();
});

test('months left and the «…гача» label', function () {
    expect(RoadmapDeadline::monthsLeft('2026 йил декабрь', 2026, '2026-09'))->toBe(3);
    expect(RoadmapDeadline::monthsLeft('2026 йил апрель-октябрь', 2026, '2026-11'))->toBe(-1);
    expect(RoadmapDeadline::untilLabel('2026-12'))->toBe('декабргача');
    expect(RoadmapDeadline::untilLabel('2026-04'))->toBe('апрелгача');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter="RoadmapPeriodTest|RoadmapDeadlineTest"`
Expected: FAIL — class `App\Support\Roadmaps\RoadmapPeriod` not found.

- [ ] **Step 3: Implement**

`app/Support/Roadmaps/RoadmapPeriod.php`:

```php
<?php

namespace App\Support\Roadmaps;

use App\Support\TaskPeriod;
use InvalidArgumentException;

/** Report periods of the road-map monitoring: months ('2026-09') and quarters ('2026-Q3'). */
final class RoadmapPeriod
{
    public const REGEX = '/^(\d{4})-(0[1-9]|1[0-2]|Q[1-4])$/';

    public static function isValid(?string $period): bool
    {
        return $period !== null && preg_match(self::REGEX, $period) === 1;
    }

    /** 'month' | 'quarter' */
    public static function type(string $period): string
    {
        return str_contains($period, 'Q') ? 'quarter' : 'month';
    }

    /** One axis for months and quarters: YYYY*12 + MM; a quarter counts as its last month. */
    public static function monthIndex(string $period): int
    {
        if (preg_match(self::REGEX, $period, $m) !== 1) {
            throw new InvalidArgumentException("Bad period «{$period}» — expected YYYY-MM or YYYY-Qn.");
        }
        $mm = $m[2][0] === 'Q' ? ((int) $m[2][1]) * 3 : (int) $m[2];

        return (int) $m[1] * 12 + $mm;
    }

    public static function fromYearMonth(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    /** «2026 йил сентябрь» / «2026 йил III чорак» (same wording as the tasks board). */
    public static function label(?string $period): string
    {
        return $period === null ? 'ҳисобот йўқ' : TaskPeriod::reportPeriodLabel($period);
    }

    /** @param iterable<string> $periods */
    public static function latest(iterable $periods): ?string
    {
        $best = null;
        $bestIdx = -1;
        foreach ($periods as $p) {
            $idx = self::monthIndex($p);
            if ($idx > $bestIdx || ($idx === $bestIdx && strcmp($p, (string) $best) > 0)) {
                $best = $p;
                $bestIdx = $idx;
            }
        }

        return $best;
    }
}
```

`app/Support/Roadmaps/RoadmapDeadline.php`:

```php
<?php

namespace App\Support\Roadmaps;

/**
 * «Муддати» cell → a deadline month. The documents write «2026 йил декабрь»,
 * «2026 йил апрель-октябрь» (a range: the last month is the deadline), rarely a
 * quarter; anything unreadable means year-end.
 */
final class RoadmapDeadline
{
    /** Lower-cased stems that start every spelling seen (декабрь / декабр / дек). */
    private const STEMS = [
        'янв' => 1, 'фев' => 2, 'мар' => 3, 'апр' => 4, 'май' => 5, 'июн' => 6,
        'июл' => 7, 'авг' => 8, 'сен' => 9, 'окт' => 10, 'ноя' => 11, 'дек' => 12,
    ];

    /** Dative forms for the countdown chip («декабргача»). */
    private const UNTIL = [
        1 => 'январгача', 2 => 'февралгача', 3 => 'мартгача', 4 => 'апрелгача', 5 => 'майгача', 6 => 'июнгача',
        7 => 'июлгача', 8 => 'августгача', 9 => 'сентябргача', 10 => 'октябргача', 11 => 'ноябргача', 12 => 'декабргача',
    ];

    /** @return string 'YYYY-MM' */
    public static function month(?string $deadlineText, int $year): string
    {
        $t = mb_strtolower(trim((string) $deadlineText));
        if (preg_match('/(?<!\d)(20\d{2})(?!\d)/', $t, $y) === 1) {
            $year = (int) $y[1];
        }

        $month = 12;
        if (preg_match_all('/(?<!\p{L})(янв|фев|мар|апр|май|июн|июл|авг|сен|окт|ноя|дек)/u', $t, $mm) > 0) {
            $month = self::STEMS[end($mm[1])];
        } elseif (preg_match('/(?<!\d)([1-4])\s*-?\s*чорак/u', $t, $q) === 1) {
            $month = ((int) $q[1]) * 3;
        }

        return RoadmapPeriod::fromYearMonth($year, $month);
    }

    /** True once the report period is the deadline month or later. */
    public static function reached(string $reportPeriod, ?string $deadlineText, int $year): bool
    {
        return RoadmapPeriod::monthIndex($reportPeriod) >= RoadmapPeriod::monthIndex(self::month($deadlineText, $year));
    }

    /** Deadline month minus the given 'YYYY-MM' (negative = overdue). */
    public static function monthsLeft(?string $deadlineText, int $year, string $todayPeriod): int
    {
        return RoadmapPeriod::monthIndex(self::month($deadlineText, $year)) - RoadmapPeriod::monthIndex($todayPeriod);
    }

    public static function untilLabel(string $deadlineMonth): string
    {
        return self::UNTIL[(int) substr($deadlineMonth, 5, 2)];
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter="RoadmapPeriodTest|RoadmapDeadlineTest"`
Expected: PASS (3 + 3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Roadmaps/RoadmapPeriod.php app/Support/Roadmaps/RoadmapDeadline.php tests/Unit/Roadmaps/RoadmapPeriodTest.php tests/Unit/Roadmaps/RoadmapDeadlineTest.php
git commit -m "feat(roadmaps): report-period axis and deadline-month parser

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 3: `RoadmapKey` (natural key) and `Roman`

**Files:**
- Create: `app/Support/Roadmaps/RoadmapKey.php`, `app/Support/Roadmaps/Roman.php`
- Modify: `app/Livewire/RoadmapsPage.php` (`roman()` delegates)
- Test: `tests/Unit/Roadmaps/RoadmapKeyTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Roadmaps/RoadmapKeyTest.php`:

```php
<?php

use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\Roman;

test('makes and parses the natural key; region-level rows use district 0', function () {
    expect(RoadmapKey::make(1733, 5, 1733208, 3))->toBe('1733-5-1733208-3');
    expect(RoadmapKey::make(1733, 1, null, 2))->toBe('1733-1-0-2');
    expect(RoadmapKey::parse('1733-5-1733208-3'))->toBe(['region' => 1733, 'section' => 5, 'district' => 1733208, 'seq' => 3]);
    expect(RoadmapKey::parse('1733-1-0-2'))->toBe(['region' => 1733, 'section' => 1, 'district' => 0, 'seq' => 2]);
    expect(RoadmapKey::isKey('1733-5-1733208-3'))->toBeTrue();
    expect(RoadmapKey::isKey('Калит'))->toBeFalse();
    expect(RoadmapKey::isKey(null))->toBeFalse();
    expect(RoadmapKey::isKey('1733-5-1733208'))->toBeFalse();
    expect(fn () => RoadmapKey::parse('x'))->toThrow(InvalidArgumentException::class);
});

test('roman numerals', function () {
    expect(Roman::of(1))->toBe('I');
    expect(Roman::of(4))->toBe('IV');
    expect(Roman::of(6))->toBe('VI');
    expect(Roman::of(9))->toBe('IX');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=RoadmapKeyTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

`app/Support/Roadmaps/Roman.php`:

```php
<?php

namespace App\Support\Roadmaps;

final class Roman
{
    public static function of(int $n): string
    {
        $out = '';
        foreach ([10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $v => $r) {
            while ($n >= $v) {
                $out .= $r;
                $n   -= $v;
            }
        }

        return $out;
    }
}
```

`app/Support/Roadmaps/RoadmapKey.php`:

```php
<?php

namespace App\Support\Roadmaps;

use InvalidArgumentException;

/**
 * Stable identity of a measure inside the xlsx files:
 * {region SOATO}-{section no}-{district SOATO | 0}-{seq no}, e.g. 1733-5-1733208-3.
 * Survives a docx re-import (DB ids do not).
 */
final class RoadmapKey
{
    public const REGEX = '/^(\d{4})-(\d{1,2})-(\d+)-(\d{1,3})$/';

    public static function make(int $regionCode, int $sectionNo, ?int $districtCode, int $seqNo): string
    {
        return $regionCode . '-' . $sectionNo . '-' . ($districtCode ?? 0) . '-' . $seqNo;
    }

    public static function isKey(mixed $value): bool
    {
        return is_string($value) && preg_match(self::REGEX, trim($value)) === 1;
    }

    /** @return array{region:int, section:int, district:int, seq:int} district 0 = region-level */
    public static function parse(string $key): array
    {
        if (preg_match(self::REGEX, trim($key), $m) !== 1) {
            throw new InvalidArgumentException("Bad road-map key «{$key}».");
        }

        return ['region' => (int) $m[1], 'section' => (int) $m[2], 'district' => (int) $m[3], 'seq' => (int) $m[4]];
    }
}
```

In `app/Livewire/RoadmapsPage.php` replace the body of `roman()` with a delegation (keep the method — the blade and tests call it):

```php
    public static function roman(int $n): string
    {
        return \App\Support\Roadmaps\Roman::of($n);
    }
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter="RoadmapKeyTest|RoadmapsPageTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Roadmaps/RoadmapKey.php app/Support/Roadmaps/Roman.php app/Livewire/RoadmapsPage.php tests/Unit/Roadmaps/RoadmapKeyTest.php
git commit -m "feat(roadmaps): natural measure key for the xlsx round trip

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 4: `MeasureRecomputer` (status / percent rules)

**Files:**
- Create: `app/Services/Roadmaps/MeasureRecomputer.php`
- Test: `tests/Unit/Roadmaps/MeasureRecomputerTest.php`, `tests/Feature/Roadmaps/MeasureRecomputerPersistTest.php`

- [ ] **Step 1: Write the failing unit test (pure rules)**

`tests/Unit/Roadmaps/MeasureRecomputerTest.php`:

```php
<?php

use App\Services\Roadmaps\MeasureRecomputer;

function rmLine(?float $plan, ?float $actual): array
{
    return ['plan' => $plan, 'actual' => $actual, 'pct' => MeasureRecomputer::pctOfPlan($plan, $actual)];
}

test('pct of plan', function () {
    expect(MeasureRecomputer::pctOfPlan(24, 14))->toBeNumericallyClose(58.3333, 1e-3);
    expect(MeasureRecomputer::pctOfPlan(null, 14))->toBeNull();
    expect(MeasureRecomputer::pctOfPlan(24, null))->toBeNull();
    expect(MeasureRecomputer::pctOfPlan(0, 5))->toBeNull();
    expect(MeasureRecomputer::pctOfPlan(0.01, 20000))->toBe(999999.9999);   // clamped to what numeric(10,4) can hold
});

test('nothing reported → in_progress, no percent, counts still truthful', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(24, null), rmLine(1, 0.0)], true);
    expect($agg['status'])->toBe('in_progress');
    expect($agg['reported'])->toBeFalse();
    expect($agg['pct'])->toBeNull();
    expect($agg['lines_total'])->toBe(2);
    expect($agg['lines_done'])->toBe(0);
});

test('every planned line at 100 % → done; percent is the mean of capped line percents', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(7.8, 7.8), rmLine(33, 40)], false);
    expect($agg['status'])->toBe('done');
    expect($agg['pct'])->toBe(100.0);
    expect($agg['lines_done'])->toBe(2);

    $partial = MeasureRecomputer::aggregate([rmLine(24, 14), rmLine(1, 1), rmLine(3, 3)], true);
    expect($partial['pct'])->toBeNumericallyClose(86.11, 0.01);   // (58.33 + 100 + 100) / 3, not the done-share 66.7
    expect($partial['lines_done'])->toBe(2);
});

test('below plan → open once the deadline is reached, else in_progress', function () {
    expect(MeasureRecomputer::aggregate([rmLine(24, 14)], false)['status'])->toBe('in_progress');
    expect(MeasureRecomputer::aggregate([rmLine(24, 14)], true)['status'])->toBe('open');
});

test('lines without a plan are informational: never counted; a missing progress row counts as 0 in the mean', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(null, 5), rmLine(10, 10), ['plan' => 10, 'actual' => null, 'pct' => null]], true);
    expect($agg['lines_total'])->toBe(2);
    expect($agg['lines_done'])->toBe(1);
    expect($agg['pct'])->toBe(50.0);
    expect($agg['status'])->toBe('open');
});

test('no planned lines at all → in_progress and null percent even when something is reported', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(null, 5)], true);
    expect($agg['status'])->toBe('in_progress');
    expect($agg['pct'])->toBeNull();
});
```

- [ ] **Step 2: Write the failing persistence test**

`tests/Feature/Roadmaps/MeasureRecomputerPersistTest.php`:

```php
<?php

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Services\Roadmaps\MeasureRecomputer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function recomputeFixtureMeasure(string $deadline = '2026 йил декабрь'): RoadmapMeasure
{
    $roadmap = Roadmap::firstOrCreate(['region_code' => 1733, 'year' => 2026], ['title_text' => 't', 'source_file' => 'f']);
    $seq     = $roadmap->measures()->count() + 1;

    return $roadmap->measures()->create([
        'section_no' => 1, 'section_title' => 's', 'seq_no' => $seq, 'title' => 't', 'body_raw' => 't', 'source_row' => $seq,
        'deadline_text' => $deadline,
    ]);
}

test('recompute writes latest period, counts, pct and status from the progress rows', function () {
    $this->seed();
    $m  = recomputeFixtureMeasure();
    $l1 = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'unit' => 'та', 'plan_value' => 24]);
    $l2 = $m->lines()->create(['line_no' => 2, 'label' => 'b', 'unit' => 'та', 'plan_value' => 1]);
    $l1->progress()->create(['report_period' => '2026-08', 'period_type' => 'month', 'actual_value' => 6, 'pct_of_plan' => 25]);
    $l1->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 14, 'pct_of_plan' => 58.3333]);
    $l2->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 1, 'pct_of_plan' => 100]);

    (new MeasureRecomputer())->recompute($m->fresh(), 2026);

    $m->refresh();
    expect($m->latest_period)->toBe('2026-09');
    expect($m->lines_total)->toBe(2);
    expect($m->lines_done)->toBe(1);
    expect((float) $m->pct)->toBeNumericallyClose(79.17, 0.01);
    expect($m->status)->toBe('in_progress');            // December deadline not reached in September
});

test('recompute after the deadline flips a below-plan measure to open; no progress at all stays in_progress', function () {
    $this->seed();
    $m = recomputeFixtureMeasure('2026 йил апрель-октябрь');
    $l = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);
    $l->progress()->create(['report_period' => '2026-11', 'period_type' => 'month', 'actual_value' => 3, 'pct_of_plan' => 30]);
    (new MeasureRecomputer())->recompute($m->fresh(), 2026);
    expect($m->fresh()->status)->toBe('open');

    $empty = recomputeFixtureMeasure();
    $empty->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);
    (new MeasureRecomputer())->recompute($empty->fresh(), 2026);
    $empty->refresh();
    expect($empty->status)->toBe('in_progress');
    expect($empty->latest_period)->toBeNull();
    expect($empty->lines_total)->toBe(1);
    expect($empty->pct)->toBeNull();
});
```

- [ ] **Step 3: Run to verify failure**

Run: `php artisan test --filter="MeasureRecomputerTest|MeasureRecomputerPersistTest"`
Expected: FAIL — class not found.

- [ ] **Step 4: Implement**

`app/Services/Roadmaps/MeasureRecomputer.php`:

```php
<?php

namespace App\Services\Roadmaps;

use App\Models\RoadmapMeasure;
use App\Support\Roadmaps\RoadmapDeadline;
use App\Support\Roadmaps\RoadmapPeriod;
use App\Support\TaskStatus;

/**
 * The single place that turns indicator lines + one period's progress into the
 * measure's denormalised columns (latest_period, status, pct, lines_total, lines_done).
 * Used by import:roadmap-progress and roadmaps:recompute; the page only reads.
 */
final class MeasureRecomputer
{
    /** Largest value the numeric(10,4) pct_of_plan column can hold — a typo like 20 000 against a plan of 0.01 must not abort the import. */
    public const PCT_MAX = 999999.9999;

    /** actual / plan × 100, 4 decimals, clamped to PCT_MAX; null when either side is missing or the plan is 0. */
    public static function pctOfPlan(null|float|int|string $plan, null|float|int|string $actual): ?float
    {
        if ($plan === null || $actual === null || (float) $plan == 0.0) {
            return null;
        }

        return min(self::PCT_MAX, round((float) $actual / (float) $plan * 100, 4));
    }

    /**
     * Pure rules over ONE period's lines.
     *
     * @param  list<array{plan: float|int|string|null, actual: float|int|string|null, pct: float|int|string|null}> $lines
     * @return array{reported: bool, lines_total: int, lines_done: int, pct: ?float, status: string}
     */
    public static function aggregate(array $lines, bool $deadlineReached): array
    {
        $agg      = TaskStatus::aggregate($lines);          // weakest link: in_progress / done / open
        $reported = false;
        $planned  = [];
        foreach ($lines as $l) {
            if ($l['actual'] !== null && (float) $l['actual'] != 0.0) {
                $reported = true;
            }
            if ($l['plan'] !== null) {
                $planned[] = $l;
            }
        }

        $pct = null;
        if ($reported && $planned !== []) {
            $sum = 0.0;
            foreach ($planned as $l) {
                $sum += min(100.0, $l['pct'] !== null ? (float) $l['pct'] : 0.0);
            }
            $pct = round($sum / count($planned), 2);
        }

        $status = $agg['status'];
        if ($agg['total'] === 0) {
            $status = 'in_progress';                          // nothing planned yet → nothing can be behind or done
        } elseif ($status === 'open' && ! $deadlineReached) {
            $status = 'in_progress';                          // running behind before the deadline = still in progress
        }

        return [
            'reported'    => $reported,
            'lines_total' => $agg['total'],
            'lines_done'  => $agg['done'],
            'pct'         => $pct,
            'status'      => $status,
        ];
    }

    /** Recompute and save one measure (loads lines.progress when not already loaded). */
    public function recompute(RoadmapMeasure $measure, int $roadmapYear): void
    {
        $measure->loadMissing('lines.progress');

        $periods = [];
        foreach ($measure->lines as $line) {
            foreach ($line->progress as $p) {
                $periods[] = $p->report_period;
            }
        }
        $latest = RoadmapPeriod::latest($periods);

        if ($latest === null) {
            $values = [
                'latest_period' => null,
                'status'        => 'in_progress',
                'pct'           => null,
                'lines_total'   => $measure->lines->whereNotNull('plan_value')->count(),
                'lines_done'    => 0,
            ];
        } else {
            $rows = [];
            foreach ($measure->lines as $line) {
                $p      = $line->progress->firstWhere('report_period', $latest);
                $rows[] = ['plan' => $line->plan_value, 'actual' => $p?->actual_value, 'pct' => $p?->pct_of_plan];
            }
            $agg    = self::aggregate($rows, RoadmapDeadline::reached($latest, $measure->deadline_text, $roadmapYear));
            $values = [
                'latest_period' => $latest,
                'status'        => $agg['status'],
                'pct'           => $agg['pct'],
                'lines_total'   => $agg['lines_total'],
                'lines_done'    => $agg['lines_done'],
            ];
        }

        $measure->fill($values);
        if ($measure->isDirty()) {
            $measure->save();
        }
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter="MeasureRecomputerTest|MeasureRecomputerPersistTest"`
Expected: PASS (6 + 2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Roadmaps/MeasureRecomputer.php tests/Unit/Roadmaps/MeasureRecomputerTest.php tests/Feature/Roadmaps/MeasureRecomputerPersistTest.php
git commit -m "feat(roadmaps): measure recomputer — weakest-link status with deadline deferral, mean capped pct

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 5: `roadmaps:recompute` command

**Files:**
- Create: `app/Console/Commands/RecomputeRoadmapMeasures.php`
- Test: `tests/Feature/Roadmaps/RoadmapsRecomputeTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/Roadmaps/RoadmapsRecomputeTest.php`:

```php
<?php

use App\Models\Roadmap;
use App\Models\RoadmapLineProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('roadmaps:recompute rebuilds status/pct from progress rows and reports counts; --dry-run writes nothing', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $m = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1, 'deadline_text' => '2026 йил декабрь']);
    $l = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);
    $l->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 10, 'pct_of_plan' => 100]);

    expect(Artisan::call('roadmaps:recompute', ['--dry-run' => true]))->toBe(0);
    expect($m->fresh()->status)->toBe('in_progress');
    expect(Artisan::output())->toContain('Dry run');

    expect(Artisan::call('roadmaps:recompute'))->toBe(0);
    $m->refresh();
    expect($m->status)->toBe('done');
    expect((float) $m->pct)->toBe(100.0);
    expect(Artisan::output())->toContain('done: 1');

    // A manual edit of the actual is picked up on the next run.
    RoadmapLineProgress::first()->update(['actual_value' => 4, 'pct_of_plan' => 40]);
    Artisan::call('roadmaps:recompute', ['--region' => 1733]);
    expect($m->fresh()->status)->toBe('in_progress');   // below plan, deadline not reached
    expect((float) $m->fresh()->pct)->toBe(40.0);

    expect(Artisan::call('roadmaps:recompute', ['--region' => 9999]))->toBe(1);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=RoadmapsRecomputeTest`
Expected: FAIL — command `roadmaps:recompute` not defined.

- [ ] **Step 3: Implement**

`app/Console/Commands/RecomputeRoadmapMeasures.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Roadmap;
use App\Services\Roadmaps\MeasureRecomputer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Rebuilds every road-map measure's status/pct/counters from roadmap_line_progress — no re-import. Idempotent. */
class RecomputeRoadmapMeasures extends Command
{
    protected $signature = 'roadmaps:recompute
        {--region= : Limit to one region SOATO code}
        {--dry-run : Report without writing}';

    protected $description = 'Recompute road-map measure status, percent and line counters from the stored progress rows.';

    public function handle(): int
    {
        $query = Roadmap::query()->with(['measures.lines.progress']);
        if ($this->option('region') !== null) {
            $query->where('region_code', (int) $this->option('region'));
        }
        $roadmaps = $query->get();
        if ($roadmaps->isEmpty()) {
            $this->error('No road map matches — nothing to recompute.');
            return self::FAILURE;
        }

        $recomputer = new MeasureRecomputer();
        $flips      = [];
        $counts     = ['done' => 0, 'in_progress' => 0, 'open' => 0];

        DB::beginTransaction();
        try {
            foreach ($roadmaps as $roadmap) {
                foreach ($roadmap->measures as $measure) {
                    $before = $measure->status;
                    $recomputer->recompute($measure, $roadmap->year);
                    if ($before !== $measure->status) {
                        $k = "{$before}→{$measure->status}";
                        $flips[$k] = ($flips[$k] ?? 0) + 1;
                    }
                    $counts[$measure->status] = ($counts[$measure->status] ?? 0) + 1;
                }
            }
            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $flipText = $flips === [] ? 'no status flips' : implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($flips), $flips));
        $this->info(sprintf('%d road map(s): done: %d, in_progress: %d, open: %d — %s.',
            $roadmaps->count(), $counts['done'], $counts['in_progress'], $counts['open'], $flipText));
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --filter=RoadmapsRecomputeTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/RecomputeRoadmapMeasures.php tests/Feature/Roadmaps/RoadmapsRecomputeTest.php
git commit -m "feat(roadmaps): roadmaps:recompute rebuilds measure status without re-import

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 6: `LineSuggester` (quantity heuristic)

**Files:**
- Create: `app/Support/Roadmaps/LineSuggester.php`
- Test: `tests/Unit/Roadmaps/LineSuggesterTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Roadmaps/LineSuggesterTest.php`:

```php
<?php

use App\Support\Roadmaps\LineSuggester;

$s = new LineSuggester();

test('one line per quantity in the title or a detail line; the number is stripped from the label', function () use ($s) {
    expect($s->suggest('Насос станцияларида таъмирлаш ишлари', [
        '24 та насос агрегатларини таъмирлаш.',
        '1. 7,8 км хўжаликлараро каналлар;',
        '64 нафар талабаларни амалиётга юбориш',
    ]))->toBe([
        ['label' => 'Насос агрегатларини таъмирлаш', 'unit' => 'та', 'plan' => 24.0],
        ['label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => 7.8],
        ['label' => 'Талабаларни амалиётга юбориш', 'unit' => 'нафар', 'plan' => 64.0],
    ]);
});

test('magnitude words combine with the unit; thousands separators and comma decimals are parsed', function () use ($s) {
    expect($s->suggest('26,7 минг гектар ер майдонларида сув тежовчи технологияларни жорий этиш', []))
        ->toBe([['label' => 'Ер майдонларида сув тежовчи технологияларни жорий этиш', 'unit' => 'минг га', 'plan' => 26.7]]);
    expect($s->suggest('484,5 млн м3 сувни иқтисод қилиш.', []))
        ->toBe([['label' => 'Сувни иқтисод қилиш', 'unit' => 'млн м³', 'plan' => 484.5]]);
    expect($s->suggest("1 280 гектар ерда лазерли текислаш", []))
        ->toBe([['label' => 'Ерда лазерли текислаш', 'unit' => 'га', 'plan' => 1280.0]]);
    expect($s->suggest('Лойиҳа қиймати 32,0 млрд сўм', []))
        ->toBe([['label' => 'Лойиҳа қиймати', 'unit' => 'млрд сўм', 'plan' => 32.0]]);
});

test('bare numbers, years and a magnitude without a unit are not quantities', function () use ($s) {
    expect($s->suggest('Вилоят бўйича 2026 йил режасига асосан ишлар', []))
        ->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0]]);
    expect($s->suggest('Лойиҳа қиймати 32,0 млрд', []))
        ->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0]]);
    expect($s->suggest('Илмий тавсиялар ишлаб чиқиш.', []))
        ->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0]]);
});

test('several quantities in one text share the label with a numbered suffix; empty label falls back', function () use ($s) {
    expect($s->suggest('7,8 км хўжаликлараро ва 33 км ички каналлар', []))->toBe([
        ['label' => 'Хўжаликлараро ва ички каналлар', 'unit' => 'км', 'plan' => 7.8],
        ['label' => 'Хўжаликлараро ва ички каналлар (2)', 'unit' => 'км', 'plan' => 33.0],
    ]);
    expect($s->suggest('3 та', []))->toBe([['label' => 'Ҳажм', 'unit' => 'та', 'plan' => 3.0]]);
});

test('a unit glued to a longer word is not a unit', function () use ($s) {
    expect($s->suggest('24 таъмирлаш иши', []))->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0]]);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=LineSuggesterTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

`app/Support/Roadmaps/LineSuggester.php`:

```php
<?php

namespace App\Support\Roadmaps;

/**
 * Suggests indicator lines for a measure from the quantities in its text
 * («24 та насос агрегатларини таъмирлаш» → label «Насос агрегатларини таъмирлаш»,
 * unit «та», plan 24). A starting point for the reviewer, never the final word.
 */
final class LineSuggester
{
    public const FALLBACK = ['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0];

    private const NUMBER = '(?:\d{1,3}(?:[ \x{00A0}]\d{3})+|\d+)(?:[,.]\d+)?';

    /** number · optional magnitude · optional unit; the lookahead forbids a unit glued to a longer word. */
    private const RE = '/(' . self::NUMBER . ')\s*(минг|млн|млрд)?\s*(та|дона|нафар|км|га|гектар|тонна|м³|м3|квт|сўм|долл\.?)?(?!\p{L})/iu';

    private const UNITS = ['гектар' => 'га', 'м3' => 'м³', 'квт' => 'кВт', 'долл' => 'долл.', 'долл.' => 'долл.'];

    /**
     * @param  list<string> $detailLines
     * @return list<array{label: string, unit: string, plan: float}>
     */
    public function suggest(string $title, array $detailLines): array
    {
        $out = [];
        foreach (array_merge([$title], $detailLines) as $text) {
            foreach ($this->linesOf($text) as $line) {
                $out[] = $line;
            }
        }

        return $out === [] ? [self::FALLBACK] : $out;
    }

    /** @return list<array{label: string, unit: string, plan: float}> */
    private function linesOf(string $text): array
    {
        if (preg_match_all(self::RE, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $found = [];
        foreach ($matches as $m) {
            $unit = isset($m[3]) && $m[3][0] !== '' ? mb_strtolower($m[3][0]) : null;
            if ($unit === null) {
                continue;                                   // bare number or a magnitude with no unit — ambiguous
            }
            $unit      = self::UNITS[$unit] ?? $unit;
            $magnitude = isset($m[2]) && $m[2][0] !== '' ? mb_strtolower($m[2][0]) . ' ' : '';
            $found[]   = [
                'span' => [$m[0][1], strlen($m[0][0])],
                'unit' => $magnitude . $unit,
                'plan' => (float) str_replace(',', '.', preg_replace('/[ \x{00A0}]/u', '', $m[1][0])),
            ];
        }
        if ($found === []) {
            return [];
        }

        // Remove the matched quantities from the text (from the end, so offsets stay valid).
        $label = $text;
        foreach (array_reverse($found) as $f) {
            $label = substr_replace($label, ' ', $f['span'][0], $f['span'][1]);
        }
        $label = self::cleanLabel($label);

        $lines = [];
        foreach ($found as $i => $f) {
            $lines[] = ['label' => $i === 0 ? $label : $label . ' (' . ($i + 1) . ')', 'unit' => $f['unit'], 'plan' => $f['plan']];
        }

        return $lines;
    }

    private static function cleanLabel(string $label): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));
        $label = (string) preg_replace('/^(?:\d+[.)]|[•\-–—])\s*/u', '', $label);
        $label = (string) preg_replace('/[\s.;:,]+$/u', '', $label);
        $label = mb_substr($label, 0, 255);
        if ($label === '') {
            return 'Ҳажм';
        }

        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --filter=LineSuggesterTest`
Expected: PASS (5 tests). If the `1 280` case fails, check that the test string uses a plain space (U+0020) between `1` and `280`.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Roadmaps/LineSuggester.php tests/Unit/Roadmaps/LineSuggesterTest.php
git commit -m "feat(roadmaps): quantity heuristic that suggests indicator lines from measure text

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 7: `RoadmapTemplateWriter` + `roadmap:template`

**Files:**
- Create: `app/Services/Roadmaps/RoadmapTemplateWriter.php`, `app/Console/Commands/RoadmapTemplate.php`
- Test: `tests/Feature/Roadmaps/RoadmapTemplateTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/Roadmaps/RoadmapTemplateTest.php`:

```php
<?php

use App\Models\RoadmapMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use Tests\Helpers\RoadmapDocxBuilder;

uses(RefreshDatabase::class);

function templateFixtureImport(int $region = 1733): void
{
    $rows = $region === 1733 ? [
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['measure', ['Илмий тавсиялар ишлаб чиқиш.'], ['Университет маблағлари'], ['2026 йил апрель-октябрь'], ['Университет']],
        ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['Суғориш тармоқларини бетонлаштириш, жумладан:', '1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.'], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
        ['measure', ['24 та насос агрегатларини таъмирлаш.'], ['Маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ']],
    ] : [
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['12 км канал.'], ['Бюджет'], ['2026 йил декабрь'], ['СХВ']],
    ];
    Artisan::call('import:roadmap', ['--region' => $region, '--file' => RoadmapDocxBuilder::make($rows)]);
}

function templateOut(): string
{
    return tempnam(sys_get_temp_dir(), 'rmtpl_') . '.xlsx';
}

test('writes a document-shaped sheet: title with period, header, section/district rows, merged measure blocks, suggested lines', function () {
    $this->seed();
    templateFixtureImport();
    $out = templateOut();

    expect(Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--out' => $out]))->toBe(0);
    expect(Artisan::output())->toContain($out);

    $book  = IOFactory::load($out);
    $sheet = $book->getSheetByName('Хоразм');
    expect($sheet)->not->toBeNull();
    expect($book->getSheetByName('Йўриқнома'))->not->toBeNull();
    expect($book->getSheetCount())->toBe(2);

    expect($sheet->getCell('A1')->getValue())->toContain('Хоразм вилояти');
    expect($sheet->getCell('A1')->getValue())->toContain('Ҳисобот даври: 2026-09');
    expect($sheet->getCell('A2')->getValue())->toBe('Калит');
    expect($sheet->getCell('G2')->getValue())->toBe('Амалда');
    expect($sheet->getCell('A3')->getValue())->toBe('I. Вилоятда амалга ошириладиган йирик лойиҳалар');

    expect($sheet->getCell('A4')->getValue())->toBe('1733-1-0-1');
    expect((int) $sheet->getCell('B4')->getValue())->toBe(1);
    expect($sheet->getCell('C4')->getValue())->toBe('484,5 млн м3 сувни иқтисод қилиш.');
    expect($sheet->getCell('D4')->getValue())->toBe('Сувни иқтисод қилиш');
    expect($sheet->getCell('E4')->getValue())->toBe('млн м³');
    expect((float) $sheet->getCell('F4')->getValue())->toBe(484.5);
    expect($sheet->getCell('G4')->getValue())->toBeNull();
    expect($sheet->getCell('I4')->getValue())->toBe('2026 йил декабрь');
    expect($sheet->getCell('D5')->getValue())->toBe('Бажарилиш даражаси');
    expect($sheet->getCell('E5')->getValue())->toBe('%');

    expect($sheet->getCell('A6')->getValue())->toBe('II. Туманларда амалга ошириладиган лойиҳалар');
    expect($sheet->getCell('A7')->getValue())->toBe('1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)');
    expect($sheet->getCell('A8')->getValue())->toBe('1733-2-1733204-1');
    expect($sheet->getCell('D8')->getValue())->toBe('Хўжаликлараро каналлар');
    expect($sheet->getCell('D9')->getValue())->toBe('Ички каналлар');
    expect((float) $sheet->getCell('F9')->getValue())->toBe(33.0);
    expect(array_keys($sheet->getMergeCells()))->toContain('C8:C9', 'A8:A9', 'J8:J9', 'A7:J7', 'A1:J1');
    expect($sheet->getCell('A10')->getValue())->toBe('2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)');
    expect($sheet->getCell('A11')->getValue())->toBe('1733-2-1733208-1');
    expect($sheet->getCell('D11')->getValue())->toBe('Насос агрегатларини таъмирлаш');

    expect($sheet->getColumnDimension('A')->getVisible())->toBeFalse();
    expect($sheet->getProtection()->getSheet())->toBeTrue();
    expect($sheet->getStyle('G8')->getProtection()->getLocked())->toBe(Protection::PROTECTION_UNPROTECTED);
    expect($sheet->getStyle('H8')->getProtection()->getLocked())->toBe(Protection::PROTECTION_UNPROTECTED);
    expect($sheet->getStyle('F8')->getProtection()->getLocked())->not->toBe(Protection::PROTECTION_UNPROTECTED);
    expect($sheet->getStyle('G8')->getFill()->getStartColor()->getRGB())->toBe('FFF2CC');
    expect($sheet->getStyle('A7')->getFill()->getStartColor()->getRGB())->toBe('EAF1FB');
    expect($sheet->getCell('G8')->getDataValidation()->getType())->toBe('decimal');
    expect($sheet->getFreezePane())->toBe('A3');
});

test('stored lines win over suggestions and carry the period actual and note', function () {
    $this->seed();
    templateFixtureImport();
    $m = RoadmapMeasure::where('section_no', 1)->where('seq_no', 2)->firstOrFail();
    $l = $m->lines()->create(['line_no' => 1, 'label' => 'Тавсиялар сони', 'unit' => 'дона', 'plan_value' => 3]);
    $m->lines()->create(['line_no' => 2, 'label' => 'Тақдимот', 'unit' => 'та', 'plan_value' => 1]);
    $l->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 2, 'pct_of_plan' => 66.67, 'note' => 'икки тайёр']);
    $l->progress()->create(['report_period' => '2026-08', 'period_type' => 'month', 'actual_value' => 1, 'pct_of_plan' => 33.33]);
    $out = templateOut();

    Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--out' => $out]);

    $sheet = IOFactory::load($out)->getSheetByName('Хоразм');
    expect($sheet->getCell('A5')->getValue())->toBe('1733-1-0-2');
    expect($sheet->getCell('D5')->getValue())->toBe('Тавсиялар сони');
    expect((float) $sheet->getCell('G5')->getValue())->toBe(2.0);
    expect($sheet->getCell('H5')->getValue())->toBe('икки тайёр');
    expect($sheet->getCell('D6')->getValue())->toBe('Тақдимот');
    expect($sheet->getCell('G6')->getValue())->toBeNull();
    expect($sheet->getCell('A7')->getValue())->toBe('II. Туманларда амалга ошириладиган лойиҳалар');   // shifted by one row
    expect(Artisan::output())->toContain('suggested');
});

test('--all writes one sheet per loaded region in region order; a region without a road map is an error', function () {
    $this->seed();
    templateFixtureImport(1703);
    templateFixtureImport(1733);
    $out = templateOut();

    expect(Artisan::call('roadmap:template', ['--all' => true, '--period' => '2026-Q3', '--out' => $out]))->toBe(0);
    $book = IOFactory::load($out);
    expect(array_map(fn ($s) => $s->getTitle(), $book->getAllSheets()))->toBe(['Андижон', 'Хоразм', 'Йўриқнома']);
    expect($book->getSheetByName('Андижон')->getCell('A1')->getValue())->toContain('Ҳисобот даври: 2026-Q3');

    expect(Artisan::call('roadmap:template', ['--region' => 1718, '--period' => '2026-09', '--out' => templateOut()]))->toBe(1);
    expect(Artisan::output())->toContain('import:roadmap');
    expect(Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-9', '--out' => templateOut()]))->toBe(1);
    expect(Artisan::output())->toContain('--period');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=RoadmapTemplateTest`
Expected: FAIL — command not defined.

- [ ] **Step 3: Implement the writer**

`app/Services/Roadmaps/RoadmapTemplateWriter.php`:

```php
<?php

namespace App\Services\Roadmaps;

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Support\Roadmaps\LineSuggester;
use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\Roman;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the xlsx the regions fill: one sheet per road map shaped like the document
 * (section / district header rows, the measure text merged down its indicator rows),
 * yellow unlocked «Амалда»/«Изоҳ» cells, everything else protected, a hidden key column.
 */
final class RoadmapTemplateWriter
{
    public const HEADERS = ['Калит', '№', 'Чора-тадбир', 'Индикатор', 'Ўлчов', 'Режа', 'Амалда', 'Изоҳ', 'Муддат', 'Масъуллар'];
    public const INSTRUCTIONS_TITLE = 'Йўриқнома';

    private const WIDTHS = ['A' => 14, 'B' => 5, 'C' => 60, 'D' => 40, 'E' => 9, 'F' => 10, 'G' => 10, 'H' => 28, 'I' => 16, 'J' => 30];

    private const INSTRUCTIONS = [
        'Фақат сариқ устунларни тўлдиринг: G «Амалда» ва (ихтиёрий) H «Изоҳ».',
        '«Амалда» — рақам, «Ўлчов» устунидаги бирликда, йил бошидан жами (ойлик қўшимча эмас).',
        'Ҳали бошланмаган индикатор учун 0 ёзинг ёки бўш қолдиринг.',
        'Қаторларни қўшманг ва ўчирманг, бошқа устунларни ўзгартирманг — варақ ҳимояланган.',
        'Файлни ҳисобот ойидан кейинги ойнинг 5-санасигача қайтаринг.',
        'Саволлар бўйича мониторинг платформаси маъмурига мурожаат қилинг.',
    ];

    /** @var array<int, array{measures: int, lines: int, suggested: int}> per region code, filled by build() */
    public array $stats = [];

    public function __construct(private readonly LineSuggester $suggester = new LineSuggester())
    {
    }

    /** @param iterable<Roadmap> $roadmaps with region, measures (document order), measures.district, measures.lines.progress loaded */
    public function build(iterable $roadmaps, string $period): Spreadsheet
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        $this->stats = [];
        foreach ($roadmaps as $roadmap) {
            $this->addRegionSheet($book, $roadmap, $period);
        }
        $this->addInstructionSheet($book);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** Excel forbids []:*?/\ in sheet titles and caps them at 31 characters. */
    public static function sheetTitle(string $name): string
    {
        return mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $name)), 0, 31);
    }

    /** @return list<array{label: ?string, unit: ?string, plan: ?float, actual: ?float, note: ?string, suggested: bool}> */
    public function linesFor(RoadmapMeasure $m, string $period): array
    {
        if ($m->lines->isNotEmpty()) {
            return $m->lines->map(function ($line) use ($period) {
                $p = $line->progress->firstWhere('report_period', $period);

                return [
                    'label'     => $line->label,
                    'unit'      => $line->unit,
                    'plan'      => $line->plan_value !== null ? (float) $line->plan_value : null,
                    'actual'    => $p?->actual_value !== null ? (float) $p->actual_value : null,
                    'note'      => $p?->note,
                    'suggested' => false,
                ];
            })->all();
        }

        return array_map(
            fn (array $s) => $s + ['actual' => null, 'note' => null, 'suggested' => true],
            $this->suggester->suggest($m->title, $m->detailLines()),
        );
    }

    private function addRegionSheet(Spreadsheet $book, Roadmap $roadmap, string $period): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(self::sheetTitle($roadmap->region->name_short));

        $sheet->setCellValue('A1', "Сув хўжалиги йўл харитаси — {$roadmap->region->name_full} — {$roadmap->year} · Ҳисобот даври: {$period}");
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $sheet->fromArray(self::HEADERS, null, 'A2');
        $sheet->getStyle('A2:J2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A2:J2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $sheet->getStyle('G2:H2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C65911');
        $sheet->getStyle('A2:J2')->getAlignment()->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A3');
        foreach (self::WIDTHS as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->getColumnDimension('A')->setVisible(false);

        $stats      = ['measures' => 0, 'lines' => 0, 'suggested' => 0];
        $row        = 3;
        $sectionNo  = null;
        $districtId = null;
        $districtNo = 0;

        foreach ($roadmap->measures as $m) {
            if ($m->section_no !== $sectionNo) {
                $sectionNo  = $m->section_no;
                $districtId = null;
                $districtNo = 0;
                $this->headerRow($sheet, $row++, Roman::of($m->section_no) . '. ' . $m->section_title, 'DBE5F1');
            }
            if ($m->district_id !== null && $m->district_id !== $districtId) {
                $districtId = $m->district_id;
                $districtNo++;
                $head = $m->district_head_text ? " (масъул – {$m->district_head_text})" : '';
                $this->headerRow($sheet, $row++, "{$districtNo}. {$m->district->name_full}{$head}", 'EAF1FB');
            }

            $lines = $this->linesFor($m, $period);
            if ($lines === []) {
                $lines = [['label' => null, 'unit' => null, 'plan' => null, 'actual' => null, 'note' => null, 'suggested' => false]];
            }
            $start = $row;
            $sheet->setCellValueExplicit("A{$row}", RoadmapKey::make($roadmap->region_code, $m->section_no, $m->district?->code, $m->seq_no), DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $m->seq_no);
            $sheet->setCellValue("C{$row}", $m->body_raw);
            $sheet->setCellValue("I{$row}", $m->deadline_text);
            $sheet->setCellValue("J{$row}", $m->responsible_text);
            foreach ($lines as $l) {
                $sheet->setCellValue("D{$row}", $l['label']);
                $sheet->setCellValue("E{$row}", $l['unit']);
                $sheet->setCellValue("F{$row}", $l['plan']);
                $sheet->setCellValue("G{$row}", $l['actual']);
                $sheet->setCellValue("H{$row}", $l['note']);
                $this->fillCells($sheet, $row);
                $row++;
                $stats['lines']++;
                if ($l['suggested']) {
                    $stats['suggested']++;
                }
            }
            $end = $row - 1;
            if ($end > $start) {
                foreach (['A', 'B', 'C', 'I', 'J'] as $c) {
                    $sheet->mergeCells("{$c}{$start}:{$c}{$end}");
                }
            }
            $stats['measures']++;
        }

        $last = max($row - 1, 3);
        $sheet->getStyle("A3:J{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle("F3:G{$last}")->getNumberFormat()->setFormatCode('#,##0.##');
        $sheet->getStyle("A3:A{$last}")->getFont()->getColor()->setRGB('999999');

        $protection = $sheet->getProtection();
        $protection->setSheet(true);
        $protection->setFormatColumns(false);       // false = allowed while protected
        $protection->setFormatRows(false);

        $this->stats[$roadmap->region_code] = $stats;
    }

    private function headerRow(Worksheet $sheet, int $row, string $text, string $rgb): void
    {
        $sheet->setCellValue("A{$row}", $text);
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    /** Yellow, unlocked «Амалда» (numeric-validated) and «Изоҳ» for one line row. */
    private function fillCells(Worksheet $sheet, int $row): void
    {
        $sheet->getStyle("G{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sheet->getStyle("G{$row}:H{$row}")->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

        $v = $sheet->getCell("G{$row}")->getDataValidation();
        $v->setType(DataValidation::TYPE_DECIMAL)
            ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
            ->setFormula1('0')
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Амалда')
            ->setError('Фақат рақам киритинг (ўлчов бирлигида, йил бошидан жами)');
    }

    private function addInstructionSheet(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(self::INSTRUCTIONS_TITLE);
        $sheet->setCellValue('A1', 'Йўриқнома — файлни қандай тўлдириш керак');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        foreach (self::INSTRUCTIONS as $i => $text) {
            $sheet->setCellValue('A' . ($i + 3), ($i + 1) . '. ' . $text);
        }
        $sheet->getColumnDimension('A')->setWidth(110);
        $sheet->getStyle('A1:A' . (count(self::INSTRUCTIONS) + 3))->getAlignment()->setWrapText(true);
    }
}
```

- [ ] **Step 4: Implement the command**

`app/Console/Commands/RoadmapTemplate.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Roadmap;
use App\Services\Roadmaps\RoadmapTemplateWriter;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RoadmapTemplate extends Command
{
    protected $signature = 'roadmap:template
        {--region= : SOATO region code, e.g. 1733 (Хоразм)}
        {--all : Every region with a loaded road map, one sheet each}
        {--period= : Report period the file is for: YYYY-MM or YYYY-Qn}
        {--out= : Output .xlsx (default: data/Сув хўжалиги бўйича йўл хариталар/мониторинг/<period>/<region>.xlsx)}
        {--year=2026 : Road-map year}
        {--domain=water : Road-map family}';

    protected $description = 'Write the xlsx a region fills with indicator actuals (stored lines, else suggested from the measure text).';

    public function handle(): int
    {
        $period = (string) $this->option('period');
        if (! RoadmapPeriod::isValid($period)) {
            $this->error('Provide --period as YYYY-MM or YYYY-Qn (e.g. 2026-09 or 2026-Q3).');
            return self::FAILURE;
        }

        $base = Roadmap::query()
            ->where('roadmaps.domain', (string) $this->option('domain'))
            ->where('roadmaps.year', (int) $this->option('year'))
            ->with([
                'region',
                'measures' => fn (Builder|\Illuminate\Database\Eloquent\Relations\HasMany $q) => $q->orderBy('source_row'),
                'measures.district',
                'measures.lines.progress',
            ]);

        if ($this->option('all')) {
            $roadmaps = $base->join('regions', 'regions.code', '=', 'roadmaps.region_code')
                ->orderBy('regions.sort_order')->select('roadmaps.*')->get();
            if ($roadmaps->isEmpty()) {
                $this->error('No road map is loaded yet — run import:roadmap first.');
                return self::FAILURE;
            }
            $name = 'Барча вилоятлар';
        } else {
            $code = (int) $this->option('region');
            if ($code <= 0) {
                $this->error('Provide --region=<SOATO code> or --all.');
                return self::FAILURE;
            }
            $roadmap = $base->where('roadmaps.region_code', $code)->first();
            if (! $roadmap) {
                $this->error("Region {$code} has no road map loaded — run import:roadmap --region={$code} first.");
                return self::FAILURE;
            }
            $roadmaps = collect([$roadmap]);
            $name     = $roadmap->region->name_short;
        }

        $out = (string) ($this->option('out') ?: base_path(ImportRoadmap::DEFAULT_DIR . "/мониторинг/{$period}/{$name}.xlsx"));
        if (! is_dir(dirname($out)) && ! mkdir(dirname($out), 0777, true) && ! is_dir(dirname($out))) {
            $this->error('Cannot create ' . dirname($out));
            return self::FAILURE;
        }

        $writer = new RoadmapTemplateWriter();
        $book   = $writer->build($roadmaps, $period);
        IOFactory::createWriter($book, 'Xlsx')->save($out);

        $rows = [];
        foreach ($roadmaps as $roadmap) {
            $s      = $writer->stats[$roadmap->region_code];
            $rows[] = [$roadmap->region->name_full, $s['measures'], $s['lines'], $s['suggested']];
        }
        $this->table(['Вилоят', 'Тадбирлар', 'Индикатор қаторлари', 'of which suggested'], $rows);
        $this->info("Written {$out}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=RoadmapTemplateTest`
Expected: PASS (3 tests). If `getDataValidation()->getType()` returns `''`, the validation was not persisted — make sure `setShowErrorMessage(true)` is on and the cell is not empty-skipped by the writer (PhpSpreadsheet writes validations for empty cells too, so this should not happen).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Roadmaps/RoadmapTemplateWriter.php app/Console/Commands/RoadmapTemplate.php tests/Feature/Roadmaps/RoadmapTemplateTest.php
git commit -m "feat(roadmaps): roadmap:template writes the document-shaped xlsx regions fill

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

> **Execution order:** run **Task 9 before Task 8**. Task 8 creates the first real progress rows, and until Task 9 lands a docx re-import (`import:roadmap`) cascade-deletes every line and progress row of the region. Task 9 has no dependency on Task 8.

### Task 8: `RoadmapProgressReader` + `import:roadmap-progress`

**Files:**
- Create: `app/Services/Roadmaps/RoadmapProgressReader.php`, `app/Console/Commands/ImportRoadmapProgress.php`
- Test: `tests/Feature/Roadmaps/ImportRoadmapProgressTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Roadmaps/ImportRoadmapProgressTest.php`:

```php
<?php

use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Helpers\RoadmapDocxBuilder;

uses(RefreshDatabase::class);

function progressFixtureImport(): void
{
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => RoadmapDocxBuilder::make([
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['measure', ['Илмий тавсиялар ишлаб чиқиш.'], ['Университет маблағлари'], ['2026 йил апрель-октябрь'], ['Университет']],
        ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['Суғориш тармоқларини бетонлаштириш, жумладан:', '1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.'], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
        ['measure', ['24 та насос агрегатларини таъмирлаш.'], ['Маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ']],
    ])]);
}

/** Template rows for this fixture: 4 = I/1 (млн м³ 484,5), 5 = I/2 (% 100), 8–9 = Боғот/1 (км 7,8 · км 33), 11 = Гурлан/1 (та 24). */
function progressTemplate(string $period = '2026-09'): string
{
    $out = tempnam(sys_get_temp_dir(), 'rmprg_') . '.xlsx';
    Artisan::call('roadmap:template', ['--region' => 1733, '--period' => $period, '--out' => $out]);

    return $out;
}

/** @param array<string, mixed> $cells coordinate => value on the «Хоразм» sheet */
function progressFill(string $path, array $cells): void
{
    $book  = IOFactory::load($path);
    $sheet = $book->getSheetByName('Хоразм');
    foreach ($cells as $coord => $value) {
        $sheet->setCellValue($coord, $value);
    }
    IOFactory::createWriter($book, 'Xlsx')->save($path);
}

function progressMeasure(int $sectionNo, int $seqNo, ?int $districtCode = null): RoadmapMeasure
{
    $q = RoadmapMeasure::where('section_no', $sectionNo)->where('seq_no', $seqNo);
    $q = $districtCode === null ? $q->whereNull('district_id') : $q->whereHas('district', fn ($d) => $d->where('code', $districtCode));

    return $q->firstOrFail();
}

test('round trip: an empty reviewed file defines the lines, the filled file adds actuals; re-import is idempotent', function () {
    $this->seed();
    progressFixtureImport();
    $file = progressTemplate();

    expect(Artisan::call('import:roadmap-progress', ['--file' => $file]))->toBe(0);          // period read from A1
    expect(Artisan::output())->toContain('2026-09');
    expect(RoadmapMeasureLine::count())->toBe(5);
    expect(RoadmapLineProgress::count())->toBe(5);
    expect(RoadmapLineProgress::whereNotNull('actual_value')->count())->toBe(0);
    expect(RoadmapMeasure::where('status', 'in_progress')->count())->toBe(4);
    expect(progressMeasure(2, 1, 1733204)->lines_total)->toBe(2);

    progressFill($file, ['G4' => '300,5', 'H5' => 'ҳали бошланмаган', 'G8' => 7.8, 'G9' => '20', 'G11' => 24]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $file, '--period' => '2026-09']))->toBe(0);

    $bogot = progressMeasure(2, 1, 1733204);
    expect($bogot->lines_total)->toBe(2);
    expect($bogot->lines_done)->toBe(1);
    expect((float) $bogot->pct)->toBeNumericallyClose(80.3, 0.1);          // (100 + 60.6) / 2
    expect($bogot->status)->toBe('in_progress');                           // December deadline, September report
    expect($bogot->latest_period)->toBe('2026-09');
    $ichki = $bogot->lines()->where('line_no', 2)->firstOrFail()->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $ichki->actual_value)->toBe(20.0);
    expect((float) $ichki->pct_of_plan)->toBeNumericallyClose(60.606, 0.001);
    expect($ichki->period_type)->toBe('month');

    $gurlan = progressMeasure(2, 1, 1733208);
    expect($gurlan->status)->toBe('done');
    expect((float) $gurlan->pct)->toBe(100.0);

    $m1 = progressMeasure(1, 1);
    expect((float) $m1->pct)->toBeNumericallyClose(62.02, 0.01);
    expect($m1->status)->toBe('in_progress');

    $m2 = progressMeasure(1, 2);
    expect($m2->status)->toBe('in_progress');
    expect($m2->pct)->toBeNull();
    expect($m2->lines->first()->progress->first()->note)->toBe('ҳали бошланмаган');

    Artisan::call('import:roadmap-progress', ['--file' => $file]);
    expect(RoadmapMeasureLine::count())->toBe(5);
    expect(RoadmapLineProgress::count())->toBe(5);
    expect((float) progressMeasure(2, 1, 1733208)->pct)->toBe(100.0);
});

test('reviewer edits travel with the file: relabelled, added and removed lines', function () {
    $this->seed();
    progressFixtureImport();
    $file = progressTemplate();
    $book = IOFactory::load($file);
    $sheet = $book->getSheetByName('Хоразм');
    $sheet->setCellValue('D8', 'Хўжаликлараро каналлар (бетон)');
    $sheet->insertNewRowBefore(10, 1);                                     // third line of the Боғот block, A stays empty
    $sheet->setCellValue('D10', 'Гидропостлар');
    $sheet->setCellValue('E10', 'та');
    $sheet->setCellValue('F10', 4);
    IOFactory::createWriter($book, 'Xlsx')->save($file);

    expect(Artisan::call('import:roadmap-progress', ['--file' => $file]))->toBe(0);
    $bogot = progressMeasure(2, 1, 1733204);
    expect($bogot->lines->pluck('label')->all())->toBe(['Хўжаликлараро каналлар (бетон)', 'Ички каналлар', 'Гидропостлар']);
    expect($bogot->lines_total)->toBe(3);

    // Next month's template carries the stored lines; the reviewer drops the last two rows.
    $next = progressTemplate('2026-10');
    progressFill($next, ['D9' => null, 'E9' => null, 'F9' => null, 'D10' => null, 'E10' => null, 'F10' => null, 'G8' => 7.8]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $next]))->toBe(0);
    expect(Artisan::output())->toContain('2 line(s) removed');
    $bogot->refresh();
    expect($bogot->lines->pluck('label')->all())->toBe(['Хўжаликлараро каналлар (бетон)']);
    expect($bogot->latest_period)->toBe('2026-10');
    expect($bogot->status)->toBe('done');
});

test('structural problems abort before anything is written', function () {
    $this->seed();
    progressFixtureImport();
    $file = progressTemplate();
    Artisan::call('import:roadmap-progress', ['--file' => $file]);
    $linesBefore = RoadmapMeasureLine::count();

    $bad = progressTemplate();
    progressFill($bad, ['G8' => 'кўп']);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $bad]))->toBe(1);
    expect(Artisan::output())->toContain('G8');

    $unknown = progressTemplate();
    progressFill($unknown, ['A11' => '1733-9-0-9']);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $unknown]))->toBe(1);
    expect(Artisan::output())->toContain('1733-9-0-9');

    expect(Artisan::call('import:roadmap-progress', ['--file' => $file, '--period' => '2026-10']))->toBe(1);
    expect(Artisan::output())->toContain('2026-10');

    $truncated = progressTemplate();
    progressFill($truncated, ['D8' => null, 'E8' => null, 'F8' => null, 'D9' => null, 'E9' => null, 'F9' => null]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $truncated]))->toBe(1);
    expect(Artisan::output())->toContain('1733-2-1733204-1');

    $noLabel = progressTemplate();
    progressFill($noLabel, ['D11' => null, 'G11' => 5]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $noLabel]))->toBe(1);
    expect(Artisan::output())->toContain('D11');

    expect(Artisan::call('import:roadmap-progress', ['--file' => 'C:/nope/missing.xlsx']))->toBe(1);

    expect(RoadmapMeasureLine::count())->toBe($linesBefore);
    expect(RoadmapLineProgress::whereNotNull('actual_value')->count())->toBe(0);

    $dry = progressTemplate();
    progressFill($dry, ['G11' => 24]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $dry, '--dry-run' => true]))->toBe(0);
    expect(Artisan::output())->toContain('Dry run');
    expect(RoadmapLineProgress::whereNotNull('actual_value')->count())->toBe(0);
    expect(progressMeasure(2, 1, 1733208)->status)->toBe('in_progress');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=ImportRoadmapProgressTest`
Expected: FAIL — command `import:roadmap-progress` not defined.

- [ ] **Step 3: Implement the reader**

`app/Services/Roadmaps/RoadmapProgressReader.php`:

```php
<?php

namespace App\Services\Roadmaps;

use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\RoadmapPeriod;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Reads a filled template back into blocks keyed by the natural measure key.
 * Row rules (column A as PhpSpreadsheet reports it — a merged range has its value on
 * the first row only): a key in A starts a block; any other non-empty A (title,
 * header, section, district) ends the current block; an empty A with D/F/G/H all
 * empty is a filler row; an empty A with anything in D/F/G/H continues the block.
 * Structural problems throw RuntimeException naming sheet and cell; nothing is written here.
 */
final class RoadmapProgressReader
{
    /**
     * @return array{period: ?string, blocks: array<string, array{sheet: string, row: int, lines: list<array{row: int, label: string, unit: ?string, plan: ?float, actual: ?float, note: ?string}>}>}
     */
    public function read(Spreadsheet $book): array
    {
        $period = null;
        $blocks = [];
        foreach ($book->getAllSheets() as $sheet) {
            if ($sheet->getTitle() === RoadmapTemplateWriter::INSTRUCTIONS_TITLE) {
                continue;
            }
            $sheetPeriod = self::periodOf($sheet);
            if ($sheetPeriod !== null) {
                if ($period !== null && $sheetPeriod !== $period) {
                    throw new RuntimeException("«{$sheet->getTitle()}» варағи {$sheetPeriod} даври учун, биринчи варақ {$period} учун.");
                }
                $period = $sheetPeriod;
            }
            $this->readSheet($sheet, $blocks);
        }

        return ['period' => $period, 'blocks' => $blocks];
    }

    /** The period written into the title row by roadmap:template («… Ҳисобот даври: 2026-09»). */
    public static function periodOf(Worksheet $sheet): ?string
    {
        $title = (string) $sheet->getCell('A1')->getValue();
        if (preg_match('/Ҳисобот даври:\s*(\S+)/u', $title, $m) === 1 && RoadmapPeriod::isValid($m[1])) {
            return $m[1];
        }

        return null;
    }

    /** Accepts 7,8 · 7.8 · 1 240 · 1 240,5 (spaces and NBSP removed, comma → dot); anything else non-empty throws. */
    public static function number(mixed $v, string $where): ?float
    {
        if (self::blank($v)) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = str_replace(',', '.', (string) preg_replace('/[ \x{00A0}]/u', '', (string) $v));
        if (! is_numeric($s)) {
            throw new RuntimeException("{$where}: «{$v}» рақам эмас.");
        }

        return (float) $s;
    }

    /** @param array<string, array{sheet: string, row: int, lines: list<array<string, mixed>>}> $blocks */
    private function readSheet(Worksheet $sheet, array &$blocks): void
    {
        $name    = $sheet->getTitle();
        $current = null;
        $last    = $sheet->getHighestDataRow();

        for ($r = 1; $r <= $last; $r++) {
            $a = self::text($sheet, "A{$r}");
            if ($a !== '' && RoadmapKey::isKey($a)) {
                $a = RoadmapKey::canonical($a);                    // hand-edited padding («1733-05-0-2») must still match
                if (isset($blocks[$a])) {
                    throw new RuntimeException("{$name}!A{$r}: калит {$a} файлда иккинчи марта учради.");
                }
                $blocks[$a] = ['sheet' => $name, 'row' => $r, 'lines' => []];
                $current    = $a;
            } elseif ($a !== '') {
                $current = null;                                   // title, header, section or district row
                continue;
            }

            $label     = self::text($sheet, "D{$r}");
            $unit      = self::text($sheet, "E{$r}");
            $planRaw   = $sheet->getCell("F{$r}")->getValue();
            $actualRaw = $sheet->getCell("G{$r}")->getValue();
            $note      = self::text($sheet, "H{$r}");
            if ($label === '' && self::blank($planRaw) && self::blank($actualRaw) && $note === '') {
                continue;                                          // filler row — the block stays open
            }
            if ($current === null) {
                throw new RuntimeException("{$name}!{$r}: индикатор қатори калитсиз (A устуни бўш).");
            }
            if ($label === '') {
                throw new RuntimeException("{$name}!D{$r}: индикатор номи бўш.");
            }

            $blocks[$current]['lines'][] = [
                'row'    => $r,
                'label'  => mb_substr($label, 0, 255),
                'unit'   => $unit === '' ? null : mb_substr($unit, 0, 48),
                'plan'   => self::number($planRaw, "{$name}!F{$r}"),
                'actual' => self::number($actualRaw, "{$name}!G{$r}"),
                'note'   => $note === '' ? null : mb_substr($note, 0, 500),
            ];
        }
    }

    private static function text(Worksheet $sheet, string $coord): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $sheet->getCell($coord)->getValue()));
    }

    private static function blank(mixed $v): bool
    {
        return $v === null || trim((string) $v) === '';
    }
}
```

- [ ] **Step 4: Implement the command**

`app/Console/Commands/ImportRoadmapProgress.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Roadmap;
use App\Models\RoadmapMeasureLine;
use App\Services\Roadmaps\MeasureRecomputer;
use App\Services\Roadmaps\RoadmapProgressReader;
use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Reads a road-map template back: the rows define the indicator lines (label / unit /
 * plan, by order inside the measure block) AND carry the period's actuals. The same
 * command serves both steps of the loop — the reviewed empty file (definitions only)
 * and the file the region returns (actuals). Idempotent per (file, period).
 */
class ImportRoadmapProgress extends Command
{
    protected $signature = 'import:roadmap-progress
        {--file= : The (filled) template .xlsx}
        {--period= : YYYY-MM or YYYY-Qn (default: the «Ҳисобот даври» in the sheet title)}
        {--year=2026 : Road-map year}
        {--domain=water : Road-map family}
        {--dry-run : Validate and report without writing}';

    protected $description = 'Import indicator line definitions and period actuals from a road-map template.';

    public function handle(): int
    {
        $file = (string) $this->option('file');
        if ($file === '' || ! is_file($file)) {
            $this->error("Файл топилмади: {$file}");
            return self::FAILURE;
        }

        try {
            $parsed = (new RoadmapProgressReader())->read(IOFactory::load($file));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $period = (string) ($this->option('period') ?: $parsed['period']);
        if (! RoadmapPeriod::isValid($period)) {
            $this->error('Provide --period as YYYY-MM or YYYY-Qn, or a sheet title carrying «Ҳисобот даври: YYYY-MM».');
            return self::FAILURE;
        }
        if ($this->option('period') && $parsed['period'] !== null && $parsed['period'] !== $period) {
            $this->error("--period {$period} does not match the file's own period {$parsed['period']}.");
            return self::FAILURE;
        }
        if ($parsed['blocks'] === []) {
            $this->error('No indicator rows found (no keys in column A).');
            return self::FAILURE;
        }

        $domain = (string) $this->option('domain');
        $year   = (int) $this->option('year');

        // Resolve every key before writing anything.
        $byRegion = [];
        foreach ($parsed['blocks'] as $key => $block) {
            $byRegion[RoadmapKey::parse($key)['region']][$key] = $block;
        }
        $work = [];
        foreach ($byRegion as $regionCode => $blocks) {
            $roadmap = Roadmap::where('domain', $domain)->where('region_code', $regionCode)->where('year', $year)
                ->with(['region', 'measures.district', 'measures.lines.progress'])->first();
            if (! $roadmap) {
                $this->error("Region {$regionCode} has no road map for {$domain}/{$year} (keys like " . array_key_first($blocks) . ') — run import:roadmap first.');
                return self::FAILURE;
            }
            $measures = [];
            foreach ($roadmap->measures as $m) {
                $measures[RoadmapKey::make($regionCode, $m->section_no, $m->district?->code, $m->seq_no)] = $m;
            }
            $items = [];
            foreach ($blocks as $key => $block) {
                $m = $measures[$key] ?? null;
                if (! $m) {
                    $this->error("{$block['sheet']}!A{$block['row']}: unknown key {$key} — the road map has no such measure.");
                    return self::FAILURE;
                }
                if ($block['lines'] === [] && $m->lines->isNotEmpty()) {
                    $this->error("{$block['sheet']}!A{$block['row']}: measure {$key} has {$m->lines->count()} stored lines but no rows in the file — truncated file?");
                    return self::FAILURE;
                }
                $items[] = [$m, $block];
            }
            $work[] = ['roadmap' => $roadmap, 'items' => $items];
        }

        $summary    = [];
        $removedAll = 0;
        DB::beginTransaction();
        try {
            $recomputer = new MeasureRecomputer();
            foreach ($work as $entry) {
                $s = ['measures' => 0, 'lines' => 0, 'removed' => 0, 'reported' => 0, 'done' => 0, 'in_progress' => 0, 'open' => 0];
                foreach ($entry['items'] as [$measure, $block]) {
                    $s['measures']++;
                    $stored = $measure->lines->keyBy('line_no');
                    foreach ($block['lines'] as $i => $l) {
                        $no   = $i + 1;
                        $line = $stored->get($no) ?? new RoadmapMeasureLine(['roadmap_measure_id' => $measure->id, 'line_no' => $no]);
                        $line->fill(['label' => $l['label'], 'unit' => $l['unit'], 'plan_value' => $l['plan']])->save();

                        $progress = $line->progress()->firstOrNew(['report_period' => $period]);
                        $progress->fill([
                            'period_type'  => RoadmapPeriod::type($period),
                            'actual_value' => $l['actual'],
                            'pct_of_plan'  => MeasureRecomputer::pctOfPlan($l['plan'], $l['actual']),
                            'note'         => $l['note'],
                            'reported_at'  => now()->toDateString(),
                        ])->save();

                        $s['lines']++;
                        if ($l['actual'] !== null) {
                            $s['reported']++;
                        }
                    }
                    if ($block['lines'] !== []) {
                        $s['removed'] += $measure->lines()->where('line_no', '>', count($block['lines']))->delete();
                    }
                    $measure->unsetRelation('lines');
                    $recomputer->recompute($measure, $entry['roadmap']->year);
                    $s[$measure->status]++;
                }
                $removedAll += $s['removed'];
                $summary[]   = [$entry['roadmap']->region->name_full, $s['measures'], $s['lines'], $s['removed'], $s['reported'], $s['done'], $s['in_progress'], $s['open']];
            }
            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Вилоят', 'Тадбирлар', 'Қаторлар', 'Ўчирилди', 'Амалда', 'done', 'in_progress', 'open'], $summary);
        if ($removedAll > 0) {
            $this->warn("{$removedAll} line(s) removed — no longer in the file (their history went with them).");
        }
        $this->info("Period {$period}: " . count($parsed['blocks']) . ' measure(s) processed from ' . basename($file) . '.');
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=ImportRoadmapProgressTest`
Expected: PASS (3 tests). If `insertNewRowBefore` shifts the merged ranges so that `A8:A9` becomes `A8:A10`, that is fine — the reader only looks at values.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Roadmaps/RoadmapProgressReader.php app/Console/Commands/ImportRoadmapProgress.php tests/Feature/Roadmaps/ImportRoadmapProgressTest.php
git commit -m "feat(roadmaps): import:roadmap-progress reads line definitions and period actuals from the template

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 9: `import:roadmap` upserts measures by position

**Files:**
- Modify: `app/Console/Commands/ImportRoadmap.php` (the `DB::transaction` block in `handle()` and the imports)
- Test: `tests/Feature/Roadmaps/ImportRoadmapTest.php` (append)

- [ ] **Step 1: Append the failing test**

Append to `tests/Feature/Roadmaps/ImportRoadmapTest.php` (add `use App\Models\RoadmapLineProgress;` and `use App\Models\RoadmapMeasureLine;` to the imports at the top):

```php
test('re-import keeps measure ids and their monitoring rows; a vanished position is removed with a notice', function () {
    $this->seed();
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapKhorezmFixture()]);
    $ids  = RoadmapMeasure::orderBy('source_row')->pluck('id')->all();
    $line = RoadmapMeasure::orderBy('source_row')->first()->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 1]);
    $line->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 1, 'pct_of_plan' => 100]);

    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapKhorezmFixture()]);

    expect(RoadmapMeasure::orderBy('source_row')->pluck('id')->all())->toBe($ids);
    expect(RoadmapMeasureLine::count())->toBe(1);
    expect(RoadmapLineProgress::count())->toBe(1);
    expect(Artisan::output())->not->toContain('removed');

    $shorter = roadmapKhorezmFixture([
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['«Куловот» каналини реконструкция қилиш — янги матн.'], ['x'], ['2026 йил декабрь'], ['y']],
    ]);
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $shorter]);

    expect(RoadmapMeasure::count())->toBe(1);
    expect(RoadmapMeasure::first()->id)->toBe($ids[0]);
    expect(RoadmapMeasure::first()->title)->toBe('«Куловот» каналини реконструкция қилиш — янги матн.');
    expect(Artisan::output())->toContain('3 measure(s) removed');
    expect(RoadmapMeasureLine::count())->toBe(1);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=ImportRoadmapTest`
Expected: the new test FAILS (ids differ / line count 0); the older tests still pass.

- [ ] **Step 3: Replace the write block**

In `app/Console/Commands/ImportRoadmap.php` add `use App\Models\RoadmapMeasure;` and replace the `DB::transaction(...)` call plus the following `$this->info('Imported …')` line with:

```php
        $removed = 0;
        DB::transaction(function () use ($parsed, $domain, $regionCode, $year, $file, &$removed): void {
            $roadmap = Roadmap::updateOrCreate(
                ['domain' => $domain, 'region_code' => $regionCode, 'year' => $year],
                [
                    'title_text'     => $parsed['title_text'],
                    'approvers_text' => $parsed['approvers_text'],
                    'source_file'    => basename($file),
                    'imported_at'    => now(),
                ],
            );

            // Upsert by position so measure ids — and the indicator lines / progress hanging
            // off them — survive a re-import; only positions that vanished are deleted.
            $existing = $roadmap->measures()->get()
                ->keyBy(fn (RoadmapMeasure $m) => self::position($m->section_no, $m->district_id, $m->seq_no));
            $kept = [];
            foreach ($parsed['measures'] as $data) {
                $pos        = self::position($data['section_no'], $data['district_id'], $data['seq_no']);
                $kept[$pos] = true;
                if ($row = $existing->get($pos)) {
                    $row->fill($data)->save();
                } else {
                    $roadmap->measures()->create($data);
                }
            }
            $gone    = $existing->filter(fn (RoadmapMeasure $m, string $pos) => ! isset($kept[$pos]));
            $removed = $gone->count();
            if ($removed > 0) {
                RoadmapMeasure::whereIn('id', $gone->pluck('id'))->delete();
            }
        });

        $this->info('Imported ' . count($parsed['measures']) . " measures for {$region->name_full}.");
        if ($removed > 0) {
            $this->warn("{$removed} measure(s) removed — their indicator lines and progress with them.");
        }
```

and add the helper at the bottom of the class:

```php
    private static function position(int $sectionNo, ?int $districtId, int $seqNo): string
    {
        return $sectionNo . ':' . ($districtId ?? 0) . ':' . $seqNo;
    }
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter="ImportRoadmapTest|RoadmapsPageTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ImportRoadmap.php tests/Feature/Roadmaps/ImportRoadmapTest.php
git commit -m "fix(roadmaps): docx re-import upserts measures by position so monitoring rows survive

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 10: `MeasureDisplay` helpers + `RoadmapsPage` status filter and aggregates

**Files:**
- Create: `app/Support/Roadmaps/MeasureDisplay.php`
- Modify: `app/Livewire/RoadmapsPage.php`
- Test: `tests/Unit/Roadmaps/MeasureDisplayTest.php`, `tests/Feature/Roadmaps/RoadmapsPageTest.php` (append; the rendering assertions of this task pass only after Task 11's blade — run both tasks before expecting green)

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/Roadmaps/MeasureDisplayTest.php`:

```php
<?php

use App\Support\Roadmaps\MeasureDisplay;

test('displayed percent caps a not-done measure at 99 and keeps null', function () {
    expect(MeasureDisplay::pshow(100.0, false))->toBe(99);
    expect(MeasureDisplay::pshow(100.0, true))->toBe(100);
    expect(MeasureDisplay::pshow(58.6, false))->toBe(59);
    expect(MeasureDisplay::pshow(null, false))->toBeNull();
});

test('line tier and bar width on the 120 % scale', function () {
    expect(MeasureDisplay::tier(null))->toBe('none');
    expect(MeasureDisplay::tier(10.0))->toBe('red');
    expect(MeasureDisplay::tier(50.0))->toBe('amber');
    expect(MeasureDisplay::tier(100.0))->toBe('green');
    expect(MeasureDisplay::barWidth(null))->toBe(0.0);
    expect(MeasureDisplay::barWidth(60.0))->toBe(50.0);
    expect(MeasureDisplay::barWidth(240.0))->toBe(100.0);
});

test('status and deadline chips', function () {
    expect(MeasureDisplay::statusChip('done'))->toBe(['cls' => 'ok', 'label' => 'Бажарилди']);
    expect(MeasureDisplay::statusChip('in_progress'))->toBe(['cls' => 'wait', 'label' => 'Бажарилмоқда']);
    expect(MeasureDisplay::statusChip('open'))->toBe(['cls' => 'bad', 'label' => 'Бажарилмаган']);

    expect(MeasureDisplay::deadlineChip('2026 йил декабрь', 2026, 'done', '2026-09'))->toBe(['cls' => 'done', 'label' => '✓ 2026 йил декабрь']);
    expect(MeasureDisplay::deadlineChip('2026 йил декабрь', 2026, 'in_progress', '2026-09'))->toBe(['cls' => 'soon', 'label' => '⏱ декабргача 3 ой']);
    expect(MeasureDisplay::deadlineChip('2026 йил декабрь', 2026, 'in_progress', '2026-12'))->toBe(['cls' => 'soon', 'label' => '⏱ шу ой']);
    expect(MeasureDisplay::deadlineChip('2026 йил апрель-октябрь', 2026, 'open', '2026-11'))->toBe(['cls' => 'over', 'label' => '⏱ муддат ўтган']);
});

test('sparkline points and ring offset', function () {
    expect(MeasureDisplay::sparkPoints([['period' => '2026-08', 'pct' => 0.0], ['period' => '2026-09', 'pct' => 100.0]]))->toBe('3,25 117,3');
    expect(MeasureDisplay::sparkPoints([['period' => '2026-09', 'pct' => 50.0]]))->toBe('');
    expect(MeasureDisplay::ringOffset(null))->toBe('113.1');
    expect(MeasureDisplay::ringOffset(50))->toBe('56.6');
    expect(MeasureDisplay::ringOffset(100))->toBe('0.0');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=MeasureDisplayTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `MeasureDisplay`**

`app/Support/Roadmaps/MeasureDisplay.php`:

```php
<?php

namespace App\Support\Roadmaps;

use App\Models\RoadmapMeasure;
use App\Support\SectorDisplay;

/** View helpers for the /roadmaps cards — formatting only, no status logic (that is MeasureRecomputer). */
final class MeasureDisplay
{
    private const STATUS = [
        'done'        => ['cls' => 'ok',   'label' => 'Бажарилди'],
        'in_progress' => ['cls' => 'wait', 'label' => 'Бажарилмоқда'],
        'open'        => ['cls' => 'bad',  'label' => 'Бажарилмаган'],
    ];

    private const RING_CIRC = 113.1;   // 2π × r18

    /** A measure that is not done never shows 100 % (cap 99). */
    public static function pshow(?float $pct, bool $done): ?int
    {
        return SectorDisplay::pshow($pct, $done);
    }

    /** none | red (<50) | amber (50–99) | green (≥100) — for indicator lines. */
    public static function tier(?float $pct): string
    {
        if ($pct === null) {
            return 'none';
        }

        return $pct >= 100 ? 'green' : ($pct >= 50 ? 'amber' : 'red');
    }

    /** Fill width in % of a bar whose full length is 120 % of plan (the tick at 83.33 % marks 100 %). */
    public static function barWidth(?float $pct): float
    {
        return $pct === null ? 0.0 : min(100.0, $pct / 120 * 100);
    }

    public static function fmt(null|float|int|string $v): string
    {
        return SectorDisplay::fmt($v);
    }

    /** @return array{cls: string, label: string} */
    public static function statusChip(string $status): array
    {
        return self::STATUS[$status] ?? self::STATUS['in_progress'];
    }

    /**
     * @param  string $today 'YYYY-MM' of the current month
     * @return array{cls: string, label: string}
     */
    public static function deadlineChip(?string $deadlineText, int $year, string $status, string $today): array
    {
        if ($status === 'done') {
            return ['cls' => 'done', 'label' => '✓ ' . ($deadlineText ?? '—')];
        }
        $left = RoadmapDeadline::monthsLeft($deadlineText, $year, $today);
        if ($left < 0) {
            return ['cls' => 'over', 'label' => '⏱ муддат ўтган'];
        }
        if ($left === 0) {
            return ['cls' => 'soon', 'label' => '⏱ шу ой'];
        }

        return ['cls' => 'soon', 'label' => '⏱ ' . RoadmapDeadline::untilLabel(RoadmapDeadline::month($deadlineText, $year)) . " {$left} ой"];
    }

    /**
     * One point per period (canonical order): mean over planned lines of min(100, pct ?? 0),
     * a line with no row in that period counting as 0.
     *
     * @return list<array{period: string, pct: float}>
     */
    public static function history(RoadmapMeasure $m): array
    {
        $planned = $m->lines->filter(fn ($l) => $l->plan_value !== null)->values();
        if ($planned->isEmpty()) {
            return [];
        }
        $periods = $planned->flatMap(fn ($l) => $l->progress->pluck('report_period'))->unique()->values()->all();
        usort($periods, fn (string $a, string $b) => RoadmapPeriod::monthIndex($a) <=> RoadmapPeriod::monthIndex($b));

        $out = [];
        foreach ($periods as $p) {
            $sum = 0.0;
            foreach ($planned as $l) {
                $row  = $l->progress->firstWhere('report_period', $p);
                $sum += max(0.0, min(100.0, $row?->pct_of_plan !== null ? (float) $row->pct_of_plan : 0.0));   // same share rule as MeasureRecomputer::aggregate()
            }
            $out[] = ['period' => $p, 'pct' => round($sum / $planned->count(), 1)];
        }

        return $out;
    }

    /** Polyline points for a 120 × 28 sparkline; empty below two points. */
    public static function sparkPoints(array $history): string
    {
        $n = count($history);
        if ($n < 2) {
            return '';
        }
        $pts = [];
        foreach (array_values($history) as $i => $h) {
            $x     = 3 + $i * (114 / ($n - 1));
            $y     = 25 - $h['pct'] / 100 * 22;
            $pts[] = rtrim(rtrim(number_format($x, 1, '.', ''), '0'), '.') . ',' . rtrim(rtrim(number_format($y, 1, '.', ''), '0'), '.');
        }

        return implode(' ', $pts);
    }

    /** stroke-dashoffset for the r=18 ring; null = empty ring. */
    public static function ringOffset(?int $shownPct): string
    {
        $p = $shownPct === null ? 0 : min(100, max(0, $shownPct));

        return number_format(self::RING_CIRC * (1 - $p / 100), 1, '.', '');
    }
}
```

- [ ] **Step 4: Run the unit test**

Run: `php artisan test --filter=MeasureDisplayTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Append the failing page tests**

Append to `tests/Feature/Roadmaps/RoadmapsPageTest.php` (add `use Illuminate\Support\Carbon;`, `use App\Models\RoadmapMeasure;` and `use Illuminate\Support\Facades\Artisan;` — the last one is already imported):

```php
/**
 * Monitoring rows on top of roadmapImportKhorezmPage(). Today is pinned to 2026-11-15.
 *  Куловот (I/1)   5 lines, 8 % reported in 2026-09          → in_progress, «яна 1 индикатор», «декабргача 1 ой»
 *  484,5 (I/2)     1 line done                                → done, «✓ 2026 йил декабрь»
 *  Талабалар (II/1) 1 line 30 % reported in 2026-11, Oct deadline → open, «муддат ўтган»
 *  Боғот (III/1)   2 lines, Aug + Sep history                 → in_progress 80 %, sparkline
 *  Гурлан (III/2)  no lines                                   → «Индикаторлар ҳали белгиланмаган»
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
    $l2->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 20, 'pct_of_plan' => 60.61]);

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
    $response->assertSee('⏱ декабргача 1 ой');
    $response->assertSee('✓ 2026 йил декабрь');
    $response->assertSee('⏱ муддат ўтган');
    $response->assertSee('wr-spark', false);                                   // Боғот has two periods
    $response->assertSee('1 тадбирда индикатор йўқ');
    $response->assertSee('бажарилмаган');                                     // KPI tile label
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
```

- [ ] **Step 6: Update the component**

Replace `app/Livewire/RoadmapsPage.php` with:

```php
<?php

namespace App\Livewire;

use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Support\CurrentRegion;
use App\Support\Roadmaps\RoadmapPeriod;
use App\Support\Roadmaps\Roman;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /roadmaps — the region's water-management road-map measures with their monitoring
 * state. Region comes from the session (RegionSwitcher). Filters: one section OR one
 * district (district wins), a status, plus a substring search. Rail counts, the hero
 * ring and the KPI tiles always describe the whole road map; only the list is filtered.
 * Status/pct come from the stored columns (MeasureRecomputer) — never computed here.
 */
class RoadmapsPage extends Component
{
    public const DOMAIN = 'water';
    public const YEAR   = 2026;

    public const STATUSES = ['all', 'done', 'in_progress', 'open'];

    #[Url(except: 'all')]
    public string $section = 'all';

    #[Url(except: 'all')]
    public string $district = 'all';   // districts.code as string

    #[Url(as: 'holat', except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $q = '';

    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = CurrentRegion::code();
    }

    public function selectSection(string $no): void
    {
        $this->section  = $no;
        $this->district = 'all';
    }

    public function selectDistrict(string $code): void
    {
        $this->district = $code;
        $this->section  = 'all';
    }

    public function selectStatus(string $status): void
    {
        $this->status = in_array($status, self::STATUSES, true) ? $status : 'all';
    }

    public function clearFilters(): void
    {
        $this->section  = 'all';
        $this->district = 'all';
        $this->status   = 'all';
        $this->q        = '';
    }

    public function render()
    {
        $region  = Region::where('code', $this->regionCode)->firstOrFail();
        $roadmap = Roadmap::where('domain', self::DOMAIN)
            ->where('region_code', $this->regionCode)
            ->where('year', self::YEAR)
            ->first();

        if (! $roadmap) {
            return view('livewire.roadmaps-page', ['roadmap' => null, 'region' => $region]);
        }

        $all = $roadmap->measures()->with(['district', 'lines.progress'])->orderBy('source_row')->get();

        $sections = $all->groupBy('section_no')->sortKeys()->map(fn (Collection $g, int $no) => [
            'no'    => $no,
            'roman' => self::roman($no),
            'title' => $g->first()->section_title,
            'count' => $g->count(),
        ])->values();

        $districtSectionNo = $all->first(fn (RoadmapMeasure $m) => $m->district_id !== null)?->section_no;

        $districts = $all->whereNotNull('district_id')->groupBy('district_id')->map(fn (Collection $g) => [
            'code'  => $g->first()->district->code,
            'name'  => $g->first()->district->name_full,
            'head'  => $g->first()->district_head_text,
            'count' => $g->count(),
            'pct'   => self::meanPct($g),
        ])->values();

        if ($this->district !== 'all' && ! $districts->contains(fn (array $d) => (string) $d['code'] === $this->district)) {
            $this->district = 'all';
        }
        if ($this->section !== 'all' && ! $sections->contains(fn (array $s) => (string) $s['no'] === $this->section)) {
            $this->section = 'all';
        }
        if (! in_array($this->status, self::STATUSES, true)) {
            $this->status = 'all';
        }

        $rows = $all;
        if ($this->district !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => $m->district && (string) $m->district->code === $this->district);
        } elseif ($this->section !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => (string) $m->section_no === $this->section);
        }
        if ($this->status !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => $m->status === $this->status);
        }
        $needle = mb_strtolower(trim($this->q));
        if ($needle !== '') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => str_contains(
                mb_strtolower(implode(' ', [$m->title, $m->details, $m->responsible_text, $m->funding_text])),
                $needle,
            ));
        }

        $groups = [];
        foreach ($rows as $m) {
            $key = $m->section_no . ':' . ($m->district_id ?? 0);
            $groups[$key] ??= [
                'key'           => $key,
                'roman'         => self::roman($m->section_no),
                'section_title' => $m->section_title,
                'district'      => $m->district,
                'head'          => $m->district_head_text,
                'measures'      => [],
            ];
            $groups[$key]['measures'][] = $m;
        }

        $counts = ['all' => $all->count()];
        foreach (['done', 'in_progress', 'open'] as $s) {
            $counts[$s] = $all->where('status', $s)->count();
        }

        return view('livewire.roadmaps-page', [
            'roadmap'           => $roadmap,
            'region'            => $region,
            'sections'          => $sections,
            'districts'         => $districts,
            'districtSectionNo' => $districtSectionNo,
            'groups'            => array_values($groups),
            'counts'            => $counts,
            'hero'              => [
                'pct'         => self::meanPct($all),
                'lines_total' => (int) $all->sum('lines_total'),
                'lines_done'  => (int) $all->sum('lines_done'),
                'no_lines'    => $all->where('lines_total', 0)->count(),
                'period'      => RoadmapPeriod::label(RoadmapPeriod::latest($all->pluck('latest_period')->filter()->all())),
            ],
            'today'             => now()->format('Y-m'),
            'shown'             => $rows->count(),
            'filtered'          => $this->section !== 'all' || $this->district !== 'all' || $this->status !== 'all' || $needle !== '',
        ]);
    }

    /** Mean of pct (unreported = 0) over measures that have planned lines; null when none has. */
    private static function meanPct(Collection $measures): ?int
    {
        $withLines = $measures->filter(fn (RoadmapMeasure $m) => (int) $m->lines_total > 0);
        if ($withLines->isEmpty()) {
            return null;
        }

        return (int) round($withLines->avg(fn (RoadmapMeasure $m) => (float) ($m->pct ?? 0)));
    }

    public static function roman(int $n): string
    {
        return Roman::of($n);
    }
}
```

- [ ] **Step 7: Run the page tests (expect the rendering ones to fail until Task 11)**

Run: `php artisan test --filter=RoadmapsPageTest`
Expected: the filter test's `assertSet('status', …)` parts pass; assertions on `wr-mcard`, chips, «яна 1 индикатор» etc. FAIL because the blade is still the old one. That is the expected state before Task 11. The older tests must still pass.

- [ ] **Step 8: Commit**

```bash
git add app/Support/Roadmaps/MeasureDisplay.php app/Livewire/RoadmapsPage.php tests/Unit/Roadmaps/MeasureDisplayTest.php tests/Feature/Roadmaps/RoadmapsPageTest.php
git commit -m "feat(roadmaps): status filter, roadmap aggregates and card display helpers

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 11: Layout-B blade and `wr-` CSS

**Files:**
- Modify: `resources/views/livewire/roadmaps-page.blade.php` (full replacement)
- Modify: `public/css/portal.css` (the `wr-` block: edit `.wr-group`/`.wr-gtitle`, delete the `.wr-card` rules, append the new rules)
- Test: `tests/Feature/Roadmaps/RoadmapsPageTest.php` (written in Task 10)

- [ ] **Step 1: Confirm the page tests fail on the old markup**

Run: `php artisan test --filter=RoadmapsPageTest`
Expected: «cards carry ring…», «status filter…» and «registry face…» FAIL on `wr-mcard` / chip / «яна 1 индикатор» assertions.

- [ ] **Step 2: Replace the blade**

`resources/views/livewire/roadmaps-page.blade.php`:

```blade
@php
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
@endphp
<div class="wr-shell">
  @if(! $roadmap)
    <div class="wr-empty">
      <h2>{{ $region->name_full }} учун сув хўжалиги йўл харитаси ҳали юкланмаган</h2>
      <p>Импорт: <code>php artisan import:roadmap --region={{ $region->code }}</code></p>
    </div>
  @else
    <aside class="wr-rail">
      <div class="wr-search">
        <input type="search" placeholder="Чора-тадбир, масъул, манба…"
               wire:model.live.debounce.500ms="q" aria-label="Чора-тадбирлар бўйича қидирув">
      </div>

      <div class="wr-hero">
        <div class="ht"><span class="kt">Умумий ижро</span><span class="per">{{ $hero['period'] }}</span></div>
        <div class="hb">
          <div class="wr-ring big" role="img" aria-label="Умумий ижро {{ $hero['pct'] === null ? 'маълумот йўқ' : $hero['pct'] . '%' }}">
            <svg viewBox="0 0 104 104">
              <circle class="tr" cx="52" cy="52" r="45"/>
              <circle class="fg" cx="52" cy="52" r="45"
                      style="stroke-dasharray:282.7;stroke-dashoffset:{{ number_format(282.7 * (1 - min(100, $hero['pct'] ?? 0) / 100), 1, '.', '') }}"/>
            </svg>
            <div class="cv"><b class="tnum">{{ $hero['pct'] === null ? '—' : $hero['pct'] . '%' }}</b></div>
          </div>
          <div class="hs">
            <div><b class="tnum">{{ $counts['done'] }}<small>/{{ $counts['all'] }}</small></b><span>тадбир бажарилди</span></div>
            <div><b class="tnum">{{ $hero['lines_done'] }}<small>/{{ $hero['lines_total'] }}</small></b><span>индикатор</span></div>
            @if($hero['no_lines'] > 0)<div class="nl">{{ $hero['no_lines'] }} тадбирда индикатор йўқ</div>@endif
          </div>
        </div>
      </div>

      <nav class="wr-kcard wr-fbtns" aria-label="Ҳолат бўйича фильтр">
        @foreach(['all' => 'Барчаси', 'done' => 'Бажарилди', 'in_progress' => 'Бажарилмоқда', 'open' => 'Бажарилмаган'] as $key => $label)
          <button type="button" class="f-{{ $key }} {{ $status === $key ? 'on' : '' }}"
                  aria-pressed="{{ $status === $key ? 'true' : 'false' }}"
                  wire:click="selectStatus('{{ $key }}')"><i></i>{{ $label }}<span class="n tnum">{{ $counts[$key] }}</span></button>
        @endforeach
      </nav>

      <nav class="wr-kcard" aria-label="Бўлимлар">
        <div class="kt">Бўлимлар</div>
        @php $allOn = $section === 'all' && $district === 'all'; @endphp
        <button type="button" class="{{ $allOn ? 'on' : '' }}" aria-pressed="{{ $allOn ? 'true' : 'false' }}"
                wire:click="selectSection('all')">Барчаси<span class="n tnum">{{ $counts['all'] }}</span></button>
        @foreach($sections as $s)
          @php $on = $district === 'all' ? $section === (string) $s['no'] : $districtSectionNo === $s['no']; @endphp
          <button type="button" class="{{ $on ? 'on' : '' }}" title="{{ $s['title'] }}"
                  aria-pressed="{{ $on ? 'true' : 'false' }}"
                  wire:click="selectSection('{{ $s['no'] }}')">
            <b>{{ $s['roman'] }}.</b> <span class="t">{{ $s['title'] }}</span><span class="n tnum">{{ $s['count'] }}</span>
          </button>
        @endforeach
      </nav>

      @if($districts->isNotEmpty())
        <nav class="wr-kcard" aria-label="Туманлар">
          <div class="kt">Туманлар</div>
          @foreach($districts as $d)
            @php $dOn = $district === (string) $d['code']; @endphp
            <button type="button" class="wr-drow {{ $dOn ? 'on' : '' }}"
                    title="{{ $d['count'] }} та чора-тадбир{{ $d['head'] ? ' · ' . $d['head'] : '' }}"
                    aria-pressed="{{ $dOn ? 'true' : 'false' }}"
                    wire:click="selectDistrict('{{ $d['code'] }}')">
              <span class="t">{{ $d['name'] }}</span>
              <span class="mb" aria-hidden="true"><i style="width:{{ $d['pct'] ?? 0 }}%"></i></span>
              <span class="p tnum">{{ $d['pct'] === null ? '—' : $d['pct'] . '%' }}</span>
            </button>
          @endforeach
        </nav>
      @endif
    </aside>

    <main class="wr-main">
      <header class="wr-head">
        <h2>Сув хўжалиги йўл харитаси <span class="rg">· {{ $region->name_full }}</span></h2>
      </header>

      <div class="wr-kpis">
        <div class="wr-kpi"><b class="tnum">{{ $counts['all'] }}</b><span>жами чора-тадбир</span></div>
        <div class="wr-kpi ok"><b class="tnum">{{ $counts['done'] }}</b><span>бажарилди</span></div>
        <div class="wr-kpi wait"><b class="tnum">{{ $counts['in_progress'] }}</b><span>бажарилмоқда</span></div>
        <div class="wr-kpi bad"><b class="tnum">{{ $counts['open'] }}</b><span>бажарилмаган</span></div>
      </div>

      @if($filtered)
        <div class="wr-filterbar">
          <span>Кўрсатилмоқда: <b class="tnum">{{ $shown }}</b> / {{ $counts['all'] }}</span>
          <button type="button" wire:click="clearFilters">Фильтрни тозалаш</button>
        </div>
      @endif

      @forelse($groups as $g)
        <section class="wr-group" wire:key="wr-group-{{ $g['key'] }}">
          <h3 class="wr-gtitle">
            <span class="rn">{{ $g['roman'] }}.</span> <span class="st">{{ $g['section_title'] }}</span>
            @if($g['district'])
              <span class="sep">·</span> <span class="dn">{{ $g['district']->name_full }}</span>
              @if($g['head'])<span class="hd">{{ $g['head'] }}</span>@endif
            @endif
          </h3>
          <div class="wr-cards">
            @foreach($g['measures'] as $m)
              @php
                $docLines = $m->detailLines();
                $chip     = MeasureDisplay::statusChip($m->status);
                $shown    = MeasureDisplay::pshow($m->pct !== null ? (float) $m->pct : null, $m->status === 'done');
                $latest   = $m->latest_period;
                $lineRows = $m->lines->map(function ($l) use ($latest) {
                    $p   = $latest ? $l->progress->firstWhere('report_period', $latest) : null;
                    $pct = $p?->pct_of_plan !== null ? (float) $p->pct_of_plan : null;
                    return [
                        'label' => $l->label, 'unit' => $l->unit, 'plan' => $l->plan_value, 'actual' => $p?->actual_value,
                        'pct' => $pct, 'tier' => MeasureDisplay::tier($pct), 'width' => MeasureDisplay::barWidth($pct), 'note' => $p?->note,
                    ];
                });
                $extra    = max(0, $lineRows->count() - 4);
                $deadline = MeasureDisplay::deadlineChip($m->deadline_text, $roadmap->year, $m->status, $today);
                $notes    = $lineRows->filter(fn ($r) => $r['note'] !== null && $r['note'] !== '');
                $history  = MeasureDisplay::history($m);
                $spark    = MeasureDisplay::sparkPoints($history);
                $sparkEnd = $spark === '' ? [0, 0] : explode(',', Str::afterLast($spark, ' '));
                $moreLabel = $docLines !== [] ? 'Батафсил (' . count($docLines) . ' банд)' : 'Батафсил';
                $hasMore  = $docLines !== [] || $notes->isNotEmpty() || $spark !== '';
              @endphp
              <article class="wr-mcard st-{{ $chip['cls'] }}" wire:key="wr-m-{{ $m->id }}" x-data="{ open: false, all: false }">
                <div class="wr-ring {{ $lineRows->isEmpty() ? 'na' : '' }}" role="img"
                     aria-label="Бажарилиш {{ $shown === null ? 'маълумот йўқ' : $shown . '%' }}">
                  <svg viewBox="0 0 44 44">
                    <circle class="tr" cx="22" cy="22" r="18"/>
                    <circle class="fg" cx="22" cy="22" r="18" style="stroke-dasharray:113.1;stroke-dashoffset:{{ MeasureDisplay::ringOffset($shown) }}"/>
                  </svg>
                  <b class="tnum">{{ $shown === null ? '—' : $shown . '%' }}</b>
                </div>
                <div class="body">
                  <div class="head">
                    <span class="no tnum">{{ $m->seq_no }}</span>
                    <div class="ttl">{{ $m->title }}</div>
                    <span class="wr-status {{ $chip['cls'] }}"><i></i>{{ $chip['label'] }}</span>
                  </div>

                  @if($lineRows->isEmpty())
                    <div class="wr-line none"><span class="lb">Индикаторлар ҳали белгиланмаган</span></div>
                  @else
                    @foreach($lineRows as $i => $r)
                      <div class="wr-line t-{{ $r['tier'] }}" @if($i >= 4) x-show="all" x-cloak @endif>
                        <span class="lb" title="{{ $r['label'] }}">{{ $r['label'] }}</span>
                        <span class="bar" aria-hidden="true"><i style="width:{{ $r['width'] }}%"></i><span class="tick"></span></span>
                        <span class="pv tnum"><b>{{ MeasureDisplay::fmt($r['actual']) }}</b> / {{ MeasureDisplay::fmt($r['plan']) }} {{ $r['unit'] }}</span>
                        <span class="pp tnum">{{ $r['pct'] === null ? '—' : round($r['pct']) . '%' }}</span>
                      </div>
                    @endforeach
                    @if($extra > 0)
                      <button type="button" class="wr-more" aria-expanded="false" x-on:click="all = !all" :aria-expanded="all">
                        <span class="c" :class="all && 'open'">▸</span>
                        <span x-text="all ? 'Камроқ' : 'яна {{ $extra }} индикатор'">яна {{ $extra }} индикатор</span>
                      </button>
                    @endif
                  @endif

                  <div class="foot">
                    <span class="wr-tag {{ $deadline['cls'] }}">{{ $deadline['label'] }}</span>
                    @if($m->funding_text)
                      <span class="wr-chip {{ mb_stripos($m->funding_text, 'талаб этилмайди') !== false ? 'muted' : '' }}" title="{{ $m->funding_text }}">{{ $m->funding_text }}</span>
                    @endif
                    @if($m->responsible_text)
                      <span class="wr-chip" title="{{ $m->responsible_text }}">{{ mb_strimwidth($m->responsible_text, 0, 60, '…') }}</span>
                    @endif
                  </div>

                  @if($hasMore)
                    <button type="button" class="wr-more" aria-expanded="false" x-on:click="open = !open" :aria-expanded="open">
                      <span class="c" :class="open && 'open'">▸</span>
                      <span x-text="open ? 'Ёпиш' : '{{ $moreLabel }}'">{{ $moreLabel }}</span>
                    </button>
                    <div class="wr-details" x-show="open" x-cloak>
                      @foreach($docLines as $line)<p>{{ $line }}</p>@endforeach
                      @if($notes->isNotEmpty())
                        <div class="wr-notes">
                          @foreach($notes as $r)<p><b>{{ $r['label'] }}:</b> {{ $r['note'] }}</p>@endforeach
                        </div>
                      @endif
                      @if($spark !== '')
                        <div class="wr-spark">
                          <span class="sl">{{ RoadmapPeriod::label($history[0]['period']) }}</span>
                          <svg viewBox="0 0 120 28" width="120" height="28" aria-hidden="true">
                            <polyline points="{{ $spark }}"/>
                            <circle cx="{{ $sparkEnd[0] }}" cy="{{ $sparkEnd[1] }}" r="2.5"/>
                          </svg>
                          <span class="sr"><b class="tnum">{{ $history[array_key_last($history)]['pct'] }}%</b> · {{ RoadmapPeriod::label($history[array_key_last($history)]['period']) }}</span>
                        </div>
                      @endif
                    </div>
                  @endif
                </div>
              </article>
            @endforeach
          </div>
        </section>
      @empty
        <div class="wr-empty small">Мос чора-тадбир топилмади.</div>
      @endforelse
    </main>
  @endif
</div>
```

Also in `tests/Feature/Roadmaps/RoadmapsPageTest.php`, test «GET /roadmaps renders the rail, KPI strip and grouped cards…», change `$response->assertSee('wr-card', false);` to `$response->assertSee('wr-mcard', false);` (the old card class is gone; the substring would otherwise still match `wr-cards`).

- [ ] **Step 3: Edit the `wr-` CSS block in `public/css/portal.css`**

(a) Replace the `.wr-group{…}` rule and the `.wr-gtitle{…}` rule (the block starting `.wr-gtitle{` and ending with `border-bottom:1px solid var(--line);` + `}`) with:

```css
.wr-group{display:flex;flex-direction:column;gap:10px}
.wr-gtitle{
  display:flex;flex-wrap:wrap;align-items:baseline;gap:8px;padding:6px 4px 0;
  font-size:13.5px;font-weight:800;color:var(--ink);
}
.wr-cards{display:flex;flex-direction:column;gap:12px}
```

(b) Delete these rules entirely (the old registry card): `.wr-card{…}`, `.wr-card:first-of-type{…}`, `.wr-card .no{…}`, `.wr-card .body{…}`, `.wr-card .ttl{…}`, `.wr-card .chips{…}`, `.wr-card .col{…}`, `.wr-card .col b{…}`, and inside the `@media (max-width:1100px)` block the two lines `.wr-card{grid-template-columns:34px minmax(0,1fr)}` and `.wr-card .col{grid-column:2}`. Keep `.wr-chip`, `.wr-more`, `.wr-details`, `.wr-empty`.

(c) Append after the `.wr-details p+p{…}` rule (before `.wr-empty{`):

```css
/* ---- monitoring (2026-09-08): hero ring, status filter, district rows, measure cards ---- */
.wr-hero{border-radius:16px;padding:14px 14px 12px;color:#fff;background:linear-gradient(135deg,#1f4f95 0%,#2b61af 55%,#3a78c9 100%);box-shadow:0 8px 22px rgba(31,79,149,.28)}
.wr-hero .ht{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
.wr-hero .kt{font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.75)}
.wr-hero .per{font-size:11px;font-weight:700;background:rgba(255,255,255,.16);border-radius:999px;padding:2px 9px;white-space:nowrap}
.wr-hero .hb{display:flex;align-items:center;gap:14px}
.wr-hero .hs{min-width:0}
.wr-hero .hs b{display:block;font-size:22px;font-weight:800;letter-spacing:-.02em;line-height:1.1}
.wr-hero .hs b small{font-size:13px;font-weight:650;color:rgba(255,255,255,.6)}
.wr-hero .hs span{font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:rgba(255,255,255,.7)}
.wr-hero .hs > div+div{margin-top:8px}
.wr-hero .hs .nl{font-size:11px;color:rgba(255,255,255,.7);text-transform:none;letter-spacing:0}

.wr-ring{position:relative;flex:0 0 auto}
.wr-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.wr-ring .tr{fill:none;stroke:var(--grey-soft);stroke-width:4}
.wr-ring .fg{fill:none;stroke-width:4;stroke-linecap:round;stroke:var(--task-green);transition:stroke-dashoffset .8s ease}
.wr-ring > b{position:absolute;inset:0;display:grid;place-items:center;font-size:11px;font-weight:800;letter-spacing:-.02em;color:var(--ink)}
.wr-ring.big{width:96px;height:96px}
.wr-ring.big .tr{stroke:rgba(255,255,255,.22);stroke-width:8}
.wr-ring.big .fg{stroke:#8fd3a8;stroke-width:8}
.wr-ring.big .cv{position:absolute;inset:0;display:grid;place-items:center}
.wr-ring.big .cv b{font-size:22px;font-weight:800;letter-spacing:-.02em;color:#fff}

.wr-fbtns button i{width:8px;height:8px;border-radius:50%;flex:0 0 auto}
.wr-fbtns .f-all i{background:var(--blue)}
.wr-fbtns .f-done i{background:var(--task-green)}
.wr-fbtns .f-in_progress i{background:#7c3aed}
.wr-fbtns .f-open i{background:var(--task-red)}

.wr-kcard button.wr-drow .mb{width:36px;height:3px;border-radius:2px;background:var(--grey-soft);position:relative;flex:0 0 auto;overflow:hidden}
.wr-kcard button.wr-drow .mb i{position:absolute;inset:0 auto 0 0;background:var(--task-green);border-radius:2px}
.wr-kcard button.wr-drow .p{flex:0 0 auto;font-size:11px;font-weight:700;color:var(--muted);min-width:32px;text-align:right}
.wr-kcard button.wr-drow.on .p{color:var(--blue)}

.wr-kpi.ok b{color:var(--task-green)}
.wr-kpi.wait b{color:#7c3aed}
.wr-kpi.bad b{color:var(--task-red)}

.wr-mcard{
  display:grid;grid-template-columns:48px minmax(0,1fr);gap:12px;align-items:start;
  background:var(--paper);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow-sm);
  padding:14px 16px 12px;transition:transform .18s ease,box-shadow .18s ease;
}
.wr-mcard:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(15,42,71,.10)}
.wr-mcard .wr-ring{width:44px;height:44px;margin-top:2px}
.wr-mcard.st-wait .wr-ring .fg{stroke:#7c3aed}
.wr-mcard.st-bad .wr-ring .fg{stroke:var(--task-red)}
.wr-mcard.st-ok .wr-ring .fg{stroke:var(--task-green)}
.wr-mcard .wr-ring.na .fg{stroke:transparent}
.wr-mcard .wr-ring.na > b{color:var(--muted)}
.wr-mcard .body{min-width:0}
.wr-mcard .head{display:flex;gap:10px;align-items:flex-start}
.wr-mcard .no{font-size:11px;font-weight:800;color:var(--muted);background:var(--grey-soft);border-radius:6px;padding:3px 7px;flex:0 0 auto;margin-top:1px}
.wr-mcard .ttl{flex:1;min-width:0;font-size:13.5px;font-weight:650;line-height:1.45;color:var(--ink);overflow-wrap:anywhere}
.wr-status{flex:0 0 auto;display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;white-space:nowrap}
.wr-status i{width:6px;height:6px;border-radius:50%;background:currentColor}
.wr-status.ok{background:var(--green-soft);color:var(--task-green)}
.wr-status.wait{background:#efe9fb;color:#7c3aed}
.wr-status.bad{background:#fdecea;color:var(--task-red)}

.wr-line{display:grid;grid-template-columns:minmax(0,1fr) 90px 128px 44px;gap:10px;align-items:center;margin-top:7px;font-size:12.5px;color:var(--muted)}
.wr-mcard .head+.wr-line{margin-top:10px}
.wr-line .lb{color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wr-line .bar{position:relative;height:4px;background:var(--grey-soft);border-radius:3px}
.wr-line .bar i{position:absolute;inset:0 auto 0 0;border-radius:3px;background:var(--task-green)}
.wr-line .bar .tick{position:absolute;top:-2px;bottom:-2px;width:1.5px;left:83.33%;background:var(--line-strong)}
.wr-line.t-amber .bar i{background:var(--task-amber)}
.wr-line.t-red .bar i{background:var(--task-red)}
.wr-line .pv{text-align:right;white-space:nowrap}
.wr-line .pv b{color:var(--ink);font-weight:700}
.wr-line .pp{text-align:right;font-weight:800}
.wr-line.t-green .pp{color:var(--task-green)}
.wr-line.t-amber .pp{color:var(--task-amber)}
.wr-line.t-red .pp{color:var(--task-red)}
.wr-line.t-none .pp{color:var(--muted);font-weight:600}
.wr-line.none{grid-template-columns:1fr;font-style:italic}
.wr-line.none .lb{color:var(--muted)}

.wr-mcard .foot{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.wr-tag{display:inline-block;font-size:11px;font-weight:700;line-height:1.35;padding:3px 9px;border-radius:9px}
.wr-tag.soon{background:#fff4e0;color:#a35e00}
.wr-tag.over{background:#fdecea;color:var(--task-red)}
.wr-tag.done{background:var(--green-soft);color:var(--task-green)}
.wr-chip.muted{opacity:.6}

.wr-notes{margin-top:6px;border-top:1px dashed var(--line);padding-top:6px}
.wr-notes p{margin:0;padding:3px 0;font-size:12px;color:var(--muted);border:0}
.wr-notes p b{color:var(--ink);font-weight:700}
.wr-spark{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:10.5px;color:var(--muted)}
.wr-spark svg{flex:0 0 auto;overflow:visible}
.wr-spark polyline{fill:none;stroke:var(--blue);stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
.wr-spark circle{fill:var(--blue)}
.wr-spark .sr b{color:var(--ink)}

@media (max-width:1100px){
  .wr-mcard{grid-template-columns:44px minmax(0,1fr)}
}
@media (max-width:600px){
  .wr-line{grid-template-columns:minmax(0,1fr) 44px}
  .wr-line .bar{display:none}
  .wr-line .pv{grid-column:1;text-align:left}
}
```

- [ ] **Step 4: Run the page tests and the whole road-map suite**

Run: `php artisan test --filter=RoadmapsPageTest` then `php artisan test --filter=Roadmap`
Expected: all PASS. Then open `http://127.0.0.1:8000/roadmaps` with `php artisan serve` running and check by eye: hero ring, status buttons with dots, district mini-bars, cards with rings, status chips, indicator rows, deadline chips, «Батафсил» with notes/sparkline. On the dev DB nothing is monitored yet, so cards show «Индикаторлар ҳали белгиланмаган» until Task 12's first template round trip.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/roadmaps-page.blade.php public/css/portal.css
git commit -m "feat(roadmaps): layout-B monitoring cards — ring, status chip, indicator rows, deadline chips, sparkline

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

### Task 12: Runbook, docs, first real template for Хоразм

**Files:**
- Create: `docs/roadmap-monitoring.md`
- Modify: `docs/roadmap-import.md` (last bullet + idempotency paragraph), `../CLAUDE.md` (two lines)

- [ ] **Step 1: Write the runbook**

`docs/roadmap-monitoring.md`:

```markdown
# Сув хўжалиги йўл хариталари — мониторинг runbook

Every measure (чора-тадбир) of a loaded road map gets **indicator lines** — label, unit,
plan — that *we* define, and the region reports the **actual** per line every month in
an xlsx we generate. Status is derived (never chosen): all planned lines ≥100 % →
Бажарилди; nothing reported → Бажарилмоқда; below plan → Бажарилмоқда until the
deadline month («2026 йил декабрь» → December), Бажарилмаган after it.

## The monthly loop

```powershell
cd backend
# 1. Template for the period (stored lines; for measures without lines, suggestions from the text)
php artisan roadmap:template --region=1733 --period=2026-09
#    → data/Сув хўжалиги бўйича йўл хариталар/мониторинг/2026-09/Хоразм.xlsx   (--all: one workbook, sheet per region)

# 2. Review the suggested lines in Excel (first month only): fix labels/units/plans, add rows
#    (leave column A empty on added rows — they belong to the block above), delete rows you
#    do not want. Measures with no quantity in the text got a «Бажарилиш даражаси · % · 100» line.
#    Add a money line («Ўзлаштирилган маблағ · млрд сўм · plan») only where it makes sense.

# 3. Store the definitions before sending (every measure becomes Бажарилмоқда):
php artisan import:roadmap-progress --file="../data/Сув хўжалиги бўйича йўл хариталар/мониторинг/2026-09/Хоразм.xlsx" --dry-run
php artisan import:roadmap-progress --file="…/2026-09/Хоразм.xlsx"

# 4. Send the same file to the region. They fill the yellow «Амалда» (and optionally «Изоҳ»)
#    cells — cumulative since the start of the year, in the unit of column E.

# 5. Import the returned file (idempotent; --period defaults to the sheet title):
php artisan import:roadmap-progress --file="…/2026-09/Хоразм (returned).xlsx"

# 6. Next month: step 1 again with --period=2026-10 — the template now carries the stored lines.
```

`php artisan roadmaps:recompute [--region=1733] [--dry-run]` rebuilds status/pct/counters
from the stored rows (after a manual DB edit or a rule change). Never re-import for that.

## File rules the import enforces

- Column A (hidden) holds the key `region-section-district-seq` (e.g. `1733-5-1733208-3`);
  rows are matched by it, not by text. An unknown key, a key appearing twice, a row with
  data but no key above it, an empty «Индикатор» on a data row, a non-numeric «Амалда»,
  or a period mismatch (`--period` vs the sheet title) aborts the whole import — nothing
  is written.
- Line identity is the **row order inside the block** (line 1, 2, …). Do not reorder
  lines between months; add new ones at the end. A block with fewer rows than stored
  lines deletes the missing lines (and their history) — the command prints how many.
  A block with **no** rows while lines are stored aborts (truncated file protection).
- Numbers accept `7,8`, `7.8`, `1 240`, `1 240,5`. Empty «Амалда» = not reported.
- Every sheet except «Йўриқнома» is read; sheet titles do not matter.

## Data model

`roadmap_measure_lines` (label/unit/plan per line) → `roadmap_line_progress` (actual,
computed pct, note per line and period). `roadmap_measures` carries the denormalised
`latest_period`, `status`, `pct` (mean of line percents capped at 100), `lines_total`,
`lines_done`. `/roadmaps` reads only these; see
`docs/superpowers/specs/2026-09-08-roadmaps-monitoring-design.md`.

## Known limitations

- The deadline parser reads month names and `N-чорак`; anything else means December.
- Suggested lines are heuristic (`24 та …`, `7,8 км …`, `26,7 минг гектар …`); bare
  `млн/млрд` without a currency and plain years are ignored on purpose.
- No period switcher on the page — history shows as a sparkline inside «Батафсил».
```

- [ ] **Step 2: Update `docs/roadmap-import.md`**

Replace the paragraph starting «The command is idempotent per (domain, region, year)» with:

```markdown
The command is idempotent per (domain, region, year): it upserts the `roadmaps` row and
upserts every measure **by position** (section, district, seq) inside one transaction —
measure ids, and the indicator lines / progress hanging off them, survive a re-import;
only positions that vanished from the document are deleted (the command says how many).
Parsing happens before any write, so a failed re-import leaves the previous import untouched.
```

Replace the last bullet («No status/progress yet …») with:

```markdown
- Monitoring (indicator lines + monthly actuals) is a separate loop — see
  `docs/roadmap-monitoring.md` (`roadmap:template`, `import:roadmap-progress`, `roadmaps:recompute`).
```

- [ ] **Step 3: Update `../CLAUDE.md`**

Replace the `/roadmaps` table row with:

```markdown
| `/roadmaps` | `RoadmapsPage` | Water-management road-map measures (сув хўжалиги йўл харитаси) for the session region (direct entry with no region chosen activates the first loaded region) with monitoring: per-measure indicator lines (plan/actual/%), derived status, rail filters by status/section/district, search; URL-access only (no sidebar link yet) |
```

Replace pipeline item 4 with:

```markdown
4. **Water road maps (сув хўжалиги йўл хариталари):** `import:roadmap --region=1733` — reads the per-region `.docx` under `data/Сув хўжалиги бўйича йўл хариталар/` (ZipArchive + DOM, header-text keyed; upserts measures by position). Monitoring loop: `roadmap:template --region=1733 --period=2026-09` writes the xlsx the region fills, `import:roadmap-progress --file=…` reads back line definitions + actuals, `roadmaps:recompute` rebuilds statuses. Runbooks: `backend/docs/roadmap-import.md`, `backend/docs/roadmap-monitoring.md`. Only Хоразм imported so far.
```

- [ ] **Step 4: Produce the first real template and review it**

Run from `backend/` (dev DB has Хоразм loaded):

```powershell
php artisan roadmap:template --region=1733 --period=2026-09
```

Expected: a table with 89 measures and the line counts, then `Written …/мониторинг/2026-09/Хоразм.xlsx`. Open the file in Excel: title row, protected sheet, yellow G/H, hidden column A, «Йўриқнома» tab. Do **not** import it yet — the user reviews the suggested lines first (step 2 of the runbook). Nothing under `data/` is committed.

- [ ] **Step 5: Full suite and commit**

Run: `php artisan test` (≈10 min, PostgreSQL running).
Expected: green. Then:

```bash
git add docs/roadmap-monitoring.md docs/roadmap-import.md ../CLAUDE.md
git commit -m "docs(roadmaps): monitoring runbook, import runbook upsert note, CLAUDE.md pipeline row

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01513E6CDpBkN4Pfv2PaNpqC"
```

---

## Deviations from the spec (decided while planning)

- Period pill wording reuses `TaskPeriod::reportPeriodLabel` («2026 йил сентябрь») instead of «2026 · сентябрь» — same wording as the tasks board.
- `roadmap_line_progress` has no separate `(line, period)` index: the unique index covers those lookups.
- The `т` (tonne) unit abbreviation is not recognised by the suggester (too many false positives); `тонна` is.
- `MeasureRecomputer::aggregate()` is pure and also returns `reported`; the persisting method lives in the same class rather than a separate service.
- A measure whose lines all lack a plan (`lines_total = 0`) is `in_progress` even when something is reported — the spec's «else open» would have called it Бажарилмаган with nothing to be behind on.
- `Roadmap::latestPeriod()` from the spec is not a model method; the page computes the latest period from the loaded measures.
- Task 11 also updates one assertion in the existing page test (`wr-card` → `wr-mcard`).
- Review-driven (Task 4): `pctOfPlan` clamps to ±`PCT_MAX`; each line contributes `max(0, min(100, pct ?? 0))` to the measure mean (a negative actual never drives a negative ring); `recompute()` returns the five computed values; a malformed stored period is reported with the measure id.
- Review-driven (Task 2/3): `RoadmapDeadline` accepts Roman quarters, whole-word month names with Uzbek endings, «NNNN-йил», and ignores years earlier than the road map's own year (decree citations); `RoadmapKey::canonical()` normalises hand-edited keys and the reader uses it.
