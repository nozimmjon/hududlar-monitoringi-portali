# Hududlar monitoring portali prototype

This repository contains the Andijan pilot prototype and source-processing scripts for the regional monitoring portal.

## Current prototype

Open this file in a browser:

`platform prototypes/andijon_integrated_platform_v7.html`

Current direction:

- first screen is KPI monitoring;
- KPI architecture follows 7 source-table directions;
- tasks are kept outside the first screen;
- district and execution-monitoring screens remain available for drilldown;
- source extraction/generation scripts are in `tools/`.

## Main files

- `platform prototypes/andijon_integrated_platform_v7.html` — latest prototype.
- `tools/generate_andijon_integrated_platform_v7.py` — generator for latest prototype.
- `data_source/Кафолат хатлар имзога/2. Андижон/` — Andijan source workbooks and guarantee letter.
- `session_2026-04-30_v7_design.md` — design notes/reference review.

## Regenerate latest prototype

Run from the repository root:

```powershell
python tools/generate_andijon_integrated_platform_v7.py
```

If using the bundled Codex runtime:

```powershell
C:\Users\n.ortiqov\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe tools\generate_andijon_integrated_platform_v7.py
```

## Handoff notes

- The repository currently includes prototype history and source data so colleagues can audit the reasoning.
- GitHub should be private unless the source documents are cleared for public sharing.
- The GitHub CLI token on this machine must be refreshed before pushing.

