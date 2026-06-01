# Hide region tasks with no plan (Режа кўрсаткичи empty / «x»)

- **Date:** 2026-06-01
- **Status:** Approved (design)
- **Area:** `backend/` — Tasks board (`/tasks`) and District profile (`/profile`)

## Problem

In the partner monitoring workbook, a region's **Режа кўрсаткичи** (plan indicator)
cell can be empty or carry a not-applicable marker (`x` / `х` / `-` / `—` / `–`).
A task with no plan for the active region is not a real plan-vs-fact promise and
should not be listed for that region.

## Decisions (clarified with stakeholder)

1. **Hide the whole task** when its plan is empty/`x` — not just blank the plan
   line. The task is removed from the list, the «X та» count, and the donut/totals.
2. **Apply everywhere**, via one shared query scope: the `/tasks` board (list,
   count, donut, and the module/indicator/district filter dropdowns) **and** the
   `/profile` district panels (`Туман топшириқлари` + per-KPI) and their counts.
3. **Filter at retrieval, not at import** (see "Approaches" below).

## Key finding: the signal already exists

`TaskWorkbookParser::num()` + `isSentinel()`
(`backend/app/Services/Tasks/TaskWorkbookParser.php:205-220`) already convert an
empty cell and every sentinel (`x`, `х`, `-`, `—`, `–`) to `null`. So in the
database a "no plan" task is exactly:

```
tasks.headline_plan IS NULL
```

`headline_plan` is the denormalized snapshot of the headline metric (`line_no 0`)
plan for the latest reported period — and it is the exact value already rendered
in the card's «Режа:» line. No parsing or import work is needed; the rule is a
pure read-time filter on an existing column.

## Approaches considered

### A — Filter at retrieval (one shared scope) — CHOSEN
A single Task scope, `whereNotNull('headline_plan')`, applied at every
region-task query site.

- Non-destructive: the "x = N/A for this region" fact survives in the data.
- Reversible: changing/relaxing the rule edits one scope; no re-import.
- Single definition; totals and donut recompute automatically from the filtered base.
- Import pipeline contract (verify-all-14-columns, idempotent per-period upsert)
  is untouched.
- Cost: the scope must be applied at ~9 query sites — mitigated by one named scope
  and tests that assert exclusion at each surface.
- Conceptual fit: hiding a plan-less task is a presentation decision, not a data
  decision, so it belongs at read time.

### B — Filter at import (skip rows) — REJECTED
Do not write the Task / progress rows when plan is empty/`x`.

- Destructive: loses the N/A-vs-missing distinction; any rule change needs a full
  re-import of every period.
- Breaks idempotent per-period upserts: a task with no plan this month but a plan
  next month would have to be "resurrected"; executor-only rows get tangled.
- Muddies the import command's contract. Worse on every axis that matters here.

### C — Hybrid: derived `has_plan` boolean column written at import — REJECTED
- Redundant: `headline_plan IS NULL` already is that flag. An extra column +
  migration + keep-in-sync logic buys nothing. YAGNI.

## Design (Approach A)

### 1. New Task scope

`backend/app/Models/Task.php`:

```php
public function scopeHasPlan(Builder $q): Builder
{
    return $q->whereNotNull('headline_plan');
}
```

Definition of "no plan" := `headline_plan IS NULL`. Name (`hasPlan`) is a
convenience; final naming can be adjusted during implementation
(`hasPlan` / `withPlan` / `planned`).

### 2. Apply the scope at all region-task query sites

No other logic changes at these sites — just chain `->hasPlan()` onto the base query.

`backend/app/Livewire/TasksBoard.php`
- `tasks()`
- `totals()`
- `moduleOptions()`  (so the filter offers only modules with visible tasks)
- `indicatorOptions()`  (same, for indicators)
- `districtOptions()`  (same, for districts)

`backend/app/Livewire/RegionProfile.php`
- `tasksForKpi()`
- `taskCounts()`
- `tasksForDistrict()`
- `districtTaskCounts()`

### 3. Explicitly unchanged

- **Import command / parser** — no change.
- **Migrations** — no new column, no migration.
- **Blade views** — no change. Hidden tasks simply do not appear; the donut and
  percentages recompute from the now-filtered totals base.

## Edge cases & accepted simplifications

- **Headline-only signal.** The rule keys on `headline_plan` (the `line_no 0`
  metric). A task whose headline has no plan but whose sub-metrics do carry a plan
  is still hidden. This matches the literal "the Режа кўрсаткичи column is empty"
  intent and the value shown on the card. Accepted.
- **No-plan but has-actual.** Stakeholder chose "hide whole task" over "hide only
  if no actual too." Therefore a task is hidden even if it reported an Амалда
  (actual) value with no plan. Accepted as intended.
- **Qualitative «чора-тадбир» measures** that never carry a numeric plan will also
  be hidden under this rule. Accepted as intended; revisit only if a future
  requirement says plan-less measures must remain visible.

## Testing

Pest 3 on PostgreSQL (`RefreshDatabase`, explicit `$this->seed()`), per repo conventions.

- **Unit** (`tests/Unit/TaskScopeTest.php`): `hasPlan()` excludes rows with
  `headline_plan IS NULL`, includes rows with a non-null plan.
- **Feature** (`tests/Feature/Tasks/TasksBoardProgressTest.php`): a null-plan task
  is absent from the list, excluded from `totals` (count + donut %), and not
  offered in the module / indicator / district filter options.
- **Feature** (`tests/Feature/Tasks/ProfileDistrictTasksTest.php`): a null-plan
  task is excluded from `tasksForDistrict` / `tasksForKpi` and both count methods.
- **Full suite** expected green. Memory notes 2 pre-existing failures on
  `v7-design-polish`; confirm this change introduces no *new* failures.

## Out of scope

- Changing the import pipeline or workbook parsing.
- Any UI/blade redesign of the task cards.
- Distinguishing "N/A (x)" from "not yet reported (empty)" in the UI — both are
  treated identically (hidden) per the decision.
