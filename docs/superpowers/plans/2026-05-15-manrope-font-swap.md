# Manrope Font Swap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Inter + Inter Tight with Manrope as the single sans-serif family across the portal, gated by a new `--font-sans` CSS custom property.

**Architecture:** Add `--font-sans: "Manrope", "Segoe UI", Arial, sans-serif;` to the existing `:root` block at the top of `public/css/portal.css`. Replace every `font-family: "Inter…"` declaration in that file with `font-family: var(--font-sans);`. Swap the Google Fonts `<link>` URL in the two blade files that load it.

**Tech Stack:** Vanilla CSS, Blade templates, Google Fonts (Manrope, weights 400–800, `cyrillic` + `cyrillic-ext` subsets).

---

## File Structure

- `backend/public/css/portal.css` — add `--font-sans` to `:root` at line 1-12 (existing design-token block), then `replace_all` every `font-family: "Inter…"` line with `font-family: var(--font-sans);` (20 occurrences total per `grep -c`).
- `backend/resources/views/layouts/app.blade.php` — replace line 10 (Google Fonts `<link>`).
- `backend/resources/views/welcome.blade.php` — replace line 18 (Google Fonts `<link>`).

No new files. No test files (CSS-only change; the existing pest suite still runs as a regression guard).

---

## Task 1: Add `--font-sans` variable + replace font-family declarations

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Confirm the current `:root` block boundaries**

Run: `sed -n '1,15p' backend/public/css/portal.css`
Expected: the file begins with a BOM + `    :root {` at line 1, design tokens (`--ink`, `--muted`, `--line`, `--blue`, etc.) inside, closing `}` before the next rule.

- [ ] **Step 2: Add `--font-sans` to the `:root` block**

Use the `Edit` tool on `backend/public/css/portal.css`. Find the existing token declaration (anywhere inside the top `:root` block — e.g. `--blue: #1769e0;`) and append the new token on the next line:

`old_string`:
```
      --blue: #1769e0;
```

`new_string`:
```
      --blue: #1769e0;
      --font-sans: "Manrope", "Segoe UI", Arial, sans-serif;
```

If the indentation of the surrounding tokens differs from 6 spaces, match it exactly (the file uses 6-space indent inside `:root`).

- [ ] **Step 3: Verify the variable is now in the file**

Run: `grep -n "\-\-font-sans" backend/public/css/portal.css`
Expected: one match near the top of the file, inside `:root`.

- [ ] **Step 4: Replace every Inter-Tight-first font-family declaration**

Use the `Edit` tool with `replace_all: true`:

`old_string`:
```
font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
```

`new_string`:
```
font-family: var(--font-sans);
```

- [ ] **Step 5: Replace every Inter-first font-family declaration**

Use the `Edit` tool with `replace_all: true`:

`old_string`:
```
font-family: "Inter", "Inter Tight", "Segoe UI", Arial, sans-serif;
```

`new_string`:
```
font-family: var(--font-sans);
```

- [ ] **Step 6: Verify no Inter references remain**

Run: `grep -n "Inter" backend/public/css/portal.css`
Expected: ZERO matches.

Run: `grep -c "var(--font-sans)" backend/public/css/portal.css`
Expected: at least `20` (every replaced declaration now references the variable).

- [ ] **Step 7: Commit**

```powershell
git add backend/public/css/portal.css
git commit -m "style(font): introduce --font-sans var + swap Inter -> Manrope"
```

---

## Task 2: Update Google Fonts `<link>` in both blade templates

**Files:**
- Modify: `backend/resources/views/layouts/app.blade.php` (line 10)
- Modify: `backend/resources/views/welcome.blade.php` (line 18)

- [ ] **Step 1: Replace the link in `app.blade.php`**

Use the `Edit` tool on `backend/resources/views/layouts/app.blade.php`:

`old_string`:
```
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
```

`new_string`:
```
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
```

- [ ] **Step 2: Replace the link in `welcome.blade.php`**

Use the `Edit` tool on `backend/resources/views/welcome.blade.php`. The Edit `old_string` should be the entire pre-existing `<link>` element on line 18; if its leading-whitespace differs from `app.blade.php`, copy whitespace verbatim from a Read of that line first. Replace with the same Manrope URL (preserve leading whitespace).

If a Read shows the welcome link is identical (`  <link rel="stylesheet" href="…Inter…">` with 2-space indent), the same `old_string` / `new_string` pair from Step 1 works. If not, adjust whitespace to match what Read returned.

- [ ] **Step 3: Verify both files now reference Manrope**

Run: `grep -n "googleapis.*family=" backend/resources/views/layouts/app.blade.php backend/resources/views/welcome.blade.php`
Expected: exactly 2 matches, both URLs starting with `https://fonts.googleapis.com/css2?family=Manrope:`.

Run: `grep -n "Inter" backend/resources/views/layouts/app.blade.php backend/resources/views/welcome.blade.php`
Expected: ZERO matches.

- [ ] **Step 4: Smoke test the dashboard**

Run: `curl -s -o nul -w "%{http_code}" "http://127.0.0.1:8765/dashboard?kpi=grp"`
Expected: `200`.

Run: `curl -s "http://127.0.0.1:8765/dashboard?kpi=grp" | grep -o 'family=Manrope' | head -1`
Expected: `family=Manrope` (the link is present in the served HTML).

- [ ] **Step 5: Commit**

```powershell
git add backend/resources/views/layouts/app.blade.php backend/resources/views/welcome.blade.php
git commit -m "style(font): load Manrope from Google Fonts (cyrillic+ext subsets)"
```

---

## Task 3: Verify Pest suite + smoke 5 portal pages

**Files:** none — verification only.

- [ ] **Step 1: Run the unit suite (regression guard)**

Run: `php -d memory_limit=2048M vendor/bin/pest tests/Unit`
Expected: `Tests: 78 passed` (unchanged from baseline). If any test fails, the change is unrelated — investigate.

- [ ] **Step 2: Smoke 5 key portal pages**

```powershell
curl -s -o nul -w "dashboard %{http_code}`n" "http://127.0.0.1:8765/dashboard?kpi=grp"
curl -s -o nul -w "districts %{http_code}`n" "http://127.0.0.1:8765/districts"
curl -s -o nul -w "tasks %{http_code}`n"     "http://127.0.0.1:8765/tasks"
curl -s -o nul -w "profile %{http_code}`n"   "http://127.0.0.1:8765/districts/profile"
curl -s -o nul -w "execution %{http_code}`n" "http://127.0.0.1:8765/execution"
```

Expected: each line ends in `200`. (If a route returns 404, drop it from the list and note in the report — different from a 500.)

- [ ] **Step 3: Confirm CSS variable resolves in served portal.css**

Run: `curl -s "http://127.0.0.1:8765/css/portal.css" | grep -m1 "\-\-font-sans"`
Expected: one line matching the new variable definition.

Run: `curl -s "http://127.0.0.1:8765/css/portal.css" | grep -c "var(--font-sans)"`
Expected: at least `20`.

- [ ] **Step 4: Git status — must be clean**

Run: `git status`
Expected: `nothing to commit, working tree clean` (Tasks 1 + 2 already committed everything).

---

## Notes for the implementer

- Manrope tops at weight 800. Existing CSS uses 850/900/950 in some rules (e.g. `.macro-hero-strip__value`). Browsers round excess weights down to 800 → slightly less heavy hero numbers. Acceptable for this spec; do NOT edit those rules.
- The `preconnect` lines pointing to `fonts.googleapis.com` and `fonts.gstatic.com` stay unchanged — same CDN.
- Manual visual confirmation step (after committing): open `/dashboard` in a browser, open DevTools → Computed → `font-family` on `<body>` → must report `Manrope`. Inspect a Cyrillic-Uzbek letter Қ / Ў / Ҳ / Ғ in DevTools "Rendered Fonts" panel — must list `Manrope` (not a system fallback). If the Uzbek-specific letter falls back to Segoe UI, the `cyrillic-ext` subset failed to load — re-check the link URL.
- Full pest feature suite has a pre-existing memory blow-up unrelated to this change. Only run unit suite + individual feature tests.
