# Сув хўжалиги йўл харитаси — monitoring runbook

Phase 2 on top of the registry (`roadmap-import.md`). Each measure (чора-тадбир) of a
road map gets **indicator lines** that *we* define — label, unit (Ўлчов) and plan
(Режа), one row each; the region fills only «Амалда» (and an optional «Изоҳ») in an
xlsx we generate for a report period, month by month. Status is never chosen by anyone:
it is derived from the lines of the **latest reported period** — nothing reported →
`Бажарилмоқда`, every planned line at ≥ 100 % → `Бажарилди`, anything below plan →
`Бажарилмоқда` until the measure's deadline month (read from «Муддати») and
`Бажарилмаган` from that month on. The measure percent is the mean over its planned
lines of each line's `max(0, min(100, actual/plan × 100))`, and **a line with no row in
that period counts as 0** — so every report must carry every indicator again, always
cumulative since the start of the year.

## The monthly loop

```powershell
cd backend

# 1. Write the file for the period (default path: data/…/мониторинг/2026-09/Хоразм вилояти.xlsx)
php artisan roadmap:template --region=1733 --period=2026-09
php artisan roadmap:template --all --period=2026-09        # every loaded region, one sheet each
#   --period accepts a month (2026-09) or a quarter (2026-Q3); lower case (2026-q3) is fine.
#   --out=path.xlsx writes somewhere else; --year / --domain pick another road-map family.
#   The default file is named after regions.name_full — «Тошкент» alone is two regions.

# 2. FIRST MONTH ONLY: open the file and review the suggested indicator lines (see below).

# 3. Import the reviewed, still-empty file — this stores the line definitions
php artisan import:roadmap-progress --file="../data/Сув хўжалиги бўйича йўл хариталар/мониторинг/2026-09/Хоразм вилояти.xlsx" --dry-run
php artisan import:roadmap-progress --file="../data/Сув хўжалиги бўйича йўл хариталар/мониторинг/2026-09/Хоразм вилояти.xlsx"

# 4. Send that same file to the region (they fill only the yellow G/H cells).

# 5. Import the returned file — same command; --period defaults to the sheet title
php artisan import:roadmap-progress --file="../data/Сув хўжалиги бўйича йўл хариталар/мониторинг/2026-09/Хоразм вилояти (тўлдирилган).xlsx"

# 6. Next month: the template now carries the stored lines, no heuristics
php artisan roadmap:template --region=1733 --period=2026-10
```

Step 3 is what makes the definitions real: after it every measure has its lines and is
`Бажарилмоқда` (nothing reported yet), and step 6 emits exactly those lines — with the
stored «Амалда»/«Изоҳ» pre-filled when that period already has values, so a correction
round is just "edit and re-import". Steps 1–2 are needed once per region; from then on
the loop is 1 → 4 → 5.

**Never import an unfilled template for a *new* period** (step 1's own output, before
step 4). It registers that period with no «Амалда» anywhere, and because status and
percent are read from the latest reported period only, every card of the region falls
back to «Бажарилмоқда» with no ring until the filled file arrives — a «Бажарилди»
measure looks reopened. Step 3 is the one exception, and only because the first month
has nothing to lose. The import says so when it happens:
`{region}: N measure(s) advanced to 2026-10 with no «Амалда» values — an unfilled
template imported for a new period? …`. The cure is to import the filled file for that
same period; the empty rows are overwritten and the numbers come back.

Rebuild the derived columns without touching any file (after a deadline-rule change, a
hand-edited progress row, or just to check):

```powershell
php artisan roadmaps:recompute --dry-run          # every road map, nothing written
php artisan roadmaps:recompute --region=1733      # one region
```

### Reviewing the suggested lines (first month)

The generator guesses lines from the measure text (`24 та насос агрегатларини
таъмирлаш` → «Насос агрегатларини таъмирлаш · та · 24»); the summary column
`of which suggested` counts them. Raw guesses are noisy, so read every block once:

- **Fix** wrong labels, units and plans in D/E/F — the label is what the page shows.
- **Add** a row inside the block (leave column A **empty**; A only carries the key on
  the block's first row) when one indicator is not enough. New rows go at the **end**
  of the block if the measure already has reported history — the row position is the
  line's identity.
- **Delete** rows that are not real indicators (a canal length quoted for context, a
  decree number the regex read as a quantity).
- **A money line** only where the money is the measurable promise; measures funded by
  «Маблағ талаб этилмайди» simply have none. Money is never suggested automatically.
- Measures with no quantity anywhere in their text got the fallback line
  «Бажарилиш даражаси · % · 100» — keep it when a percent is the honest measure,
  replace it when a countable one exists.

## What healthy output looks like

`roadmap:template`:

```
+----------------+-----------+---------------------+--------------------+
| Вилоят         | Тадбирлар | Индикатор қаторлари | of which suggested |
+----------------+-----------+---------------------+--------------------+
| Хоразм вилояти | 89        | 178                 | 178                |
+----------------+-----------+---------------------+--------------------+
Written …/data/Сув хўжалиги бўйича йўл хариталар/мониторинг/2026-09/Хоразм вилояти.xlsx
```

(`of which suggested` = 178 of 178 means nothing is stored yet — this is the first
month. After step 3 it must read 0: the lines now come from the DB.)

`import:roadmap-progress` on a filled file:

```
+----------------+-------------+----------+-----------+--------+------+-------------+------+
| Вилоят         | Файлда/жами | Қаторлар | Ўчирилди  | Амалда | done | in_progress | open |
+----------------+-------------+----------+-----------+--------+------+-------------+------+
| Хоразм вилояти | 89/89       | 178      | 0         | 129    | 12   | 77          | 0    |
+----------------+-------------+----------+-----------+--------+------+-------------+------+
Period 2026-09: 89 measure(s) processed from Хоразм вилояти.xlsx.
```

`Файлда/жами` = measures found in the file / measures the road map has (89/89 = the
file is complete); `Қаторлар` = indicator rows read; `Ўчирилди` = stored lines dropped
because the file no longer has them; `Амалда` = rows carrying a value (129 of 178 — the
rest were left blank and count as 0 for this period). No warning lines at all is the
healthy case.

`roadmaps:recompute`:

```
1 road map(s) [1733], 89 measure(s) — 89 updated; done: 12, in_progress: 77, open: 0 — 12 in_progress→done.
```

The tail lists every status that moved (here 12 measures closed). Run right after an
import the line should instead read `— 0 updated; … — no status flips.`: the importer
already recomputed everything it touched, so a recompute that still changes rows means
something wrote progress behind its back.

## Warnings and what to do

All of these are warnings, not aborts — the import already happened (unless
`--dry-run`), so read them and decide.

- **`{region}: N line(s) removed — no longer in the file (their history went with them).`**
  Stored lines beyond the block's last row were deleted, together with every period
  they had ever reported. Intended when you deliberately dropped an indicator; if not,
  the file was sent back short — restore the missing rows and re-import (the history is
  gone either way, only the definition comes back). One line per region, so an `--all`
  workbook says which region lost the rows.
- **`{region}: N line(s) with reported history changed their label — a row inserted mid-block shifts the numbering; check that the history still belongs to the right indicator.`**
  Line identity is the row position, so a row inserted in the middle pushes every
  following line's reported history onto the next indicator. Either fix the file (put
  the new rows at the **end** of the block, restore the original order) and re-import,
  or accept it when the reshuffle was intended and the history is meaningless anyway.
- **`{region}: N previously reported «Амалда» value(s) cleared by this file.`**
  Cells that had a value for this period arrived empty. Almost always an **older copy
  of the file** was imported over a newer one — re-import the newer file; it restores
  the values. If the region really did retract numbers, nothing to do.
- **`{region}: N measure(s) advanced to {period} with no «Амалда» values — an unfilled template imported for a new period? Their status fell back to Бажарилмоқда until the filled file is imported.`**
  A period newer than the one those measures last reported arrived with every «Амалда»
  cell empty — normally step 1's own template imported by mistake before step 4. See the
  monthly loop above; import the filled file for that period and the numbers return.
- **`N of M measure(s) of {region} are not in the file — left untouched.`**
  Rows (or a whole sheet) were deleted from the workbook. Those measures keep their old
  lines and status — they are *not* zeroed. Restore them from a fresh
  `roadmap:template` and re-import, unless you split the reporting on purpose.
- **The docx importer** (`import:roadmap`) prints its own two:
  `N measure(s) removed — their indicator lines and progress with them.` and
  `N measure(s) with indicator lines changed their title — a measure inserted or dropped mid-section shifts the numbering; check that the monitoring rows still belong to the right measures.`
  A re-imported document that gained or lost a measure mid-section renumbers everything
  after it, and the monitoring rows follow the *number*, not the text. Always run
  `import:roadmap --region=… --dry-run` first: it previews the damage as
  `Dry run: N measure(s) would be removed (M with indicator lines); K measure(s) with indicator lines would change title.`

## File rules the import enforces

Everything below is checked before a single row is written; a structural problem aborts
naming the sheet and cell, and nothing is written.

- **Column A is the key** (`1733-5-1733206-3` = region · section · district SOATO (0 =
  region-level) · seq). It is hidden, not secret — hand-edited padding like
  `1733-05-0-2` is canonicalised and still matches. A key that the road map does not
  have aborts (`unknown key … — the road map has no such measure`); the same key twice
  in one workbook aborts naming both places.
- **Row order is line identity.** `line_no` = position of the row inside the block, so
  the file's order defines which stored line each row updates. Rows beyond the last one
  in the file are deleted (see the warning above).
- **Blank rows:** one empty row inside a block is a filler and is skipped; **two in a
  row close the block**, and only a key row can open the next one. Because of that a
  stray value typed far below the table (a «ЖАМИ» line, a note) aborts with
  `индикатор қатори калитсиз (A устуни бўш)` instead of being absorbed as an indicator.
  An indicator row with an empty D aborts with `индикатор номи бўш`.
- **Numbers (F «Режа» and G «Амалда»)** accept `7,8`, `7.8`, `1 240`, `1 240,5`
  (ordinary, non-breaking and narrow spaces all stripped, comma or dot as the decimal
  separator) and a formula's calculated value. Rejected, each naming the exact cell:
  a **percent-formatted** cell (`50%` is stored as 0,5 — «50% эмас, 50 деб ёзинг»), a
  **date-formatted** cell, a **boolean** (TRUE/FALSE), the ambiguous comma-grouped
  **«1,240»** («1240 бўлса «1 240», 1,24 бўлса «1,24» деб ёзинг»), anything else
  non-numeric («… рақам эмас»), and absurd magnitudes above 1e12 («… жуда катта»).
  Percent is never read from the file — it is always recomputed as actual / plan × 100.
- **Every sheet is read** except the one titled «Йўриқнома». The period comes from
  `--period`, else from each sheet's A1 title («… Ҳисобот даври: 2026-09»); sheets
  disagreeing with each other, or a `--period` disagreeing with the file, abort.
- **`.xlsx` only.** Saving as CSV or the old `.xls` loses the hidden key column (and
  the sheet structure), and the file becomes unimportable — instruction 5 in the
  «Йўриқнома» sheet says so to the region.
- A block that has stored lines but arrives with **zero rows** aborts
  (`has N stored lines but no rows in the file — truncated file?`) rather than wiping
  the definitions.

## Reading the page (`/roadmaps`)

- **Statuses** are the tasks-board three: «Бажарилди» (green) — every planned line at
  ≥ 100 % in the latest period; «Бажарилмоқда» (violet) — nothing reported yet, no
  indicator lines at all, or behind plan but still before the deadline month;
  «Бажарилмаган» (red) — behind plan in or after the deadline month. The rail's four
  buttons filter by them (`?holat=done|in_progress|open`).
- **The ring percent** is the mean over planned lines of the capped line share, so
  14/24 + 1/1 + 3/3 shows 86 %, not the two-of-three 67 %. A measure that is not
  `Бажарилди` never displays 100 % (capped at 99), and a measure with no lines shows
  «—».
- **The deadline chip** counts months from *today* (Asia/Tashkent), not from the report
  the deadline exactly as the document writes it («2026 йил декабрь»): with a calendar icon: amber while open, red once the deadline month has passed, green once done. No countdown.
- **A 📅 period chip on a card** means this measure's last report is **older than the
  road map's latest period** — it was missing from the newest file (a blank «Амалда»
  still registers the period, so a chip means the rows themselves were absent). The
  hero pill shows the road map's latest period; matching cards show no chip.
- **A dip in the sparkline** is usually not a regression: a line left blank in a later
  period counts as 0 for that period, so a partially filled report drags the whole
  measure down. Re-report every indicator each month (instruction 9 in «Йўриқнома»).
- «Батафсил» opens the document's own detail lines, the «Изоҳ» texts the region typed,
  and — from two reported periods on — the sparkline. «яна N индикатор» / «Камроқ»
  expands a measure with more than four lines.

## Data model

`roadmap_measure_lines` (line_no / label / unit / plan_value) →
`roadmap_line_progress` (report_period, actual_value, pct_of_plan, note, reported_at);
`roadmap_measures` carries the derived `latest_period / status / pct / lines_total /
lines_done`, rebuilt only by `App\Services\Roadmaps\MeasureRecomputer`. Full design and
rationale: `docs/superpowers/specs/2026-09-08-roadmaps-monitoring-design.md` (with the
as-built deviations at the top), implementation plan:
`docs/superpowers/plans/2026-09-08-roadmaps-monitoring.md`.

## Known limitations (as of 2026-09-08)

- **Deadline parsing** covers Cyrillic month names with Uzbek/Russian endings,
  «N-чорак» / «IV чорак» and «NNNN йил»; a year earlier than the road map's year is
  treated as a citation («2019-йил … ПФ-5742-сон қарор») and ignored. Latin-script
  months («aprel-oktyabr») and Cyrillic «І» in Roman numerals are not recognised — both
  fall back to December, which only ever *delays* a «Бажарилмаган» verdict.
- **The line suggester is a heuristic** (numbers followed by a known unit in the
  measure text). It needs the one review pass per region described above and is never
  used again for a measure once lines are stored.
- **No period switcher on the page** — the cards always show the latest period; history
  lives in the DB and surfaces only as the sparkline.
- `pct_of_plan` is `numeric(10,4)`, so a percent is clamped to ±999999,9999 (a plan of
  0,01 against an actual of 20 000 would otherwise abort the import).
- Only Хоразм has a road map loaded, and its indicator lines are still the suggested
  ones — nothing has been reviewed or reported yet.
