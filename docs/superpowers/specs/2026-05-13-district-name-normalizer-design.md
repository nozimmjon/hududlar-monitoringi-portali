# District name normalizer for the import pipeline

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Fix `DistrictResolver` so workbook district strings with suffix variants (` тумани` / ` т.` / ` шаҳри` / ` ш.`), orthography variants (ў/у, ҳ/х, ғ/г), and Latin-look-alike typos (Latin `p` instead of Cyrillic `р`, etc.) resolve to the correct `District::code` instead of emitting `unknown_district` data-quality issues.

---

## 1. Goal

After the SOATO migration, `php artisan import:region andijan 2026` emits 15+ `unknown_district` issues per region because:

- The seeder writes district `name_full` as `Олтинкўл тумани` and `name_short` as `Олтинкўл т.`, but the workbooks use bare `Олтинкўл`.
- The workbooks use ў/у and ҳ/х variants (`Бустон` vs seeded `Бўстон`, `Шаҳриҳон` vs seeded `Шахрихон`).
- One workbook cell has a typo: `Улуғноp` (last character is Latin `p`, not Cyrillic `р`).

`DistrictResolver` currently does exact-string lookup against `name_short`, `name_full`, `name_latin`, and `alt_labels`. None of the variants above match.

This spec adds a pure normalization function and updates `DistrictResolver` to fall back to normalized matching after exact-match misses. Cities keep a ` ш.` marker in their normalized form so they stay distinct from same-named districts (e.g. `Андижон шаҳри` vs `Андижон тумани`).

## 2. Non-goals

- No Levenshtein/edit-distance fuzzy match. The normalizer is deterministic. Typos beyond the documented Latin-look-alike map still emit `unknown_district` issues.
- No upstream workbook fixes.
- No change to `IssueKind::Sentinel` handling (`холи ҳудуд` etc.).
- No change to sheet/header detection in macro module (covered by a separate spec).
- No change to seeder data — alt_labels stay as-is.

## 3. Strategy

Two-file change plus tests:

- New pure helper `App\Support\Import\DistrictNameNormalizer::normalize(string): string` performs lowercase + suffix handling + character substitution + whitespace collapse.
- `DistrictResolver::loadFor` registers each alias both raw AND normalized. For cities, also registers the bare normalized form (without ` ш.`) when no other district has claimed it.
- `DistrictResolver::resolve` tries exact, then normalized, then emits `UnknownDistrict`.

## 4. `DistrictNameNormalizer`

### 4.1 Suffix rules

- Trailing ` тумани` → strip entirely (`Олтинкўл тумани` → `олтинкўл`).
- Trailing ` т.` → strip entirely.
- Trailing ` шаҳри` → replace with ` ш.` (canonical city marker: `Андижон шаҳри` → `андижон ш.`).
- Trailing ` ш.` → keep as-is (already canonical).
- No trailing suffix → bare name unchanged.

This is the user-clarified policy: districts drop their suffix, cities keep a short ` ш.` marker so they remain distinguishable from same-named districts.

### 4.2 Character substitution map

```php
private const CHAR_MAP = [
    // Uzbek orthography variants (canonical → simplified)
    'ў' => 'у', 'Ў' => 'У',
    'ҳ' => 'х', 'Ҳ' => 'Х',
    'ғ' => 'г', 'Ғ' => 'Г',
    // Latin look-alikes occasionally present in workbook cells
    'p' => 'р',
    'o' => 'о',
    'a' => 'а',
    'c' => 'с',
    'e' => 'е',
    'x' => 'х',
    'y' => 'у',
];
```

`Қ`/`қ` is NOT mapped — it's a distinct Cyrillic letter used in both source workbooks and seeded names consistently.

### 4.3 Algorithm

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

### 4.4 Worked examples

| Input | Normalized |
|---|---|
| `Олтинкўл тумани` | `олтинкул` |
| `Олтинкўл т.` | `олтинкул` |
| `Олтинкўл` (bare workbook) | `олтинкул` |
| `Андижон тумани` | `андижон` |
| `Андижон шаҳри` | `андижон ш.` |
| `Андижон ш.` | `андижон ш.` |
| `Андижон` (bare workbook) | `андижон` (matches district) |
| `Бустон` (workbook) | `бустон` |
| `Бўстон тумани` (seeded) | `бустон` (matches) |
| `Шаҳриҳон` (workbook) | `шахрихон` |
| `Шахрихон тумани` (seeded) | `шахрихон` (matches) |
| `Улуғноp` (Latin p typo) | `улугнор` |
| `Улуғнор тумани` (seeded) | `улугнор` (matches) |
| `Хонобод` (bare workbook city) | `хонобод` |
| `Хонобод ш.` (seeded city short) | `хонобод ш.` |
| `Хонобод шаҳри` (seeded city full) | `хонобод ш.` |

The Хонобод case is handled by §5 — `loadFor` registers a bare-name key for cities when no district claims the same normalized bare form.

## 5. `DistrictResolver` change

### 5.1 `loadFor`

```php
public function loadFor(int $regionCode): void
{
    $this->aliasToCode = [];
    District::where('region_code', $regionCode)->get()->each(function (District $d) {
        $aliases = is_array($d->alt_labels)
            ? $d->alt_labels
            : (json_decode($d->alt_labels ?? '[]', true) ?: []);
        $aliases[] = $d->name_short;
        $aliases[] = $d->name_full;
        if ($d->name_latin) {
            $aliases[] = $d->name_latin;
        }

        foreach ($aliases as $alias) {
            $key = trim((string) $alias);
            if ($key === '') continue;

            // Raw exact key
            $this->aliasToCode[$key] = $d->code;

            // Normalized key (lower priority — only register if not already taken)
            $norm = DistrictNameNormalizer::normalize($key);
            if ($norm !== '' && ! isset($this->aliasToCode[$norm])) {
                $this->aliasToCode[$norm] = $d->code;
            }
        }

        // Cities: also register bare-name normalized key (without ' ш.') if no other
        // district has claimed it. Lets workbook bare 'Хонобод' resolve to the city.
        if ($d->kind === 'city') {
            $bareNorm = preg_replace('/ ш\.$/u', '', DistrictNameNormalizer::normalize($d->name_short));
            if ($bareNorm !== '' && ! isset($this->aliasToCode[$bareNorm])) {
                $this->aliasToCode[$bareNorm] = $d->code;
            }
        }
    });
}
```

### 5.2 `resolve`

```php
public function resolve(string $workbookString, ImportContext $ctx, string $sourceLabel): ?string
{
    $key = trim($workbookString);

    // Exact match (fast path)
    if (isset($this->aliasToCode[$key])) {
        return $this->aliasToCode[$key];
    }

    // Normalized match
    $norm = DistrictNameNormalizer::normalize($key);
    if ($norm !== '' && isset($this->aliasToCode[$norm])) {
        return $this->aliasToCode[$norm];
    }

    $this->issues->add(
        kind: IssueKind::UnknownDistrict,
        severity: IssueSeverity::High,
        detail: "District string did not match any alt_label in region {$ctx->regionCode()}",
        regionCode: $ctx->regionCode(),
        detectedValue: $key,
        sourceLabel: $sourceLabel,
        importRunId: $ctx->run->id,
    );
    return null;
}
```

### 5.3 Ambiguity policy

When a district and a city share a bare normalized form (e.g. Andijan has both `Андижон тумани` and `Андижон шаҳри`):

- District is registered first via `name_short`/`name_full` iteration.
- City's bare-name fallback at the end is gated by `! isset($this->aliasToCode[$bareNorm])`, so it does NOT overwrite.
- Result: bare workbook `Андижон` resolves to district. City needs explicit ` шаҳри`/` ш.` suffix.

This is the deterministic, documented behaviour. Operators who want bare `Андижон` to resolve to the city must use `Андижон ш.` in the workbook.

## 6. Tests

### 6.1 `tests/Unit/DistrictNameNormalizerTest.php` (new)

```php
test('lowercases input', function () {
    expect(DistrictNameNormalizer::normalize('АНДИЖОН ТУМАНИ'))->toBe('андижон');
});

test('strips " тумани" suffix from districts', function () {
    expect(DistrictNameNormalizer::normalize('Олтинкўл тумани'))->toBe('олтинкул');
});

test('strips " т." abbreviation', function () {
    expect(DistrictNameNormalizer::normalize('Олтинкўл т.'))->toBe('олтинкул');
});

test('canonicalises " шаҳри" to " ш."', function () {
    expect(DistrictNameNormalizer::normalize('Андижон шаҳри'))->toBe('андижон ш.');
});

test('leaves " ш." as-is', function () {
    expect(DistrictNameNormalizer::normalize('Андижон ш.'))->toBe('андижон ш.');
});

test('bare name stays bare', function () {
    expect(DistrictNameNormalizer::normalize('Андижон'))->toBe('андижон');
});

test('maps ў to у', function () {
    expect(DistrictNameNormalizer::normalize('Бўстон'))->toBe('бустон');
});

test('maps ҳ to х', function () {
    expect(DistrictNameNormalizer::normalize('Шаҳриҳон'))->toBe('шахрихон');
});

test('maps ғ to г', function () {
    expect(DistrictNameNormalizer::normalize('Қўрғонтепа'))->toBe('кургонтепа');
    // Note: ў → у twice; Қ stays Қ
});

test('replaces Latin look-alikes', function () {
    expect(DistrictNameNormalizer::normalize('Улуғноp'))->toBe('улугнор');
});

test('collapses whitespace', function () {
    expect(DistrictNameNormalizer::normalize('  Андижон   тумани  '))->toBe('андижон');
});

test('preserves Қ as a distinct letter', function () {
    expect(DistrictNameNormalizer::normalize('Қашқадарё'))->toContain('қ');
});
```

### 6.2 `tests/Feature/Import/DistrictResolverTest.php` (new)

Seeds Andijan region + a handful of districts (one city, one district that shares a name with the city, one district with no city collision). Drives `DistrictResolver` through workbook strings and asserts the resolved code.

```php
beforeEach(function () {
    // ... seed region 1703 + districts 1703202 (Олтинкул), 1703203 (Андижон тумани),
    //     1703209 (Бўстон), 1703230 (Шахрихон), 1703401 (Андижон шаҳри),
    //     1703408 (Хонобод шаҳри)
});

test('exact name_full match still works', function () {
    $resolver = makeResolver(...);
    expect($resolver->resolve('Олтинкўл тумани', $ctx, 'src'))->toBe(1703202);
});

test('bare workbook name resolves to district', function () {
    expect($resolver->resolve('Олтинкўл', ...))->toBe(1703202);
});

test('ў/у variant resolves', function () {
    expect($resolver->resolve('Бустон', ...))->toBe(1703209);
});

test('ҳ/х variant resolves', function () {
    expect($resolver->resolve('Шаҳриҳон', ...))->toBe(1703230);
});

test('Latin-p typo resolves', function () {
    expect($resolver->resolve('Улуғноp', ...))->toBe(1703217);
    // (requires 1703217 seeded as Улуғнор тумани)
});

test('city resolves via bare-name fallback', function () {
    expect($resolver->resolve('Хонобод', ...))->toBe(1703408);
});

test('shared bare name resolves to district first', function () {
    // Andijan: bare 'Андижон' could be tумани (1703203) or шаҳри (1703401).
    // District wins.
    expect($resolver->resolve('Андижон', ...))->toBe(1703203);
});

test('city still resolves via explicit suffix', function () {
    expect($resolver->resolve('Андижон шаҳри', ...))->toBe(1703401);
    expect($resolver->resolve('Андижон ш.', ...))->toBe(1703401);
});

test('non-district string emits issue and returns null', function () {
    expect($resolver->resolve('ДСБ солиқ тўловчилари', ...))->toBeNull();
    // assert IssueCollector got an UnknownDistrict entry
});
```

## 7. Files touched

| File | Action |
|---|---|
| `backend/app/Support/Import/DistrictNameNormalizer.php` | new (~50 lines) |
| `backend/app/Services/Import/DistrictResolver.php` | modify (`loadFor` + `resolve`) |
| `backend/tests/Unit/DistrictNameNormalizerTest.php` | new |
| `backend/tests/Feature/Import/DistrictResolverTest.php` | new (file doesn't exist yet) |

No migration, no model, no view changes.

## 8. Risks

- **Risk:** Latin look-alike substitution may incorrectly fold legitimate Latin text. *Mitigation:* district names are always Cyrillic Uzbek; if a workbook cell contains real Latin text (e.g. an English name), it would not currently match anyway. Substitution doesn't break what was already broken.
- **Risk:** A district's bare name might collide with another district's normalized form. *Mitigation:* SOATO codes are unique; if such a collision exists in real data, the first registered district wins (`isset` guard). Seeder iteration is deterministic by `sort_order`.
- **Risk:** `Қ` not in char map means `Қашқадарё`-style names retain `Қ` in normalized form. *Mitigation:* documented and tested. Workbooks consistently use `Қ`, so no mismatch.
- **Risk:** Future workbook variants introduce new substitutions. *Mitigation:* `CHAR_MAP` is a const array; new variants are one-line additions.
- **Risk:** Sentinel `холи ҳудуд` is now normalized to `холи ҳудуд` (no change) and still emits an `unknown_district` issue with high severity. *Mitigation:* out of scope; existing `IssueKind::Sentinel` detection runs in parsers before resolver is reached on those rows.
