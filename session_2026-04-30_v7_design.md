# Session log — v7 prototype design & build

**Date:** 2026-04-30
**Project:** Худудлар мониторинги портали (Regional Monitoring Portal — Andijon pilot)
**Outcome:** v7 prototype shipped — 3-page integrated HTML platform, real Andijon data wired in, v6 untouched.

---

## 1. Initial audit — "audit and understand where we stand"

**Project structure surveyed:**

```
project root/
├── data_source/
│   ├── 4. Тезисга илова (23.04).xlsx
│   └── Кафолат хатлар имзога/
│       ├── 1. Қорақалпоғистон Республикаси/  …  14. Тошкент ш/   (14 regions)
│       └── 2. Андижон/                          (only one processed)
│           ├── 0. Кафолат хати (Андижон).docx
│           ├── 1.1-1.5-жадваллар (макро).xlsx
│           ├── 2.1-2.2-жадваллар (инфляция).xlsx
│           ├── 3-жадвал (бюджет).xlsx
│           ├── 4.1-жадвал (бюджет инвестка).xlsx
│           ├── 4.2-жадвал (инвестициялар).xlsx
│           ├── 5.1-5.2-жадваллар (экспорт).xlsx
│           └── 6-жадвал (бандлик ва камбағаллик даражаси).xlsx
├── platform prototypes/
│   ├── chatpro/  (earliest webapp drafts)
│   ├── claude/   (early Claude iterations + v2 with own .git)
│   ├── final_*.html  (Apr 29 morning iterations)
│   ├── andijon_full_pilot_*.html  (v1, v2, v3)
│   ├── andijon_commitment_workflow_v4.html
│   ├── andijon_simple_monitoring_platform_v5.html
│   ├── andijon_integrated_platform_v6.html  ← latest live
│   └── andijon_full_pilot_assets/
│       ├── andijon_full_pilot_audit.md
│       └── andijon_full_pilot_data.json
└── tools/
    ├── extract_andijon_pilot.py
    ├── audit_andijon_source_links.py
    └── generate_andijon_*.py
```

**v6 status:**
- 5 pages: KPI / Топшириқлар / Туманлар / Туман профили / Ҳисобот
- 16 districts × 8 source xlsx → 149 monitoring rows, 61 task candidates
- Status distribution: green 59 / amber 4 / red 81 / grey 5
- Latest builder: `generate_andijon_integrated_platform_v6.py` (3,220 lines)
- Latest HTML: `andijon_integrated_platform_v6.html` (3,066 lines)

**Gaps identified:**
- Only Andijon processed; 13 other regions sitting unprocessed
- 30+ PNG screenshots cluttering the prototypes folder
- No git in project root (only `claude/v2/.git`)
- CLAUDE.md is generic placeholder template
- Stale Excel lock files (`~$*.xlsx`)

---

## 2. Concept-level proposals (initial brainstorm)

User asked for audit + improvement plan covering design, visualization, mechanism, concept.

**Identified weaknesses in v6:**
1. Hard-coded "Andijon" everywhere — fork-required to add regions
2. Generator monolith (3,220 lines, single file)
3. Flat JSON structure (220 KB per region; 14 regions = ~3 MB)
4. Static district profile, no fallback for empty data
5. Source citations not clickable
6. No inter-region comparison
7. No map / geographic context
8. Data quality not surfaced in UI

**Proposed direction:**
1. Multi-region URL routing (`?region=andijon`)
2. 3-level hierarchy: Republic → Region → District
3. New "Comparative" page with heatmap + ranking
4. SVG choropleth map components
5. Data quality badge system per KPI
6. Builder split into 4 modules (`extract/`, `normalize/`, `render/`, CLI)
7. Lazy JSON loading

---

## 3. Multi-skill prototype audit

Applied each Claude skill lens to v6:

| Skill | Score / verdict |
|---|---|
| **audit** (a11y, perf, theming, responsive) | 62/100 — P0: missing ARIA, color-only deltas; P1: 232px sidebar squeezes mid-screens; P2: 220KB JSON inline |
| **critique** (UX) | 6.8/10 — visual hierarchy 7, IA 6, emotion 5, cognitive load 7 |
| **arrange / layout** | Inconsistent vertical rhythm; KPI grid lines but section breaks weak |
| **typeset** | Segoe UI weak for Cyrillic; 7 font weights in use; no `tabular-nums` |
| **colorize / quieter** | Graph-paper background too school-like; amber/red deltas hard to distinguish |
| **clarify** (UX copy) | "Йиллик KPI", "Кутилмоқда", "Энг фаол ҳудудлар" — all ambiguous |
| **harden** (i18n, overflow) | Long district names overflow; no `Intl.NumberFormat`; no error states |
| **polish** | Inconsistent radii (8/14/999), avatar/icon size mismatch |
| **normalize** (design system) | 24 CSS vars but inline hardcodes; no icon library standard |
| **adapt** (responsive) | Tablet collapses awkwardly; touch targets 34px (< AAA 44px) |
| **distill** | Topbar KPI mark, chevron pattern, paper-grid background all decorative noise |
| **animate** | Zero transitions beyond `scroll-behavior: smooth` |
| **bolder** | Page-head text weak vs topbar; deltas 12px (should be 18px+) |
| **delight** | None |
| **onboard** | Zero — no tour, no empty-state coaching |
| **optimize** | 327 KB HTML, ~1.4s FCP, ~3,200 DOM nodes |
| **extract** | `kpi-tile`, `status-pill`, `command-card` not abstracted |

**Top 5 P0 fixes:**
1. ARIA labels, `aria-current`, icon+text status (not color-only)
2. JSON external + lazy page DOM
3. Long-name overflow + locale number format
4. "Туман / Туман профили / Туманлар кесими" naming overlap
5. Onboarding tour or empty-state coaching

---

## 4. v7 visual mockup (preview before implementation)

Built `andijon_v7_mockup.html` — static visual demo of the new direction:

- White shell, no graph-paper background
- Inter font (Google Fonts), `tabular-nums`
- Region picker (chevron + green dot) + breadcrumbs in topbar
- "Кафолат хати" + "Республика" + "Экспорт" + "Янги ҳисобот" buttons
- Sidebar: Кузатув (KPI / Қиёсий таҳлил **янги** / Туманлар харитаси) + Иш юритиш (Топширикдар **12** / Ҳисоботлар / Созламалар)
- KPI grid: 4×2 with sparkline, quality badge (✓ / ⚠ / ⊘), shape-coded delta (↑ / ↓ / →)
- Hex-grid SVG choropleth (Andijon districts)
- 14-region mini-strip
- District list with progress bar + status pill
- Onboarding tooltip (1/3 steps)

Two render bugs fixed during capture:
- `<button>` containing `<div>` (HTML invalid, only first KPI rendered) → switched to `<div role="button">`
- `</button>` strays in KPI section after sed → manually replaced with `</div>`

Final mockup screenshots: `v7_mockup_desktop.png`, `v7_mockup_mobile.png`.

---

## 5. Re-audit — concept, architecture, interconnection

**Core purpose reframed:**

> Платформа = "Кафолат хатидаги ваъдалар бажариляптими?" саволига жавоб
> = system that links **promise ↔ execution**

```
Guarantee letter (macro KPI)  ← the promise
       ↓
Driver KPI (industry, investment, localization)
       ↓
District contribution (16 districts)
       ↓
Task (who, by when, current status)
       ↓
Result → feeds back into macro
```

**v6's invisible link:** the 5 pages slice this chain but don't show the trail. Going from KPI to Tasks is just menu-switching.

**v7 mockup gaps (concept-level):**
- Driver chain invisible — clicking a KPI doesn't show "what tasks drive this"
- No guarantee-letter framing — letter exists but isn't referenced
- No promise vs execution comparison on tiles
- Leader/executor split missing
- Workflow status (v6 commitment_workflow_v4) lost
- District profile vertical aggregation collapsed

**Conceptual zones to rethink:**
- Audience persona — President / Hokim / analyst (default A)
- Task as first-class object vs born-from-KPI (decision: both — top-down + list)
- Republic vs Region — different layouts inside same shell
- Letter document — surface as "View letter" button

**Decision matrix presented:**
| Option | Effort | Risk | Value |
|---|---|---|---|
| A. Implement v7 as-is | 2–3 weeks | High | Medium |
| B. Keep v6 architecture, polish to v7 visual | 1 week | Low | Medium |
| C. Redesign concept | 1 month | Low–Medium | High |
| D. Pilot 1 region with new concept | 2 weeks | Low | High |

Recommendation: **C + D hybrid** — but user chose to ship today.

---

## 6. Build decisions (locked-in)

User answered question list with mostly defaults:

| Topic | Decision |
|---|---|
| Primary user | Executive (default) with work-mode toggle |
| Region scope | Andijon only; multi-region structure ready |
| Republic page | Skip — placeholder button only |
| Pages | Merge to **3**: Promise vs Execution / Districts / Guarantee letter & tasks |
| District profile | Side-drawer, not a separate page |
| Letter document | "View letter" button → opens docx |
| Promise vs fact | Yes — every KPI tile shows promise (H1 + year) + fact + delta |
| Tasks ↔ KPI | Both directions: KPI drill-down + standalone task page |
| Workflow stages | Simpler — `assigned → in progress → done` + `blocked` flag |
| Executor display | Yes (synthesized; real data lacks this field) |
| Data lifecycle | Snapshot (CERR rebuilds weekly), real backend later |
| Visual direction | v7 mockup as designed |
| Font | Inter (Google Fonts for now; local later) |
| Map | Hex grid (real geography deferred — would have taken 2–3h) |
| Export | Print CSS for PDF; Excel deferred |
| Build order | Promise vs Execution first |
| Builder | Reuse v6 JSON; clean module split deferred |
| v6 parallel | Untouched |
| **Deadline** | **Today** |

---

## 7. Implementation

**File 1: `tools/generate_andijon_integrated_platform_v7.py`** (~750 lines)

Reads `andijon_full_pilot_data.json`, hand-coded promise targets from the guarantee letter, mocked 13 other-region GDP growth values, synthesized task workflow distribution.

Key functions:
- `build_promise_kpis(data)` — produces 10 KPI tiles (5 macro + budget + FI + export + unemployment + poverty)
- `kpi_status(promise_pct, fact_pct, lower_is_better)` — green if delta ≥+0.1pp, red if ≤-0.5pp, amber otherwise
- `assign_workflow_state(idx, total)` — deterministically distributes 61 tasks across 4 stages (35/35/25/5) with rotating executor names
- `build_districts_payload(data)` — flattens nested district structure, filters string `poverty_h1` ("холи ҳудуд")

**File 2: `platform prototypes/andijon_integrated_platform_v7.html`** (98 KB)

Single-file HTML, all 3 pages embedded, page switching via JS class toggle. URL hash routing (`#districts`, `#tasks`).

**Bugs encountered during build:**
- `regional.foreign_investment` is a dict, not a list — fixed field paths
- `regional.export` values in минг $ (not млн $) — added unit conversion
- Macro indicator keys are full strings ("Қишлоқ хўжалиги маҳсулотлари", not "Қишлоқ хўжалиги") — fixed lookup with explicit promise_key parameter
- Districts page metric calculation was using `pct - 100` for investment, but `h1_pct` is already the absorption percentage, not growth — fixed metric-aware logic
- Edge headless screenshot: `$PWD` was at project root not platform prototypes — fixed URL path
- Edge headless rendering missed KPI grid initially (1 column instead of 4) — caused by `<button>` containing block-level `<div>` (HTML-invalid), switched to `<div role="button">`

---

## 8. What ships in v7

### Page 1 — Ваъда vs Ижро (Promise vs Execution)

10 macro KPIs, each tile showing:
- Label + quality badge (✓ тасдиқланди / ⚠ текширилмоқда / ⊘ янгиланмоқда)
- Fact value (large, tabular-nums)
- Promise row: "Ваъда (I ярим): X | Йил: Y" with dashed border
- Delta pill (color-coded by status)
- Sparkline (trend visualization)
- Status accent (3px left border: green / amber / red / grey)
- Click → side-drawer with related tasks for that sector

Below KPIs: hex map of 16 districts colored by industry growth + 14-region comparative strip + sources panel.

### Page 2 — Туманлар

- Metric switcher: Саноат / Бюджет / Инвестиция / Бандлик
- Hex map recolors by selected metric
- Districts table: name, metric value with progress bar, status pill, owner
- Click row → side-drawer with 6-cell district profile (industry, agriculture, budget, investment, unemployment, poverty) + localization driver

### Page 3 — Кафолат хати топшириқлари

- Gradient letter banner (Андижон вилояти Кафолат хати · 2026) with summary stats and "Хатни очиш" button
- 5 summary cards: Жами 61 / Бириктирилган 22 / Жараёнда 21 / Бажарилган 15 / Блокда 3
- Sector filter chips (Макро иқтисодиёт 29, Экспорт 9, Бюджет 7, Бандлик 10, Хорижий инвестиция 6)
- Search input
- 4-column kanban — each card with ID, sector tag, title (3-line clamp), executor avatar + name, due date
- Click card → drawer with full task detail

### Cross-cutting

- Region picker with green dot (Andijon active)
- Republic button (disabled placeholder)
- Letter button (top-right, opens docx via `file://`)
- Print CSS for PDF export
- Sidebar data quality footer
- Side-drawer (520px) with backdrop, ESC to close, smooth slide-in
- Mobile responsive: KPI 2-col, kanban stacks, drawer full-width
- ARIA labels, `aria-current="page"`, focus-visible 2.5px outline

---

## 9. Known compromises (today's deadline)

| Compromise | Why | Future work |
|---|---|---|
| Hex map, not real Andijon SVG geography | Sourcing + cleaning admin boundaries needs 2–3h | Replace `DIST_LAYOUT` with real geo paths |
| Workflow stages synthesized | Real data: all 61 tasks `grey`; no backend yet | Pull from workflow API |
| Executor names placeholder (7 rotating) | Source xlsx has no `executor` column | Add to extract pipeline once data available |
| Republic data mocked (13 regions) | Only Andijon processed | Extract remaining 13 region folders |
| Inter from Google Fonts | Online dependency | Embed local woff2 |
| No real PDF/Excel export | Used print CSS as MVP | Wire openpyxl + weasyprint |
| Builder not split into modules | Time vs scope tradeoff | `extract/ normalize/ render/` per Sprint 1 plan |

---

## 10. Files written this session

| Path | Purpose |
|---|---|
| `platform prototypes/andijon_v7_mockup.html` | Visual mockup (preview before implementation) |
| `platform prototypes/andijon_integrated_platform_v7.html` | Live v7 prototype (98 KB) |
| `tools/generate_andijon_integrated_platform_v7.py` | v7 builder |
| `platform prototypes/v7_mockup_desktop.png` · `v7_mockup_mobile.png` | Mockup screenshots |
| `platform prototypes/v7_promise_desktop.png` · `v7_promise_mobile.png` | Promise page screenshots |
| `platform prototypes/v7_districts_desktop.png` | Districts page |
| `platform prototypes/v7_tasks_desktop.png` · `v7_tasks_mobile.png` | Tasks page |
| `session_2026-04-30_v7_design.md` | This log |

**v6 untouched.**

---

## 11. To regenerate

```bash
py "tools/generate_andijon_integrated_platform_v7.py"
```

Open `platform prototypes/andijon_integrated_platform_v7.html` in any browser.

---

## 12. Next decisions (when ready)

1. Real Andijon SVG geography (replace hex)
2. Backend wiring for workflow status + executor data
3. Process remaining 13 regions through extraction pipeline
4. Local font embedding for offline use
5. Builder modularization (`extract/`, `normalize/`, `render/`)
6. Republic-level page (currently disabled button)
7. PDF/Excel export beyond print CSS
8. Onboarding tour for first-time users
