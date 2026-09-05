# Water road maps (/roadmaps) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import the 2026 regional water-management "ЙЎЛ ХАРИТАСИ" measures from `.docx` into the portal and show them at `/roadmaps` with section and district filters (Хоразм first).

**Architecture:** Two new tables (`roadmaps`, `roadmap_measures`) fed by an Artisan command that reads `word/document.xml` directly (`DocxTableReader` → blocks, `RoadmapParser` → measures, keyed on header text). A region-scoped Livewire page (`RoadmapsPage`) renders a left rail (sections, districts, search) and a grouped card list with collapsible details. No status tracking, no sidebar link.

**Tech Stack:** Laravel 12, Livewire 3 (v4.4 lock) + bundled Alpine, PostgreSQL, Pest 3, PHP `ZipArchive` + `DOMDocument` (no PhpWord API needed), hand-maintained `public/css/portal.css`.

**Spec:** `docs/superpowers/specs/2026-09-06-water-roadmaps-design.md`

**Branch:** `roadmaps` (already created off `starter-page`). Work in `backend/` for all PHP paths below (paths are given relative to `backend/` unless they start with `docs/` or `CLAUDE.md`).

**Running tests:** `php artisan test --filter=Roadmap` from `backend/` (Postgres must be running). Never run two `php artisan test` processes at once against the same DB — if you must, set `$env:DB_DATABASE="hududlar_test_<unique>"` first.

---

## File structure

| File | Responsibility |
| --- | --- |
| `database/migrations/2026_09_06_000001_create_roadmaps_table.php` | one row per (domain, region, year) document |
| `database/migrations/2026_09_06_000002_create_roadmap_measures_table.php` | measures with section / district / seq position |
| `app/Models/Roadmap.php`, `app/Models/RoadmapMeasure.php` | Eloquent models, relations, scopes |
| `app/Services/Roadmaps/DocxTableReader.php` | docx → ordered blocks (paragraph text, table rows of cell lines). Knows XML, knows nothing about road maps |
| `app/Services/Roadmaps/RoadmapParser.php` | blocks → `{title_text, approvers_text, measures[]}`. Knows road-map semantics, knows nothing about XML or DB (district lookup is an injected callable) |
| `app/Console/Commands/ImportRoadmap.php` | `import:roadmap` — file resolution, district resolver, transaction, summary |
| `app/Livewire/RoadmapsPage.php` + `resources/views/livewire/roadmaps-page.blade.php` | the page |
| `resources/views/pages/roadmaps.blade.php`, `routes/web.php` | route + layout wrapper |
| `public/css/portal.css` | appended `wr-` block |
| `tests/Helpers/RoadmapDocxBuilder.php` | synthetic docx fixture builder |
| `tests/Unit/Roadmaps/RoadmapParserRulesTest.php` | pure rule tests |
| `tests/Unit/Roadmaps/DocxTableReaderTest.php` | reader on a built docx |
| `tests/Feature/Roadmaps/RoadmapSchemaTest.php` | tables + models |
| `tests/Feature/Roadmaps/RoadmapParserTest.php` | parser on built docx (happy path + aborts) |
| `tests/Feature/Roadmaps/ImportRoadmapTest.php` | command end-to-end |
| `tests/Feature/Roadmaps/RoadmapsPageTest.php` | route + Livewire filters |
| `docs/roadmap-import.md` (under `backend/`) | operator runbook |
| `CLAUDE.md`, spec | docs updates |

---

### Task 1: Schema and models

**Files:**
- Create: `database/migrations/2026_09_06_000001_create_roadmaps_table.php`
- Create: `database/migrations/2026_09_06_000002_create_roadmap_measures_table.php`
- Create: `app/Models/Roadmap.php`
- Create: `app/Models/RoadmapMeasure.php`
- Test: `tests/Feature/Roadmaps/RoadmapSchemaTest.php`

- [ ] **Step 1: Write the failing schema/model test**

```php
<?php

use App\Models\District;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('roadmaps and roadmap_measures tables exist with the expected columns', function () {
    expect(Schema::hasColumns('roadmaps', [
        'id', 'domain', 'region_code', 'year', 'title_text', 'approvers_text', 'source_file', 'imported_at',
    ]))->toBeTrue();
    expect(Schema::hasColumns('roadmap_measures', [
        'id', 'roadmap_id', 'section_no', 'section_title', 'district_id', 'district_head_text', 'seq_no',
        'title', 'details', 'body_raw', 'funding_text', 'deadline_text', 'responsible_text', 'source_row',
    ]))->toBeTrue();
});

test('a roadmap owns its measures, measures resolve district and split by level', function () {
    $this->seed();
    $roadmap = Roadmap::create([
        'region_code' => 1733, 'year' => 2026, 'title_text' => 'Хоразм йўл харитаси',
        'source_file' => '13. Хоразм.docx', 'imported_at' => now(),
    ]);
    expect($roadmap->domain)->toBe('water');
    expect($roadmap->region->name_short)->toBe('Хоразм');

    $bogot = District::where('code', 1733204)->firstOrFail();
    $roadmap->measures()->create([
        'section_no' => 1, 'section_title' => 'Йирик лойиҳалар', 'seq_no' => 1,
        'title' => 'Канал', 'body_raw' => 'Канал', 'source_row' => 2,
    ]);
    $roadmap->measures()->create([
        'section_no' => 5, 'section_title' => 'Туманларда амалга ошириладиган лойиҳалар',
        'district_id' => $bogot->id, 'district_head_text' => 'туман ҳокими Ж.Назаров', 'seq_no' => 1,
        'title' => 'Бетонлаштириш', 'details' => "1. 7,8 км;\n2. 33 км.", 'body_raw' => 'x', 'source_row' => 30,
    ]);

    expect($roadmap->measures()->count())->toBe(2);
    expect(RoadmapMeasure::regionLevel()->count())->toBe(1);
    expect(RoadmapMeasure::districtLevel()->count())->toBe(1);
    $m = RoadmapMeasure::districtLevel()->first();
    expect($m->district->name_full)->toBe('Боғот тумани');
    expect($m->detailLines())->toBe(['1. 7,8 км;', '2. 33 км.']);
    expect(RoadmapMeasure::regionLevel()->first()->detailLines())->toBe([]);
});

test('deleting a roadmap cascades to its measures', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1]);
    $roadmap->delete();
    expect(RoadmapMeasure::count())->toBe(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RoadmapSchemaTest`
Expected: FAIL — `Class "App\Models\Roadmap" not found` / hasColumns false.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_09_06_000001_create_roadmaps_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 24)->default('water');     // road-map family; 'water' for now
            $table->unsignedInteger('region_code');             // SOATO, FK regions.code
            $table->smallInteger('year');
            $table->text('title_text');                         // the three title paragraphs joined
            $table->text('approvers_text')->nullable();         // table-1 "ТАСДИҚЛАЙМАН" cells joined with ' | '
            $table->string('source_file', 255);                 // basename of the imported docx
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->foreign('region_code')->references('code')->on('regions');
            $table->unique(['domain', 'region_code', 'year'], 'uq_roadmaps_domain_region_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmaps');
    }
};
```

`database/migrations/2026_09_06_000002_create_roadmap_measures_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roadmap_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_id')->constrained('roadmaps')->cascadeOnDelete();
            $table->smallInteger('section_no');                          // Roman numeral of the section header → int
            $table->string('section_title', 255);                        // header text after the numeral
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('district_head_text', 255)->nullable();       // "туман ҳокими Ж.Назаров"
            $table->smallInteger('seq_no');                              // 1-based inside (section, district)
            $table->text('title');                                       // first line / text before "жумладан"
            $table->text('details')->nullable();                         // remaining lines, "\n"-separated
            $table->text('body_raw');                                    // full cell text, "\n"-separated
            $table->text('funding_text')->nullable();                    // column 3
            $table->string('deadline_text', 128)->nullable();            // column 4
            $table->text('responsible_text')->nullable();                // column 5
            $table->smallInteger('source_row');                          // 0-based row in the docx table
            $table->timestamps();

            // NULL district_id rows are distinct for Postgres; the importer guarantees
            // uniqueness itself, the index guards the district case.
            $table->unique(['roadmap_id', 'section_no', 'district_id', 'seq_no'], 'uq_roadmap_measures_pos');
            $table->index(['roadmap_id', 'district_id'], 'idx_roadmap_measures_district');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_measures');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/Roadmap.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roadmap extends Model
{
    protected $fillable = [
        'domain', 'region_code', 'year', 'title_text', 'approvers_text', 'source_file', 'imported_at',
    ];

    protected $casts = [
        'region_code' => 'integer',
        'year'        => 'integer',
        'imported_at' => 'datetime',
    ];

    protected $attributes = [
        'domain' => 'water',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function measures(): HasMany
    {
        return $this->hasMany(RoadmapMeasure::class);
    }
}
```

`app/Models/RoadmapMeasure.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadmapMeasure extends Model
{
    protected $fillable = [
        'roadmap_id', 'section_no', 'section_title', 'district_id', 'district_head_text', 'seq_no',
        'title', 'details', 'body_raw', 'funding_text', 'deadline_text', 'responsible_text', 'source_row',
    ];

    protected $casts = [
        'section_no' => 'integer',
        'seq_no'     => 'integer',
        'source_row' => 'integer',
    ];

    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(Roadmap::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** Sections I–IV: measures without a district. */
    public function scopeRegionLevel(Builder $q): Builder
    {
        return $q->whereNull('district_id');
    }

    /** The "Туманларда амалга ошириладиган лойиҳалар" section. */
    public function scopeDistrictLevel(Builder $q): Builder
    {
        return $q->whereNotNull('district_id');
    }

    /** @return list<string> the collapsed «Батафсил» lines */
    public function detailLines(): array
    {
        if ($this->details === null || $this->details === '') {
            return [];
        }

        return explode("\n", $this->details);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=RoadmapSchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_09_06_000001_create_roadmaps_table.php backend/database/migrations/2026_09_06_000002_create_roadmap_measures_table.php backend/app/Models/Roadmap.php backend/app/Models/RoadmapMeasure.php backend/tests/Feature/Roadmaps/RoadmapSchemaTest.php
git commit -m "feat(roadmaps): roadmaps + roadmap_measures schema and models"
```

---

### Task 2: Docx fixture builder and `DocxTableReader`

**Files:**
- Create: `tests/Helpers/RoadmapDocxBuilder.php`
- Create: `app/Services/Roadmaps/DocxTableReader.php`
- Test: `tests/Unit/Roadmaps/DocxTableReaderTest.php`

- [ ] **Step 1: Write the fixture builder (test helper, no test of its own)**

`tests/Helpers/RoadmapDocxBuilder.php`:

```php
<?php

namespace Tests\Helpers;

use ZipArchive;

/**
 * Builds a minimal .docx shaped like the regional "ЙЎЛ ХАРИТАСИ" documents:
 * table 1 (approvers, one row) · title paragraphs · table 2 (5-column road map
 * with the real header row).
 *
 * Road-map rows:
 *   ['section',  'I. Вилоятда амалга ошириладиган йирик лойиҳалар']       → one merged cell (gridSpan=5)
 *   ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)']   → one merged cell
 *   ['measure',  $bodyLines, $fundingLines, $deadlineLines, $responsibleLines]  → 5 cells, first one empty
 *
 * Each *Lines value is list<string>: separate strings become separate paragraphs
 * (w:p); a "\n" inside one string becomes a soft break (w:br) inside one paragraph.
 */
class RoadmapDocxBuilder
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '</Types>';

    private const RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';

    public const HEADER = ['Т/р', 'Чора-тадбир номи', 'Лойиҳанинг молиялаштириш манбаси', 'Муддати', 'Масъуллар'];

    /**
     * @param list<array> $rows
     * @param list<string> $title
     * @param list<string> $approvers
     */
    public static function make(
        array $rows,
        ?string $path = null,
        array $title = ['2026 йилда Тест вилоятида сув хўжалиги соҳасида амалга ошириладиган', 'тадбирларнинг илмий ечимларига қаратилган', '“ЙЎЛ ХАРИТАСИ”'],
        array $approvers = ['ТАСДИҚЛАЙМАН Университет ректори', 'ТАСДИҚЛАЙМАН Сув хўжалиги вазири', 'ТАСДИҚЛАЙМАН Вилоят ҳокими'],
    ): string {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<w:document xmlns:w="' . self::W_NS . '"><w:body>';

        $xml .= '<w:tbl><w:tr>' . implode('', array_map(fn (string $a) => self::tc([$a]), $approvers)) . '</w:tr></w:tbl>';
        foreach ($title as $t) {
            $xml .= self::p($t);
        }

        $xml .= '<w:tbl>';
        $xml .= '<w:tr>' . implode('', array_map(fn (string $h) => self::tc([$h]), self::HEADER)) . '</w:tr>';
        foreach ($rows as $r) {
            $xml .= '<w:tr>';
            if ($r[0] === 'measure') {
                $xml .= self::tc([]) . self::tc($r[1]) . self::tc($r[2] ?? []) . self::tc($r[3] ?? []) . self::tc($r[4] ?? []);
            } elseif ($r[0] === 'raw') {
                // ['raw', [cellLines, cellLines, ...]] — arbitrary cell layout for edge cases
                foreach ($r[1] as $cell) {
                    $xml .= self::tc($cell);
                }
            } else {
                $xml .= self::tc([$r[1]], 5);
            }
            $xml .= '</w:tr>';
        }
        $xml .= '</w:tbl>';

        $xml .= '<w:tbl><w:tr>' . self::tc(['Вилоят ҳокимининг ўринбосари']) . self::tc([]) . self::tc(['Илмий маслаҳатчи']) . '</w:tr></w:tbl>';
        $xml .= '</w:body></w:document>';

        $path ??= tempnam(sys_get_temp_dir(), 'roadmap_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::RELS);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return $path;
    }

    /** @param list<string> $lines */
    private static function tc(array $lines, int $span = 1): string
    {
        $pr = $span > 1 ? '<w:tcPr><w:gridSpan w:val="' . $span . '"/></w:tcPr>' : '';
        $ps = $lines === [] ? '<w:p/>' : implode('', array_map(fn (string $l) => self::p($l), $lines));

        return '<w:tc>' . $pr . $ps . '</w:tc>';
    }

    private static function p(string $text): string
    {
        $runs = [];
        foreach (explode("\n", $text) as $i => $part) {
            if ($i > 0) {
                $runs[] = '<w:r><w:br/></w:r>';
            }
            $runs[] = '<w:r><w:t xml:space="preserve">' . htmlspecialchars($part, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r>';
        }

        return '<w:p>' . implode('', $runs) . '</w:p>';
    }
}
```

- [ ] **Step 2: Write the failing reader test**

`tests/Unit/Roadmaps/DocxTableReaderTest.php`:

```php
<?php

use App\Services\Roadmaps\DocxTableReader;
use Tests\Helpers\RoadmapDocxBuilder;

test('reads body blocks in order: approvers table, title paragraphs, road-map table', function () {
    $file = RoadmapDocxBuilder::make([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['Канал', "Сатр икки\u{00A0}NBSP билан"], ['Республика бюджети,', '32,0 млрд сўм'], ['2026 йил', 'декабрь'], ['СХВ (Ў.Шералиев)']],
    ]);

    $blocks = (new DocxTableReader())->read($file);

    expect($blocks[0]['type'])->toBe('tbl');
    expect($blocks[0]['rows'])->toHaveCount(1);
    expect($blocks[0]['rows'][0])->toHaveCount(3);
    expect($blocks[0]['rows'][0][0])->toBe(['ТАСДИҚЛАЙМАН Университет ректори']);

    expect($blocks[1])->toBe(['type' => 'p', 'text' => '2026 йилда Тест вилоятида сув хўжалиги соҳасида амалга ошириладиган']);
    expect($blocks[3])->toBe(['type' => 'p', 'text' => '“ЙЎЛ ХАРИТАСИ”']);

    $tbl = $blocks[4];
    expect($tbl['type'])->toBe('tbl');
    expect($tbl['rows'][0])->toBe(array_map(fn ($h) => [$h], RoadmapDocxBuilder::HEADER));
    expect($tbl['rows'][1])->toBe([['I. Йирик лойиҳалар']]);                 // merged → ONE cell
    expect($tbl['rows'][2])->toBe([
        [],                                                                  // empty Т/р cell
        ['Канал', 'Сатр икки NBSP билан'],                                   // NBSP → space
        ['Республика бюджети,', '32,0 млрд сўм'],
        ['2026 йил', 'декабрь'],
        ['СХВ (Ў.Шералиев)'],
    ]);
    expect($blocks[5]['type'])->toBe('tbl');                                 // trailing signers table
});

test('soft breaks (w:br) split lines exactly like paragraphs', function () {
    $file = RoadmapDocxBuilder::make([
        ['measure', ["Бетонлаштириш, жумладан:\n1. 7,8 км;\n2. 33 км."], ['x'], ['y'], ['z']],
    ]);

    $rows = (new DocxTableReader())->read($file)[4]['rows'];

    expect($rows[1][1])->toBe(['Бетонлаштириш, жумладан:', '1. 7,8 км;', '2. 33 км.']);
});

test('a non-docx file is rejected with a clear message', function () {
    $file = tempnam(sys_get_temp_dir(), 'notdocx_');
    file_put_contents($file, 'hello');

    expect(fn () => (new DocxTableReader())->read($file))
        ->toThrow(RuntimeException::class, 'docx');
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --filter=DocxTableReaderTest`
Expected: FAIL — `Class "App\Services\Roadmaps\DocxTableReader" not found`.

- [ ] **Step 4: Write the reader**

`app/Services/Roadmaps/DocxTableReader.php`:

```php
<?php

namespace App\Services\Roadmaps;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Reads word/document.xml of a .docx and returns the body as ordered blocks:
 * paragraphs (cleaned text) and tables (rows → cells → non-empty cleaned lines).
 *
 * Horizontally merged cells (w:gridSpan) arrive as ONE cell; vertically merged
 * continuation cells arrive as an empty cell. Line breaks inside a cell come
 * from paragraph boundaries (w:p) and soft breaks (w:br / w:cr) alike.
 */
class DocxTableReader
{
    public const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return list<array{type:'p',text:string}|array{type:'tbl',rows:list<list<list<string>>>}>
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Файлни docx сифатида очиб бўлмади: {$path}");
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException("word/document.xml топилмади — {$path} .docx эмас (эски .doc бўлса Word'да .docx қилиб сақланг).");
        }

        $dom  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok   = $dom->loadXML($xml, LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if (! $ok) {
            throw new RuntimeException("document.xml ни ўқиб бўлмади: {$path}");
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W_NS);
        $body = $xp->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException("w:body топилмади: {$path}");
        }

        $blocks = [];
        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W_NS) {
                continue;
            }
            if ($node->localName === 'p') {
                $text = implode(' ', $this->paragraphLines($node, $xp));
                if ($text !== '') {
                    $blocks[] = ['type' => 'p', 'text' => $text];
                }
            } elseif ($node->localName === 'tbl') {
                $blocks[] = ['type' => 'tbl', 'rows' => $this->tableRows($node, $xp)];
            }
        }

        return $blocks;
    }

    /** @return list<list<list<string>>> */
    private function tableRows(DOMElement $tbl, DOMXPath $xp): array
    {
        $rows = [];
        foreach ($xp->query('./w:tr', $tbl) as $tr) {
            $cells = [];
            foreach ($xp->query('./w:tc', $tr) as $tc) {
                $lines = [];
                foreach ($xp->query('.//w:p', $tc) as $p) {
                    foreach ($this->paragraphLines($p, $xp) as $line) {
                        $lines[] = $line;
                    }
                }
                $cells[] = $lines;
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /** @return list<string> cleaned, non-empty lines of one paragraph */
    private function paragraphLines(DOMElement $p, DOMXPath $xp): array
    {
        $buf = '';
        foreach ($xp->query('.//w:t | .//w:br | .//w:cr | .//w:tab', $p) as $n) {
            $buf .= match ($n->localName) {
                't'     => $n->textContent,
                'tab'   => ' ',
                default => "\n",
            };
        }

        $out = [];
        foreach (explode("\n", $buf) as $raw) {
            $clean = self::clean($raw);
            if ($clean !== '') {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /** Collapse NBSP variants and runs of whitespace to single spaces. */
    public static function clean(string $v): string
    {
        $v = str_replace(["\u{00A0}", "\u{2007}", "\u{202F}"], ' ', $v);

        return trim((string) preg_replace('/\s+/u', ' ', $v));
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=DocxTableReaderTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/tests/Helpers/RoadmapDocxBuilder.php backend/app/Services/Roadmaps/DocxTableReader.php backend/tests/Unit/Roadmaps/DocxTableReaderTest.php
git commit -m "feat(roadmaps): docx table reader + synthetic docx fixture builder"
```

---

### Task 3: `RoadmapParser` — pure rules

**Files:**
- Create: `app/Services/Roadmaps/RoadmapParser.php` (rules only; `parse()` comes in Task 4)
- Test: `tests/Unit/Roadmaps/RoadmapParserRulesTest.php`

- [ ] **Step 1: Write the failing rules test**

```php
<?php

use App\Services\Roadmaps\RoadmapParser;

test('roman numerals convert, including Cyrillic look-alikes', function () {
    expect(RoadmapParser::romanToInt('I'))->toBe(1);
    expect(RoadmapParser::romanToInt('IV'))->toBe(4);
    expect(RoadmapParser::romanToInt('V'))->toBe(5);
    expect(RoadmapParser::romanToInt('VI'))->toBe(6);
    expect(RoadmapParser::romanToInt('IX'))->toBe(9);
    expect(RoadmapParser::romanToInt("\u{0406}V"))->toBe(4);    // Cyrillic І typed instead of Latin I
    expect(RoadmapParser::romanToInt("\u{0425}"))->toBe(10);    // Cyrillic Х typed instead of Latin X
    expect(RoadmapParser::romanToInt("\u{0456}v"))->toBe(4);    // lowercase look-alikes upper-cased first
    expect(RoadmapParser::romanToInt('1'))->toBeNull();
    expect(RoadmapParser::romanToInt(''))->toBeNull();
});

test('section headers are recognised by a leading Roman numeral and a dot', function () {
    expect(RoadmapParser::matchSectionHeader('I. Вилоятда амалга ошириладиган йирик лойиҳалар'))
        ->toBe(['no' => 1, 'title' => 'Вилоятда амалга ошириладиган йирик лойиҳалар']);
    expect(RoadmapParser::matchSectionHeader('V.Туманларда амалга ошириладиган лойиҳалар'))
        ->toBe(['no' => 5, 'title' => 'Туманларда амалга ошириладиган лойиҳалар']);
    expect(RoadmapParser::matchSectionHeader('1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'))->toBeNull();
    expect(RoadmapParser::matchSectionHeader('Ирригация тармоқлари'))->toBeNull();
});

test('district headers yield the district name and the hokim text', function () {
    expect(RoadmapParser::matchDistrictHeader('1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'))
        ->toBe(['name' => 'Боғот тумани', 'head' => 'туман ҳокими Ж.Назаров']);
    expect(RoadmapParser::matchDistrictHeader('11. Тупроққалъа тумани (масъул - туман ҳокими А.Жималязов)'))
        ->toBe(['name' => 'Тупроққалъа тумани', 'head' => 'туман ҳокими А.Жималязов']);
    expect(RoadmapParser::matchDistrictHeader('3. Урганч шаҳри'))
        ->toBe(['name' => 'Урганч шаҳри', 'head' => null]);
    expect(RoadmapParser::matchDistrictHeader('1. 7,8 км хўжаликлараро каналлар;'))->toBeNull();
    expect(RoadmapParser::matchDistrictHeader('II. Халқаро молия'))->toBeNull();
});

test('measure text splits into title and details', function () {
    // «жумладан» on the first line → title before it, rest = details
    expect(RoadmapParser::splitMeasure(['Насос станцияларида ишлари, жумладан:', '24 та насос агрегатлари.', '1 та электродвигател.']))
        ->toBe(['title' => 'Насос станцияларида ишлари', 'details' => "24 та насос агрегатлари.\n1 та электродвигател."]);
    // «жумладан» with list content on the same line → that content becomes the first detail line
    expect(RoadmapParser::splitMeasure(['Бетонлаштириш, жумладан: 7,8 км каналлар;', '33 км ички каналлар.']))
        ->toBe(['title' => 'Бетонлаштириш', 'details' => "7,8 км каналлар;\n33 км ички каналлар."]);
    // multi-line without «жумладан» → first line is the title (trailing colon stripped)
    expect(RoadmapParser::splitMeasure(['Илмий-тадқиқот ишларини бажариш:', '1. Каналларни бетонлаштириш.', '2. Сув сифатини баҳолаш.']))
        ->toBe(['title' => 'Илмий-тадқиқот ишларини бажариш', 'details' => "1. Каналларни бетонлаштириш.\n2. Сув сифатини баҳолаш."]);
    // single line → no details
    expect(RoadmapParser::splitMeasure(['484,5 млн м3 сувни иқтисод қилиш.']))
        ->toBe(['title' => '484,5 млн м3 сувни иқтисод қилиш.', 'details' => null]);
});

test('cell lines join with a comma after a closing bracket or full stop, else a space', function () {
    expect(RoadmapParser::joinLines(['2026 йил', 'декабрь']))->toBe('2026 йил декабрь');
    expect(RoadmapParser::joinLines(['Республика бюджети маблағлари,', '32,0 млрд сўм']))->toBe('Республика бюджети маблағлари, 32,0 млрд сўм');
    expect(RoadmapParser::joinLines(['Сув хўжалиги вазирлиги (Ў.Шералиев),', 'Вилоят ҳокимлиги (Ў.Машарипов),', 'Илмий маслаҳатчи', '(Б.Матякубов)']))
        ->toBe('Сув хўжалиги вазирлиги (Ў.Шералиев), Вилоят ҳокимлиги (Ў.Машарипов), Илмий маслаҳатчи (Б.Матякубов)');
    expect(RoadmapParser::joinLines(['Чапқирғоқ-Амударё', 'ИТҲБ (Э.Нурметов)', 'Туман ҳокимлари,', 'Илмий маслаҳатчи (Б.Матякубов)']))
        ->toBe('Чапқирғоқ-Амударё ИТҲБ (Э.Нурметов), Туман ҳокимлари, Илмий маслаҳатчи (Б.Матякубов)');
    expect(RoadmapParser::joinLines([]))->toBe('');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RoadmapParserRulesTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the parser rules (parse() is a stub until Task 4)**

`app/Services/Roadmaps/RoadmapParser.php`:

```php
<?php

namespace App\Services\Roadmaps;

use RuntimeException;

/**
 * Turns DocxTableReader blocks of a regional "ЙЎЛ ХАРИТАСИ" into measures.
 *
 * Rows are classified by their TEXT, never by position: section headers start
 * with a Roman numeral ("I. …"), district headers with "N. <name> тумани (…)",
 * everything else with a non-empty second cell is a measure. The Т/р cell is
 * ignored (Word auto-numbering; some regions type "1." in it).
 */
class RoadmapParser
{
    /** Cyrillic capitals typed instead of Latin Roman numerals (І U+0406, Х U+0425). */
    private const ROMAN_LOOKALIKES = ["\u{0406}" => 'I', "\u{0425}" => 'X'];

    private const ROMAN = ['I' => 1, 'V' => 5, 'X' => 10];

    /** @var callable(string):?int */
    private $resolveDistrict;

    /** @param callable(string):?int $resolveDistrict district name ("Боғот тумани") → districts.id, null if unknown */
    public function __construct(callable $resolveDistrict)
    {
        $this->resolveDistrict = $resolveDistrict;
    }

    public function parse(array $blocks): array
    {
        throw new RuntimeException('not implemented yet');
    }

    public static function romanToInt(string $s): ?int
    {
        $s = strtr(mb_strtoupper(trim($s)), self::ROMAN_LOOKALIKES);
        if ($s === '' || preg_match('/^[IVX]+$/', $s) !== 1) {
            return null;
        }
        $total = 0;
        $prev  = 0;
        foreach (array_reverse(str_split($s)) as $ch) {
            $v = self::ROMAN[$ch];
            $total += $v < $prev ? -$v : $v;
            $prev = max($prev, $v);
        }

        return $total;
    }

    /** @return ?array{no:int,title:string} */
    public static function matchSectionHeader(string $text): ?array
    {
        // Latin I/V/X plus the Cyrillic look-alikes І/і (U+0406/U+0456) and Х/х (U+0425/U+0445).
        if (preg_match('/^([IVXivx\x{0406}\x{0456}\x{0425}\x{0445}]+)\s*\.\s*(.+)$/u', trim($text), $m) !== 1) {
            return null;
        }
        $no = self::romanToInt($m[1]);

        return $no === null ? null : ['no' => $no, 'title' => trim($m[2])];
    }

    /**
     * "1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)" → name + hokim text.
     * The parenthesis is optional; a leading "масъул –" is stripped from it.
     *
     * @return ?array{name:string,head:?string}
     */
    public static function matchDistrictHeader(string $text): ?array
    {
        $re = '/^\d+\s*\.\s*(.+?(?:тумани|туман|шаҳри|шахри))\s*(?:\((.*)\))?\s*$/u';
        if (preg_match($re, trim($text), $m) !== 1) {
            return null;
        }
        $head = isset($m[2]) ? trim((string) preg_replace('/^масъул\s*[–—-]\s*/u', '', trim($m[2]))) : '';

        return ['name' => trim($m[1]), 'head' => $head === '' ? null : $head];
    }

    /**
     * @param list<string> $lines non-empty cell lines
     * @return array{title:string,details:?string}
     */
    public static function splitMeasure(array $lines): array
    {
        $first = $lines[0];
        $rest  = array_slice($lines, 1);
        $pos   = mb_stripos($first, 'жумладан');

        if ($pos !== false) {
            $title = mb_substr($first, 0, $pos);
            $after = ltrim(mb_substr($first, $pos + mb_strlen('жумладан')), " :,;");   // keep the item's own trailing ";"
            if ($after !== '') {
                array_unshift($rest, $after);
            }
        } else {
            $title = $first;
        }

        $title = trim((string) preg_replace('/[\s,:;]+$/u', '', $title));

        return ['title' => $title, 'details' => $rest === [] ? null : implode("\n", $rest)];
    }

    /**
     * Join cell lines back into one string: the document already carries commas
     * where it wants separation, so a soft wrap joins with a space; a line that
     * ends in ")" or "." is a complete item and gets ", " before the next one.
     *
     * @param list<string> $lines
     */
    public static function joinLines(array $lines): string
    {
        $out = '';
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $out .= preg_match('/[).]$/u', $out) === 1 ? ', ' : ' ';
            }
            $out .= $line;
        }

        return $out;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=RoadmapParserRulesTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Roadmaps/RoadmapParser.php backend/tests/Unit/Roadmaps/RoadmapParserRulesTest.php
git commit -m "feat(roadmaps): parser rules — roman numerals, header regexes, title/details split"
```

---

### Task 4: `RoadmapParser::parse()` — rows → measures

**Files:**
- Modify: `app/Services/Roadmaps/RoadmapParser.php` (replace the `parse()` stub, add `measures()`)
- Test: `tests/Feature/Roadmaps/RoadmapParserTest.php`

- [ ] **Step 1: Write the failing parse test**

```php
<?php

use App\Services\Roadmaps\DocxTableReader;
use App\Services\Roadmaps\RoadmapParser;
use Tests\Helpers\RoadmapDocxBuilder;

/** Districts of the fake region: name → id. Mirrors the real resolver's contract. */
function fakeDistricts(): Closure
{
    $map = ['боғот' => 11, 'тупроққалъа' => 12, 'тупроққала' => 12];

    return function (string $name) use ($map): ?int {
        $key = mb_strtolower(trim(preg_replace('/\s+тумани$/u', '', $name)));

        return $map[$key] ?? null;
    };
}

function parseFixture(array $rows): array
{
    $blocks = (new DocxTableReader())->read(RoadmapDocxBuilder::make($rows));

    return (new RoadmapParser(fakeDistricts()))->parse($blocks);
}

function standardRows(): array
{
    return [
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['«Куловот» каналини реконструкция қилиш лойиҳасида илмий-техник кузатиш.'], ['Республика бюджети маблағлари,', '32,0 млрд сўм'], ['2026 йил', 'декабрь'], ['Сув хўжалиги вазирлиги (Ў.Шералиев),', 'Вилоят ҳокимлиги (Ў.Машарипов)']],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['Чапқирғоқ-Амударё', 'ИТҲБ (Э.Нурметов)']],
        ['section', 'II. Дуал таълимни ташкил қилиш'],
        ['measure', ['Талабаларни амалиётга юбориш:', '1. 9 нафар механизация.', '2. 5 нафар гидротехника.'], ['Университет маблағлари'], ['2026 йил апрель-октябрь'], ['Университет (Б.Мирзаев)']],
        ['section', 'III. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ["Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар."], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['measure', ['44 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Тупроққала тумани (масъул – туман ҳокими А.Жималязов)'],
        ['measure', ['1 280 гектар ерда сув тежовчи технологиялар.'], ['Банк кредити'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
    ];
}

test('parses title, approvers and every measure with its section/district position', function () {
    $out = parseFixture(standardRows());

    expect($out['title_text'])->toBe('2026 йилда Тест вилоятида сув хўжалиги соҳасида амалга ошириладиган тадбирларнинг илмий ечимларига қаратилган “ЙЎЛ ХАРИТАСИ”');
    expect($out['approvers_text'])->toBe('ТАСДИҚЛАЙМАН Университет ректори | ТАСДИҚЛАЙМАН Сув хўжалиги вазири | ТАСДИҚЛАЙМАН Вилоят ҳокими');

    $m = $out['measures'];
    expect($m)->toHaveCount(6);

    expect($m[0])->toMatchArray([
        'section_no' => 1, 'section_title' => 'Вилоятда амалга ошириладиган йирик лойиҳалар',
        'district_id' => null, 'district_head_text' => null, 'seq_no' => 1,
        'title' => '«Куловот» каналини реконструкция қилиш лойиҳасида илмий-техник кузатиш.',
        'details' => null,
        'funding_text' => 'Республика бюджети маблағлари, 32,0 млрд сўм',
        'deadline_text' => '2026 йил декабрь',
        'responsible_text' => 'Сув хўжалиги вазирлиги (Ў.Шералиев), Вилоят ҳокимлиги (Ў.Машарипов)',
        'source_row' => 2,
    ]);
    expect($m[1]['seq_no'])->toBe(2);
    expect($m[1]['responsible_text'])->toBe('Чапқирғоқ-Амударё ИТҲБ (Э.Нурметов)');

    expect($m[2])->toMatchArray(['section_no' => 2, 'seq_no' => 1, 'title' => 'Талабаларни амалиётга юбориш']);
    expect($m[2]['details'])->toBe("1. 9 нафар механизация.\n2. 5 нафар гидротехника.");

    expect($m[3])->toMatchArray([
        'section_no' => 3, 'district_id' => 11, 'district_head_text' => 'туман ҳокими Ж.Назаров', 'seq_no' => 1,
        'title' => 'Суғориш тармоқларини бетонлаштириш',
        'details' => "1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.",
        'body_raw' => "Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.",
    ]);
    expect($m[4])->toMatchArray(['district_id' => 11, 'seq_no' => 2]);
    expect($m[5])->toMatchArray(['district_id' => 12, 'district_head_text' => 'туман ҳокими А.Жималязов', 'seq_no' => 1]);   // seq resets per district
});

test('an unknown district aborts with the offending name', function () {
    $rows = standardRows();
    $rows[6] = ['district', '1. Йўқтуман тумани (масъул – туман ҳокими X)'];

    expect(fn () => parseFixture($rows))->toThrow(RuntimeException::class, 'Йўқтуман тумани');
});

test('a district header outside the district section aborts', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
    ]))->toThrow(RuntimeException::class, 'туманлар бўлимидан ташқарида');
});

test('a measure before any district header inside the district section aborts', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
    ]))->toThrow(RuntimeException::class, 'туман сарлавҳасидан олдин');
});

test('non-consecutive section numbers abort', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
        ['section', 'III. Дуал таълим'],
    ]))->toThrow(RuntimeException::class, 'кутилган 2');
});

test('an unrecognised merged row aborts instead of being skipped', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['raw', [['Изоҳ: бу қатор нима эканини билмаймиз']]],
    ]))->toThrow(RuntimeException::class, 'танилмаган');
});

test('fully empty rows are skipped and a measure row with only the body cell is still a measure', function () {
    $out = parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['raw', [[], [], [], [], []]],
        ['raw', [[], ['Фақат матн'], [], [], []]],
    ]);

    expect($out['measures'])->toHaveCount(1);
    expect($out['measures'][0])->toMatchArray(['title' => 'Фақат матн', 'funding_text' => null, 'deadline_text' => null, 'responsible_text' => null]);
});

test('a document without a second table aborts', function () {
    $blocks = [['type' => 'tbl', 'rows' => [[['a']]]], ['type' => 'p', 'text' => 'title']];

    expect(fn () => (new RoadmapParser(fakeDistricts()))->parse($blocks))
        ->toThrow(RuntimeException::class, '2 та жадвал');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RoadmapParserTest`
Expected: FAIL — `not implemented yet`.

- [ ] **Step 3: Implement parse() and measures()**

Replace the `parse()` stub in `app/Services/Roadmaps/RoadmapParser.php` with:

```php
    /**
     * @param list<array> $blocks output of DocxTableReader::read()
     * @return array{title_text:string, approvers_text:?string, measures:list<array<string,mixed>>}
     */
    public function parse(array $blocks): array
    {
        $tables = array_values(array_filter($blocks, fn (array $b) => $b['type'] === 'tbl'));
        if (count($tables) < 2) {
            throw new RuntimeException('Ҳужжатда камида 2 та жадвал бўлиши керак (тасдиқловчилар + йўл харита), топилди: ' . count($tables));
        }

        $approvers = [];
        foreach ($tables[0]['rows'] as $row) {
            foreach ($row as $cell) {
                if ($cell !== []) {
                    $approvers[] = implode(' ', $cell);
                }
            }
        }

        // Title = body paragraphs between the first and the second table.
        $title = [];
        $seen  = 0;
        foreach ($blocks as $b) {
            if ($b['type'] === 'tbl') {
                if (++$seen === 2) {
                    break;
                }
                continue;
            }
            if ($seen === 1) {
                $title[] = $b['text'];
            }
        }

        return [
            'title_text'     => implode(' ', $title),
            'approvers_text' => $approvers === [] ? null : implode(' | ', $approvers),
            'measures'       => $this->measures($tables[1]['rows']),
        ];
    }

    /**
     * @param list<list<list<string>>> $rows
     * @return list<array<string,mixed>>
     */
    private function measures(array $rows): array
    {
        $out             = [];
        $section         = null;   // ['no'=>int,'title'=>string,'districts'=>bool]
        $district        = null;   // ['id'=>int,'head'=>?string]
        $seq             = 0;
        $expectedSection = 1;
        $headerSeen      = false;

        foreach ($rows as $i => $cells) {
            $nonEmpty = array_values(array_filter($cells, fn (array $c) => $c !== []));
            if ($nonEmpty === []) {
                continue;                                              // spacer row
            }

            if (! $headerSeen && mb_strtolower(implode(' ', $cells[0] ?? [])) === 'т/р') {
                $headerSeen = true;
                continue;
            }

            if (count($nonEmpty) === 1) {
                $text = implode(' ', $nonEmpty[0]);

                if ($h = self::matchSectionHeader($text)) {
                    if ($h['no'] !== $expectedSection) {
                        throw new RuntimeException("{$i}-қатор: бўлим рақами кутилган {$expectedSection}, топилди {$h['no']} («{$text}»)");
                    }
                    $expectedSection++;
                    $section  = ['no' => $h['no'], 'title' => $h['title'], 'districts' => mb_stripos($h['title'], 'туман') !== false];
                    $district = null;
                    $seq      = 0;
                    continue;
                }

                if ($d = self::matchDistrictHeader($text)) {
                    if (! $section || ! $section['districts']) {
                        throw new RuntimeException("{$i}-қатор: туман сарлавҳаси туманлар бўлимидан ташқарида («{$text}»)");
                    }
                    $id = ($this->resolveDistrict)($d['name']);
                    if ($id === null) {
                        throw new RuntimeException("{$i}-қатор: туман топилмади — «{$d['name']}». districts.alt_labels га қўшинг ёки ҳужжатни текширинг.");
                    }
                    $district = ['id' => $id, 'head' => $d['head']];
                    $seq      = 0;
                    continue;
                }

                if (count($cells) === 1) {
                    throw new RuntimeException("{$i}-қатор: танилмаган бирлашган қатор («{$text}»)");
                }
                // else: a 5-cell row with only one cell filled — falls through to the measure path
            }

            $body = $cells[1] ?? [];
            if ($body === []) {
                throw new RuntimeException("{$i}-қатор: чора-тадбир матни (2-устун) бўш");
            }
            if (! $section) {
                throw new RuntimeException("{$i}-қатор: бўлим сарлавҳасидан олдин чора-тадбир");
            }
            if ($section['districts'] && ! $district) {
                throw new RuntimeException("{$i}-қатор: туман сарлавҳасидан олдин чора-тадбир");
            }

            $split = self::splitMeasure($body);
            $out[] = [
                'section_no'         => $section['no'],
                'section_title'      => $section['title'],
                'district_id'        => $district['id'] ?? null,
                'district_head_text' => $district['head'] ?? null,
                'seq_no'             => ++$seq,
                'title'              => $split['title'],
                'details'            => $split['details'],
                'body_raw'           => implode("\n", $body),
                'funding_text'       => self::joinLines($cells[2] ?? []) ?: null,
                'deadline_text'      => self::joinLines($cells[3] ?? []) ?: null,
                'responsible_text'   => self::joinLines($cells[4] ?? []) ?: null,
                'source_row'         => $i,
            ];
        }

        if ($out === []) {
            throw new RuntimeException('Йўл харита жадвалида бирорта чора-тадбир топилмади.');
        }

        return $out;
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=RoadmapParser`
Expected: PASS (rules 5 + parse 8 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Roadmaps/RoadmapParser.php backend/tests/Feature/Roadmaps/RoadmapParserTest.php
git commit -m "feat(roadmaps): parse road-map table rows into positioned measures"
```

---

### Task 5: `import:roadmap` command

**Files:**
- Create: `app/Console/Commands/ImportRoadmap.php`
- Test: `tests/Feature/Roadmaps/ImportRoadmapTest.php`

- [ ] **Step 1: Write the failing command test**

```php
<?php

use App\Models\District;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\RoadmapDocxBuilder;

uses(RefreshDatabase::class);

function khorezmFixture(?array $rows = null): string
{
    return RoadmapDocxBuilder::make($rows ?? [
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['«Куловот» каналини реконструкция қилиш.'], ['Республика бюджети маблағлари,', '32,0 млрд сўм'], ['2026 йил', 'декабрь'], ['Сув хўжалиги вазирлиги (Ў.Шералиев),', 'Вилоят ҳокимлиги (Ў.Машарипов)']],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['Чапқирғоқ-Амударё', 'ИТҲБ (Э.Нурметов)']],
        ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ["Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар."], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Тупроққала тумани (масъул – туман ҳокими А.Жималязов)'],   // alt_labels spelling
        ['measure', ['1 280 гектар ерда сув тежовчи технологиялар.'], ['Банк кредити'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
    ]);
}

test('imports a regional road map: header row, measures, district links', function () {
    $this->seed();

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => khorezmFixture()]);

    expect($exit)->toBe(0);
    $roadmap = Roadmap::where('region_code', 1733)->firstOrFail();
    expect($roadmap->domain)->toBe('water');
    expect($roadmap->year)->toBe(2026);
    expect($roadmap->title_text)->toContain('“ЙЎЛ ХАРИТАСИ”');
    expect($roadmap->approvers_text)->toContain('Вилоят ҳокими');
    expect($roadmap->source_file)->toEndWith('.docx');
    expect($roadmap->imported_at)->not->toBeNull();

    expect($roadmap->measures()->count())->toBe(4);
    $bogot = District::where('code', 1733204)->firstOrFail();
    $tq    = District::where('code', 1733221)->firstOrFail();
    expect($roadmap->measures()->where('district_id', $bogot->id)->count())->toBe(1);
    expect($roadmap->measures()->where('district_id', $tq->id)->count())->toBe(1);       // resolved via alt_labels

    $m = $roadmap->measures()->where('district_id', $bogot->id)->first();
    expect($m->title)->toBe('Суғориш тармоқларини бетонлаштириш');
    expect($m->detailLines())->toBe(['1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.']);
    expect($m->district_head_text)->toBe('туман ҳокими Ж.Назаров');
    expect($m->section_no)->toBe(2);
    expect($m->seq_no)->toBe(1);

    $first = $roadmap->measures()->orderBy('id')->first();
    expect($first->funding_text)->toBe('Республика бюджети маблағлари, 32,0 млрд сўм');
    expect($first->deadline_text)->toBe('2026 йил декабрь');
    expect($first->responsible_text)->toBe('Сув хўжалиги вазирлиги (Ў.Шералиев), Вилоят ҳокимлиги (Ў.Машарипов)');

    expect(Artisan::output())->toContain('Total: 4 measures, 2 districts');
});

test('re-import replaces the measures instead of duplicating them', function () {
    $this->seed();
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => khorezmFixture()]);
    $firstId = Roadmap::where('region_code', 1733)->value('id');

    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => khorezmFixture()]);

    expect(Roadmap::count())->toBe(1);
    expect(Roadmap::where('region_code', 1733)->value('id'))->toBe($firstId);
    expect(RoadmapMeasure::count())->toBe(4);
});

test('dry run parses and reports but writes nothing', function () {
    $this->seed();

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => khorezmFixture(), '--dry-run' => true]);

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('Dry run');
    expect(Roadmap::count())->toBe(0);
});

test('an unknown district aborts the whole import', function () {
    $this->seed();
    $file = khorezmFixture([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Йўқтуман тумани (масъул – туман ҳокими X)'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
    ]);

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $file]);

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('Йўқтуман тумани');
    expect(Roadmap::count())->toBe(0);
});

test('a missing or unknown region is rejected before reading the file', function () {
    $this->seed();

    expect(Artisan::call('import:roadmap', ['--file' => khorezmFixture()]))->toBe(1);
    expect(Artisan::call('import:roadmap', ['--region' => 9999, '--file' => khorezmFixture()]))->toBe(1);
    expect(Artisan::output())->toContain('--region');
});

test('a missing file is reported', function () {
    $this->seed();

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => 'C:/nope/missing.docx']);

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('топилмади');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ImportRoadmapTest`
Expected: FAIL — `There are no commands defined in the "import" namespace` / command not found.

- [ ] **Step 3: Write the command**

`app/Console/Commands/ImportRoadmap.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Region;
use App\Models\Roadmap;
use App\Services\Roadmaps\DocxTableReader;
use App\Services\Roadmaps\RoadmapParser;
use App\Support\Import\DistrictNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportRoadmap extends Command
{
    /** Relative to base_path(); the docx files are named "<N>. <Region> …docx" where N = region folder number. */
    public const DEFAULT_DIR = '../data/Сув хўжалиги бўйича йўл хариталар';

    protected $signature = 'import:roadmap
        {--region= : SOATO region code, e.g. 1733 (Хоразм)}
        {--file= : Path to the .docx (default: the one file under data/Сув хўжалиги бўйича йўл хариталар/ whose name starts with the region folder number)}
        {--year=2026 : Road-map year}
        {--domain=water : Road-map family}
        {--dry-run : Parse and print the summary without writing}';

    protected $description = 'Import a regional "ЙЎЛ ХАРИТАСИ" (water-management measures) .docx into roadmaps / roadmap_measures.';

    public function handle(): int
    {
        $regionCode = (int) $this->option('region');
        $region     = $regionCode > 0 ? Region::where('code', $regionCode)->first() : null;
        if (! $region) {
            $this->error('Provide --region=<SOATO code>, e.g. --region=1733 (Хоразм).');
            return self::FAILURE;
        }

        $year   = (int) $this->option('year');
        $domain = (string) $this->option('domain');

        $file = $this->option('file') ?: $this->defaultFile($region);
        if ($file === null) {
            return self::FAILURE;                       // defaultFile() already explained why
        }
        if (! is_file($file)) {
            $this->error("Файл топилмади: {$file}");
            return self::FAILURE;
        }

        $this->info("Parsing {$file} — {$region->name_full}, {$year}, {$domain}…");
        try {
            $blocks = (new DocxTableReader())->read($file);
            $parsed = (new RoadmapParser($this->districtResolver($regionCode)))->parse($blocks);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->printSummary($parsed['measures']);

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($parsed, $domain, $regionCode, $year, $file): void {
            $roadmap = Roadmap::updateOrCreate(
                ['domain' => $domain, 'region_code' => $regionCode, 'year' => $year],
                [
                    'title_text'     => $parsed['title_text'],
                    'approvers_text' => $parsed['approvers_text'],
                    'source_file'    => basename($file),
                    'imported_at'    => now(),
                ],
            );
            $roadmap->measures()->delete();             // full replace — the docx is the source of truth
            foreach ($parsed['measures'] as $m) {
                $roadmap->measures()->create($m);
            }
        });

        $this->info('Imported ' . count($parsed['measures']) . " measures for {$region->name_full}.");

        return self::SUCCESS;
    }

    /**
     * Region-scoped district lookup tolerant to spelling variants (name_full,
     * name_short, alt_labels, all normalised the same way the KPI importer does).
     *
     * @return callable(string):?int
     */
    private function districtResolver(int $regionCode): callable
    {
        $map = [];
        District::where('region_code', $regionCode)->get()->each(function (District $d) use (&$map): void {
            $aliases = array_merge([$d->name_full, $d->name_short], is_array($d->alt_labels) ? $d->alt_labels : []);
            foreach ($aliases as $alias) {
                $key = DistrictNameNormalizer::normalize((string) $alias);
                if ($key !== '' && ! isset($map[$key])) {
                    $map[$key] = $d->id;
                }
            }
        });

        return fn (string $name): ?int => $map[DistrictNameNormalizer::normalize($name)] ?? null;
    }

    private function defaultFile(Region $region): ?string
    {
        $dir = base_path(self::DEFAULT_DIR);
        if (preg_match('/^(\d+)/', (string) $region->folder_name, $m) !== 1) {
            $this->error("Region {$region->code} has no numeric folder_name prefix — pass --file explicitly.");
            return null;
        }
        $hits = array_values(array_filter(
            glob($dir . '/*.docx') ?: [],
            fn (string $p) => preg_match('/^' . $m[1] . '[.\s]/u', basename($p)) === 1,
        ));
        if (count($hits) !== 1) {
            $this->error(count($hits) === 0
                ? "No .docx starting with «{$m[1]}.» in {$dir} — pass --file."
                : "Several .docx start with «{$m[1]}.» in {$dir} — pass --file.");
            return null;
        }

        return $hits[0];
    }

    /** @param list<array<string,mixed>> $measures */
    private function printSummary(array $measures): void
    {
        $bySection  = [];
        $byDistrict = [];
        foreach ($measures as $m) {
            $k = $m['section_no'] . '. ' . $m['section_title'];
            $bySection[$k] = ($bySection[$k] ?? 0) + 1;
            if ($m['district_id'] !== null) {
                $byDistrict[$m['district_id']] = ($byDistrict[$m['district_id']] ?? 0) + 1;
            }
        }

        $rows = [];
        foreach ($bySection as $k => $n) {
            $rows[] = [$k, $n];
        }
        $this->table(['Бўлим', 'Тадбирлар'], $rows);

        if ($byDistrict !== []) {
            $names = District::whereIn('id', array_keys($byDistrict))->pluck('name_full', 'id');
            $rows  = [];
            foreach ($byDistrict as $id => $n) {
                $rows[] = [$names[$id] ?? $id, $n];
            }
            $this->table(['Туман', 'Тадбирлар'], $rows);
        }

        $this->info('Total: ' . count($measures) . ' measures, ' . count($byDistrict) . ' districts.');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=ImportRoadmapTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/ImportRoadmap.php backend/tests/Feature/Roadmaps/ImportRoadmapTest.php
git commit -m "feat(roadmaps): import:roadmap command (docx → roadmaps/roadmap_measures)"
```

---

### Task 6: Import the real Хоразм road map

**Files:** none (data only; `data/` is git-ignored).

- [ ] **Step 1: Migrate the dev database**

Run (from `backend/`): `php artisan migrate`
Expected: the two `2026_09_06_*` migrations run.

- [ ] **Step 2: Dry run on the real file**

Run:
```powershell
php artisan import:roadmap --region=1733 --dry-run
```
Expected output (verified counts from the spec):

| Бўлим | Тадбирлар |
| --- | --- |
| 1. Вилоятда амалга ошириладиган йирик лойиҳалар | 5 |
| 2. Халқаро молия институтлари маблағлари ҳисобидан амалга ошириладиган лойиҳалар | 2 |
| 3. Дуал таълимни ташкил қилиш | 4 |
| 4. Вилоятнинг хусусиятидан келиб чиқиб амалга ошириладиган лойиҳалар | 12 |
| 5. Туманларда амалга ошириладиган лойиҳалар | 66 |

11 districts × 6, `Total: 89 measures, 11 districts.`

If the default-file lookup fails, pass `--file="../data/Сув хўжалиги бўйича йўл хариталар/13. Хоразм вилояти якуний.docx"`. If a row aborts, read the message (row index + text), fix the parser rule in Task 3/4 with a test, re-run.

- [ ] **Step 3: Real import and spot check**

Run:
```powershell
php artisan import:roadmap --region=1733
php artisan tinker --execute='$r=\App\Models\Roadmap::first(); echo $r->measures()->count()," / ",$r->measures()->distinct("district_id")->whereNotNull("district_id")->count("district_id"),PHP_EOL; $m=$r->measures()->where("section_no",5)->orderBy("id")->skip(2)->first(); echo $m->district->name_full,PHP_EOL,$m->title,PHP_EOL,$m->details,PHP_EOL,$m->responsible_text,PHP_EOL;'
```
Expected: `89 / 11`; Боғот тумани; title `Насос станцияларида амалга ошириладиган таъмирлаш, ишлаш сифат кўрсаткичларини аниқлаш ва янгисини ўрнатиш ишлари`; details start `1 та насос агрегатларини янгилаш.`; responsible contains `ИТҲБ (Э.Нурметов)`.

No commit (no tracked files change).

---

### Task 7: `/roadmaps` route + `RoadmapsPage` Livewire component

**Files:**
- Modify: `routes/web.php` (add one route after the `/sectors` view route)
- Create: `resources/views/pages/roadmaps.blade.php`
- Create: `app/Livewire/RoadmapsPage.php`
- Create: `resources/views/livewire/roadmaps-page.blade.php`
- Test: `tests/Feature/Roadmaps/RoadmapsPageTest.php`

- [ ] **Step 1: Write the failing page test**

```php
<?php

use App\Livewire\RoadmapsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\Helpers\RoadmapDocxBuilder;

uses(RefreshDatabase::class);

function importKhorezmPage(): void
{
    $file = RoadmapDocxBuilder::make([
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['«Куловот» каналини реконструкция қилиш.'], ['Республика бюджети, 32,0 млрд сўм'], ['2026 йил декабрь'], ['СХВ (Ў.Шералиев)']],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['section', 'II. Дуал таълимни ташкил қилиш'],
        ['measure', ['Талабаларни амалиётга юбориш.'], ['Университет маблағлари'], ['2026 йил апрель-октябрь'], ['Университет (Б.Мирзаев)']],
        ['section', 'III. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ["Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар."], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
        ['measure', ['52 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
    ]);
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $file]);
}

beforeEach(function () {
    $this->seed();
});

test('GET /roadmaps shows the empty state for a region without a road map', function () {
    Session::put('region_code', 1703);

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('Андижон вилояти учун сув хўжалиги йўл харитаси ҳали юкланмаган');
    $response->assertSee('import:roadmap --region=1703');
});

test('GET /roadmaps renders the rail, KPI strip and grouped cards for the session region', function () {
    Session::put('region_code', 1733);
    importKhorezmPage();

    $response = $this->get('/roadmaps');

    $response->assertOk();
    $response->assertSee('Сув хўжалиги йўл харитаси');
    $response->assertSee('wr-card', false);
    $response->assertSeeInOrder(['I.', 'Вилоятда амалга ошириладиган йирик лойиҳалар', 'II.', 'Дуал таълимни ташкил қилиш', 'III.', 'Боғот тумани', 'Гурлан тумани']);
    $response->assertSee('туман ҳокими Ж.Назаров');
    $response->assertSee('Батафсил (2 банд)');
    $response->assertSee('7,8 км хўжаликлараро каналлар');
    $response->assertSee('жами чора-тадбир');
});

test('component reads the region from the session', function () {
    Session::put('region_code', 1733);

    Livewire::test(RoadmapsPage::class)->assertSet('regionCode', 1733);
});

test('district filter shows only that district and hides region-level groups; rail counts stay total', function () {
    Session::put('region_code', 1733);
    importKhorezmPage();

    Livewire::test(RoadmapsPage::class)
        ->call('selectDistrict', '1733204')
        ->assertSee('Суғориш тармоқларини бетонлаштириш')
        ->assertDontSee('«Куловот»')
        ->assertDontSee('52 млн м3')
        ->assertSeeHtml('Барчаси<span class="n tnum">5</span>')
        ->assertSee('Кўрсатилмоқда:')
        ->assertSet('section', 'all');
});

test('section filter shows one section and clears the district', function () {
    Session::put('region_code', 1733);
    importKhorezmPage();

    Livewire::test(RoadmapsPage::class)
        ->call('selectDistrict', '1733204')
        ->call('selectSection', '1')
        ->assertSet('district', 'all')
        ->assertSee('«Куловот»')
        ->assertSee('484,5 млн м3')
        ->assertDontSee('Талабаларни амалиётга')
        ->assertDontSee('Суғориш тармоқларини бетонлаштириш');
});

test('search narrows the cards and reports no match', function () {
    Session::put('region_code', 1733);
    importKhorezmPage();

    // Section/district names also live in the rail, so only measure text is asserted absent.
    Livewire::test(RoadmapsPage::class)
        ->set('q', 'куловот')
        ->assertSee('«Куловот»')
        ->assertDontSee('484,5 млн м3')
        ->assertDontSee('Талабаларни амалиётга')
        ->set('q', 'зззйўқ')
        ->assertSee('Мос чора-тадбир топилмади')
        ->call('clearFilters')
        ->assertSet('q', '')
        ->assertSee('484,5 млн м3');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RoadmapsPageTest`
Expected: FAIL — 404 on `/roadmaps` / class not found.

- [ ] **Step 3: Add the route and the page wrapper**

In `routes/web.php`, after the `/sectors/{code}` route, add:

```php
Route::view('/roadmaps', 'pages.roadmaps')->name('roadmaps');
```

Create `resources/views/pages/roadmaps.blade.php`:

```blade
@extends('layouts.app')

@section('content')
  <livewire:roadmaps-page />
@endsection
```

- [ ] **Step 4: Write the component**

`app/Livewire/RoadmapsPage.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Support\CurrentRegion;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /roadmaps — registry of the region's water-management road-map measures.
 * Region comes from the session (RegionSwitcher). Filters: one section OR one
 * district (district wins), plus a substring search. Rail counts are never
 * filtered; KPI tiles describe the whole road map.
 */
class RoadmapsPage extends Component
{
    public const DOMAIN = 'water';
    public const YEAR   = 2026;

    #[Url]
    public string $section = 'all';

    #[Url]
    public string $district = 'all';   // districts.code as string

    #[Url]
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
        $this->section  = 'all';   // the rail highlights the district section while a district is chosen
    }

    public function clearFilters(): void
    {
        $this->section  = 'all';
        $this->district = 'all';
        $this->q        = '';
    }

    public function render()
    {
        $region  = CurrentRegion::current();
        $roadmap = Roadmap::where('domain', self::DOMAIN)
            ->where('region_code', $this->regionCode)
            ->where('year', self::YEAR)
            ->first();

        if (! $roadmap) {
            return view('livewire.roadmaps-page', ['roadmap' => null, 'region' => $region]);
        }

        $all = $roadmap->measures()->with('district')->orderBy('id')->get();   // id = document order

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
        ])->values();

        $rows = $all;
        if ($this->district !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => $m->district && (string) $m->district->code === $this->district);
        } elseif ($this->section !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => (string) $m->section_no === $this->section);
        }
        $needle = mb_strtolower(trim($this->q));
        if ($needle !== '') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => str_contains(
                mb_strtolower(implode(' ', [$m->title, $m->details, $m->responsible_text, $m->funding_text])),
                $needle,
            ));
        }

        // Document-order groups: section → (district) → measures.
        $groups = [];
        foreach ($rows as $m) {
            $key = $m->section_no . ':' . ($m->district_id ?? 0);
            $groups[$key] ??= [
                'roman'         => self::roman($m->section_no),
                'section_title' => $m->section_title,
                'district'      => $m->district,
                'head'          => $m->district_head_text,
                'measures'      => [],
            ];
            $groups[$key]['measures'][] = $m;
        }

        return view('livewire.roadmaps-page', [
            'roadmap'           => $roadmap,
            'region'            => $region,
            'sections'          => $sections,
            'districts'         => $districts,
            'districtSectionNo' => $districtSectionNo,
            'groups'            => array_values($groups),
            'kpi'               => [
                'total'          => $all->count(),
                'region_level'   => $all->whereNull('district_id')->count(),
                'district_level' => $all->whereNotNull('district_id')->count(),
                'districts'      => $districts->count(),
            ],
            'shown'             => $rows->count(),
            'filtered'          => $this->section !== 'all' || $this->district !== 'all' || $needle !== '',
        ]);
    }

    public static function roman(int $n): string
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

- [ ] **Step 5: Write the view**

`resources/views/livewire/roadmaps-page.blade.php`:

```blade
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
               wire:model.live.debounce.300ms="q" aria-label="Чора-тадбирлар бўйича қидирув">
      </div>

      <nav class="wr-kcard" aria-label="Бўлимлар">
        <div class="kt">Бўлимлар</div>
        <button type="button" class="{{ $section === 'all' && $district === 'all' ? 'on' : '' }}"
                wire:click="selectSection('all')">Барчаси<span class="n tnum">{{ $kpi['total'] }}</span></button>
        @foreach($sections as $s)
          @php $on = $district === 'all' ? $section === (string) $s['no'] : $districtSectionNo === $s['no']; @endphp
          <button type="button" class="{{ $on ? 'on' : '' }}" title="{{ $s['title'] }}"
                  wire:click="selectSection('{{ $s['no'] }}')">
            <b>{{ $s['roman'] }}.</b> <span class="t">{{ $s['title'] }}</span><span class="n tnum">{{ $s['count'] }}</span>
          </button>
        @endforeach
      </nav>

      @if($districts->isNotEmpty())
        <nav class="wr-kcard" aria-label="Туманлар">
          <div class="kt">Туманлар</div>
          @foreach($districts as $d)
            <button type="button" class="{{ $district === (string) $d['code'] ? 'on' : '' }}" title="{{ $d['head'] }}"
                    wire:click="selectDistrict('{{ $d['code'] }}')">
              <span class="t">{{ $d['name'] }}</span><span class="n tnum">{{ $d['count'] }}</span>
            </button>
          @endforeach
        </nav>
      @endif
    </aside>

    <main class="wr-main">
      <header class="wr-head">
        <h2>Сув хўжалиги йўл харитаси</h2>
        <p class="sub">{{ $roadmap->title_text }}</p>
      </header>

      <div class="wr-kpis">
        <div class="wr-kpi"><b class="tnum">{{ $kpi['total'] }}</b><span>жами чора-тадбир</span></div>
        <div class="wr-kpi"><b class="tnum">{{ $kpi['region_level'] }}</b><span>вилоят даражаси</span></div>
        <div class="wr-kpi"><b class="tnum">{{ $kpi['district_level'] }}</b><span>туман лойиҳалари</span></div>
        <div class="wr-kpi"><b class="tnum">{{ $kpi['districts'] }}</b><span>туман</span></div>
      </div>

      @if($filtered)
        <div class="wr-filterbar">
          <span>Кўрсатилмоқда: <b class="tnum">{{ $shown }}</b> / {{ $kpi['total'] }}</span>
          <button type="button" wire:click="clearFilters">Фильтрни тозалаш</button>
        </div>
      @endif

      @forelse($groups as $g)
        <section class="wr-group" wire:key="wr-group-{{ $loop->index }}">
          <h3 class="wr-gtitle">
            <span class="rn">{{ $g['roman'] }}.</span> {{ $g['section_title'] }}
            @if($g['district'])
              <span class="sep">·</span><span class="dn">{{ $g['district']->name_full }}</span>
              @if($g['head'])<span class="hd">{{ $g['head'] }}</span>@endif
            @endif
          </h3>
          @foreach($g['measures'] as $m)
            @php $lines = $m->detailLines(); @endphp
            <article class="wr-card" wire:key="wr-m-{{ $m->id }}" x-data="{ open: false }">
              <span class="no tnum">{{ $m->seq_no }}</span>
              <div class="body">
                <div class="ttl">{{ $m->title }}</div>
                @if($lines !== [])
                  <button type="button" class="wr-more" x-on:click="open = !open" :aria-expanded="open">
                    <span class="c" :class="open && 'open'">▸</span>
                    <span x-text="open ? 'Ёпиш' : 'Батафсил ({{ count($lines) }} банд)'">Батафсил ({{ count($lines) }} банд)</span>
                  </button>
                  <div class="wr-details" x-show="open" x-cloak>
                    @foreach($lines as $line)<p>{{ $line }}</p>@endforeach
                  </div>
                @endif
                <div class="chips">
                  @if($m->district)<span class="wr-chip d">{{ $m->district->name_full }}</span>@endif
                  @if($m->funding_text)<span class="wr-chip">{{ $m->funding_text }}</span>@endif
                </div>
              </div>
              <div class="col"><b>Масъуллар</b>{{ $m->responsible_text ?? '—' }}</div>
              <div class="col"><b>Муддат</b>{{ $m->deadline_text ?? '—' }}</div>
            </article>
          @endforeach
        </section>
      @empty
        <div class="wr-empty small">Мос чора-тадбир топилмади.</div>
      @endforelse
    </main>
  @endif
</div>
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=RoadmapsPageTest`
Expected: PASS (6 tests). If `assertSeeHtml('Барчаси<span class="n tnum">5</span>')` fails on whitespace, the view line for «Барчаси» must keep the `</button>` markup exactly as written above (no newline between the text and the `<span>`).

- [ ] **Step 7: Run the whole roadmap suite + the region context test**

Run: `php artisan test --filter="Roadmap|RegionContext"`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add backend/routes/web.php backend/resources/views/pages/roadmaps.blade.php backend/app/Livewire/RoadmapsPage.php backend/resources/views/livewire/roadmaps-page.blade.php backend/tests/Feature/Roadmaps/RoadmapsPageTest.php
git commit -m "feat(roadmaps): /roadmaps page — rail filters, KPI strip, grouped measure cards"
```

---

### Task 8: `wr-` styles in portal.css

**Files:**
- Modify: `public/css/portal.css` (append at the end of the file — it is hand-maintained, no build step)

- [ ] **Step 1: Append the block**

Append to the end of `public/css/portal.css`:

```css

/* ==========================================================================
   /roadmaps · Сув хўжалиги йўл харитаси (wr- namespace, inside layouts.app)
   ========================================================================== */
[x-cloak]{display:none!important}
.wr-shell{display:grid;grid-template-columns:272px minmax(0,1fr);gap:20px;align-items:start}
.wr-shell .tnum{font-variant-numeric:tabular-nums}
.wr-shell button{font-family:inherit;cursor:pointer}

.wr-rail{display:flex;flex-direction:column;gap:14px;position:sticky;top:78px}
.wr-search input{
  width:100%;border:1px solid var(--line);border-radius:10px;background:var(--paper);
  padding:10px 12px;font:inherit;font-size:13px;color:var(--ink);box-shadow:var(--shadow-sm);
}
.wr-search input:focus{outline:0;border-color:var(--blue);box-shadow:var(--ring-blue)}
.wr-kcard{background:var(--paper);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow-sm);padding:10px}
.wr-kcard .kt{font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin:4px 8px 8px}
.wr-kcard button{
  display:flex;align-items:center;gap:7px;width:100%;text-align:left;border:0;background:none;
  border-radius:9px;padding:8px 10px;font-size:12.5px;font-weight:600;color:var(--muted);
  line-height:1.3;transition:background .16s,color .16s;
}
.wr-kcard button b{color:var(--ink);font-weight:800;flex:0 0 auto}
.wr-kcard button .t{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wr-kcard button:hover{background:var(--bg)}
.wr-kcard button.on{background:var(--blue-soft);color:var(--blue)}
.wr-kcard button.on b{color:var(--blue)}
.wr-kcard button .n{margin-left:auto;flex:0 0 auto;font-size:11px;font-weight:700;color:var(--muted);background:var(--grey-soft);border-radius:999px;padding:1px 8px}
.wr-kcard button.on .n{background:#fff;color:var(--blue)}

.wr-main{min-width:0;display:flex;flex-direction:column;gap:16px}
.wr-head h2{font-size:20px;font-weight:800;letter-spacing:-.015em;color:var(--ink)}
.wr-head .sub{margin-top:4px;font-size:12.5px;color:var(--muted);max-width:820px;line-height:1.45}

.wr-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.wr-kpi{background:var(--paper);border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow-sm);padding:14px 16px}
.wr-kpi b{display:block;font-size:24px;font-weight:800;letter-spacing:-.02em;color:var(--ink);line-height:1.1}
.wr-kpi span{font-size:11.5px;color:var(--muted);font-weight:600}

.wr-filterbar{display:flex;align-items:center;gap:14px;font-size:12.5px;color:var(--muted)}
.wr-filterbar b{color:var(--ink)}
.wr-filterbar button{border:0;background:none;color:var(--blue);font-size:12.5px;font-weight:700;padding:0}

.wr-group{background:var(--paper);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow-sm);padding:4px 20px 6px}
.wr-gtitle{
  display:flex;flex-wrap:wrap;align-items:baseline;gap:8px;padding:12px 0 10px;
  font-size:13.5px;font-weight:800;color:var(--ink);border-bottom:1px solid var(--line);
}
.wr-gtitle .rn{color:var(--blue)}
.wr-gtitle .sep{color:var(--line-strong)}
.wr-gtitle .dn{color:var(--green)}
.wr-gtitle .hd{font-size:12px;font-weight:600;color:var(--muted)}

.wr-card{
  display:grid;grid-template-columns:34px minmax(0,1fr) 210px 120px;gap:14px;align-items:start;
  padding:14px 0;border-top:1px solid var(--line);
}
.wr-card:first-of-type{border-top:0}
.wr-card .no{font-size:11px;font-weight:800;color:var(--muted);background:var(--grey-soft);border-radius:6px;padding:3px 0;text-align:center;margin-top:1px}
.wr-card .ttl{font-size:13.5px;font-weight:650;line-height:1.45;color:var(--ink)}
.wr-card .chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.wr-chip{display:inline-flex;align-items:center;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:var(--grey-soft);color:var(--muted);max-width:100%}
.wr-chip.d{background:var(--green-soft);color:var(--green)}
.wr-card .col{font-size:12px;color:var(--muted);line-height:1.4}
.wr-card .col b{display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);opacity:.75;margin-bottom:3px}

.wr-more{margin-top:6px;border:0;background:none;padding:2px 0;font-size:12px;font-weight:700;color:var(--blue);display:inline-flex;align-items:center;gap:6px}
.wr-more .c{display:inline-block;transition:transform .2s}
.wr-more .c.open{transform:rotate(90deg)}
.wr-details{margin-top:8px;border:1px solid var(--line);border-radius:10px;background:var(--surface);padding:6px 12px}
.wr-details p{margin:0;padding:4px 0;font-size:12.5px;line-height:1.45;color:var(--ink)}
.wr-details p+p{border-top:1px dashed var(--line)}

.wr-empty{grid-column:1/-1;background:var(--paper);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow-sm);padding:48px 24px;text-align:center;color:var(--muted)}
.wr-empty h2{font-size:17px;font-weight:750;color:var(--ink);margin-bottom:8px}
.wr-empty code{font-size:12px;background:var(--grey-soft);border-radius:6px;padding:2px 6px}
.wr-empty.small{padding:30px}

@media (max-width:1100px){
  .wr-shell{grid-template-columns:1fr}
  .wr-rail{position:static}
  .wr-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
  .wr-card{grid-template-columns:34px minmax(0,1fr)}
  .wr-card .col{grid-column:2}
}
```

- [ ] **Step 2: Sanity-check the stylesheet still parses**

Run: `php -r "echo substr_count(file_get_contents('public/css/portal.css'), '{') === substr_count(file_get_contents('public/css/portal.css'), '}') ? 'braces ok' : 'BRACE MISMATCH', PHP_EOL;"`
Expected: `braces ok`.

- [ ] **Step 3: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(roadmaps): wr- rail, KPI strip, grouped cards and details block"
```

---

### Task 9: Browser verification on the real data

**Files:** none.

- [ ] **Step 1: Serve**

If `php artisan serve` (port 8000) is not already running (see the ngrok memory: it usually is), start it from `backend/`:
```powershell
php artisan serve
```

- [ ] **Step 2: Check in Chrome (claude-in-chrome tools) or manually**

1. Open `http://localhost:8000/roadmaps`. With the default region (Андижон) you must see the empty-state panel with the `import:roadmap --region=1703` hint.
2. Use the sidebar `RegionSwitcher` to pick **Хоразм**. The page must show: title «Сув хўжалиги йўл харитаси», KPI tiles `89 · 23 · 66 · 11`, rail with 5 sections and 11 districts, groups I…V in document order.
3. Click **Боғот** in the rail: only the Боғот group (6 cards) stays, the filter bar says `Кўрсатилмоқда: 6 / 89`, the URL carries `?district=1733204`, the rail highlights section V and Боғот.
4. Open «Батафсил» on the Боғот pump-station card: the detail lines expand, the caret rotates, the label turns into «Ёпиш».
5. Type `насос` in the search: every group with a pump-station measure remains, others disappear; type `qqqq`: «Мос чора-тадбир топилмади».
6. Click «Фильтрни тозалаш»: all 89 back.
7. Narrow the window below 1100px: rail stacks above the list, cards collapse to two columns.

Fix anything visibly off (spacing, overflow of long funding chips, rail label truncation) in `portal.css` and re-check. Commit any CSS fix as `style(roadmaps): …`.

---

### Task 10: Docs — runbook, CLAUDE.md, spec as-built notes

**Files:**
- Create: `docs/roadmap-import.md` (under `backend/`)
- Modify: `CLAUDE.md` (route table + import pipelines)
- Modify: `docs/superpowers/specs/2026-09-06-water-roadmaps-design.md` (status + as-built deviations)

- [ ] **Step 1: Write the runbook**

`backend/docs/roadmap-import.md`:

```markdown
# Сув хўжалиги йўл хариталари — import runbook

Source: `data/Сув хўжалиги бўйича йўл хариталар/<N>. <Вилоят> … .docx`, one file per
region (N = the region's folder number, same as `regions.folder_name`). The documents
are the 2026 "ЙЎЛ ХАРИТАСИ" approved by the Ministry of Water Resources, ТИҚХММИ and
the regional hokim. They are a registry (no plan/actual numbers); the portal shows
them at `/roadmaps` for the session region.

## Import one region

```powershell
cd backend
php artisan import:roadmap --region=1733 --dry-run   # parse + summary, no write
php artisan import:roadmap --region=1733             # write (replaces the region's measures)
```

Options: `--file=` (explicit path; needed when the default lookup finds 0 or 2+ files),
`--year=2026`, `--domain=water`.

The command is idempotent per (domain, region, year): it upserts the `roadmaps` row and
replaces all its `roadmap_measures` inside one transaction.

## What the parser expects

- Table 1 = approvers; paragraphs after it = title; table 2 = the road map with the
  `Т/р | Чора-тадбир номи | … | Муддати | Масъуллар` header.
- Section headers: one merged cell starting with a Roman numeral (`I.`, `IV.`, `VI.`).
  Numbers must be consecutive from I.
- District headers (only inside the section whose title contains «туман»):
  `N. <Туман номи> тумани (масъул – …)`. The name is matched against
  `districts.name_full / name_short / alt_labels` of the region — an unknown name aborts
  the import; add the spelling to `alt_labels` (SoatoSeeder) or fix the docx.
- Any other merged (single-cell) row aborts with its row index and text.
- The `Т/р` cell is ignored — Word auto-numbering; the importer counts `seq_no` itself.

## Known limitations

- Сурхондарё's file is `.doc` — open in Word, *Save As* `.docx` first.
- Other regions differ in section numbering (Андижон has districts in VI) — that is
  fine; the parser keys on text. New surprises (a row shape not listed above) need a
  parser rule + test, not a manual edit of the DB.
- No status/progress yet. Adding it later = a `roadmap_measure_progress` table; the
  registry schema stays as is.
```

- [ ] **Step 2: Update CLAUDE.md**

In the route table (after the `/sectors` row) add:

```markdown
| `/roadmaps` | `RoadmapsPage` | Water-management road-map measures (сув хўжалиги йўл харитаси) for the session region; rail filters by section/district, search; registry only, URL-access only (no sidebar link yet) |
```

In "Data import pipelines" add item 4:

```markdown
4. **Water road maps (сув хўжалиги йўл хариталари):** `import:roadmap --region=1733` — reads the per-region `.docx` under `data/Сув хўжалиги бўйича йўл хариталар/` (ZipArchive + DOM, header-text keyed). Runbook: `backend/docs/roadmap-import.md`.
```

- [ ] **Step 3: Update the spec header**

In `docs/superpowers/specs/2026-09-06-water-roadmaps-design.md` change `**Status:**` to `implemented (2026-09-06)` and add below the Phase line:

```markdown
> **As-built deviations:** parser classes live in `App\Services\Roadmaps\`
> (`DocxTableReader` + `RoadmapParser`), matching the existing `App\Services\Tasks`
> layout, not `App\Support\Roadmap`; a measure row is any multi-cell row whose second
> cell is non-empty (the "≥ 2 non-empty cells" rule was too strict for rows with an
> empty funding/deadline cell); fully empty rows are skipped; `responsible_text` /
> `funding_text` / `deadline_text` lines join with `, ` after a line ending in `)` or
> `.` and with a space otherwise; choosing a district leaves `section=all` in the URL
> and the rail highlights the district section instead.
```

- [ ] **Step 4: Commit**

```bash
git add backend/docs/roadmap-import.md CLAUDE.md docs/superpowers/specs/2026-09-06-water-roadmaps-design.md
git commit -m "docs(roadmaps): import runbook, CLAUDE.md route/pipeline rows, spec as-built notes"
```

---

### Task 11: Full test run and wrap-up

- [ ] **Step 1: Run the full suite once**

Run (from `backend/`, ~10 min, single process): `php artisan test`
Expected: green. If an unrelated test fails, report it — do not fix unrelated code in this branch.

- [ ] **Step 2: Report**

Summarise: commits on `roadmaps`, real-data counts (89 / 11), how to open the page (`/roadmaps` + RegionSwitcher → Хоразм), what is deferred (sidebar link, statuses, 13 regions). Then use the `superpowers:finishing-a-development-branch` skill.
