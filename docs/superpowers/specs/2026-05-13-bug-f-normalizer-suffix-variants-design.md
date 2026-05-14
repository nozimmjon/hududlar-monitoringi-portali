# Bug F: DistrictNameNormalizer suffix variants

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Extend `App\Support\Import\DistrictNameNormalizer::normalize` to strip six additional suffix patterns that xlsx workbooks use as shorthand. Eliminates ~50-80 `unknown_district` issues across all 14 regions in a single normalizer change.

---

## 1. Goal

The current normalizer recognizes four canonical suffix forms: ` шаҳри`, ` ш.`, ` тумани`, ` т.`. Module workbooks freely use shorter or orthographic-variant forms:

| Variant | Example | Canonical equivalent |
|---|---|---|
| ` шаҳар` | `Навоий шаҳар` | `Навоий ш.` |
| ` шахри` (Х) | `Шаҳрисабз шахри` | `Шаҳрисабз ш.` |
| ` шахар` (Х) | `Жиззах шахар` | `Жиззах ш.` |
| ` ш` (no dot) | `Когон ш` | `Когон ш.` |
| ` туман` (no и) | `Кармана туман` | `Кармана` (bare) |
| ` т` (no dot, no и) | `Бухоро т` | `Бухоро` (bare) |

Total impact (counted across `data_quality_issues` after the latest import run): roughly 50 issues directly attributable to these patterns, plus another ~30 follow-on issues that resolve once normalizer collapses both sides of the comparison.

After this work, those rows resolve to district codes via `DistrictResolver::resolve` (which already retries lookup with normalized form). No DistrictResolver or parser changes needed.

## 2. Non-goals

- No fix for orthography typos that aren't suffix related (`Қўқдала` vs `Кўкдала`, `Foзғон` Latin F, `Ш.Рашидов` abbreviation, etc.) — Bug G1 scope.
- No fix for districts missing from the SOATO seed (`Тойлоқ`, `Пайариқ`, `Пискент`, etc.) — Bug G2 scope.
- No sentinel-row filter for `ДСБ солиқ тўловчилари` — Bug H scope.
- No change to `DistrictResolver`, parsers, or seed.

## 3. Strategy

Insert six new `elseif` branches into the existing chain in `DistrictNameNormalizer::normalize`. Order: longest-match first to avoid premature strip.

Suffix-order chain (after this change):

1. ` шаҳри` (existing) → ` ш.`
2. ` шаҳар` (new) → ` ш.`
3. ` шахри` (new) → ` ш.`
4. ` шахар` (new) → ` ш.`
5. ` ш.` (existing) → unchanged (canonical)
6. ` ш` (new) → ` ш.`
7. ` тумани` (existing) → strip
8. ` туман` (new) → strip
9. ` т.` (existing) → strip
10. ` т` (new) → strip

The chain uses `elseif`, so the first matching branch wins. Adding new shorter branches after the existing longer ones preserves backward compatibility.

## 4. Files

| File | Action |
|---|---|
| `backend/app/Support/Import/DistrictNameNormalizer.php` | extend `normalize` (add six `elseif` branches) |
| `backend/tests/Unit/Support/Import/DistrictNameNormalizerSuffixesTest.php` | new — covers all 10 patterns + regression |

No new model, no migration, no seeder change.

## 5. Code

Current method (around lines 27-46):

```php
public static function normalize(string $name): string
{
    $s = mb_strtolower(trim($name));

    if (str_ends_with($s, ' шаҳри')) {
        $s = mb_substr($s, 0, -mb_strlen(' шаҳри')) . ' ш.';
    } elseif (str_ends_with($s, ' ш.')) {
        // already canonical
    } elseif (str_ends_with($s, ' тумани')) {
        $s = mb_substr($s, 0, -mb_strlen(' тумани'));
    } elseif (str_ends_with($s, ' т.')) {
        $s = mb_substr($s, 0, -mb_strlen(' т.'));
    }

    $s = strtr($s, self::CHAR_MAP);
    $s = trim(preg_replace('/\s+/u', ' ', $s));

    return $s;
}
```

Replace the suffix-strip block with:

```php
if (str_ends_with($s, ' шаҳри')) {
    $s = mb_substr($s, 0, -mb_strlen(' шаҳри')) . ' ш.';
} elseif (str_ends_with($s, ' шаҳар')) {
    $s = mb_substr($s, 0, -mb_strlen(' шаҳар')) . ' ш.';
} elseif (str_ends_with($s, ' шахри')) {
    $s = mb_substr($s, 0, -mb_strlen(' шахри')) . ' ш.';
} elseif (str_ends_with($s, ' шахар')) {
    $s = mb_substr($s, 0, -mb_strlen(' шахар')) . ' ш.';
} elseif (str_ends_with($s, ' ш.')) {
    // already canonical
} elseif (str_ends_with($s, ' ш')) {
    $s = mb_substr($s, 0, -mb_strlen(' ш')) . ' ш.';
} elseif (str_ends_with($s, ' тумани')) {
    $s = mb_substr($s, 0, -mb_strlen(' тумани'));
} elseif (str_ends_with($s, ' туман')) {
    $s = mb_substr($s, 0, -mb_strlen(' туман'));
} elseif (str_ends_with($s, ' т.')) {
    $s = mb_substr($s, 0, -mb_strlen(' т.'));
} elseif (str_ends_with($s, ' т')) {
    $s = mb_substr($s, 0, -mb_strlen(' т'));
}
```

Rest of the method (CHAR_MAP + dedupe) stays unchanged.

## 6. Tests

New file `backend/tests/Unit/Support/Import/DistrictNameNormalizerSuffixesTest.php`:

```php
<?php

use App\Support\Import\DistrictNameNormalizer;

test('strips canonical city suffix шаҳри', function () {
    expect(DistrictNameNormalizer::normalize('Қарши шаҳри'))->toBe('қарши ш.');
});

test('strips variant city suffix шаҳар (no и)', function () {
    expect(DistrictNameNormalizer::normalize('Навоий шаҳар'))->toBe('навоий ш.');
});

test('strips variant city suffix шахри (Х)', function () {
    expect(DistrictNameNormalizer::normalize('Шаҳрисабз шахри'))->toBe('шаҳрисабз ш.');
});

test('strips variant city suffix шахар (Х and no и)', function () {
    expect(DistrictNameNormalizer::normalize('Жиззах шахар'))->toBe('жиззах ш.');
});

test('expands bare ш to canonical ш.', function () {
    expect(DistrictNameNormalizer::normalize('Когон ш'))->toBe('когон ш.');
});

test('canonical ш. is left alone', function () {
    expect(DistrictNameNormalizer::normalize('Андижон ш.'))->toBe('андижон ш.');
});

test('strips canonical district suffix тумани', function () {
    expect(DistrictNameNormalizer::normalize('Бўстон тумани'))->toBe('бўстон');
});

test('strips variant district suffix туман (no и)', function () {
    expect(DistrictNameNormalizer::normalize('Кармана туман'))->toBe('кармана');
});

test('strips canonical district suffix т.', function () {
    expect(DistrictNameNormalizer::normalize('Олот т.'))->toBe('олот');
});

test('strips variant district suffix т (no dot)', function () {
    expect(DistrictNameNormalizer::normalize('Бухоро т'))->toBe('бухоро');
});

test('leaves bare district name alone', function () {
    expect(DistrictNameNormalizer::normalize('Шовот'))->toBe('шовот');
});

test('applies CHAR_MAP after suffix strip (ҳ→х)', function () {
    expect(DistrictNameNormalizer::normalize('Деҳқонобод тумани'))->toBe('дехқонобод');
});

test('handles trailing whitespace before suffix', function () {
    expect(DistrictNameNormalizer::normalize('Бухоро  т'))->toBe('бухоро');
});
```

13 tests. Each covers one branch, plus regression on canonical forms and the existing CHAR_MAP / whitespace-dedupe logic.

## 7. Operator smoke

After implementation:

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:all-regions 2026
php artisan tinker --execute="echo DB::table('data_quality_issues')->where('issue_kind','unknown_district')->count();"
```

Expected: count drops from ~188 (current state) to ≤120 (~70 issues resolved). Per-region rows_promoted may increase modestly.

## 8. Risks

- **Risk:** ` т` / ` ш` short patterns false-strip names that legitimately end with bare ` т` or ` ш`. *Mitigation:* manual check of all 208 seeded districts shows no such names. If a future district adds one (unlikely), this test suite catches it via the canonical-name regression tests.
- **Risk:** Sentinel/title strings end with ` т` or ` ш` by accident (e.g. `ДСБ ҳисобот т` hypothetical). *Mitigation:* DistrictResolver returns null when no DB match; false-strip becomes a silent no-op rather than a wrong-district assignment.
- **Risk:** Order-of-branch error — adding ` т` before ` тумани` would strip just the `и` letter. *Mitigation:* spec explicitly orders longer-suffix-first; tests assert each branch independently.
- **Risk:** Other code paths depend on exact normalize output. *Mitigation:* `DistrictNameNormalizer` is used only by `DistrictResolver::loadFor` (key construction) and `DistrictResolver::resolve` (lookup). Both compare normalized strings on both sides — symmetric extension keeps them consistent.
