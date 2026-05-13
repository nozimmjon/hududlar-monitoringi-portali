# Districts map click fix

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Two surgical fixes to make the districts-page map clickable again post-SOATO migration. (1) Cast in `DistrictsPage::selectedDistrict` so int district codes compare against string `$district` URL state. (2) Correct two Cyrillic orthography typos in `AndijanMapGeometry::CELLS` that block district-name lookup for Марҳамат and Шаҳрихон.

---

## 1. Goal

User reports the districts-page map is not clickable. Root cause:

- `DistrictsPage` line 32 declares `public string $district = '';` with a `#[Url]` attribute.
- Map cells fire `wire:click="selectDistrict('1703207')"` (always a string).
- `selectDistrict` sets `$this->district = $code;` (string).
- `selectedDistrict()` line 164 then compares `$row['district']->code === $this->district`.
- After the SOATO migration, `District->code` is an integer (e.g. `1703207`). Strict `===` between `int` and `string` is `false`, so the lookup always falls through to `$rows[0]`.
- Effect: visual selection stays stuck on whichever district is first in `rankedDistricts`. Map clicks appear to do nothing.

Independently, `AndijanMapGeometry::CELLS` rows 27 and 33 use the wrong Cyrillic letter:
- `'Мархамат тумани'` (Х) but DB has `'Марҳамат тумани'` (Ҳ).
- `'Шахрихон тумани'` (Х) but DB has `'Шаҳрихон тумани'` (Ҳ).

These two cells therefore have `$cellCode = ''` and clicking them yields `selectDistrict('')` — silent no-op.

After this work, clicking any of the 16 map cells (14 districts + 2 cities) selects that district and updates the table/aside accordingly.

## 2. Non-goals

- No change to `District` model casts.
- No change to `#[Url] public string $district` property type.
- No refactor of map geometry data structure.
- No fix for unrelated `kind`/`status` mismatch issues elsewhere in the page.
- No browser smoke (operator clicks through after merge).

## 3. Changes

### 3.1 `DistrictsPage::selectedDistrict` — cast comparison

`backend/app/Livewire/DistrictsPage.php` line 164:

```php
// before
if ($row['district']->code === $this->district) {

// after
if ((string) $row['district']->code === $this->district) {
```

This is the only place the bug surfaces; all other code paths use `District->code` directly without comparing to `$this->district`.

### 3.2 `AndijanMapGeometry::CELLS` — orthography

`backend/app/Support/AndijanMapGeometry.php`:

- Line 27 entry: `'name' => 'Мархамат тумани'` → `'name' => 'Марҳамат тумани'`. `'short' => 'Мархамат'` → `'short' => 'Марҳамат'`.
- Line 33 entry: `'name' => 'Шахрихон тумани'` → `'name' => 'Шаҳрихон тумани'`. `'short' => 'Шахрихон'` → `'short' => 'Шаҳрихон'`.

Path strings (SVG geometry) are unchanged; only the `'name'` and `'short'` fields are corrected to use Ҳ (U+04B3) instead of Х (U+0425).

## 4. Tests

New file `backend/tests/Feature/Livewire/DistrictsPageSelectionTest.php` with two tests:

```php
<?php

use App\Livewire\DistrictsPage;
use App\Models\District;
use App\Support\AndijanMapGeometry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('selectDistrict updates selectedDistrict when code matches', function () {
    // Bukhara district 1706204 is sort_order=1 → would be $rows[0] fallback.
    // Pick a NON-first Andijan district to detect the strict-compare bug.
    $district = District::where('region_code', 1703)->where('sort_order', '>', 1)->orderBy('sort_order')->first();
    expect($district)->not->toBeNull();

    Livewire::test(DistrictsPage::class)
        ->call('selectDistrict', (string) $district->code)
        ->tap(function ($t) use ($district) {
            $selected = invade($t->instance())->selectedDistrict();
            expect($selected['district']->code)->toBe($district->code);
        });
});

test('every map geometry cell name matches a District row', function () {
    foreach (AndijanMapGeometry::CELLS as $cell) {
        $match = District::where('region_code', 1703)->where('name_full', $cell['name'])->first();
        expect($match)->not->toBeNull("Missing district for map cell '{$cell['name']}'");
    }
});
```

The first test BITES the strict-compare bug: before the fix, `selectedDistrict()` falls back to `$rows[0]`, so the asserted code mismatches. The second test BITES the orthography bug: before the fix, two cells (Мархамат, Шахрихон) have no DB match and fail.

## 5. Risks

- **Risk:** Other Livewire components use the same pattern (`$row->code === $this->stringState`). *Mitigation:* grep confirmed only `DistrictsPage::selectedDistrict` does this. Profile page uses URL-coerced int directly.
- **Risk:** Future migration changes District code type. *Mitigation:* the cast keeps the comparison sound regardless of underlying int/string ambiguity.
- **Risk:** Orthography fix breaks pages that explicitly reference the wrong spelling. *Mitigation:* `Мархамат`/`Шахрихон` are only declared in `AndijanMapGeometry` — no other code path references those exact strings.

## 6. Files

| File | Action |
|---|---|
| `backend/app/Livewire/DistrictsPage.php` | modify line 164 (cast) |
| `backend/app/Support/AndijanMapGeometry.php` | modify 2 cells (Ҳ orthography) |
| `backend/tests/Feature/Livewire/DistrictsPageSelectionTest.php` | new (2 tests) |
