# Karakalpak budget/export/employment 0-row fix

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Extend `BudgetModuleParser`, `ExportModuleParser`, and `EmploymentModuleParser` to recognize Karakalpak's rollup label conventions (`Қорақалпоғистон Республикаси`, `Жами`, `ЖАМИ` in col A *or* col B). Add one `DISTRICT_ALT_LABELS` entry for `Нукус шаҳар` (employment xlsx variant of `Нукус шаҳри`).

---

## 1. Goal

`php artisan import:region karakalpak 2026` currently produces 0 rows for three modules:

- `budget`: 0 (workbook `3-жадвал (бюджет).xlsx`)
- `export`: 0 (workbook `5.1-5.2-жадваллар (экспорт).xlsx`)
- `employment`: 0 (workbook `6-жадвал (бандлик ва камбағаллик даражаси).xlsx`)

Root cause: each parser's `findRollupRow` aborts when no rollup is found, returning 0 rows silently. The rollup detection assumes one of the conventions used by the 13 non-Karakalpak regions:

| Parser | Current assumption | Karakalpak reality |
|---|---|---|
| `BudgetModuleParser` | col B contains `вилояти` | rollup is `Қорақалпоғистон Республикаси` in col A row 8; col B is null |
| `ExportModuleParser` | col A ends with `вилояти` | rollup is `Қорақалпоғистон Республикаси` in col A row 6 |
| `EmploymentModuleParser` | col A equals `ЖАМИ` | rollup is `ЖАМИ` in col B row 7; col A is null |

Plus one bonus mismatch: Karakalpak employment xlsx row 8 col B = `Нукус шаҳар` (note: `шаҳар`, not the canonical `шаҳри`). DistrictResolver normalizer does not handle this variant.

After this work, the three Karakalpak modules promote nonzero rows, mirroring the Bug D pattern that already unblocked `foreign_invest`.

## 2. Non-goals

- No refactor into a shared `RollupDetector` helper. Each parser stays inline (mirrors Bug D scope).
- No change to the other 13 regions' workbook behavior.
- No schema migration. No new service classes.
- No fix for the 1 remaining `unknown_district` issue (`Заҳира лойиҳаларни жадаллаштириш ҳисобидан` in foreign_invest) — that is a non-district line item that should stay unmatched.

## 3. Strategy

Three independent parser extensions, plus one seeder entry:

1. **`BudgetModuleParser::findRollupRow`** — scan both col A and col B for `вилояти` or `Республикаси` suffix. Cap at 40 chars to avoid matching title rows.
2. **`ExportModuleParser::isRollupCell`** — extend predicate to also accept `Республикаси` suffix and `Жами` exact match (mirrors Bug D's `ForeignInvestModuleParser::isRollupCell`).
3. **`EmploymentModuleParser::findRollupRow` + `classifyRow`** — scan both col A and col B for exact `ЖАМИ`.
4. **`SoatoSeeder::DISTRICT_ALT_LABELS`** — add `1735401 => ['Нукус шаҳар']` so DistrictResolver matches the variant.

## 4. Changes

### 4.1 `BudgetModuleParser::findRollupRow`

Current (around line 62):

```php
private function findRollupRow(Worksheet $sheet): ?int
{
    for ($row = 1; $row <= 15; $row++) {
        $colB = $sheet->getCell([2, $row])->getCalculatedValue();
        if (is_string($colB) && str_contains($colB, 'вилояти')) {
            return $row;
        }
    }
    return null;
}
```

Replace with:

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

The 40-char cap is added to avoid matching multi-sentence title rows that contain `вилояти` as a sub-word. `mb_strlen` is used because Cyrillic characters are multi-byte (mirrors Bug D's `isRollupCell` correction). `str_ends_with` replaces `str_contains` to tighten the predicate.

### 4.2 `ExportModuleParser::isRollupCell`

Current (line 94-101):

```php
private function isRollupCell(mixed $value): bool
{
    if (! is_string($value)) return false;
    $trimmed = trim($value);
    return strlen($trimmed) <= 40 && str_ends_with($trimmed, 'вилояти');
}
```

Replace with:

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

`strlen` → `mb_strlen` (Cyrillic multi-byte fix). Adds Республикаси and `Жами` exact match. `classifyRow` already calls `isRollupCell` so no further change in that method.

### 4.3 `EmploymentModuleParser::findRollupRow` and `classifyRow`

Current `findRollupRow` (line 92-99):

```php
private function findRollupRow(Worksheet $sheet): ?int
{
    for ($row = 1; $row <= 15; $row++) {
        $colA = $sheet->getCell([1, $row])->getCalculatedValue();
        if (is_string($colA) && trim($colA) === 'ЖАМИ') return $row;
    }
    return null;
}
```

Replace with:

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

Current `classifyRow` (line 101-107):

```php
private function classifyRow(mixed $colA, mixed $colB): string
{
    if (is_string($colA) && trim($colA) === 'ЖАМИ') return 'rollup';
    if (! is_string($colB) || trim($colB) === '') return 'skip';
    if (is_int($colA) || (is_string($colA) && ctype_digit(trim((string) $colA)))) return 'district';
    return 'skip';
}
```

Replace the first branch only:

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

### 4.4 `SoatoSeeder::DISTRICT_ALT_LABELS`

Append one entry under the existing Karakalpak block:

```php
1735401 => ['Нукус шаҳар'],
```

DistrictResolver picks this up automatically on load; no further changes.

## 5. Files

| File | Action |
|---|---|
| `backend/app/Services/Import/Modules/BudgetModuleParser.php` | modify `findRollupRow` |
| `backend/app/Services/Import/Modules/ExportModuleParser.php` | modify `isRollupCell` |
| `backend/app/Services/Import/Modules/EmploymentModuleParser.php` | modify `findRollupRow` + `classifyRow` |
| `backend/database/seeders/SoatoSeeder.php` | add 1 `DISTRICT_ALT_LABELS` entry |
| `backend/tests/Feature/Import/BudgetKarakalpakRollupTest.php` | new |
| `backend/tests/Feature/Import/ExportKarakalpakRollupTest.php` | new |
| `backend/tests/Feature/Import/EmploymentKarakalpakRollupTest.php` | new |

No migration, no model, no Livewire changes.

## 6. Tests

### 6.1 Per-parser rollup detection tests

For each parser, build an in-memory `Spreadsheet` mirroring the Karakalpak workbook structure for that module's sheet, then assert `findRollupRow` (or equivalent) returns the expected row number.

`BudgetKarakalpakRollupTest.php`:

```php
test('BudgetModuleParser findRollupRow detects Қорақалпоғистон Республикаси rollup in col A', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A2', '2026 йилда Қорақалпоғистон Республикаси бюджетига тушумлар бўйича ПРОГНОЗ ПАРАМЕТРЛАР');
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Ҳудудлар');
    $sheet->setCellValue('A8', 'Қорақалпоғистон Республикаси');

    $parser = $this->app->make(BudgetModuleParser::class);
    expect(invade($parser)->findRollupRow($sheet))->toBe(8);
});

test('BudgetModuleParser findRollupRow still detects Андижон вилояти in col B (no regression)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('B7', 'Андижон вилояти');

    $parser = $this->app->make(BudgetModuleParser::class);
    expect(invade($parser)->findRollupRow($sheet))->toBe(7);
});
```

`ExportKarakalpakRollupTest.php`:

```php
test('ExportModuleParser isRollupCell accepts Республикаси and Жами', function () {
    $parser = $this->app->make(ExportModuleParser::class);
    expect(invade($parser)->isRollupCell('Қорақалпоғистон Республикаси'))->toBeTrue();
    expect(invade($parser)->isRollupCell('Жами'))->toBeTrue();
    expect(invade($parser)->isRollupCell('Андижон вилояти'))->toBeTrue();
});

test('ExportModuleParser isRollupCell rejects long title rows', function () {
    $parser = $this->app->make(ExportModuleParser::class);
    $title = 'Қорақалпоғистон Республикасининг 2026 йил январь-март ойи учун белгиланган экспорт прогнози';
    expect(invade($parser)->isRollupCell($title))->toBeFalse();
});
```

`EmploymentKarakalpakRollupTest.php`:

```php
test('EmploymentModuleParser findRollupRow detects ЖАМИ in col B', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('B4', 'Туман (шаҳар)номи');
    $sheet->setCellValue('B7', 'ЖАМИ');

    $parser = $this->app->make(EmploymentModuleParser::class);
    expect(invade($parser)->findRollupRow($sheet))->toBe(7);
});

test('EmploymentModuleParser findRollupRow still detects ЖАМИ in col A (no regression)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A8', 'ЖАМИ');

    $parser = $this->app->make(EmploymentModuleParser::class);
    expect(invade($parser)->findRollupRow($sheet))->toBe(8);
});
```

### 6.2 Operator smoke

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:region karakalpak 2026
```

Expected:

```
  · budget: 17 rows buffered          (was 0)
  · export: ~30 rows buffered          (was 0)
  · employment: ~50 rows buffered     (was 0)
```

Exact counts depend on row count of each xlsx. The success criterion is "nonzero, no parser crash."

Then `import:all-regions 2026` — Karakalpak should now stage 400+ rows (was 302).

## 7. Risks

- **Risk:** Other regions' workbooks contain `Республикаси` or `Жами` in unrelated contexts (title rows, footnotes). *Mitigation:* the 40-char length cap blocks long titles. `Жами` requires exact equality (not substring). All affected regions are already passing today; if a regression surfaces, this test suite catches it because Andijan parity tests still run.
- **Risk:** Karakalpak export's 2-row-per-district sub-category layout may still emit 0 rows even after rollup detection works. The parser's per-row classification expects one row per district, but Karakalpak interleaves `саноат маҳсулотлари` / `мева-сабзавотлар` sub-category labels between districts. *Mitigation:* this risk is acknowledged but out of scope. If the smoke shows export still at 0, file as Bug F (separate sub-row handling for Karakalpak export layout).
- **Risk:** `Нукус шаҳар` alt_label collides with other usage. *Mitigation:* checked all 14 regions — no other district has `шаҳар` (without `и`) as a substring in any district name.
- **Risk:** `ЖАМИ` in col B might appear in non-rollup contexts elsewhere. *Mitigation:* `ЖАМИ` uppercase exact-match is rare; current employment parser already trusts the exact match in col A.
- **Risk:** Spec-style strlen→mb_strlen change might surface latent bugs in other regions' parsing. *Mitigation:* the cap only changes behavior for Cyrillic strings 21-40 characters long. None of the rollup labels for other regions falls in that range, so no behavior change for them.
