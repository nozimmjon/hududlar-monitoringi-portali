# Tasks page design

**Date:** 2026-05-08
**Status:** Approved (pending user spec review)
**Scope:** Implement the Andijan-region tasks page in the Laravel app: schema, importer, Livewire view, Blade markup mirroring `index.html#tasksPage`.

---

## 1. Goal

Build a filterable, read-only catalogue of guarantee-letter tasks for Andijan region. Source data: `data/tasks/00_Чора_тадбир_Андижон.docx` (1 table, 5 columns, ~86 data rows, 17 section/sub-section headers). Layout and class names match the `index.html` prototype's tasks page exactly so the existing CSS in `backend/public/css/portal.css` covers the visual.

The data model is **schema-ready for all 14 regions** (every task carries a `region_code`) but only Andijan is ingested in this spec. Tasks linked to specific districts via a many-to-many pivot, so a future districts-page spec can render `District::tasks()` without schema changes.

## 2. Non-goals

- No status editing UI. `tasks.status` exists with default `'open'` and is shown in the layout for visual parity, but UI is read-only.
- No evidence/report linkage. The prototype's `task-actions` chips that reference evidence/report counts are out of scope here.
- No ingestion of the other 13 regions. The schema supports them; a future spec will add their docx files.
- No districts-page integration in this spec — only the schema and pivot enable it.
- No edits to the dashboard or any other existing page.

## 3. Strategy

Single-pass implementation: schema → models → importer → Livewire view → Blade markup → tests. Mirrors the existing `index.html#tasksPage` markup 1:1; CSS already exists in `portal.css` so this spec ships zero new CSS rules.

## 4. Schema

### 4.1 `tasks` migration (`2026_05_08_000001_create_tasks_table.php`)

| Column | Type | Notes |
|---|---|---|
| `id` | bigInt PK | |
| `region_code` | string(32) | FK → `regions.code` |
| `guarantee_letter_id` | foreignId nullable | FK → `guarantee_letters.id` (cascade null on delete) |
| `task_number` | string(16) | source col 0 (e.g. `"1"`, `"27"`) |
| `title` | text | source col 1 |
| `deadline_text` | string(128) nullable | raw col 2 (e.g. `"2026 йил I ярим йиллик"`) |
| `period_code` | string(16) nullable | derived: `'h1'` / `'year'` / null |
| `executor_text` | text | raw col 3 |
| `kind` | string(16) | `'kpi'` or `'measure'` |
| `module_code` | string(32) nullable | FK → `modules.code` |
| `indicator_code` | string(48) nullable | FK → `indicators.code` |
| `section_path` | string(16) | e.g. `"I.1.2"` |
| `section_label` | string(255) | full subsection text e.g. `"1.2. Саноат йўналишида"` |
| `source_paragraph_index` | int | row index in docx |
| `status` | string(16) default `'open'` | reserved for future status edit |
| `created_at`/`updated_at` | timestamps | |

Indexes:
- composite `(region_code, module_code)`
- composite `(region_code, indicator_code)`
- composite `(region_code, kind)`
- unique `(region_code, task_number)` — task numbering is unique within a region's letter.

Foreign keys: `region_code` → `regions.code`, `guarantee_letter_id` → `guarantee_letters.id` (`nullOnDelete`), `module_code` → `modules.code` (`nullOnDelete`), `indicator_code` → `indicators.code` (`nullOnDelete`).

### 4.2 `task_districts` pivot (`2026_05_08_000002_create_task_districts_table.php`)

| Column | Type |
|---|---|
| `task_id` | foreignId, cascade delete |
| `district_id` | foreignId, cascade delete |

Composite primary key on `(task_id, district_id)`. Index on `district_id` for the District→tasks reverse lookup.

## 5. Models

### 5.1 `app/Models/Task.php` (new)

Fillable: every schema column except `id`/timestamps.

Relationships:

- `region()` → `belongsTo(Region::class, 'region_code', 'code')`
- `guaranteeLetter()` → `belongsTo(GuaranteeLetter::class)`
- `module()` → `belongsTo(Module::class, 'module_code', 'code')`
- `indicator()` → `belongsTo(Indicator::class, 'indicator_code', 'code')`
- `districts()` → `belongsToMany(District::class, 'task_districts')`

Scopes (all return `Builder`):

- `scopeForRegion($q, string $code)`
- `scopeForModule($q, string $code)`
- `scopeForIndicator($q, string $code)`
- `scopeForDistrict($q, int $districtId)` — uses `whereHas('districts', fn($d) => $d->where('districts.id', $districtId))`
- `scopeForPeriod($q, string $code)`
- `scopeOfKind($q, string $kind)`
- `scopeSearch($q, string $term)` — `WHERE title ILIKE ? OR executor_text ILIKE ? OR section_label ILIKE ?` with `%term%` (Postgres ILIKE).

### 5.2 `app/Models/District.php` (extend)

Add `tasks()` → `belongsToMany(Task::class, 'task_districts')`.

## 6. Importer

### 6.1 `app/Console/Commands/ImportTasks.php` (new)

Signature: `php artisan import:tasks {region=andijan} {--file=}`.

Flow:

1. Resolve region by `code`. If `--file=` not given, look up filename from internal map keyed by region code:
   - `andijan` → `data/tasks/00_Чора_тадбир_Андижон.docx`
   - other 13 entries listed for completeness so future regions plug in without code changes.
2. Load the docx via `PhpOffice\PhpWord\IOFactory::load($path)`. Read the first table (5 cols).
3. Walk rows tracking running state (`current_module`, `current_indicator`, `current_section_path`, `current_section_label`). Row classification (in order):
   - **Section/sub-section header**: all 5 cells contain the identical text (the docx merges them visually). The header text in col 0 is then matched against:
     - **Roman heading** regex `^(VII|VI|IV|V|III|II|I)\.\s` (longest alternative first to avoid `V` matching the start of `VII`). Whitespace class matches NBSP (`\xa0`). Set `current_module` per map and reset `current_indicator` to null and `current_section_path` to the Roman numeral.
       - `I → macro, II → inflation, III → budget, IV → budget_investment, V → foreign_invest, VI → export, VII → employment`
     - **Numeric subsection** regex `^(\d+)\.(\d+)\.\s` (whitespace class matches NBSP). Set `current_indicator` per map. Set `current_section_path = current_module_roman + '.' + numeric_match`. Set `current_section_label` to full cell text.
       - `1.1 → grp, 1.2 → industry, 1.3 → services, 1.4 → agri, 1.5 → build, 7.1 → unemployment, 7.2 → poverty`
       - Other subsections (e.g. inflation, budget have only Roman headers and no numeric sub-sections) leave `current_indicator` null.
   - **Data row**: any two cells differ. Assemble a Task using the running state.
4. Parse executor cell:
   - Split on `,` and `\n`. Trim each token. Strip trailing ` ҳокимлиги`/` ҳокимияти` suffixes.
   - Match each token in this order:
     1. exact `name_full` for region
     2. exact `name_short` for region
     3. any string in `alt_labels` jsonb (case-insensitive)
   - Unmatched tokens are logged as `import:tasks` warnings via `$this->warn(...)`; do not error the run.
5. Normalize `kind`: `KPI` → `'kpi'`, anything starting with `Чора-тадбир` → `'measure'`, else default `'measure'`.
6. Derive `period_code` from `deadline_text`:
   - contains `I ярим йиллик` → `'h1'`
   - contains `якуни` → `'year'`
   - else null
7. Wrap inserts in `DB::transaction(...)`. Idempotent: at the start of the transaction, `Task::where('region_code', $code)->delete()` (the cascade on `task_districts` cleans the pivot). Then insert all parsed tasks; for each task with district matches, attach via `$task->districts()->sync($ids)`.
8. Output summary: counts of inserted tasks, sections seen, executor tokens unmatched, skipped rows.

### 6.2 Tests for the importer

`tests/Feature/Console/ImportTasksCommandTest.php`:

- Seeds the `regions`, `districts`, `modules`, `indicators` tables with at least Andijan + the 7 modules + 7 indicators + ~6 sample districts including `Бўстон`, `Улуғнор`, `Пахтаобод` (the multi-district row's targets).
- Copies the production docx to a fixture path or uses it directly.
- Asserts after `php artisan import:tasks andijan`:
  - `Task::where('region_code', 'andijan')->count() ≥ 80`
  - A sample row (e.g. `task_number = '3'`) has `module_code = 'macro'`, `indicator_code = 'industry'`, `section_path = 'I.1.2'`, `kind = 'kpi'`, `period_code = 'h1'`.
  - A known multi-district row attaches exactly 3 districts via the pivot.
  - Re-running the command leaves the row count unchanged (idempotent).

## 7. Livewire `TasksBoard`

### 7.1 `app/Livewire/TasksBoard.php` (replaces stub)

URL-synced state via `#[Url]`:

- `module: string = 'all'`
- `indicator: string = 'all'`
- `status: string = 'open'`
- `period: string = 'all'`
- `district: string = 'all'`
- `search: string = ''`

Computed (`#[Computed]`) properties:

- `tasks()` — base query `Task::with('module', 'indicator', 'districts')->forRegion('andijan')`, then chain scopes for any non-`'all'` filter and `searchScope` if `search !== ''`.
- `moduleOptions()` — distinct `module_code` for region with module label lookup.
- `indicatorOptions()` — distinct `indicator_code` filtered by current `module` if not `'all'`, with indicator label lookup.
- `districtOptions()` — districts with at least one task in current scope.
- `total()`, `done()`, `open()` — counts. `done()` always returns 0 in this spec (status field reserved); `open()` = `total()`. Kept so the prototype's pill counters render.
- `donePct()` — `total ? round(done/total * 100) : 0`.

Action methods (one per filter for explicit Livewire wiring): `selectModule($code)`, `selectIndicator($code)`, `selectStatus($code)`, `selectPeriod($code)`, `selectDistrict($code)`, `clearFilters()`. Each method also resets cascading filters (e.g. `selectModule` resets `indicator` to `'all'`).

### 7.2 `resources/views/livewire/tasks-board.blade.php` (replaces stub)

Markup mirrors `index.html` lines 8282-8377 with classes verbatim: `.task-filter.report-filter`, `.task-advanced-filters`, `.task-summary-strip.execution-overview`, `.task-workspace`, `.task-groups`, `.task-group`, `.task-list`, `.task-card.compact`, `.task-focus`, `.task-side-stack`, `.task-side-row`. Selects use `wire:model.live`. Pills use `wire:click="selectStatus('open')"` etc.

Per-task card body:

```blade
<article class="task-card compact" data-task-id="{{ $task->id }}">
  <header>
    <span class="task-code">{{ $task->task_number }}</span>
    <strong>{{ $task->title }}</strong>
    <div class="task-meta">
      <span>{{ $task->deadline_text }}</span>
      <span>{{ $task->districts->count() ? $task->districts->count() . ' туман/шаҳар' : 'вилоят' }}</span>
      <span>{{ $task->module?->label ?? $task->section_label }}</span>
    </div>
  </header>
  <div class="task-actions">
    <span class="chip grey">{{ $task->kind === 'kpi' ? 'KPI' : 'Чора-тадбир' }}</span>
    @if($task->indicator)
      <span class="chip blue">{{ $task->indicator->label_short }}</span>
    @endif
  </div>
</article>
```

`task-side-stack` rows: total in current scope, selected indicator label, "Ҳисобот киритилган: 0/{{ total }}" placeholder, conditional district-cross-cut button (`data-open-districts="{{ $indicator }}"` or `wire:click` to navigate to districts route — out of scope, just the button).

CSS: zero additions. All classes already styled in `portal.css`.

## 8. Routing

The `/tasks` route already exists and renders `pages/tasks.blade.php` which mounts `<livewire:tasks-board />`. No change.

The `pages/tasks.blade.php` view currently has `@section('page-title')` and `@section('page-subtitle')` directives that are unused by the layout (the page-head was removed in commit `a966f3e`). The directives can stay — they're inert.

## 9. Tests

| Path | Purpose |
|---|---|
| `tests/Feature/Console/ImportTasksCommandTest.php` | importer correctness + idempotency |
| `tests/Feature/Http/TasksPageTest.php` | route 200, markup classes present, filter behavior via Livewire test |
| `tests/Unit/TaskScopeTest.php` | quick scope-chain checks (forRegion, forModule, forDistrict, search) |

## 10. Files touched

| File | Action |
|---|---|
| `backend/database/migrations/2026_05_08_000001_create_tasks_table.php` | new |
| `backend/database/migrations/2026_05_08_000002_create_task_districts_table.php` | new |
| `backend/app/Models/Task.php` | new |
| `backend/app/Models/District.php` | add `tasks()` relationship |
| `backend/app/Console/Commands/ImportTasks.php` | new |
| `backend/app/Livewire/TasksBoard.php` | replace stub |
| `backend/resources/views/livewire/tasks-board.blade.php` | replace stub |
| `backend/tests/Feature/Console/ImportTasksCommandTest.php` | new |
| `backend/tests/Feature/Http/TasksPageTest.php` | new |
| `backend/tests/Unit/TaskScopeTest.php` | new |

No CSS or JS changes. No data file changes.

## 11. Risks and mitigations

- **Risk:** docx parsing brittle to formatting shifts (cell merging, extra paragraphs, alternate Roman/numeric formats). *Mitigation:* importer logs unmatched tokens and skipped rows; idempotent re-run lets the user fix the docx and re-import without manual cleanup.
- **Risk:** district name matching misses non-canonical spellings. *Mitigation:* three-step match (`name_full` → `name_short` → `alt_labels`); unmatched tokens warn but don't error. Add an `alt_labels` entry to fix recurring misses.
- **Risk:** `tasks.status` field unused now but might drift if a status-tracking spec arrives later. *Mitigation:* default `'open'` and a comment in the migration note that it's reserved.
- **Risk:** prototype's `task-focus` aside has hooks like `data-open-districts` that the prototype JS handles. Livewire version stub-renders these as visible buttons but their navigation is wired to the future districts spec, not to behavior here. *Mitigation:* document as a known stub in §7.2 above.
