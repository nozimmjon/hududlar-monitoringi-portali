# District Name Normalizer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `App\Support\Import\DistrictNameNormalizer` and rewire `DistrictResolver` so workbook district strings with suffix variants, ў/ҳ/ғ orthography variants, and Latin look-alike typos resolve to the correct district `code` instead of emitting `unknown_district` issues.

**Architecture:** One new pure helper class (lower + suffix-rule + char-substitution + whitespace-collapse). `DistrictResolver::loadFor` registers each alias raw AND normalized; cities also register their bare-name normalized form when no district has claimed it. `resolve` tries exact then normalized.

**Tech Stack:** Laravel 11 + Pest 3.

**Spec:** `docs/superpowers/specs/2026-05-13-district-name-normalizer-design.md`

**Working directory:** `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali`. `php artisan` and `vendor/bin/pest` run from `backend/`. `git` from project root.

---

## File Structure

| File | Action |
|---|---|
| `backend/app/Support/Import/DistrictNameNormalizer.php` | new — pure static helper |
| `backend/app/Services/Import/DistrictResolver.php` | modify `loadFor` + `resolve` |
| `backend/tests/Unit/DistrictNameNormalizerTest.php` | new |
| `backend/tests/Feature/Import/DistrictResolverTest.php` | new (file does not exist yet) |

---

### Task 1: DistrictNameNormalizer + unit test

**Files:**
- Create: `backend/app/Support/Import/DistrictNameNormalizer.php`
- Create: `backend/tests/Unit/DistrictNameNormalizerTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/DistrictNameNormalizerTest.php`:

```php
<?php

use App\Support\Import\DistrictNameNormalizer;

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
    expect(DistrictNameNormalizer::normalize('Улуғнор'))->toBe('улугнор');
});

test('replaces Latin look-alikes (Latin p to Cyrillic р)', function () {
    expect(DistrictNameNormalizer::normalize('Улуғноp'))->toBe('улугнор');
});

test('collapses whitespace', function () {
    expect(DistrictNameNormalizer::normalize('  Андижон   тумани  '))->toBe('андижон');
});

test('preserves Қ as a distinct letter', function () {
    expect(DistrictNameNormalizer::normalize('Қашқадарё'))->toContain('қ');
});
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Unit/DistrictNameNormalizerTest.php
```

Expected: FAIL — `App\Support\Import\DistrictNameNormalizer` class not found.

- [ ] **Step 3: Implement the normalizer**

Create `backend/app/Support/Import/DistrictNameNormalizer.php`:

```php
<?php

namespace App\Support\Import;

class DistrictNameNormalizer
{
    /**
     * Cyrillic orthography variants + Latin look-alikes that occasionally
     * appear in workbook cells.
     *
     * Қ/қ is intentionally NOT in this map — it is a distinct Cyrillic letter
     * used consistently in both source workbooks and seeded names.
     */
    private const CHAR_MAP = [
        'ў' => 'у', 'Ў' => 'У',
        'ҳ' => 'х', 'Ҳ' => 'Х',
        'ғ' => 'г', 'Ғ' => 'Г',
        'p' => 'р',
        'o' => 'о',
        'a' => 'а',
        'c' => 'с',
        'e' => 'е',
        'x' => 'х',
        'y' => 'у',
    ];

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
}
```

- [ ] **Step 4: Run test, expect PASS**

```bash
cd backend && vendor/bin/pest tests/Unit/DistrictNameNormalizerTest.php
```

Expected: 12 tests pass.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/Import/DistrictNameNormalizer.php backend/tests/Unit/DistrictNameNormalizerTest.php
git commit -m "feat(import): DistrictNameNormalizer for suffix/orthography variants"
```

---

### Task 2: Wire normalizer into DistrictResolver

**Files:**
- Modify: `backend/app/Services/Import/DistrictResolver.php`
- Create: `backend/tests/Feature/Import/DistrictResolverTest.php`

The current `DistrictResolver` does exact-string matching only. We add normalized-key registration in `loadFor` and a normalized-lookup fallback in `resolve`. Cities also get a bare-name fallback so workbook bare `Хонобод` resolves to the city when no district claims that key.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/DistrictResolverTest.php`:

```php
<?php

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Region;
use App\Services\Import\DistrictResolver;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('regions')->where('code', 1703)->value('id');

    $rows = [
        ['code' => 1703202, 'name_short' => 'Олтинкўл т.', 'name_full' => 'Олтинкўл тумани',  'name_latin' => 'oltinkol_district',     'kind' => 'district', 'sort_order' => 1],
        ['code' => 1703203, 'name_short' => 'Андижон т.',  'name_full' => 'Андижон тумани',   'name_latin' => 'andijan_district',      'kind' => 'district', 'sort_order' => 2],
        ['code' => 1703209, 'name_short' => 'Бўстон т.',   'name_full' => 'Бўстон тумани',    'name_latin' => 'boston_district',       'kind' => 'district', 'sort_order' => 3],
        ['code' => 1703217, 'name_short' => 'Улуғнор т.',  'name_full' => 'Улуғнор тумани',   'name_latin' => 'ulugnor_district',      'kind' => 'district', 'sort_order' => 4],
        ['code' => 1703230, 'name_short' => 'Шахрихон т.', 'name_full' => 'Шахрихон тумани',  'name_latin' => 'shakhrikhan_district',  'kind' => 'district', 'sort_order' => 5],
        ['code' => 1703401, 'name_short' => 'Андижон ш.',  'name_full' => 'Андижон шаҳри',    'name_latin' => 'andijan_city',          'kind' => 'city',     'sort_order' => 6],
        ['code' => 1703408, 'name_short' => 'Хонобод ш.',  'name_full' => 'Хонобод шаҳри',    'name_latin' => 'khonobod_city',         'kind' => 'city',     'sort_order' => 7],
    ];
    foreach ($rows as $r) {
        DB::table('districts')->insert(array_merge($r, [
            'region_id' => $regionId, 'region_code' => 1703,
            'alt_labels' => null, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }
});

function makeResolver(): array
{
    $issues = new IssueCollector();
    $resolver = new DistrictResolver($issues);
    $resolver->loadFor(1703);

    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => ImportRunStatus::Parsing,
        'started_at'   => now(),
    ]);
    $ctx = new ImportContext(run: $run, region: $region, year: 2026, dataPath: null);

    return [$resolver, $ctx, $issues];
}

test('exact name_full match still works', function () {
    [$r, $ctx] = makeResolver();
    expect($r->resolve('Олтинкўл тумани', $ctx, 'src'))->toBe(1703202);
});

test('bare workbook name resolves to district', function () {
    [$r, $ctx] = makeResolver();
    expect($r->resolve('Олтинкўл', $ctx, 'src'))->toBe(1703202);
});

test('ў to у variant resolves', function () {
    [$r, $ctx] = makeResolver();
    expect($r->resolve('Бустон', $ctx, 'src'))->toBe(1703209);
});

test('ҳ to х variant resolves', function () {
    [$r, $ctx] = makeResolver();
    expect($r->resolve('Шаҳриҳон', $ctx, 'src'))->toBe(1703230);
});

test('Latin-p typo resolves', function () {
    [$r, $ctx] = makeResolver();
    expect($r->resolve('Улуғноp', $ctx, 'src'))->toBe(1703217);
});

test('city resolves via bare-name fallback', function () {
    [$r, $ctx] = makeResolver();
    expect($r->resolve('Хонобод', $ctx, 'src'))->toBe(1703408);
});

test('shared bare name resolves to district first', function () {
    [$r, $ctx] = makeResolver();
    expect($r->resolve('Андижон', $ctx, 'src'))->toBe(1703203);
});

test('city resolves via explicit suffix', function () {
    [$r, $ctx] = makeResolver();
    expect($r->resolve('Андижон шаҳри', $ctx, 'src'))->toBe(1703401);
    expect($r->resolve('Андижон ш.', $ctx, 'src'))->toBe(1703401);
});

test('non-district string returns null and emits issue', function () {
    [$r, $ctx, $issues] = makeResolver();
    expect($r->resolve('ДСБ солиқ тўловчилари', $ctx, 'src'))->toBeNull();
    expect($issues->bufferedCount())->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run test, expect mostly FAIL**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/DistrictResolverTest.php
```

Expected: the `exact name_full match` test passes (existing behaviour); the rest fail because the resolver does not yet normalize.

- [ ] **Step 3: Implement the normalized-lookup wiring**

Replace the entire contents of `backend/app/Services/Import/DistrictResolver.php` with:

```php
<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\District;
use App\Support\Import\DistrictNameNormalizer;

class DistrictResolver
{
    private array $aliasToCode = [];

    public function __construct(private IssueCollector $issues) {}

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

                // Raw exact key (always registers)
                $this->aliasToCode[$key] = $d->code;

                // Normalized key — first writer wins, so districts don't
                // overwrite each other when their normalized forms collide.
                $norm = DistrictNameNormalizer::normalize($key);
                if ($norm !== '' && ! isset($this->aliasToCode[$norm])) {
                    $this->aliasToCode[$norm] = $d->code;
                }
            }

            // Cities also register the bare-name normalized form (without ' ш.')
            // so workbook bare 'Хонобод' resolves when no district claims that key.
            if ($d->kind === 'city') {
                $bareNorm = preg_replace('/ ш\.$/u', '', DistrictNameNormalizer::normalize($d->name_short));
                if ($bareNorm !== '' && ! isset($this->aliasToCode[$bareNorm])) {
                    $this->aliasToCode[$bareNorm] = $d->code;
                }
            }
        });
    }

    public function resolve(string $workbookString, ImportContext $ctx, string $sourceLabel): ?string
    {
        $key = trim($workbookString);

        if (isset($this->aliasToCode[$key])) {
            return $this->aliasToCode[$key];
        }

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
}
```

- [ ] **Step 4: Run test, expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/DistrictResolverTest.php
```

Expected: 9 tests pass.

If `shared bare name resolves to district first` fails because the bare-name key is occupied by the city, inspect the seed order in `beforeEach` — districts must precede cities in the loop. The `Eloquent::get()` call in `loadFor` returns rows in insertion order by default; cities have higher `sort_order` so they come last. If your DB ordering differs, add `->orderBy('sort_order')` to the query in `loadFor` for determinism.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Import/DistrictResolver.php backend/tests/Feature/Import/DistrictResolverTest.php
git commit -m "feat(import): DistrictResolver falls back to normalized matching"
```

---

### Task 3: Run import:region andijan to verify integration

**Files:** none.

This is a smoke-test step — no code edits expected.

- [ ] **Step 1: Fresh DB**

```bash
cd backend && php artisan migrate:fresh --seed
```

Expected: `Seeded 14 regions and 208 districts.`

- [ ] **Step 2: Import Andijan**

```bash
cd backend && php artisan import:region andijan 2026 2>&1 | tail -15
```

Expected one of:
- Best case: `Run #N: <rows> rows staged, <issues> issues. Status: awaiting_review.` Issue count should be much lower than before — districts no longer fail.
- More likely (because the macro module bug from a separate spec is still unfixed): `Run #N failed: <N> blocker issue(s).` But the blocker count should come **only** from macro module (sheet/header detection), NOT from district resolution. Verify with:

```bash
cd backend && php artisan tinker --execute "
\$run = App\Models\ImportRun::latest('id')->first();
\$byKind = DB::table('data_quality_issues')
    ->where('import_run_id', \$run->id)
    ->selectRaw('issue_kind, count(*) as c')
    ->groupBy('issue_kind')->get();
foreach (\$byKind as \$r) echo \$r->issue_kind . ': ' . \$r->c . PHP_EOL;
"
```

Expected: zero (or near-zero) `unknown_district` rows. Macro module issues (`sheet_missing`, `header_not_found`) still appear — those are out of scope here.

- [ ] **Step 3: Optional follow-up commit if any tweak surfaced during smoke**

```bash
cd backend && git status
```

If nothing changed, skip. If a real-data corner case revealed a missing transform (e.g. a new Latin look-alike), extend `CHAR_MAP` in the normalizer, add a test for it, and commit:

```bash
cd backend && git add -A
git commit -m "fix(import): cover additional workbook variant <description>"
```

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| §4 DistrictNameNormalizer (suffix + char map + algorithm) | Task 1 |
| §5.1 DistrictResolver::loadFor with normalized registration + city bare-name fallback | Task 2 |
| §5.2 DistrictResolver::resolve normalized lookup | Task 2 |
| §5.3 Ambiguity policy (district wins) | Task 2 (covered by `! isset` guard) |
| §6.1 Normalizer unit tests | Task 1 |
| §6.2 Resolver feature tests | Task 2 |
| §7 Files touched | each task |
| Spec smoke expectation (Andijan reimports cleanly for districts) | Task 3 |

**Placeholder scan:** no TBD/handwave; every step has concrete code or commands.

**Type/name consistency:**

- `App\Support\Import\DistrictNameNormalizer` class + `normalize(string): string` method — defined Task 1, consumed Task 2.
- `DistrictResolver::loadFor(int)` + `::resolve(string, ImportContext, string)` signatures unchanged from current code; only bodies extend.
- Char map keys (`ў`, `ҳ`, `ғ`, Latin look-alikes) match test cases in Task 1.
- Andijan SOATO codes used in Task 2 fixtures (1703202, 1703209, 1703217, 1703230, 1703401, 1703408) match the seeded values used everywhere else in the codebase.
