# Remove Ҳисобот references from districts/profile/tasks

**Date:** 2026-05-14
**Status:** Approved (pending user spec review)
**Scope:** Strip every `Ҳисобот` / `ҳисобот` mention from districts-page, profile partials (filter, hero, bottom), and tasks-board. The reports system isn't built yet; placeholder copy + buttons confuse operators. Drops one test that asserts a removed string.

---

## 1. Goal

The portal currently surfaces a "Ҳисобот киритиш" action and an empty "Ҳисоботлар" panel in several places, despite no reports table/model/UI workflow existing. Users see disabled buttons and `ҳисобот йўқ` chips that promise functionality that does not ship. Remove all of it. UI stays consistent with what backend actually supports today.

After this work, `grep -r "ҳисобот\|Ҳисобот" backend/resources/views/livewire/` returns nothing.

## 2. Non-goals

- No backend changes. No model, no migration, no schema.
- No CSS file edits (existing classes stay — they may be reused later).
- No tasks-board logic change (status filter just drops one disabled button).
- No new tests for empty/removal — existing tests cover the surrounding behavior.

## 3. Changes

### 3.1 `resources/views/livewire/districts-page.blade.php`

Three deletions:

| Line area | Current | Action |
|---|---|---|
| ~255 (aside) | `<span class="chip grey">ҳисобот йўқ</span>` | delete the line |
| ~310 (table thead) | `<th>Ҳисобот / таъсир</th>` | delete the line |
| ~332 (table tbody) | `<td><span class="chip grey">ҳисобот йўқ</span><small>амалдаги натижа киритилмаган</small></td>` | delete the line |

### 3.2 `resources/views/livewire/profile/filter.blade.php`

One deletion (line 23):

```blade
<button class="mini-button" type="button" disabled title="Тез орада">Ҳисобот киритиш</button>
```

Delete the entire button.

### 3.3 `resources/views/livewire/profile/hero.blade.php`

Four edits:

| Line | Current | Action |
|---|---|---|
| ~21 (hero `<p>`) | `<p>Танланган KPI бўйича туман ҳолати: режа, амалдаги натижа, ҳисобот таъсири ва очиқ топшириқлар.</p>` | replace with `<p>Танланган KPI бўйича туман ҳолати: режа, амалдаги натижа ва очиқ топшириқлар.</p>` |
| ~27 (action row) | `<span class="chip grey">ҳисобот йўқ</span>` | delete the line |
| ~65 (side-stat) | `<div class="profile-side-stat"><span>Ҳисобот таъсири</span><strong>ҳисобот йўқ</strong></div>` | delete the line |
| ~67 (action) | `<button class="mini-button primary" type="button" disabled title="Тез орада">Ҳисобот киритиш</button>` | delete the line |

### 3.4 `resources/views/livewire/profile/bottom.blade.php`

Drop the entire Reports `<article>` panel (lines 8-22 area). The bottom becomes a single tasks panel. Wrapper change: replace `<div class="profile-bottom-grid">…</div>` with just the tasks `<article>` (no wrapper, full width).

New `bottom.blade.php` should contain only the tasks `<article class="panel">…</article>` plus the existing `@php` block at the top.

### 3.5 `resources/views/livewire/tasks-board.blade.php`

Three edits:

| Lines | Action |
|---|---|
| 80-83 (4th status pill) | Delete the entire `<button class="exec-status-pill blue" type="button" disabled>…Ҳисобот киритилган…</button>` block |
| 129 | Change `<h3>KPI → топшириқ → ҳисобот</h3>` to `<h3>KPI → топшириқ</h3>` |
| 140-143 (3rd side-stack row) | Delete the entire `<div class="task-side-row">…Ҳисобот киритилган…</div>` block |

### 3.6 `tests/Feature/Livewire/RegionProfileTest.php`

Delete the test:

```php
test('reports panel always shows empty state', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'kpi' => 'industry'])
        ->assertSee('Ҳисобот йўқ');
});
```

Other 7 tests stay unchanged.

## 4. Files

| File | Action |
|---|---|
| `backend/resources/views/livewire/districts-page.blade.php` | delete 3 lines |
| `backend/resources/views/livewire/profile/filter.blade.php` | delete 1 line |
| `backend/resources/views/livewire/profile/hero.blade.php` | edit 1, delete 3 |
| `backend/resources/views/livewire/profile/bottom.blade.php` | drop reports panel + wrapper |
| `backend/resources/views/livewire/tasks-board.blade.php` | delete 2 blocks, edit 1 line |
| `backend/tests/Feature/Livewire/RegionProfileTest.php` | delete 1 test |

No service/model/migration.

## 5. Tests

- Drop the one test (3.6).
- Existing tests still pass (no logic change).
- Manual smoke:
  ```
  grep -rn "ҳисобот\|Ҳисобот" backend/resources/views/livewire/
  ```
  Expected: no matches.
- Pest full suite still 255 passed.

## 6. Operator smoke

After implementation:

1. Restart dev server.
2. Visit `/profile?districtCode=1703401&kpi=industry`. Confirm:
   - No reports panel at bottom (tasks panel takes full width or stays centered).
   - Hero action-row has 3 chips (not 4 — no `ҳисобот йўқ`).
   - Hero side-stack has 4 stat rows (not 5 — no `Ҳисобот таъсири`).
   - Filter row has 2 action buttons (not 3 — no `Ҳисобот киритиш`).
   - Quick-status side panel has 2 action buttons (not 3 — no `Ҳисобот киритиш`).
3. Visit `/districts?kpi=industry`. Confirm:
   - Table thead has no `Ҳисобот / таъсир` column.
   - Each row has no `ҳисобот йўқ` chip cell.
   - Aside has no `ҳисобот йўқ` chip.
4. Visit `/tasks`. Confirm:
   - Status filter shows 3 pills: Жами / Бажарилди / Бажарилмади.
   - Hero h3 reads `KPI → топшириқ` (no `→ ҳисобот`).
   - Side-stack has 2 rows (Танланган йўналиш + Танланган KPI).

## 7. Risks

- **Risk:** Some CSS-based layout depends on the removed elements. Mitigation: CSS uses flex/grid auto-flow; removal is graceful. Visual smoke catches issues.
- **Risk:** Future reports system needs re-introduction. Mitigation: spec captures the exact lines removed; reinstatement is a near-mechanical patch.
- **Risk:** Translation drift — operator pastes back `ҳисобот` elsewhere. Mitigation: documented grep check.
- **Risk:** Disabled buttons may have been load-bearing for visual rhythm. Mitigation: action rows shrink gracefully; if any line looks off, re-balance via blade edits without re-introducing reports copy.
