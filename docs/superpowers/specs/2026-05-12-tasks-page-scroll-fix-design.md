# Tasks page scroll fix

**Date:** 2026-05-12
**Status:** Approved (pending user spec review)
**Scope:** Stop the tasks page (`/tasks`) from pushing filters and statistics off-screen when 86 tasks are visible. Make the task list an internally-scrolling region.

---

## 1. Goal

On `/tasks`, the current layout renders four blocks in normal document flow:

1. `.task-filter.report-filter` — four selects (module / KPI / status / search).
2. `<details class="task-advanced-filters">` — period + district selects.
3. `.task-summary-strip.execution-overview` — four stat pills + donut + actions.
4. `.task-workspace` — `.task-groups > .task-group > .task-list` + `.task-focus` aside.

With 86 cards (`.task-card.compact`), the list grows tall and the page scrolls. Filters and statistics disappear above the viewport. Users have to scroll back up to change a filter or read the donut.

This spec turns `.task-list` into its own scrollable container so filter + summary stay in view at all times.

## 2. Non-goals

- No pagination, virtualization, or sticky positioning.
- No JavaScript.
- No Blade markup changes.
- No Livewire changes.
- No restyling of the aside `.task-focus`; it stays alongside the list as today.
- No changes to other pages or other `.task-list` instances on the dashboard or districts page.

## 3. Strategy

Apply `max-height` + `overflow-y: auto` to `.task-group .task-list` (the selector that already targets the tasks-page list at `portal.css:4759`).

`max-height: calc(100vh - 320px)`:

- 60px topbar (`.topbar` height) + 24px page padding (top + bottom) + 60px filter row + 132px summary strip + 44px slack = 320px.
- On a 1080-pixel-tall viewport the list area is ~760px — about 7-8 `.task-card.compact` rows visible before the scrollbar kicks in.
- Resizing the window resizes the list area continuously.

Additional rules:

- `overflow-y: auto` — vertical scrollbar appears only when content exceeds `max-height`. Short filtered lists keep their natural height, no scrollbar.
- `padding-right: 4px` — so the scrollbar gutter doesn't overlap the card content.
- `overscroll-behavior: contain` — when the inner list hits its top or bottom edge, the page itself doesn't start scrolling. Keeps the interaction tight.
- `scroll-behavior: smooth` — minor polish for jump-to-position interactions (anchor links, future "scroll to task" features).

Mobile fallback: at `≤768px` the existing media query at `portal.css:5553` already collapses `.task-workspace` to a single column. Inside that same media query block, reset `.task-group .task-list { max-height: none; overflow: visible; padding-right: 0; }` so mobile scrolls naturally with the page (no nested scrollbar in a narrow viewport).

## 4. Cascade considerations

The `.task-list` selector also appears at `portal.css:1188` (`.task-list, .district-list` — general grid). That rule sets grid and gap, not max-height. Our rule targets `.task-group .task-list` (specificity 0,2,0 — beats the general rule's 0,1,0) so only the tasks-page instance picks up the new behaviour. The dashboard `.task-list` in `.macro-layout-card` and the districts page list are unaffected.

## 5. Files touched

| File | Change |
|---|---|
| `backend/public/css/portal.css` | Append properties to existing `.task-group .task-list` rule at line ~4759. Append a one-line reset to existing `@media (max-width: 768px)` block at line ~5553. |

No Blade, Livewire, JS, or migration changes.

## 6. Test plan (manual)

1. `cd backend && php artisan serve` (port 8000).
2. Open `http://127.0.0.1:8000/tasks` in Chrome and Edge.
3. With all 86 tasks visible (default filter `status=open`), confirm:
   - Filter row + summary strip + advanced-filters `<details>` stay in normal flow at the top of the page.
   - Only the `.task-list` scrolls internally as the user pages through cards.
   - Scrollbar appears on `.task-list` right edge, not overlapping content.
   - When list reaches bottom or top, the page does NOT scroll (overscroll contained).
4. Apply filter `module=macro` → list shrinks to ~7 cards → no scrollbar visible (list shorter than max-height).
5. Resize viewport to 768px → confirm inner scroll resets; list flows in page normally.
6. Resize viewport up to 1440px → inner scroll area grows; ~10-12 cards visible at once.
7. Verify no regression on dashboard `/` (`.task-list` inside `.macro-layout-card` — different selector path, should be unaffected).

## 7. Risks and mitigations

- **Risk:** different topbar height across modes (e.g. mobile-collapsed brand) would mean 320px offset is wrong. *Mitigation:* the 320px constant is only used at desktop widths; mobile resets max-height entirely.
- **Risk:** a future redesign that adds rows above the list would push the visible area below the viewport bottom. *Mitigation:* fix is a one-line constant; update when redesign lands. Documented as a magic number in the rule body comment.
- **Risk:** browsers with overlay scrollbars (macOS Safari) won't show a gutter, making `padding-right: 4px` extra space. *Mitigation:* 4px is negligible visually; acceptable trade.
- **Risk:** Livewire re-render after filter change preserves scroll position inside the inner container, possibly confusing user. *Mitigation:* Livewire's default morphdom keeps the container element so its scrollTop is preserved. Acceptable; matches expectations.
