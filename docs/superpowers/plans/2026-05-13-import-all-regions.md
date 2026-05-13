# All-Regions Batch Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `import:all-regions {year}` artisan that loops every region in `SoatoSeeder::REGION_LATIN`, runs `import:region` → `import:promote` → `import:tasks` per region, captures status, prints a summary table, and continues past per-region failures. Remove the four-line Navoiy skip in `ImportRegionCommand`.

**Architecture:** Single new artisan command using `Artisan::call` to drive the three existing per-region commands. Per-region `try/catch` so failures don't break the batch. End-of-run Laravel `$this->table()` summary. No new migrations, models, or views.

**Tech Stack:** Laravel 11 + Pest 3 + PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-05-13-import-all-regions-design.md`

**Working directory:** `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali`. All `php artisan` / `vendor/bin/pest` commands run from inside `backend/`. All `git` commands from project root.

---

## File Structure

| File | Action |
|---|---|
| `backend/app/Console/Commands/ImportAllRegionsCommand.php` | new — orchestrator |
| `backend/app/Console/Commands/ImportRegionCommand.php` | remove 4-line Navoiy skip |
| `backend/tests/Feature/Console/ImportAllRegionsCommandTest.php` | new — signature + filter smoke |

---

### Task 1: Remove Navoiy skip in `ImportRegionCommand`

**Files:**
- Modify: `backend/app/Console/Commands/ImportRegionCommand.php`

- [ ] **Step 1: Delete the four-line guard**

Open `backend/app/Console/Commands/ImportRegionCommand.php`. Find this block (around lines 46-49):

```php
        if ($arg === 'navoiy') {
            $this->warn("Skipped 'navoiy' — see data_quality_issues for upstream macro 1.2 contamination.");
            return 0;
        }
```

Delete those four lines entirely. The blank line above (or below) can stay.

- [ ] **Step 2: Verify**

```bash
grep -n "Skipped 'navoiy'" backend/app/Console/Commands/ImportRegionCommand.php
```

Expected: no matches.

```bash
cd backend && php -l app/Console/Commands/ImportRegionCommand.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Console/Commands/ImportRegionCommand.php
git commit -m "feat(import): remove Navoiy skip — upstream data fixed"
```

---

### Task 2: Create `ImportAllRegionsCommand`

**Files:**
- Create: `backend/app/Console/Commands/ImportAllRegionsCommand.php`

- [ ] **Step 1: Create the command file**

Create `backend/app/Console/Commands/ImportAllRegionsCommand.php` with the contents below.

```php
<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\IndicatorFact;
use App\Models\Task;
use Database\Seeders\SoatoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ImportAllRegionsCommand extends Command
{
    protected $signature = 'import:all-regions
        {year=2026 : Reporting year, passed through to import:region}
        {--only= : Comma-separated region slugs or SOATO codes to limit the batch}
        {--no-tasks : Skip import:tasks calls}
        {--no-promote : Stop at staging; do not auto-promote}';

    protected $description = 'Run import:region + import:promote + import:tasks for every region.';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $slugs = $this->resolveSlugs();

        if (empty($slugs)) {
            $this->error('No regions selected. Check --only filter.');
            return self::SUCCESS;
        }

        $this->info("Importing " . count($slugs) . " region(s) for year {$year}.");

        $summary = [];
        foreach ($slugs as $slug) {
            $summary[] = $this->processRegion($slug, $year);
        }

        $this->printSummary($summary);
        return self::SUCCESS;
    }

    /** @return list<string> Region slugs in SoatoSeeder order, filtered by --only. */
    private function resolveSlugs(): array
    {
        $allSlugs = array_values(SoatoSeeder::REGION_LATIN);

        $only = (string) ($this->option('only') ?? '');
        if ($only === '') {
            return $allSlugs;
        }

        $tokens = array_map('trim', explode(',', $only));
        $selected = [];
        foreach ($tokens as $tok) {
            if ($tok === '') continue;
            if (ctype_digit($tok)) {
                $code = (int) $tok;
                if (isset(SoatoSeeder::REGION_LATIN[$code])) {
                    $selected[] = SoatoSeeder::REGION_LATIN[$code];
                }
            } elseif (in_array($tok, $allSlugs, true)) {
                $selected[] = $tok;
            }
        }
        return array_values(array_unique($selected));
    }

    /** @return array{slug:string, xlsx:string, rows_staged:int, rows_promoted:int, tasks:string, tasks_count:int, note:string} */
    private function processRegion(string $slug, int $year): array
    {
        $row = [
            'slug'          => $slug,
            'xlsx'          => 'pending',
            'rows_staged'   => 0,
            'rows_promoted' => 0,
            'tasks'         => 'pending',
            'tasks_count'   => 0,
            'note'          => '',
        ];

        $regionCode = array_search($slug, SoatoSeeder::REGION_LATIN, true) ?: null;
        if ($regionCode === null) {
            $row['xlsx'] = 'fail';
            $row['note'] = 'unknown slug';
            return $row;
        }
        $regionCode = (int) $regionCode;

        $this->line(" → {$slug} ({$regionCode}): staging…");

        try {
            $exit = Artisan::call('import:region', [
                'region_code' => $slug,
                'year' => $year,
            ]);

            if ($exit !== 0) {
                $row['xlsx'] = 'fail';
                $row['note'] = "import:region exit={$exit}";
            } else {
                $run = ImportRun::where('region_code', $regionCode)
                    ->where('year', $year)
                    ->latest('id')
                    ->first();

                if ($run === null) {
                    $row['xlsx'] = 'fail';
                    $row['note'] = 'no ImportRun found';
                } else {
                    $row['rows_staged'] = (int) $run->rows_staged;
                    $row['xlsx'] = $run->status === ImportRunStatus::AwaitingReview
                        ? 'staged'
                        : 'fail';

                    if ($row['xlsx'] === 'staged'
                        && ! $this->option('no-promote')
                        && (int) $run->issues_blocker_count === 0
                    ) {
                        $promoteExit = Artisan::call('import:promote', ['run_id' => $run->id]);
                        if ($promoteExit === 0) {
                            $row['xlsx'] = 'promoted';
                            $row['rows_promoted'] = IndicatorFact::where('region_code', $regionCode)->count();
                        } else {
                            $row['note'] = "import:promote exit={$promoteExit}";
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $row['xlsx'] = 'error';
            $row['note'] = $this->truncate($e->getMessage(), 120);
        }

        if ($this->option('no-tasks')) {
            $row['tasks'] = 'skipped';
        } else {
            try {
                $tasksExit = Artisan::call('import:tasks', ['region' => $slug]);
                if ($tasksExit === 0) {
                    $row['tasks'] = 'ok';
                    $row['tasks_count'] = Task::where('region_code', $regionCode)->count();
                } else {
                    $row['tasks'] = 'fail';
                    $row['note'] = trim($row['note'] . " import:tasks exit={$tasksExit}");
                }
            } catch (\Throwable $e) {
                $row['tasks'] = 'error';
                $row['note'] = trim($row['note'] . ' tasks: ' . $this->truncate($e->getMessage(), 80));
            }
        }

        return $row;
    }

    /** @param list<array<string, mixed>> $summary */
    private function printSummary(array $summary): void
    {
        $headers = ['region', 'xlsx', 'rows_staged', 'rows_promoted', 'tasks', 'tasks_count', 'note'];
        $rows = array_map(fn ($r) => [
            $r['slug'], $r['xlsx'], $r['rows_staged'], $r['rows_promoted'],
            $r['tasks'], $r['tasks_count'], $r['note'],
        ], $summary);

        $this->newLine();
        $this->table($headers, $rows);

        $total = count($summary);
        $xlsxOk = count(array_filter($summary, fn ($r) => $r['xlsx'] === 'promoted' || ($this->option('no-promote') && $r['xlsx'] === 'staged')));
        $tasksOk = count(array_filter($summary, fn ($r) => $r['tasks'] === 'ok'));

        $this->info("Run complete. {$xlsxOk}/{$total} xlsx ok, {$tasksOk}/{$total} tasks ok.");
    }

    private function truncate(string $s, int $n): string
    {
        return mb_strlen($s) <= $n ? $s : mb_substr($s, 0, $n) . '…';
    }
}
```

- [ ] **Step 2: Verify command registers**

```bash
cd backend && php artisan list 2>&1 | grep "import:all-regions"
```

Expected: one line containing `import:all-regions  Run import:region + import:promote + import:tasks for every region.`

- [ ] **Step 3: Verify --only filter works without seeding DB**

```bash
cd backend && php artisan import:all-regions 2026 --only=__nonexistent__ 2>&1 | tail -3
```

Expected: line `No regions selected. Check --only filter.` (because the unknown token doesn't match any slug or SOATO).

- [ ] **Step 4: Commit**

```bash
git add backend/app/Console/Commands/ImportAllRegionsCommand.php
git commit -m "feat(import): ImportAllRegionsCommand orchestrator with continue-on-failure"
```

---

### Task 3: Smoke test for the orchestrator

**Files:**
- Create: `backend/tests/Feature/Console/ImportAllRegionsCommandTest.php`

This is a light smoke test. It verifies the command exists, its signature is correct, and `--only` filters the slug list. It does NOT exercise the full per-region pipeline (that's covered by existing `ImportRegionCommandTest` variants).

- [ ] **Step 1: Write the test**

Create `backend/tests/Feature/Console/ImportAllRegionsCommandTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('command is registered with correct signature', function () {
    $output = Artisan::call('list', ['--raw' => true]);
    $registered = Artisan::output();
    expect($registered)->toContain('import:all-regions');
});

test('--only with no matching tokens exits 0 with empty-selection message', function () {
    $exit = Artisan::call('import:all-regions', [
        'year' => 2026,
        '--only' => '__none__',
    ]);

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('No regions selected');
});

test('--only=andijan does not iterate other regions', function () {
    // Seed the regions table so the orchestrator can resolve slugs to SOATO ints.
    \Illuminate\Support\Facades\DB::table('regions')->insert([
        [
            'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
            'name_latin' => 'andijan', 'sort_order' => 2,
            'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $exit = Artisan::call('import:all-regions', [
        'year' => 2026,
        '--only' => 'andijan',
        '--no-tasks' => true,
    ]);

    $output = Artisan::output();
    expect($exit)->toBe(0);
    expect($output)->toContain('andijan');
    // The summary table must not mention regions we did not request.
    expect($output)->not->toContain(' bukhara ');
    expect($output)->not->toContain(' navoi ');
});
```

- [ ] **Step 2: Run the test**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/ImportAllRegionsCommandTest.php
```

Expected: 3 tests pass.

If a test fails because the orchestrator's `import:region` call errors (e.g. no `reporting_years` row for 2026 in the test DB) — that's fine, the orchestrator's `try/catch` should catch it and the exit code stays 0. Verify by reading the test output. If the third test fails because `bukhara` text appears in the summary even though we limited to andijan, check the `--only` filter logic in `resolveSlugs()`.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Console/ImportAllRegionsCommandTest.php
git commit -m "test(import): smoke for ImportAllRegionsCommand --only filter"
```

---

### Task 4: Operator smoke run

**Files:** none.

The operator runs the orchestrator against the real data once. This isn't an automated test — it's a verify-it-works step.

- [ ] **Step 1: Reset DB**

```bash
cd backend && php artisan migrate:fresh --seed
```

Expected: 14 regions + 208 districts seeded.

- [ ] **Step 2: Dry-run with only Andijan**

```bash
cd backend && php artisan import:all-regions 2026 --only=andijan 2>&1 | tail -20
```

Expected:
- Summary table includes one row for `andijan`.
- `xlsx` column shows `promoted`.
- `tasks` column shows `ok`.
- Trailing line: `Run complete. 1/1 xlsx ok, 1/1 tasks ok.`

- [ ] **Step 3: Full run all 14 regions**

```bash
cd backend && php artisan import:all-regions 2026 2>&1 | tail -25
```

Expected: 14-row summary table; ideally all `promoted` + `ok`. If some regions show `fail` or `error`, the `note` column gives the reason. Operator inspects and reports.

- [ ] **Step 4: Spot-check counts**

```bash
cd backend && php artisan tinker --execute "
echo 'regions: ' . App\Models\Region::count() . PHP_EOL;
echo 'districts: ' . App\Models\District::count() . PHP_EOL;
echo 'indicator facts total: ' . App\Models\IndicatorFact::count() . PHP_EOL;
echo 'tasks total: ' . App\Models\Task::count() . PHP_EOL;
echo 'distinct task regions: ' . App\Models\Task::distinct()->count('region_code') . PHP_EOL;
"
```

Expected (rough):
- regions: 14
- districts: 208
- indicator facts total: several thousand
- tasks total: ~1000+ (sum across 14 task docx)
- distinct task regions: up to 14

If a region fails import, its `tasks` may still succeed; or vice versa. That's by design (independent steps per region).

- [ ] **Step 5: Optional follow-up commit if any tweak needed**

If the operator hits an edge case during the run that requires a code tweak (e.g. a region's xlsx has a column the parser doesn't handle), commit the fix:

```bash
cd backend && git add -A
git commit -m "fix(import): smoke-run touch-ups"
```

If clean, skip.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| §3 Strategy — orchestrator + iterations | Task 2 |
| §4 Command signature | Task 2 (`$signature` block) |
| §5 Iteration logic | Task 2 (`processRegion`) |
| §6 Summary output | Task 2 (`printSummary`) |
| §7 Navoiy skip removal | Task 1 |
| §8 Smoke test | Task 3 |
| §9 Files touched | each task |
| §10 Operator usage | Task 4 |
| §11 Risks (memory, double-count) | Documented in spec; smoke run in Task 4 surfaces them |

**Placeholder scan:** no TBD/handwave; every step has concrete code or commands.

**Type/name consistency:**

- `SoatoSeeder::REGION_LATIN` const referenced in Task 2 — exists from prior plan.
- `ImportRunStatus::AwaitingReview` enum case used in Task 2 — verified to exist at `backend/app/Enums/ImportRunStatus.php:8`.
- `import:region`, `import:promote`, `import:tasks` artisan commands all exist (per prior project work).
- Summary table columns (`region`, `xlsx`, `rows_staged`, `rows_promoted`, `tasks`, `tasks_count`, `note`) match between spec §6 and Task 2 implementation.
