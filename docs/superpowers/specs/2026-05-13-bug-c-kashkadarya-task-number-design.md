# Bug C: kashkadarya duplicate task_number

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Operator action. No code change. Patch source docx `data/tasks/00_Чора_тадбир_Қашқадарё.docx` to remove duplicate `task_number=15` in section I.1.2.

---

## 1. Goal

`php artisan import:tasks kashkadarya` crashes with `SQLSTATE 23505 unique violation` on `uq_tasks_region_number (1710, 15)`. Source docx table 1 has two adjacent rows numbered `15`:

| Row | task_number | First-words of title |
|----:|---:|---|
| 19  | 15 | Аукцион савдоларига чиқарилган 13 та фойдали қазилма конларини… |
| 20  | 15 | 11,8 трлн сўмлик давлат харидларини амалга ошириб… |
| 21  | 16 | 2026 йилда ўзлаштириш режалаштирилган 1 688 млн долларлик… |
| 23  | 17 | Энергия самарадорлигини ошириш орқали… |

Operator typo when authoring docx. Rows 21+ continue with the correct sequence (16, 17, 18, …) so renumbering only the duplicate row is enough.

After fix: `import:tasks kashkadarya` succeeds.

## 2. Non-goals

- No code change in `App\Console\Commands\ImportTasks`, no schema change to `tasks`, no relaxation of `uq_tasks_region_number`.
- No automated docx editor — single typo, one-off manual fix.
- No fix for any other region's docx — only kashkadarya is affected.

## 3. Operator action

Open `data/tasks/00_Чора_тадбир_Қашқадарё.docx` in Microsoft Word. Locate the table on the first page, find the two adjacent rows where column A is `15`. Change the second occurrence (row 20 by table index — the row beginning with "11,8 трлн сўмлик давлат харидларини…") from `15` to `15.1`.

`15.1` is chosen because:
- it sorts numerically right after 15 in the UI (preserves operator intent),
- the `tasks.task_number` column stores integer cast of the string, but the docx column is text. Since the parser inserts the cast value, the actual DB value becomes whatever PHP gives for `(int) "15.1"` → `15`. That collides again.

**Therefore the correct fix is:** change the second occurrence from `15` to an unused integer in the kashkadarya task sequence. The current sequence ends at task 36 (verified by running `php artisan import:tasks kashkadarya` after fix and inspecting `select max(task_number) from tasks where region_code=1710`). Use `37` for the second occurrence and adjust the operator's docx accordingly.

Alternative if operator prefers in-place renumbering: shift rows 20, 21, 23, 24, … each up by 1 (row 20 → 16, row 21 → 17, …). Slower but preserves a contiguous sequence. Either approach unblocks the importer.

## 4. Verification

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php artisan import:tasks kashkadarya
```

Expected: `Imported 91 tasks for region '1710'.` (or 90 — operator-edit-dependent). No `SQLSTATE 23505`.

Then full smoke:

```bash
php -d memory_limit=2G artisan import:all-regions 2026
```

Expected: kashkadarya row shows `xlsx=promoted, tasks=ok`.

## 5. Risks

- **Risk:** Operator edits the wrong row. Mitigation: docx row 19 begins with "Аукцион" — keep that one as `15`; edit only the row beginning with "11,8 трлн". Step-by-step in §3.
- **Risk:** Other regions have similar typos uncovered later. Mitigation: each region's `import:tasks` will surface its own unique-violation independently. Handle as discovered.
- **Risk:** Operator's chosen renumber value collides with another existing task. Mitigation: query `max(task_number)` post-import as shown in §4 and pick `max + 1`.
