# Road-map admin panel — design

**Date:** 2026-09-30 · **Status:** approved in brainstorming, awaiting user review of this document
**Scope:** an authenticated panel to enter, correct and monitor the «Амалда» values of the
water road maps (`/roadmaps`), plus uploading the filled xlsx template through the browser.

## Background

`/roadmaps` shows every region's water-management road map with monitoring: indicator
lines per measure (label · unit · plan), one «Амалда» value per line per report period,
and a status derived from them (`MeasureRecomputer`). Today the only way to get values in
is the terminal: `roadmap:template` → the region fills the xlsx → `import:roadmap-progress`.
All 13 regions are loaded (1 308 measures, 2 335 lines); only Андижон has values (2026-09).

The user asked for an admin panel "to monitor and insert actual values for roadmap
tasks", "very high quality and very easy to insert". Facts established while
brainstorming (2026-09-30):

- **No authentication exists.** The `users` table is empty, there is no login route, and
  the portal is public through ngrok. A writing page needs a login.
- **npm is not needed.** Livewire 3 ships Alpine; `portal.css` is hand-written. Vite and
  Tailwind in `package.json` stay unused.
- Values are **cumulative from the start of the year** and a measure's status is computed
  from **one period's lines** (a line without a row for that period counts as 0 %), so
  every line of a measure must be reported for the period together.
- Chosen in the visual companion (`.superpowers/brainstorm/51913-1790773276/content/entry-layout.html`):
  layout **A** — a spreadsheet-like entry grid per region and month with a correction
  panel per measure — over B (cards with inline inputs) and C (master–detail).

## Decisions (with alternatives considered)

| Topic | Decision | Rejected |
| --- | --- | --- |
| Access | One admin login (session auth, existing `users` table, account created by an Artisan command) | Per-region accounts; secret URL without login |
| What the panel edits | Actuals and notes per line per month, **plus** corrections: line label, unit, plan, add/remove lines | Actuals only; full measure editing (text, deadline, responsibles) |
| Opening a new month | Fields **pre-filled from the latest earlier month**, visibly marked; nothing written until saved | Empty fields; pre-fill and auto-save on open |
| Layout | A — entry grid grouped by section/district, correction panel under the measure | B cards with inputs; C list + editor |
| Stack | Livewire + Alpine inside the portal shell, `ad-` CSS block, no build step | Plain Blade forms; Filament |
| xlsx upload | Yes, in the panel: dry-run report first, then apply — same code as `import:roadmap-progress` | CLI only |
| Save granularity | A whole measure at a time (all its lines get the period), touched measures only; «confirm the rest» writes carried values for the untouched ones | Write every line on the page on each save; write only the edited lines |
| History | Every change logged with user, time, old and new value | No history |

## Access and navigation

- **Login page** `GET /login` (guests only): minimal layout without the sidebar
  (`layouts/auth.blade.php`), centred card in portal style — CERR logo, «Бошқарув маркази»,
  «Электрон почта», «Парол», «Кириш». `POST /login` authenticates with Laravel's session
  guard (`Auth::attempt`, `remember` on), regenerates the session and redirects to the
  intended URL or `/admin/roadmaps`. Wrong credentials → «Почта ёки парол нотўғри» on the
  same page. Throttled: 5 attempts per minute per email + IP (`RateLimiter::for('login')`),
  the 6th shows «Жуда кўп уриниш — {n} сониядан кейин қайта уриниб кўринг».
- `POST /logout` (auth): invalidates the session, redirects to `/`.
- **Account management:** `php artisan admin:create {email} {--name=Администратор}
  {--password=}` creates the user, or resets the password of an existing one; without
  `--password` it prompts with hidden input and asks twice. No registration, no reset by
  email.
- **Admin routes** (`auth` middleware): `GET /admin/roadmaps` (overview) and
  `GET /admin/roadmaps/{code}` (entry grid; `{code}` = SOATO region code; unknown code or
  a region without a road map → 404 page in the shell «Йўл харитаси йўқ»). Livewire keeps
  the `auth` middleware on its follow-up requests (persistent middleware), and every
  admin component also aborts 403 in `mount()` when there is no user — a logged-out tab
  cannot save.
- **Sidebar** (`layouts/app.blade.php`): when `auth()->check()`, one more `.nav-btn`
  «Админ» (gear icon, active on `admin.*` routes) under «Топшириқлар», and a small
  «Чиқиш» form button at the bottom of the sidebar above the region switcher. Logged-out
  visitors see the sidebar exactly as today. Public pages stay public and read-only.
- **Region choice in the panel** is the URL (`/admin/roadmaps/{code}`) plus a select in
  the grid header; it never calls `CurrentRegion::set()`, so the public pages keep their
  own region.
- **Default period** = the current month in `Asia/Tashkent`, as the deadline chip
  already judges "today".

## Schema

Existing tables are enough for values. Two additions:

- `users` — used as is (`name`, `email`, `password`, `remember_token`).
- **New `roadmap_change_log`** (migration `2026_09_30_000001`):

  | column | type | notes |
  | --- | --- | --- |
  | id | bigint | |
  | user_id | FK users, nullable, nullOnDelete | null for terminal imports (not logged today; the column is there so the upload can name the user) |
  | roadmap_id | FK roadmaps, cascadeOnDelete | region scope for the overview |
  | roadmap_measure_id | FK roadmap_measures, nullable, cascadeOnDelete | null for an `import` entry |
  | roadmap_measure_line_id | FK roadmap_measure_lines, nullable, **nullOnDelete** | the entry outlives a removed line |
  | report_period | string(16), nullable, as in `roadmap_line_progress` | for `actual`, `note`, `confirm`, `import` |
  | action | string(16) | `actual` · `note` · `label` · `unit` · `plan` · `line_added` · `line_removed` · `confirm` · `import` |
  | old_value | text, nullable | number as written by the decimal cast, text as stored; `line_removed` stores «label · unit · plan · n ой» |
  | new_value | text, nullable | `import` stores the file name and the counts line |
  | created_at | timestamp | no `updated_at` |

  Indexes: `(roadmap_measure_id, created_at)`, `(roadmap_id, created_at)`.
  Model `RoadmapChangeLog` with `user()`, `measure()`, `line()`.

## Services

### `App\Services\Roadmaps\ProgressSheet` — read side of the grid

`static build(Roadmap $roadmap, string $period): array` — the region-month sheet, one
entry per measure in document order (section, district, seq), each with its lines:

```
measure: id, section_no, section_title, district (name, head), seq_no, title, details,
         deadline_text, responsible_text, status, pct, deadline_reached (bool, for the
         live status), state: 'saved' | 'carry' | 'empty'
line:    id, line_no, label, unit, plan,
         actual, note                — the value shown
         state: 'saved' | 'carry' | 'empty'
         source_period               — the month a carried value comes from (null otherwise)
         saved_actual, saved_note    — this period's stored row, if any (for old-value logging)
```

Resolution per line, given the period **P**:

1. A progress row for **P** exists → `saved`; `actual`/`note` are its values (a null
   actual shows as an empty saved cell, not as a carry — the region reported nothing
   for it).
2. Otherwise the progress row with the greatest `RoadmapPeriod::monthIndex()` below P's
   that has a non-null `actual_value` → `carry`; `actual`/`note` from that row,
   `source_period` = its period.
3. Otherwise `empty` (no value anywhere).

Measure state: `saved` when any of its lines has a row for P (saves are whole-measure),
else `carry` when any line carries, else `empty`. Quarter periods (`2026-Q3`) take part
in the "below P" search like months (a quarter counts as its last month), but the panel
itself only offers months.

Also `static currentPeriod(): string` (Asia/Tashkent) and
`static periodOptions(Roadmap $r): list<string>` — `{year}-01` … current month (at least
`{year}-01`), newest first.

### `App\Services\Roadmaps\ProgressEntry` — write side

All writes run in one transaction, then recompute the touched measures through
`MeasureLineSync::sync()` (which calls `MeasureRecomputer`) — the same path the import
uses, so the page can never disagree with the importer.

- `save(Roadmap $roadmap, string $period, array $edits, User $user): array{measures:int, changes:int}`
  `$edits` = `[lineId => ['actual' => string|null, 'note' => string|null]]` for the
  **edited** lines only (raw strings from the inputs, `null` = cleared). For every
  measure that owns at least one edited line, build the **complete** line list:
  label/unit/plan from the DB; `actual`/`note` = the edit when present, else the
  resolved sheet value (saved or carried), else null. Then
  `MeasureLineSync::sync($measure, $lines, $period, $year)`. Log one `actual` and/or
  `note` entry per edited line whose stored value for P actually changes
  (old = the stored P value or null, never the carried value); the carried lines of a
  touched measure are written without an entry of their own — the edited lines' entries
  date the measure's save. Untouched measures are not written. A line id outside the
  road map → `InvalidArgumentException`, nothing written.
- `confirmRest(Roadmap $roadmap, string $period, User $user): int` — every measure whose
  state is not `saved` gets its resolved values written for P the same way (lines without
  any value stay null) and one `confirm` log entry per measure. Returns the count.
- `correct(RoadmapMeasure $measure, array $lines, User $user): void`
  `$lines` = the measure's complete, ordered line list as
  `[{id: int|null, label, unit, plan}]`; a line missing from the list is removed.
  Validation (all-or-nothing, messages name the line): label required and ≤ 4 000
  characters (`RoadmapMeasureLine::LABEL_MAX`), unit ≤ 48 (the column), plan empty or a non-negative
  number ≤ 1e12. Apply: update changed fields (log `label` / `unit` / `plan` with old and
  new), create new lines (`line_added`, new = «label · unit · plan»), delete missing ones
  (`line_removed`, old = «label · unit · plan · {n} ой», the progress rows go with them
  by FK cascade), then renumber `line_no` 1..n in the given order. A **plan change**
  rewrites `pct_of_plan` for every progress row of that line
  (`MeasureRecomputer::pctOfPlan`). Finally `MeasureRecomputer::recompute()` for the
  measure.
- `history(RoadmapMeasure $measure, int $limit = 30): Collection` — latest log entries
  with user names, for the history drawer.

### Number parsing — `App\Support\Roadmaps\EntryValue`

`static parse(?string $raw): ?float` — trims; empty → null; accepts `12,5`, `12.5`,
`1 240`, `1 240,5` (ordinary, NBSP, narrow and thin spaces removed, comma → dot);
anything else, a negative number or a value above 1e12 → `InvalidArgumentException`
with the message the cell shows («рақам эмас», «манфий бўлмасин», «жуда катта»).
`static format(?float $v): string` — the value as the inputs display it: up to 6
decimals, trailing zeros dropped, comma as the decimal separator, no thousands separator.

### `App\Services\Roadmaps\ProgressImport` — the importer as a service

The body of `import:roadmap-progress` moves into
`ProgressImport::run(string $file, ?string $period, int $year, string $domain, bool $write, ?User $user): ImportReport`.
The command becomes a thin printer (options → `run()` → the same table and warnings as
today, same exit codes), so its existing tests keep passing. `ImportReport` is a small
value object: `period`, `file`, `blocks` (count), `rows` (the per-region summary rows
the command prints), `notes` (the per-region counters the warnings are built from),
`warnings` (the rendered warning sentences), `error` (the abort message, or null);
`write = false` rolls the transaction back exactly as `--dry-run` does. With `write =
true` and a user, one `import` log entry per region (`new_value` = «{file} · {measures}
та чора-тадбир · {reported} та амалда»).

## Pages

Both pages render inside the portal shell (`layouts/app.blade.php`, sidebar untouched
apart from the two auth-only links).

### Overview — `/admin/roadmaps` (`App\Livewire\Admin\RoadmapOverview`)

- Header: title «Йўл хариталари · мониторинг», month select (`#[Url(as: 'davr')]`,
  default current month), a summary strip: «13 та вилоят · {saved} сақланган · {carry}
  ўтган ойдан · {empty} бўш» counting measures across regions for the month.
- One card per region (`regions.sort_order`, 14 cards, Тошкент шаҳри as «йўл харитаси
  йўқ», muted, not clickable): region name, «{n} та чора-тадбир · {m} та индикатор», a
  three-segment bar (saved green / carry amber / empty grey) with the counts under it, the
  status split as three small pills (Бажарилди · Бажарилмоқда · Бажарилмаган), the last
  change («{user} · {d.m.Y H:i}» from the log, or «ҳали ўзгариш йўқ»), and an
  «Excel’дан юклаш» button. Clicking the card body opens
  `/admin/roadmaps/{code}?davr={period}`.
- The counts come from one query per page over `roadmap_line_progress` grouped by
  measure (no N+1: measures with a row for P → saved; else measures with any earlier
  row with a value → carry; else empty).

### Entry grid — `/admin/roadmaps/{code}` (`App\Livewire\Admin\RoadmapEntry`)

**Header** (sticky under the topbar): region select (the 13 regions with a road map;
changing it navigates), month select (`#[Url(as: 'davr')]`), search box («Чора-тадбир,
индикатор…», matches measure title, details and line labels), filter pills with counts:
«Барчаси · Тўлдирилмаган · Ўзгартирилган · Ўтган ойдан · Сақланган» (measure states:
Тўлдирилмаган = any line still `empty`; Ўзгартирилган = has unsaved edits), and
«Excel’дан юклаш». The region is always rendered whole (≤ 270 lines), and search and
the pills filter client-side (Alpine hides rows), so typing never waits for the server
and the edit buffer survives filtering.

**Body:** section header rows («V. Туманларда амалга ошириладиган лойиҳалар») and
district header rows («Сариосиё тумани · туман ҳокими О.Яқубов»), sticky while their
group scrolls. Then per measure:

- **Measure row:** ring (`wr-ring`, the measure's pct), «{seq}.», title (details on a
  «Батафсил» toggle, as on `/roadmaps`), status pill (`wr-status`), deadline chip
  (`wr-tag`), a state chip («сақланган» / «ўтган ойдан» / «бўш» / «ўзгартирилган»), a
  pencil button (correction panel) and a clock button (history drawer).
- **Line rows:** label (full text, wrapped, never truncated), unit, plan (tabular), the
  «Амалда» input, the live % cell, the «Изоҳ» input (single line, 500 characters).
- A measure without lines shows one muted row «Индикаторлар белгиланмаган» and its
  pencil is the way to add some.

**Cell states** (`.ad-cell.saved | .carry | .edited | .empty | .invalid`): saved = normal;
carry = grey italic value with a tooltip «{month label}дан кўчирилган»; edited = amber
border and tint; empty = dashed border, placeholder «—»; invalid = red border and the
parser's message under the cell. The state chip on the measure row follows its lines.

**Keyboard:** Enter or ↓ in an «Амалда» cell moves to the next «Амалда» cell (across
measures and groups, skipping filtered-out rows), ↑ to the previous, Tab to «Изоҳ» and
on, Esc restores the cell to its loaded value, Ctrl+S / ⌘S triggers «Сақлаш». Typing a
value in a carried cell turns it edited; restoring the exact loaded value turns it back.

**Live feedback** (Alpine, no server round trip): the % cell recomputes on input
(`actual / plan × 100`, rounded to a whole number, «—» when either side is missing or
the plan is 0); the measure's ring, pct and status pill recompute with a JS mirror of
`MeasureRecomputer::aggregate()` (weakest link over planned lines; nothing reported →
Бажарилмоқда; all planned ≥ 100 % → Бажарилди; behind and `deadline_reached` →
Бажарилмаган, else Бажарилмоқда) using the `deadline_reached` flag the server rendered.
The server's value after the save is authoritative and replaces the client's.

**Edit buffer:** the inputs are not `wire:model`ed — an Alpine store keeps
`edits[lineId] = {actual, note}` for changed cells only, drives the counts and states,
and the save sends the buffer in one call (`$wire.save(edits)`). A page-level
`beforeunload` guard fires while the buffer is non-empty. Cell validation runs on input
with the same rules as `EntryValue::parse` (mirrored in JS) and again on the server;
a save with any invalid cell is refused with the first offending cell named and
focused.

**Save bar** (sticky bottom): «{n} та ўзгариш · {c} қатор ўтган ойдан · {e} бўш»,
«Сақлаш» (disabled at 0 edits) and «Қолганини ўзгаришсиз тасдиқлаш» (disabled when no
measure is left unsaved; asks «{k} та чора-тадбир {month} учун ўтган ой қийматлари
билан сақланади. Давом этасизми?»). After a save: a toast «{n} та чора-тадбир
сақланди», the grid re-renders from `ProgressSheet` (edited cells become saved, rings
and pills take the server values), the buffer clears, the filter and scroll position
are kept.

**Correction panel** (pencil): expands under the measure row, one row per line with
editable label (textarea, auto-height), unit, plan, «↑/↓» buttons to reorder (no drag
and drop), a bin per line, «+ Индикатор қўшиш» adds an empty row at the end,
«Сақлаш» / «Бекор». Removing a line with reported months asks
«{label}: {n} ойлик ҳисобот ҳам ўчирилади. Давом этасизми?». While the panel is open
the measure's value inputs are disabled; unsaved value edits on that measure are kept.
Saving the panel re-renders the measure from the server.

**History drawer** (clock): a side panel with the measure's last 30 changes, newest
first — «{d.m.Y H:i} · {user} · {action label} · {line label}: {old} → {new}»; times
in Asia/Tashkent.

### Upload panel (`App\Livewire\Admin\ProgressUpload`, used by both pages)

A slide-over opened by «Excel’дан юклаш»; on the overview it is per region card, on the
grid it is the current region. Steps:

1. File input (`.xlsx`, ≤ 20 MB, Livewire `WithFileUploads`; other types refused with
   «Фақат .xlsx») and month select (default = the page's month). «Текшириш».
2. Server runs `ProgressImport::run(..., write: false)`. The report renders as the
   command prints it: a table «Вилоят · Файлда/жами · Қаторлар · Ўчирилди · Амалда ·
   Бажарилди · Бажарилмоқда · Бажарилмаган», the warnings as amber lines, an abort as
   a red line («Region 1722 has no road map…», «unknown key…», «F12: … рақам эмас»).
   Nothing is written. A file whose keys belong to another region than the card's is
   accepted (the keys decide, as in the command) but the report names the region(s).
3. «Қўллаш» (only when the dry run had no error) runs the same call with `write: true`,
   shows «{n} та чора-тадбир {month} учун юкланди», closes the panel and refreshes the
   page behind it. The temporary upload is deleted after either step fails or the apply
   finishes.

## Behaviour rules worth stating twice

- A save never writes a partial measure: every line of a touched measure gets a progress
  row for the period (value or null).
- Carried values are never written by merely opening a month; only «Сақлаш» (for the
  touched measures) or «Қолганини ўзгаришсиз тасдиқлаш» (for the rest) writes them.
- Notes carry over exactly like values.
- Old values in the log are what was stored for that period before the save (null when
  the period had no row), so a carried value that is saved unchanged logs as `confirm`
  on the measure, not as an `actual` change per line.
- Corrections that change a plan rewrite the affected line's percentages for every
  reported period, then recompute the measure — the public page never shows a stale %.
- Upload dry run and apply use the same service call; the only difference is the
  transaction commit.

## CSS

New `ad-` block appended to `public/css/portal.css` (premium language of `/roadmaps`:
16 px cards, soft shadows, pills, `wr-ring` reuse): `.ad-head`, `.ad-pills`, `.ad-grid`
(a real `<table>` with sticky `<thead>` and sticky group rows), `.ad-mrow`, `.ad-lrow`,
`.ad-cell` and its states, `.ad-pct` tiers (`t-g`/`t-a`/`t-r`), `.ad-savebar`,
`.ad-panel` (correction), `.ad-hist` (drawer), `.ad-upload`, `.ad-cards` (overview),
`.ad-toast`, and `.auth-card` for the login page. Widths: label column takes the
remaining space, unit 72 px, plan 96 px, actual 112 px, % 64 px, note 220 px; below
1 100 px the note moves under the label. Inputs use tabular numerals, right aligned.

## Testing

Pest, `RefreshDatabase`, explicit seeding as in the existing road-map tests; fixtures
reuse `RoadmapXlsxBuilder` / the template writer.

- **Auth:** login page renders for guests; correct credentials log in and redirect;
  wrong ones show the message; the 6th attempt in a minute is throttled; logout ends the
  session; `/admin/*` redirects guests to `/login`; the sidebar shows «Админ» / «Чиқиш»
  only when logged in; `admin:create` creates and resets.
- **ProgressSheet (unit-style feature):** saved / carry / empty resolution incl. a
  quarter as the carried source, a saved-null cell not carrying, measure states,
  `periodOptions`.
- **ProgressEntry:** `save` writes the whole measure (carried lines included) and only
  touched measures; percentages and status recompute; log entries with correct old/new;
  bad line id refused; `confirmRest` writes the untouched measures and logs `confirm`;
  `correct` updates/adds/removes, renumbers, rewrites % on plan change, refuses a blank
  label, cascades progress on removal and logs it.
- **EntryValue:** the accepted and refused spellings.
- **RoadmapEntry component:** renders states and counts; `save` applies edits and
  re-renders; invalid cell refused with its message; region/period switching; search and
  filters; 403 when logged out mid-session.
- **RoadmapOverview:** counts per region for the month; no-road-map region card.
- **ProgressUpload:** non-xlsx refused; dry run shows the report and writes nothing;
  apply writes and logs; an aborting file shows the red line and disables «Қўллаш».
- **ProgressImport:** the command's existing tests pass unchanged through the service.
- The full suite stays green (660 today; expect ≈ 700).

## Runbook and docs

- `backend/docs/roadmap-monitoring.md`: a new section «Панель орқали киритиш» —
  creating the admin, the monthly cycle in the panel (open the month, type, save,
  confirm the rest, or upload the xlsx), what the cell states mean, corrections and
  history, and that the CLI commands still work and are what the panel calls.
- `CLAUDE.md`: routes table gets `/login`, `/admin/roadmaps`, `/admin/roadmaps/{code}`;
  the road-map pipeline entry mentions the panel; a line that the portal now has a
  single admin login.
- This spec gets an as-built note at the top when implementation deviates.

## Out of scope (later phases)

- Per-region accounts and an approval workflow.
- Entering the Топшириқлар board or sector tasks through the panel.
- Editing measure text, deadlines, responsibles, or districts; adding measures.
- Quarter periods in the panel's month select (the importer still accepts them).
- Password reset by email; two-factor.
- Export of the entered month to xlsx (the template writer already does the plan side).

## As built (2026-09-30)

Where the implementation deviates from the design above:

- Upload limit is **12 MB**, not 20 — Livewire's own temporary-upload default; raising it
  would mean publishing `config/livewire.php` for nothing.
- A CLI import logs its change-log row with `user_id` null (the panel shows «терминал»).
- The overview computes 13 × `ProgressSheet::build()` per render (≈ 300 ms / 53 queries on
  the dev DB) instead of one grouped query; the aggregate-query rewrite is deferred.
- No native `confirm()` dialogs anywhere — every destructive action is a two-click arm
  («Ўчирилсинми? Ўчириш / Йўқ», «Ҳа, тасдиқлаш / Йўқ»).
- `ProgressSheet::periodOptions(int $year, ?string $current = null)` — year-bound, not
  road-map-bound, and clamped to January … the current month.
- `ProgressEntry::correct()` refuses an empty line list («Камида битта индикатор қолсин.»).
- Entry component state is `panelFor` / `historyFor`: a public property named like an
  action shadows that action in `$wire`.
- The grid JS captures the component root in a closure and keeps a memoised DOM index
  (nested `x-data` scopes made `$root` unreliable); the index is invalidated on
  `ad-refresh`, on any Livewire commit of the component, and when the re-keyed inputs
  detach.
- Overview summary wording differs from the mock: «{n} та чора-тадбир · {m} та индикатор»
  as the subtitle, with a сақланган / ўтган ойдан / бўш strip under it.
- `ProgressEntry::save()` returns `['measures' => n, 'changes' => n]`; the component wraps
  it as `['ok' => …, 'measures' => …, 'changes' => …]` (or `['ok' => false, 'message']`)
  for the JS caller.
- Sidebar: an «Админ» nav item with a pencil icon, and a «Чиқиш» form at the sidebar foot.
- The upload panel stays open after a successful apply (the report stays readable); the
  entry page locks «Excel’дан юклаш» while unsaved edits exist, and an import discards the
  edit buffer.
- Import and sync messages are Cyrillic; PhpSpreadsheet's reader exception is wrapped as
  «Файлни ўқиб бўлмади …».
- `ProgressUpload::openFor()` reuses `RoadmapOverview::month()` for the clamp (sibling
  coupling accepted over a third copy of the rule).
- The confirm-rest arm states the carried/empty split, not just the count.
- An empty filter result shows an in-grid row «Фильтрга мос чора-тадбир йўқ» with a way
  back, rather than a bare `<thead>`.
- The login card has its own minimal layout (`layouts.auth`, portal.css, no sidebar);
  404 renders inside the portal shell, and an expired-session 419 redirects back to the
  login form — all with Cyrillic messages.
