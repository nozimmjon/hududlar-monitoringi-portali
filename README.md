# Hududlar monitoring portali prototype

This repository contains the Andijan pilot prototype and source-processing scripts for the regional monitoring portal.

## Current prototype

Open this file in a browser:

`platform prototypes/andijon_integrated_platform_v8.html`

Current direction:

- first screen is executive KPI monitoring;
- KPI architecture follows 7 source-table directions;
- tasks are kept outside the first screen;
- district screen uses KPI -> district visual drilldown -> profile/table;
- execution-monitoring screen keeps report approval logic and KPI impact clear;
- source extraction/generation scripts are in `tools/`.

## Main files

- `platform prototypes/andijon_integrated_platform_v8.html` — latest prototype.
- `tools/generate_andijon_integrated_platform_v8.py` — generator for latest prototype.
- `data_source/Кафолат хатлар имзога/2. Андижон/` — local-only Andijan source workbooks and guarantee letter; ignored by Git.
- `session_2026-04-30_v7_design.md` — design notes/reference review.

## Regenerate latest prototype

Run from the repository root:

```powershell
python tools/generate_andijon_integrated_platform_v8.py
```

If using the bundled Codex runtime:

```powershell
C:\Users\n.ortiqov\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe tools\generate_andijon_integrated_platform_v8.py
```

## Handoff notes

- The GitHub repository excludes `data_source/`; raw DOCX/XLSX source files stay local.
- The prototype itself contains derived Andijan pilot data and should remain private unless cleared for sharing.
- Screenshots for quick review are stored next to the latest prototype.
