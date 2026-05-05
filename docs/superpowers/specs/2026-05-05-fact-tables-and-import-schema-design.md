# Fact Tables and Import Schema — Design

**Date:** 2026-05-05
**Status:** Approved through Sections 1-4 in brainstorming. Awaiting user spec review before implementation plan.
**Branch:** `v7-design-polish`
**Builds on:** existing reference migrations under `backend/database/migrations/2026_05_04_*` (regions, districts, reporting_years, modules, region_workbooks, region_workbook_sheets) and seeders under `backend/database/seeders/`.

---

## 1. Context

The portal's prototype is a single-file `index.html` (~9800 lines) embedding the data for one region (Andijan) inline. Production needs:

- Real Postgres backend driving Laravel 12 + Filament 3 admin.
- All 13 regions imported (Navoi data import deferred — upstream macro 1.2 sheet contains Surkhandarya data, awaiting fix).
- Statkom (State Statistics Committee) actuals will arrive in the future, alongside hokimyat self-reported values, so the system can highlight discrepancies.
- Tasks (Топшириқлар) data will be supplied later by the user — task tables out of scope for this design.

The current `index.html` defines ~17 distinct indicators across three JavaScript arrays (`kpiDefs`, `macroComponentDefs`, `districtOnlyDefs`). The schema must absorb new indicators without per-KPI migrations.

## 2. Constraints

- **Cyrillic Uzbek throughout** — labels are stored in Cyrillic; Latin transliteration is optional metadata.
- **Tashkent city has no agriculture (`Қишлоқ хўжалиги`)** — the source workbook for that region does not contain a 1.4 sheet. UI must not show the indicator for that region.
- **Navoi region data is blocked** until upstream fixes the cross-region contamination in macro 1.2 (Surkhandarya districts appear under Navoi). Region row exists; fact rows do not.
- **Construction is region-scope only** — source workbooks have no per-district construction breakdown.
- **Sentinel `"холи ҳудуд"`** appears in poverty columns for some districts — represented as `is_sentinel = true` with `sentinel_label = 'холи ҳудуд'` and numeric values NULL.
- **Workbook variance** — sheet names diverge across regions (14 different `budget_invest` suffixes, 4 patterns for `foreign_invest`, etc.). Importer resolves sheets by content-pattern, not by literal name.
- **Two actuals per indicator/period** — `actual_hokimyat` (workbook self-report) and `actual_statkom` (official, future). Stored side-by-side.

## 3. Architecture (5 layers)

```
1. REFERENCE          (already migrated, stable)
   regions • districts • reporting_years • modules
   region_workbooks • region_workbook_sheets

2. INDICATOR CATALOG  (admin-managed in Filament)
   indicators • region_indicator_availability

3. FACT DATA          (the cube + tabular extras)
   indicator_facts • food_balance • warehouses

4. NARRATIVE COMMITMENTS  (parsed from .docx)
   guarantee_letters • promise_targets

5. IMPORT INFRASTRUCTURE  (workflow / audit)
   import_runs • import_files • data_quality_issues
   import_staging_indicator_facts
   import_staging_food_balance
   import_staging_warehouses
```

Layer 1 is built. Layers 2-5 are this design's deliverable.

## 4. Naming conventions

Domain entities (regions, districts, indicators, modules, years) reference each other by **natural key** (`region_code`, `district_code`, `indicator_code`, `module_code`, `year`). Lifecycle records (import_runs, import_files, guarantee_letters, data_quality_issues) use **surrogate `id`** primary keys.

This makes raw SQL queries readable (`WHERE region_code='andijan' AND year=2026`) without joins and lets backups/exports stay self-describing.

### Required adjustment to existing schema

The `districts` table currently has `code` unique within `region_id`. To support composite FK `(region_code, district_code)` from fact tables, add a denormalized `region_code` column to `districts` and a composite UNIQUE index `(region_code, code)`. One follow-up migration handles this.

## 5. Indicator catalog (`indicators`)

```sql
indicators (
  id                  smallserial PRIMARY KEY,
  code                varchar(48) UNIQUE,            -- 'grp' | 'industry' | 'small_business_share' | ...
  label_full          varchar(192) NOT NULL,
  label_short         varchar(96) NOT NULL,
  sector              varchar(96),                   -- 'Макро иқтисодиёт' | 'ЯҲМ таркиби' | ...
  module_code         varchar(32) NULL,              -- FK modules(code), source workbook
  scope               varchar(16) NOT NULL,          -- 'region' | 'district' | 'both'
  default_unit        varchar(48) NOT NULL,
  lower_is_better     boolean NOT NULL DEFAULT false,
  supported_periods   jsonb NOT NULL DEFAULT '["q1","h1","m9","year"]',
  has_growth_pct      boolean NOT NULL DEFAULT false,
  has_pct_of_plan     boolean NOT NULL DEFAULT false,
  has_sentinel        boolean NOT NULL DEFAULT false,
  count_extra_label   varchar(64) NULL,              -- 'Экспортчи корхоналар сони'
  count_extra_2_label varchar(64) NULL,              -- 'Иш ўринлари'
  icon                varchar(32),
  sort_order          smallint,
  notes               text,
  created_at, updated_at
);
```

### Seed list (20 indicators)

| code                   | scope    | unit            | growth | pct_plan | sentinel | count_extra              | count_extra_2 |
| ---------------------- | -------- | --------------- | ------ | -------- | -------- | ------------------------ | ------------- |
| `grp`                  | region   | млрд сўм        | ✓      |          |          |                          |               |
| `industry`             | both     | млрд сўм        | ✓      |          |          |                          |               |
| `agriculture`          | both¹    | млрд сўм        | ✓      |          |          |                          |               |
| `construction`         | region   | млрд сўм        | ✓      |          |          |                          |               |
| `services`             | both     | млрд сўм        | ✓      |          |          |                          |               |
| `inflation`            | region   | %               |        |          |          |                          |               |
| `budget`               | both     | млрд сўм        |        | ✓        |          |                          |               |
| `budget_investment`    | both     | млн сўм         |        | ✓        |          | objects (count)          | commissioning_count |
| `investment`           | both     | млн доллар      |        | ✓        |          | projects                 | jobs          |
| `export`               | both     | минг доллар     | ✓      |          |          | exporters                |               |
| `unemployment`         | both     | %               |        |          |          |                          |               |
| `poverty`              | both     | %               |        |          | ✓        |                          |               |
| `small_business_share` | region   | %               |        |          |          |                          |               |
| `localization`         | district | млн сўм         |        |          |          | projects                 |               |
| `energy_electricity`   | district | млн кВт·ч       |        |          |          |                          |               |
| `energy_gas`           | district | млн м³          |        |          |          |                          |               |
| `jobs`                 | district | минг нафар      |        |          |          |                          |               |
| `legalization`         | district | минг нафар      |        |          |          |                          |               |
| `mfy_clear`            | district | count           |        |          |          |                          |               |
| `microprojects`        | district | count           |        |          |          |                          |               |

¹ `agriculture` is `not_applicable` for `tashkent_city` (city has no agricultural sector).

## 6. Indicator facts cube (`indicator_facts`)

```sql
indicator_facts (
  id                     bigserial PRIMARY KEY,
  region_code            varchar(32) NOT NULL,
  district_code          varchar(64) NULL,            -- NULL = region rollup
  year                   smallint NOT NULL,
  indicator_code         varchar(48) NOT NULL,
  period                 varchar(8) NOT NULL,         -- 'q1' | 'h1' | 'm9' | 'year'

  plan_value             numeric(20,6) NULL,          -- forecast / target (hokimyat)
  expected_value         numeric(20,6) NULL,          -- mid-year update (kutilish)
  actual_hokimyat        numeric(20,6) NULL,          -- workbook 'амалда' column
  actual_statkom         numeric(20,6) NULL,          -- official Statkom actual (future)
  growth_pct             numeric(10,4) NULL,          -- y/y growth (e.g. 108.6)
  pct_of_plan            numeric(10,4) NULL,          -- execution %
  count_extra            integer NULL,
  count_extra_2          integer NULL,

  is_sentinel            boolean NOT NULL DEFAULT false,
  sentinel_label         varchar(64) NULL,            -- 'холи ҳудуд'

  unit                   varchar(48) NOT NULL,        -- snapshot of indicator unit at import time
  source_label           varchar(255) NOT NULL,       -- '1.1-1.5-жадваллар … · 1.1. ЯҲМ · row 6'
  hokimyat_reported_at   timestamp NULL,
  statkom_published_at   timestamp NULL,
  created_at, updated_at,

  CONSTRAINT uq_indicator_facts UNIQUE (region_code, district_code, year, indicator_code, period),
  FOREIGN KEY (region_code) REFERENCES regions(code),
  FOREIGN KEY (region_code, district_code) REFERENCES districts(region_code, code),
  FOREIGN KEY (year) REFERENCES reporting_years(year),
  FOREIGN KEY (indicator_code) REFERENCES indicators(code)
);

CREATE INDEX idx_facts_region_year_indicator ON indicator_facts (region_code, year, indicator_code);
CREATE INDEX idx_facts_region_district_year ON indicator_facts (region_code, district_code, year);
CREATE INDEX idx_facts_year_indicator_period ON indicator_facts (year, indicator_code, period);
```

### Semantics

- `district_code IS NULL` denotes the **region rollup** row (the workbook's "Андижон вилояти" total). Trust workbook totals, do not recompute.
- `plan_value` is the source-of-truth forecast at year start. Should match the corresponding numeric promise from `promise_targets`; divergence is a `data_quality_issue`.
- `actual_hokimyat` and `actual_statkom` are stored separately so the dashboard can render both side-by-side.
- `pct_of_plan` and `growth_pct` are stored, not derived, so the importer copies the pre-computed workbook value (avoids floating-point drift).
- Sentinel handling: numeric columns NULL, `is_sentinel = true`, `sentinel_label` text rendered as a badge.
- `unit` is denormalized from `indicators.default_unit` at import time so historical rows preserve their original unit if the indicator's default ever changes.

## 7. Region indicator availability (`region_indicator_availability`)

```sql
region_indicator_availability (
  id                  bigserial PRIMARY KEY,
  region_code         varchar(32) NOT NULL,
  indicator_code      varchar(48) NOT NULL,
  status              varchar(16) NOT NULL,    -- 'available' | 'not_applicable' | 'blocked' | 'pending'
  note                text NULL,
  blocked_until       date NULL,
  updated_by_user_id  bigint NULL,
  created_at, updated_at,

  UNIQUE (region_code, indicator_code),
  FOREIGN KEY (region_code) REFERENCES regions(code),
  FOREIGN KEY (indicator_code) REFERENCES indicators(code)
);
```

Seed: 14 regions × ~20 indicators ≈ 280 rows, default `status='available'`. Exceptions seeded:

- `tashkent_city × agriculture` → `not_applicable`, note: "Тошкент шаҳри учун қишлоқ хўжалиги кесими йўқ"
- `navoiy × {grp, industry, agriculture, construction, services}` → `blocked`, blocked_until set when known. Note references the upstream macro 1.2 cross-region contamination.
- All other (region × indicator) pairs default to `available`. Adjustments are made when surfaced by the importer.

## 8. Tabular extras

### `food_balance` (one row per region/year/product)

```sql
food_balance (
  id                  bigserial PRIMARY KEY,
  region_code         varchar(32) NOT NULL,
  year                smallint NOT NULL,
  product             varchar(96) NOT NULL,
  product_sort_order  smallint,
  resource_total      numeric(20,6) NULL,
  year_start_stock    numeric(20,6) NULL,
  production          numeric(20,6) NULL,
  import_volume       numeric(20,6) NULL,             -- 'import' is reserved
  use_total           numeric(20,6) NULL,
  use_household       numeric(20,6) NULL,
  use_processing      numeric(20,6) NULL,
  use_other           numeric(20,6) NULL,
  per_capita_norm     numeric(20,6) NULL,
  per_capita_balance  numeric(20,6) NULL,
  local_supply_ratio  numeric(20,6) NULL,
  year_end_stock      numeric(20,6) NULL,
  source_label        varchar(255),
  created_at, updated_at,

  UNIQUE (region_code, year, product)
);
```

### `warehouses` (one row per district/year, with region rollup using NULL district)

```sql
warehouses (
  id                              bigserial PRIMARY KEY,
  region_code                     varchar(32) NOT NULL,
  district_code                   varchar(64) NULL,
  year                            smallint NOT NULL,
  reserve_warehouses              integer NULL,
  reserve_capacity_t              integer NULL,
  cold_storage_count              integer NULL,
  cold_storage_capacity_t         integer NULL,
  new_small_cold_count            integer NULL,       -- 100 тоннагача (план)
  new_small_cold_capacity_t       integer NULL,
  new_small_cold_mfys             integer NULL,
  new_large_cold_count            integer NULL,       -- 100 тоннадан юқори (план)
  new_large_cold_capacity_t       integer NULL,
  source_label                    varchar(255),
  created_at, updated_at,

  UNIQUE (region_code, district_code, year)
);
```

## 9. Narrative commitments

### `guarantee_letters` (one row per region/year)

```sql
guarantee_letters (
  id                  bigserial PRIMARY KEY,
  region_code         varchar(32) NOT NULL,
  year                smallint NOT NULL,
  source_path         varchar(512),
  sha256              char(64),
  paragraph_count     integer,
  raw_text            text,
  signed_at           date NULL,
  status              varchar(16),                    -- 'imported' | 'pending' | 'archived'
  imported_at         timestamp NULL,
  created_at, updated_at,

  UNIQUE (region_code, year)
);
```

### `promise_targets` (numeric + narrative commitments)

```sql
promise_targets (
  id                       bigserial PRIMARY KEY,
  guarantee_letter_id      bigint NOT NULL REFERENCES guarantee_letters(id) ON DELETE CASCADE,
  region_code              varchar(32) NOT NULL,
  year                     smallint NOT NULL,
  kind                     varchar(16) NOT NULL,          -- 'numeric' | 'narrative'
  title                    varchar(255),
  body                     text,
  sector                   varchar(96),
  indicator_code           varchar(48) NULL,              -- present for numeric promises
  period                   varchar(8) NULL,
  target_value             numeric(20,6) NULL,
  target_text              varchar(128),                  -- '5,8 бар.' | '1 335 млн долларлик'
  direction                varchar(16),                   -- 'higher' | 'lower' | 'unspecified'
  target_districts         jsonb NULL,                    -- ['andijan_city', 'shahrikhan_district']
  source_paragraph_index   integer,
  created_at, updated_at
);
```

When `kind='numeric'` and `indicator_code` is set, the dashboard correlates this promise to the matching `indicator_facts.plan_value`. Mismatches raise a `data_quality_issue` of kind `promise_plan_mismatch`.

## 10. Import infrastructure

### `import_runs`

```sql
import_runs (
  id                       bigserial PRIMARY KEY,
  region_code              varchar(32) NOT NULL,
  year                     smallint NOT NULL,
  triggered_by_user_id     bigint NULL,
  trigger_kind             varchar(16),                  -- 'cli' | 'filament' | 'scheduled'
  status                   varchar(16) NOT NULL,         -- 'parsing' | 'awaiting_review' | 'promoting'
                                                         -- | 'promoted' | 'rejected' | 'failed'
  started_at               timestamp NOT NULL,
  parsed_at                timestamp NULL,
  promoted_at              timestamp NULL,
  rejected_at              timestamp NULL,
  failed_at                timestamp NULL,
  files_processed          integer NOT NULL DEFAULT 0,
  rows_staged              integer NOT NULL DEFAULT 0,
  rows_promoted            integer NOT NULL DEFAULT 0,
  issues_open_count        integer NOT NULL DEFAULT 0,
  issues_blocker_count     integer NOT NULL DEFAULT 0,
  notes                    text,
  created_at, updated_at
);

CREATE INDEX idx_runs_region_year_status ON import_runs (region_code, year, status);
```

### `import_files`

```sql
import_files (
  id                  bigserial PRIMARY KEY,
  import_run_id       bigint NOT NULL REFERENCES import_runs(id) ON DELETE CASCADE,
  module_code         varchar(32),
  file_name           varchar(255),
  file_path           varchar(512),
  sha256              char(64),
  size_bytes          bigint,
  sheet_count         smallint,
  parsed_ok           boolean NOT NULL DEFAULT false,
  error_text          text,
  parsed_at           timestamp NULL,
  created_at, updated_at
);

CREATE INDEX idx_import_files_run_module ON import_files (import_run_id, module_code);
```

### `data_quality_issues`

```sql
data_quality_issues (
  id                       bigserial PRIMARY KEY,
  import_run_id            bigint NULL REFERENCES import_runs(id) ON DELETE SET NULL,
  region_code              varchar(32) NOT NULL,
  district_code            varchar(64) NULL,
  indicator_code           varchar(48) NULL,
  year                     smallint NULL,
  period                   varchar(8) NULL,
  issue_kind               varchar(48) NOT NULL,         -- 'sentinel' | 'unknown_district' |
                                                         --  'cross_region_data' | 'sum_mismatch' |
                                                         --  'missing_sheet' | 'missing_row' |
                                                         --  'negative_value' | 'unit_mismatch' |
                                                         --  'header_not_found' | 'typo' |
                                                         --  'promise_plan_mismatch'
  severity                 varchar(16) NOT NULL,         -- 'low' | 'medium' | 'high' | 'blocker'
  detail                   text NOT NULL,
  detected_value           text NULL,
  expected_value           text NULL,
  source_label             varchar(255),
  detected_at              timestamp NOT NULL,
  resolved_at              timestamp NULL,
  resolved_by_user_id      bigint NULL,
  resolution_kind          varchar(32) NULL,             -- 'data_fixed_at_source' | 'mapped_to_canonical' |
                                                         --  'accepted_as_sentinel' | 'wontfix'
  resolution_note          text,
  created_at, updated_at
);

CREATE INDEX idx_dqi_region_year_severity ON data_quality_issues (region_code, year, severity, resolved_at);
CREATE INDEX idx_dqi_kind_severity ON data_quality_issues (issue_kind, severity);
CREATE INDEX idx_dqi_run ON data_quality_issues (import_run_id);
```

### Staging tables

`import_staging_indicator_facts` mirrors `indicator_facts` 1:1, plus:

```sql
import_run_id            bigint NOT NULL REFERENCES import_runs(id) ON DELETE CASCADE,
staging_status           varchar(16) NOT NULL,          -- 'pending' | 'validated' | 'rejected' | 'promoted'
validation_errors        jsonb NULL,                    -- [{issue_kind, detail, source_label}]
```

No unique key on staging — multiple runs can hold competing versions of the same logical row.

`import_staging_food_balance` and `import_staging_warehouses` follow the same pattern.

## 11. Data flow

```
1. Operator: php artisan import:region andijan 2026
       │
       ▼
2. INSERT import_runs (status='parsing', started_at=now())
       │
       ▼
3. For each module workbook in data/{region_folder}/:
       hash file → INSERT import_files
       resolve sheet by content-pattern (using region_workbook_sheets cache)
       detect header row dynamically (look for 'ҳажми (млрд.сўм)' or known signatures)
       parse data rows
       resolve district name against districts.alt_labels jsonb
       INSERT into import_staging_indicator_facts / food_balance / warehouses
       INSERT into data_quality_issues for sentinels, unknown districts, sum mismatches,
              cross-region data (Navoi case), header-not-found, etc.
       │
       ▼
4. Update import_runs:
       rows_staged, issues_open_count, issues_blocker_count
       status = 'awaiting_review' if no blockers, else 'failed'
       parsed_at = now()
       │
       ▼
5. Filament review page (/admin/import-runs/{id}/review):
       - Side-by-side staging vs production diff
       - List of open data_quality_issues for this run
       - Admin resolves each issue (mark resolution_kind, add note)
       - "Promote" button (enabled only when issues_blocker_count == 0):
              UPSERT staging rows → indicator_facts (matching unique key)
              UPDATE staging rows: staging_status='promoted'
              UPDATE import_runs: status='promoted', promoted_at, rows_promoted
       - "Reject" button:
              UPDATE staging rows: staging_status='rejected'
              UPDATE import_runs: status='rejected', rejected_at
       │
       ▼
6. Read path (public dashboard / region drilldown):
       SELECT from indicator_facts / food_balance / warehouses only.
       Staging is invisible to end users.
```

### Why staging rather than direct write

- The Navoi corruption case: importer detects cross-region district names, raises a `blocker` issue, run sits in `awaiting_review` indefinitely until source is fixed. **Production data is untouched.**
- Lets the operator re-run an import N times against a corrected workbook without polluting production.
- Diff view in Filament builds trust: admin sees exactly what will change.

## 12. Read-path semantics

- Public dashboard joins `indicator_facts` to `region_indicator_availability` and **hides** indicators where `status ∈ {not_applicable, blocked, pending}` (renders an empty-state badge with the localized status reason).
- For an `available` indicator, the dashboard prefers `actual_statkom` if present, falls back to `actual_hokimyat`, and shows `plan_value` for forecasted comparison.
- When both `actual_statkom` and `actual_hokimyat` are present and differ by > X% (configurable threshold, ~1% default), the dashboard renders a discrepancy badge.

## 13. Required adjustment to existing schema (one follow-up migration)

Add denormalized `region_code` column to `districts` and a composite UNIQUE index, so fact tables can FK on `(region_code, district_code) → districts(region_code, code)`:

```sql
ALTER TABLE districts ADD COLUMN region_code varchar(32);
UPDATE districts d SET region_code = r.code FROM regions r WHERE d.region_id = r.id;
ALTER TABLE districts ALTER COLUMN region_code SET NOT NULL;
ALTER TABLE districts ADD CONSTRAINT uq_districts_region_code UNIQUE (region_code, code);
```

Existing seeders update trivially.

## 14. Testing approach

- **Andijan parity test**: extract `indicator_facts` rows for Andijan after a full import, JSON-encode them in the same shape as the inlined `DATA` blob in `index.html`, and assert equality against the original. Numeric values are compared within an absolute tolerance of `1e-6` (decimal `numeric` storage avoids float drift, but the original blob has occasional rounding). Goal: full reproduction of Andijan's prototype data before onboarding the other 12 regions.
- **Schema integrity**: every region listed in the seed JSON has at least one row in `region_indicator_availability` for every indicator.
- **Importer idempotency**: running the same import twice produces the same number of `indicator_facts` rows (no duplicates, upsert works).
- **Sentinel handling**: rows with `is_sentinel=true` have all numeric columns NULL.
- **FK integrity**: no orphan rows after rollback of an import_run.

## 15. Out of scope (deferred)

- **Tasks (`tasks`, `task_districts`, `task_status_history`)** — user will provide tasks data later. Schema design at that time.
- **Authentication / RBAC** — Spatie Permission setup, region-scoped users — separate iteration.
- **Filament resource layout** — covered in the implementation plan, not this spec.
- **Real-time discrepancy thresholds and notifications** — Statkom integration details when that data starts flowing.
- **Multi-year archival strategy** — current design assumes Postgres holds all years; partitioning if/when needed.

## 16. Migration plan summary

Order of new migrations (additive to the existing 6 reference migrations):

```
2026_05_05_000001_add_region_code_to_districts.php           -- adjustment to existing schema
2026_05_05_000002_create_indicators_table.php
2026_05_05_000003_create_region_indicator_availability_table.php
2026_05_05_000004_create_indicator_facts_table.php
2026_05_05_000005_create_food_balance_table.php
2026_05_05_000006_create_warehouses_table.php
2026_05_05_000007_create_guarantee_letters_table.php
2026_05_05_000008_create_promise_targets_table.php
2026_05_05_000009_create_import_runs_table.php
2026_05_05_000010_create_import_files_table.php
2026_05_05_000011_create_data_quality_issues_table.php
2026_05_05_000012_create_import_staging_indicator_facts_table.php
2026_05_05_000013_create_import_staging_food_balance_table.php
2026_05_05_000014_create_import_staging_warehouses_table.php
```

Plus seeders:

```
IndicatorSeeder                      -- 20 indicators with metadata
RegionIndicatorAvailabilitySeeder    -- 280 rows, default 'available', exceptions noted
```

The implementation plan (next phase, via writing-plans skill) decomposes each migration + seeder into discrete, testable steps.

