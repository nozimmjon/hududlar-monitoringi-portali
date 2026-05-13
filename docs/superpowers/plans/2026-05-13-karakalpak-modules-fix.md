# Karakalpak budget/export/employment fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Three parser extensions + one seeder alt_label entry so Karakalpak `budget`, `export`, and `employment` modules promote nonzero rows.

**Architecture:** Each parser's `findRollupRow` (or `isRollupCell`) is widened inline to accept Karakalpak rollup conventions: `Қорақалпоғистон Республикаси` (col A or B), `Жами` / `ЖАМИ`, and `Нукус шаҳар` variant. No shared helper, no refactor — mirrors Bug D scope.

**Tech Stack:** PHP 8.3 · Laravel 11 · Pest 3 · PhpOffice/PhpSpreadsheet · PostgreSQL. All commands run from `backend/`.

---

## File structure

| File | Responsibility |
|---|---|
| `backend/app/Services/Import/Modules/BudgetModuleParser.php` | extend `findRollupRow` (col A+B, Республикаси) |
| `backend/app/Services/Import/Modules/ExportModuleParser.php` | extend `isRollupCell` (Республикаси, Жами, mb_strlen) |
| `backend/app/Services/Import/Modules/EmploymentModuleParser.php` | extend `findRollupRow` + `classifyRow` (ЖАМИ in col A or B) |
| `backend/database/seeders/SoatoSeeder.php` | add `1735401 => ['Нукус шаҳар']` to `DISTRICT_ALT_LABELS` |
| `backend/tests/Feature/Import/BudgetKarakalpakRollupTest.php` | new — 2 tests |
| `backend/tests/Feature/Import/ExportKarakalpakRollupTest.php` | new — 2 tests |
| `backend/tests/Feature/Import/EmploymentKarakalpakRollupTest.php` | new — 2 tests |

---

### Task 1: BudgetModuleParser rollup extension

**Files:**
- Modify: `backend/app/Services/Import/Modules/BudgetModuleParser.php`
- Create: `backend/tests/Feature/Import/BudgetKarakalpakRollupTest.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Import/BudgetKarakalpakRollupTest.php`:

```php
<?php

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\BudgetModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

function makeBudgetParser(): BudgetModuleParser
{
    $issues = new IssueCollector();
    return new BudgetModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        new StagingWriter(),
        $issues,
    );
}

test('BudgetModuleParser findRollupRow detects Қорақалпоғистон Республикаси rollup in col A', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A2', '2026 йилда Қорақалпоғистон Республикаси бюджетига тушумлар бўйича ПРОГНОЗ ПАРАМЕТРЛАР');
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Ҳудудлар');
    $sheet->setCellValue('A8', 'Қорақалпоғистон Республикаси');

    expect(invade(makeBudgetParser())->findRollupRow($sheet))->toBe(8);
});

test('BudgetModuleParser findRollupRow still detects Андижон вилояти in col B (no regression)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('B7', 'Андижон вилояти');

    expect(invade(makeBudgetParser())->findRollupRow($sheet))->toBe(7);
});
```

- [ ] **Step 2: Run tests → expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/BudgetKarakalpakRollupTest.php
```

Expected: first test FAIL (Karakalpak rollup not detected). Second test should PASS (Andijan case already supported).

- [ ] **Step 3: Replace `findRollupRow` in BudgetModuleParser.php (around line 62)**

```php
private function findRollupRow(Worksheet $sheet): ?int
{
    for ($row = 1; $row <= 15; $row++) {
        foreach ([1, 2] as $col) {
            $val = $sheet->getCell([$col, $row])->getCalculatedValue();
            if (! is_string($val)) continue;
            $trimmed = trim($val);
            if (mb_strlen($trimmed) > 40) continue;
            if (str_ends_with($trimmed, 'вилояти') || str_ends_with($trimmed, 'Республикаси')) {
                return $row;
            }
        }
    }
    return null;
}
```

- [ ] **Step 4: Run tests → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/BudgetKarakalpakRollupTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Import/Modules/BudgetModuleParser.php backend/tests/Feature/Import/BudgetKarakalpakRollupTest.php
git commit -m "feat(import): BudgetModuleParser accepts Республикаси rollup in col A"
```

---

### Task 2: ExportModuleParser rollup extension

**Files:**
- Modify: `backend/app/Services/Import/Modules/ExportModuleParser.php`
- Create: `backend/tests/Feature/Import/ExportKarakalpakRollupTest.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Import/ExportKarakalpakRollupTest.php`:

```php
<?php

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\ExportModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeExportParser(): ExportModuleParser
{
    $issues = new IssueCollector();
    return new ExportModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        new StagingWriter(),
        $issues,
    );
}

test('ExportModuleParser isRollupCell accepts Республикаси, Жами, and вилояти', function () {
    $parser = makeExportParser();
    expect(invade($parser)->isRollupCell('Қорақалпоғистон Республикаси'))->toBeTrue();
    expect(invade($parser)->isRollupCell('Жами'))->toBeTrue();
    expect(invade($parser)->isRollupCell('Андижон вилояти'))->toBeTrue();
});

test('ExportModuleParser isRollupCell rejects long title rows and non-strings', function () {
    $parser = makeExportParser();
    $title = 'Қорақалпоғистон Республикасининг 2026 йил январь-март ойи учун белгиланган экспорт прогнози';
    expect(invade($parser)->isRollupCell($title))->toBeFalse();
    expect(invade($parser)->isRollupCell(42))->toBeFalse();
    expect(invade($parser)->isRollupCell(null))->toBeFalse();
});
```

- [ ] **Step 2: Run tests → expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ExportKarakalpakRollupTest.php
```

Expected: first test FAIL (Республикаси and Жами not accepted yet). Long-title rejection currently relies on `strlen <= 40` — for 90+ Cyrillic chars (180+ bytes) the current `strlen` correctly rejects, so the rejection test should pass even before the mb_strlen change, but switching to mb_strlen is still required by the spec.

- [ ] **Step 3: Replace `isRollupCell` in ExportModuleParser.php (around lines 94-99)**

```php
private function isRollupCell(mixed $value): bool
{
    if (! is_string($value)) return false;
    $trimmed = trim($value);
    if (mb_strlen($trimmed) > 40) return false;
    return str_ends_with($trimmed, 'вилояти')
        || str_ends_with($trimmed, 'Республикаси')
        || $trimmed === 'Жами';
}
```

- [ ] **Step 4: Run tests → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ExportKarakalpakRollupTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Import/Modules/ExportModuleParser.php backend/tests/Feature/Import/ExportKarakalpakRollupTest.php
git commit -m "feat(import): ExportModuleParser accepts Республикаси and Жами rollup labels"
```

---

### Task 3: EmploymentModuleParser rollup extension

**Files:**
- Modify: `backend/app/Services/Import/Modules/EmploymentModuleParser.php`
- Create: `backend/tests/Feature/Import/EmploymentKarakalpakRollupTest.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Import/EmploymentKarakalpakRollupTest.php`:

```php
<?php

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\EmploymentModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

function makeEmploymentParser(): EmploymentModuleParser
{
    $issues = new IssueCollector();
    return new EmploymentModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        new StagingWriter(),
        $issues,
    );
}

test('EmploymentModuleParser findRollupRow detects ЖАМИ in col B (karakalpak layout)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('B4', 'Туман (шаҳар)номи');
    $sheet->setCellValue('B7', 'ЖАМИ');

    expect(invade(makeEmploymentParser())->findRollupRow($sheet))->toBe(7);
});

test('EmploymentModuleParser findRollupRow still detects ЖАМИ in col A (no regression)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A8', 'ЖАМИ');

    expect(invade(makeEmploymentParser())->findRollupRow($sheet))->toBe(8);
});

test('EmploymentModuleParser classifyRow returns rollup for ЖАМИ in col B', function () {
    expect(invade(makeEmploymentParser())->classifyRow(null, 'ЖАМИ'))->toBe('rollup');
    expect(invade(makeEmploymentParser())->classifyRow('ЖАМИ', null))->toBe('rollup');
    expect(invade(makeEmploymentParser())->classifyRow('1', 'Нукус шаҳри'))->toBe('district');
});
```

- [ ] **Step 2: Run tests → expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/EmploymentKarakalpakRollupTest.php
```

Expected: first test FAIL (col B not scanned). classifyRow test for col B ЖАМИ also FAIL.

- [ ] **Step 3: Replace `findRollupRow` in EmploymentModuleParser.php (around lines 92-99)**

```php
private function findRollupRow(Worksheet $sheet): ?int
{
    for ($row = 1; $row <= 15; $row++) {
        foreach ([1, 2] as $col) {
            $val = $sheet->getCell([$col, $row])->getCalculatedValue();
            if (is_string($val) && trim($val) === 'ЖАМИ') return $row;
        }
    }
    return null;
}
```

- [ ] **Step 4: Replace `classifyRow` in EmploymentModuleParser.php (around lines 101-107) — only the first branch changes**

```php
private function classifyRow(mixed $colA, mixed $colB): string
{
    if ((is_string($colA) && trim($colA) === 'ЖАМИ')
        || (is_string($colB) && trim($colB) === 'ЖАМИ')) {
        return 'rollup';
    }
    if (! is_string($colB) || trim($colB) === '') return 'skip';
    if (is_int($colA) || (is_string($colA) && ctype_digit(trim((string) $colA)))) return 'district';
    return 'skip';
}
```

- [ ] **Step 5: Run tests → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/EmploymentKarakalpakRollupTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/Import/Modules/EmploymentModuleParser.php backend/tests/Feature/Import/EmploymentKarakalpakRollupTest.php
git commit -m "feat(import): EmploymentModuleParser accepts ЖАМИ rollup in col A or col B"
```

---

### Task 4: SoatoSeeder alt_label for Нукус шаҳар

**Files:**
- Modify: `backend/database/seeders/SoatoSeeder.php`

- [ ] **Step 1: Add the entry**

In `backend/database/seeders/SoatoSeeder.php`, append to `DISTRICT_ALT_LABELS` under the existing Karakalpak block:

```php
        1735401 => ['Нукус шаҳар'],
```

After this edit, the constant should contain (insert as the last line before the closing `];`):

```php
        // Karakalpak orthography variants — DB canonical from districts.xlsx differs from
        // module xlsx spellings.
        1735218 => ['Қонликўл тумани', 'Қонликўл'],
        1735243 => ['Шўманой тумани', 'Шўманой'],
        1735250 => ['Элликқалъа тумани', 'Элликқалъа', 'Элликқальа тумани', 'Элликқальа'],
        1735401 => ['Нукус шаҳар'],
    ];
```

- [ ] **Step 2: Verify via tinker (no test added — covered by smoke)**

```bash
cd backend && php artisan migrate:fresh --seed
php artisan tinker --execute="echo DB::table('districts')->where('code',1735401)->value('alt_labels');"
```

Expected output: JSON array containing `"Нукус шаҳар"`.

- [ ] **Step 3: Commit**

```bash
git add backend/database/seeders/SoatoSeeder.php
git commit -m "fix(import): alt_label \"Нукус шаҳар\" for karakalpak employment xlsx variant"
```

---

### Task 5: End-to-end karakalpak smoke

**Files:** none (operator verification).

- [ ] **Step 1: Fresh DB + import karakalpak**

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:region karakalpak 2026
```

Expected (compare against current state — macro=230, inflation=28, budget_invest=54, foreign_invest=18, others=0):

```
  · macro: 230 rows buffered
  · inflation: 28 rows buffered
  · budget: >0 rows buffered      ← was 0
  · budget_invest: 54 rows buffered
  · foreign_invest: 18 rows buffered
  · export: >0 rows buffered       ← was 0 (may still be 0 if sub-row layout blocks emit — see Risks)
  · employment: >0 rows buffered  ← was 0
```

- [ ] **Step 2: Promote**

```bash
php artisan import:promote {run_id}
```

Expected: succeeds, no SQLSTATE error.

- [ ] **Step 3: Full all-regions smoke**

```bash
php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:all-regions 2026
```

Expected: 14/14 xlsx ok. Karakalpak rows_promoted ≥ 350 (was 302). Other 13 regions unchanged.

- [ ] **Step 4: Empty commit to record smoke**

```bash
git commit --allow-empty -m "test(import): karakalpak modules smoke — budget/employment promote nonzero"
```

If `export` still reports 0 rows after this work, note it in the commit message as a follow-up Bug F (separate sub-row handling for Karakalpak export layout). Do NOT block the smoke commit on that.

---

## Self-review

**Spec coverage:**
- §3 Strategy → Tasks 1, 2, 3, 4
- §4.1 BudgetModuleParser → Task 1
- §4.2 ExportModuleParser → Task 2
- §4.3 EmploymentModuleParser → Task 3
- §4.4 SoatoSeeder alt_label → Task 4
- §6.1 Per-parser rollup tests → Tasks 1, 2, 3
- §6.2 Operator smoke → Task 5
- §7 Risk (export sub-row layout) → Task 5 step 4 (follow-up Bug F noted)

**No placeholders.** All code blocks are concrete. The "> 0 rows" expectation in smoke uses a true lower bound; exact counts depend on xlsx content.

**Type consistency:**
- `Worksheet $sheet`, `?int` return for `findRollupRow`, `string` return for `classifyRow`, `bool` return for `isRollupCell` — all match existing signatures.
- `mb_strlen` vs `strlen` change applied uniformly in budget and export parsers (Cyrillic correctness, same as Bug D).
- `DISTRICT_ALT_LABELS` is `array<int, array<int, string>>` — matches existing entries.

**Naming:** Parser method names unchanged (`findRollupRow`, `isRollupCell`, `classifyRow`). Test function helpers use `make<X>Parser()` convention matching Bug D's `makeForeignInvestParser()`.
