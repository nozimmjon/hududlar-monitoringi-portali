"""Builder for Andijon regional monitoring portal v7.

Reads `andijon_full_pilot_data.json` (extracted by extract_andijon_pilot.py for v6),
renders a single-file HTML with three pages:
  1. Promise vs Execution  (macro KPIs vs guarantee-letter targets)
  2. Districts             (map + list + side-drawer profile)
  3. Guarantee letter      (kanban + task list + executor + due date)

Reuses v6 data pipeline. v6 prototype is left untouched.
"""
from __future__ import annotations

import json
import re
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DATA_JSON = ROOT / "platform prototypes" / "andijon_full_pilot_assets" / "andijon_full_pilot_data.json"
LETTER_DOCX = ROOT / "data_source" / "Кафолат хатлар имзога" / "2. Андижон" / "0. Кафолат хати (Андижон).docx"
OUT_HTML = ROOT / "platform prototypes" / "andijon_integrated_platform_v7.html"


# ----- Promise targets from the guarantee letter (extracted by hand from docx, sector × indicator) -----
# These are the *committed* values for 2026 from the regional governor's guarantee letter to the President.
# Where the letter gives an H1 + year split, we use both. Otherwise we treat the year value as the promise.
PROMISES = {
    "ЯҲМ":                 {"h1_pct": 7.2,  "year_pct": 7.8,  "year_abs": 124778.1, "unit": "трлн сўм"},
    "Саноат маҳсулотлари":  {"h1_pct": 6.8,  "year_pct": 7.5,  "year_abs": 126185.5, "unit": "трлн сўм"},
    "Қишлоқ хўжалиги":     {"h1_pct": 4.2,  "year_pct": 5.0,  "year_abs": 60309.7,  "unit": "трлн сўм"},
    "Қурилиш":             {"h1_pct": 9.0,  "year_pct": 10.5, "year_abs": 18156.3,  "unit": "трлн сўм"},
    "Бозор хизматлари":     {"h1_pct": 8.0,  "year_pct": 9.2,  "year_abs": 66361.5,  "unit": "трлн сўм"},
    "Хорижий инвестиция":   {"h1_pct": None, "year_pct": None, "year_abs": 3508.6,   "unit": "млн $"},
    "Экспорт":             {"h1_pct": None, "year_pct": None, "year_abs": 976.8,    "unit": "млн $"},
    "Бюджет тушуми":       {"h1_pct": None, "year_pct": None, "year_abs": 5888.6,   "unit": "млрд сўм"},
    "Ишсизлик":            {"h1_pct": 3.84, "year_pct": 3.60, "year_abs": None,     "unit": "%", "lower_is_better": True},
    "Камбағаллик":          {"h1_pct": 4.50, "year_pct": 2.70, "year_abs": None,     "unit": "%", "lower_is_better": True},
}


# Mock republic-level data (the other 13 regions) — H1 GDP growth %.
# These are illustrative numbers for the comparative strip; only Andijon uses real data.
REPUBLIC_MOCK = [
    ("Қорақалпоғистон Респ.",  "Қрқ", 5.2),
    ("Андижон вилояти",        "Анд", 7.2),  # real H1
    ("Бухоро вилояти",         "Бух", 6.9),
    ("Жиззах вилояти",         "Жиз", 6.1),
    ("Қашқадарё вилояти",       "Қаш", 6.7),
    ("Навоий вилояти",         "Нав", 6.4),
    ("Наманган вилояти",       "Нам", 6.5),
    ("Самарқанд вилояти",      "Сам", 7.5),
    ("Сурхондарё вилояти",      "Сур", 5.8),
    ("Сирдарё вилояти",         "Сир", 5.6),
    ("Тошкент вилояти",        "ТВ",  7.2),
    ("Фарғона вилояти",        "Фар", 7.3),
    ("Хоразм вилояти",         "Хор", 6.0),
    ("Тошкент шаҳри",          "Тш",  8.4),
]


def fmt_num(value, digits: int = 1) -> str:
    if value is None or value == "":
        return "—"
    if isinstance(value, (int, float)):
        if abs(value) >= 1000:
            s = f"{value:,.0f}".replace(",", " ")
        else:
            s = f"{value:.{digits}f}"
        return s.replace(".", ",")
    return str(value)


def kpi_status(promise_pct, fact_pct, lower_is_better=False):
    """Return (status, delta_text, delta_dir) for a KPI tile."""
    if promise_pct is None or fact_pct is None:
        return ("grey", "—", "flat")
    delta = fact_pct - promise_pct
    if lower_is_better:
        delta = -delta
    if delta >= 0.1:
        return ("green", f"+{abs(fact_pct-promise_pct):.1f} п.п.".replace(".", ","), "up" if not lower_is_better else "down")
    if delta <= -0.5:
        return ("red", f"−{abs(fact_pct-promise_pct):.1f} п.п.".replace(".", ","), "down" if not lower_is_better else "up")
    return ("amber", f"±{abs(fact_pct-promise_pct):.1f} п.п.".replace(".", ","), "flat")


def build_promise_kpis(data: dict) -> list[dict]:
    """Build the 8 main KPI tiles for the Promise vs Execution page."""
    macro = {row["indicator"]: row for row in data["regional"].get("macro", [])}
    budget = data["regional"].get("budget", {})
    fi = data["regional"].get("foreign_investment", {})
    exp = data["regional"].get("export", {})
    emp = data["regional"].get("employment", {})

    def macro_kpi(indicator_key: str, label: str, promise_key: str = None):
        row = macro.get(indicator_key, {})
        promise = PROMISES.get(promise_key or indicator_key, {})
        h1_pct = (row.get("h1_growth") or 100) - 100
        promised_h1 = promise.get("h1_pct")
        status, delta, dir_ = kpi_status(promised_h1, h1_pct)
        return {
            "label": label,
            "fact_value": h1_pct,
            "fact_text": fmt_num(h1_pct, 1) + " %",
            "promise_text": (fmt_num(promised_h1, 1) + " %") if promised_h1 is not None else "—",
            "year_promise_text": (fmt_num(promise.get("year_pct"), 1) + " %") if promise.get("year_pct") else "—",
            "status": status,
            "delta": delta,
            "delta_dir": dir_,
            "quality": "ok",
            "quality_text": "тасдиқланди",
            "source": row.get("source", ""),
            "spark": [100, 102, 104, 105, 106.5, h1_pct + 100],
        }

    kpis = [
        macro_kpi("ЯҲМ",                            "ЯҲМ ўсиш суръати",       "ЯҲМ"),
        macro_kpi("Саноат маҳсулотлари",            "Саноат ўсиши",            "Саноат маҳсулотлари"),
        macro_kpi("Қишлоқ хўжалиги маҳсулотлари",   "Қишлоқ хўжалиги",         "Қишлоқ хўжалиги"),
        macro_kpi("Қурилиш ишлари",                  "Қурилиш",                 "Қурилиш"),
        macro_kpi("Бозор хизматлари",                "Бозор хизматлари",        "Бозор хизматлари"),
    ]

    # Budget
    if budget:
        h1_pct_growth = (budget.get("h1_execution_pct") or 100) - 100
        kpis.append({
            "label": "Бюджет тушуми ижроси",
            "fact_value": h1_pct_growth,
            "fact_text": fmt_num(budget.get("h1_expected"), 1) + " " + budget.get("unit", ""),
            "promise_text": fmt_num(budget.get("h1_plan"), 1) + " " + budget.get("unit", ""),
            "year_promise_text": fmt_num(budget.get("year_plan"), 1) + " " + budget.get("unit", ""),
            "status": "green" if (budget.get("h1_execution_pct") or 0) >= 100 else "amber",
            "delta": fmt_num(h1_pct_growth, 1) + " %",
            "delta_dir": "up" if h1_pct_growth >= 0 else "down",
            "quality": "warn",
            "quality_text": "текширилмоқда",
            "source": budget.get("source", ""),
            "spark": [98, 99, 102, 105, 107, 100 + h1_pct_growth],
        })

    # Foreign investment
    if fi:
        h1_actual = fi.get("h1_expected") or fi.get("q1_actual") or 0
        h1_plan = fi.get("h1_plan") or 0
        year_target = fi.get("year_forecast") or fi.get("year_expected") or PROMISES["Хорижий инвестиция"]["year_abs"]
        pct_of_year = (h1_actual / year_target * 100) if year_target else None
        exec_pct = (h1_actual / h1_plan * 100) if h1_plan else None
        kpis.append({
            "label": "Хорижий инвестиция",
            "fact_value": h1_actual,
            "fact_text": fmt_num(h1_actual, 1) + " млн $",
            "promise_text": fmt_num(h1_plan, 1) + " млн $",
            "year_promise_text": fmt_num(year_target, 1) + " млн $",
            "status": "green" if (exec_pct or 0) >= 100 else ("amber" if (exec_pct or 0) >= 90 else "red"),
            "delta": (fmt_num(exec_pct, 1) + " % режа") if exec_pct else "—",
            "delta_dir": "up" if (exec_pct or 0) >= 100 else "flat",
            "quality": "ok",
            "quality_text": "тасдиқланди",
            "source": fi.get("source", ""),
            "spark": [40, 50, 60, 75, 90, exec_pct or 100],
        })

    # Export
    if exp:
        # JSON values are in минг $ (thousand $); convert to млн $ for display
        h1_actual_thsd = exp.get("h1_expected") or 0
        year_target_thsd = exp.get("year_expected") or PROMISES["Экспорт"]["year_abs"] * 1000
        h1_actual_mln = h1_actual_thsd / 1000
        year_target_mln = year_target_thsd / 1000
        h1_plan_mln = year_target_mln / 2
        h1_growth = exp.get("h1_growth", 100) - 100
        kpis.append({
            "label": "Экспорт ҳажми",
            "fact_value": h1_actual_mln,
            "fact_text": fmt_num(h1_actual_mln, 1) + " млн $",
            "promise_text": fmt_num(h1_plan_mln, 1) + " млн $",
            "year_promise_text": fmt_num(year_target_mln, 1) + " млн $",
            "status": "green" if h1_growth >= 15 else "amber",
            "delta": ("+" + fmt_num(h1_growth, 1) + " % ўсиш") if h1_growth >= 0 else fmt_num(h1_growth, 1) + " %",
            "delta_dir": "up" if h1_growth >= 0 else "down",
            "quality": "ok",
            "quality_text": "тасдиқланди",
            "source": exp.get("source", ""),
            "spark": [100, 105, 110, 115, 118, 100 + h1_growth],
        })

    # Employment / Poverty
    if emp:
        unemp = emp.get("unemployment_h1", 3.84)
        promised = PROMISES["Ишсизлик"]["h1_pct"]
        status, delta, dir_ = kpi_status(promised, unemp, lower_is_better=True)
        kpis.append({
            "label": "Ишсизлик",
            "fact_value": unemp,
            "fact_text": fmt_num(unemp, 2) + " %",
            "promise_text": fmt_num(promised, 2) + " %",
            "year_promise_text": fmt_num(PROMISES["Ишсизлик"]["year_pct"], 2) + " %",
            "status": status,
            "delta": delta,
            "delta_dir": dir_,
            "quality": "ok",
            "quality_text": "тасдиқланди",
            "source": emp.get("source", ""),
            "spark": [4.2, 4.1, 4.0, 3.95, 3.9, unemp],
        })

        pov = emp.get("poverty_h1", 4.5)
        promised_p = PROMISES["Камбағаллик"]["h1_pct"]
        status, delta, dir_ = kpi_status(promised_p, pov, lower_is_better=True)
        kpis.append({
            "label": "Камбағаллик",
            "fact_value": pov,
            "fact_text": fmt_num(pov, 2) + " %",
            "promise_text": fmt_num(promised_p, 2) + " %",
            "year_promise_text": fmt_num(PROMISES["Камбағаллик"]["year_pct"], 2) + " %",
            "status": status,
            "delta": delta,
            "delta_dir": dir_,
            "quality": "warn",
            "quality_text": "диспропорция",
            "source": emp.get("source", ""),
            "spark": [5.5, 5.2, 5.0, 4.8, 4.6, pov],
        })

    return kpis


# Synthesize task workflow state for visual demo (real data has all 'grey').
# Distribute 61 tasks across stages so the kanban has visual life.
def assign_workflow_state(idx: int, total: int) -> dict:
    """Deterministically assign a stage + executor + due to each task for the prototype.

    In production this comes from the workflow backend.
    """
    # Roughly: 35% assigned, 35% in_progress, 25% done, 5% blocked
    bucket = idx % 20
    if bucket < 7:
        stage = "assigned"
    elif bucket < 14:
        stage = "in_progress"
    elif bucket < 19:
        stage = "done"
    else:
        stage = "blocked"

    executors = [
        ("Б. Раҳимов",         "Андижон вилоят ҳокими ўринбосари"),
        ("М. Юсупов",          "Иқтисодий ривожланиш бошқармаси"),
        ("Ш. Тошпўлатов",      "Ҳокимият идораси"),
        ("Н. Каримова",        "Молия бошқармаси"),
        ("О. Алиев",           "Инвестиция бўлими"),
        ("Ф. Иброҳимова",      "Бандлик маркази"),
        ("Д. Эргашев",         "Андижон шаҳар ҳокимияти"),
    ]
    executor_name, executor_role = executors[idx % len(executors)]

    months = ["31 март", "30 июн", "30 сен", "31 дек"]
    due = months[idx % 4]

    return {"stage": stage, "executor": executor_name, "executor_role": executor_role, "due": due}


def build_tasks(data: dict) -> list[dict]:
    raw = data.get("tasks", [])
    out = []
    for idx, t in enumerate(raw):
        wf = assign_workflow_state(idx, len(raw))
        out.append({
            "id": t["id"],
            "sector": t["sector"],
            "source": t["source"],
            "title": t["title"],
            "owner": t.get("owner", ""),
            "period": t.get("period", ""),
            **wf,
        })
    return out


def build_districts_payload(data: dict) -> list[dict]:
    """Trim district records for client-side use."""
    out = []
    for d in data.get("districts", []):
        dd = d.get("data", {}) or {}
        ind = dd.get("industry") or {}
        ag = dd.get("agriculture") or {}
        sv = dd.get("services") or {}
        bdg = dd.get("budget") or {}
        bi = dd.get("budget_investment") or {}
        fi = dd.get("foreign_investment") or {}
        emp = dd.get("employment") or {}
        # poverty_h1 may be a string ("холи ҳудуд") for non-poverty districts
        pov = emp.get("poverty_h1")
        if not isinstance(pov, (int, float)):
            pov = None
        out.append({
            "name": d["name"],
            "owner": d.get("owner", ""),
            "industry_h1": ind.get("h1_value"),
            "industry_pct": ind.get("h1_growth"),
            "agriculture_h1": ag.get("h1_value"),
            "agriculture_pct": ag.get("h1_growth"),
            "services_h1": sv.get("h1_value"),
            "services_pct": sv.get("h1_growth"),
            "budget_h1_plan": bdg.get("h1_plan"),
            "budget_h1_fact": bdg.get("h1_expected"),
            "budget_exec_pct": bdg.get("h1_execution_pct"),
            "investment_h1": bi.get("h1_absorption"),
            "investment_pct": bi.get("h1_pct"),
            "investment_year_pct": bi.get("year_pct"),
            "fi_h1": fi.get("h1_expected"),
            "fi_h1_plan": fi.get("h1_plan"),
            "unemployment": emp.get("unemployment_h1"),
            "poverty": pov,
            "debt": d.get("debt") or [],
            "localization_projects": dd.get("localization_projects_h1"),
        })
    return out


def main() -> None:
    data = json.loads(DATA_JSON.read_text(encoding="utf-8"))
    kpis = build_promise_kpis(data)
    tasks = build_tasks(data)
    districts = build_districts_payload(data)

    payload = {
        "meta": {
            "region": "Андижон вилояти",
            "generated_at": date.today().isoformat(),
            "snapshot_label": "I ярим йиллик · 2026",
            "letter_path": str(LETTER_DOCX).replace("\\", "/"),
        },
        "kpis": kpis,
        "tasks": tasks,
        "districts": districts,
        "republic": [{"name": n, "abbr": a, "value": v} for n, a, v in REPUBLIC_MOCK],
        "data_quality": data.get("data_quality", []),
        "sources": data.get("sources", []),
    }

    html = render(payload)
    OUT_HTML.write_text(html, encoding="utf-8")
    print(f"wrote {OUT_HTML.relative_to(ROOT)}  ({len(html):,} bytes)")


def render(p: dict) -> str:
    js_payload = json.dumps(p, ensure_ascii=False)
    return TEMPLATE.replace("__PAYLOAD__", js_payload)


# -------------------------------------------------------------------------------------
# HTML template (the actual prototype). All 3 pages live in a single file.
# Visuals follow the v7 mockup.
# -------------------------------------------------------------------------------------

TEMPLATE = r"""<!doctype html>
<html lang="uz-Cyrl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Худудлар мониторинги · v7 · Андижон</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#fafbfc; --paper:#fff; --surface:#f5f7fa;
  --ink:#0e2233; --ink-2:#1f3a55; --muted:#5b7080; --muted-2:#8597a8;
  --line:#e3e9f0; --line-2:#cdd7e2;
  --nav:#0a1a2c; --nav-2:#0f2740;
  --accent:#1858c4; --accent-soft:#e7f0ff; --accent-ink:#0a3a8c;
  --success:#0e7a4d; --success-soft:#e2f5ec;
  --warning:#a85e00; --warning-soft:#fdf0d6;
  --danger:#b8281e; --danger-soft:#fde4e1;
  --neutral:#5b7080; --neutral-soft:#eef1f5;
  --r-sm:6px; --r:10px; --r-lg:14px;
  --s-1:4px; --s-2:8px; --s-3:12px; --s-4:16px; --s-5:24px; --s-6:32px;
  --shadow-sm:0 1px 2px rgba(14,34,51,.04), 0 1px 1px rgba(14,34,51,.06);
  --shadow:0 4px 12px rgba(14,34,51,.06), 0 1px 3px rgba(14,34,51,.04);
  --shadow-lg:0 12px 32px rgba(14,34,51,.10), 0 2px 6px rgba(14,34,51,.05);
  font-family:'Inter','Segoe UI',system-ui,sans-serif;
  font-feature-settings:'tnum';
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;color:var(--ink);background:var(--bg);font-size:14px;line-height:1.5}
button,input,select,textarea{font:inherit;color:inherit}
button{cursor:pointer;border:0;background:transparent}
:focus-visible{outline:2.5px solid var(--accent);outline-offset:2px;border-radius:var(--r-sm)}
.tnum{font-variant-numeric:tabular-nums}
.hidden{display:none !important}

/* TOPBAR */
.topbar{position:sticky;top:0;z-index:30;background:#fff;border-bottom:1px solid var(--line);box-shadow:var(--shadow-sm)}
.mast{display:grid;grid-template-columns:auto auto 1fr auto;gap:var(--s-5);align-items:center;padding:var(--s-3) var(--s-5);max-width:1600px;margin:0 auto}
.brand{display:flex;align-items:center;gap:var(--s-3)}
.brand-mark{width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent-ink));color:#fff;display:grid;place-items:center;font-weight:800;font-size:13px;letter-spacing:.04em}
.brand h1{margin:0;font-size:15px;font-weight:700;letter-spacing:-.01em}
.brand small{display:block;color:var(--muted);font-size:11px;font-weight:500;margin-top:1px}
.region-picker{display:flex;align-items:center;gap:var(--s-2);padding:6px var(--s-3);background:var(--surface);border:1px solid var(--line);border-radius:999px;font-size:13px;font-weight:600}
.region-picker:hover{background:var(--accent-soft);border-color:var(--accent)}
.region-picker .dot{width:8px;height:8px;border-radius:50%;background:var(--success)}
.region-picker svg{width:14px;height:14px;color:var(--muted)}
.crumbs{display:flex;align-items:center;gap:6px;color:var(--muted);font-size:12px;font-weight:500}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--accent)}
.crumbs .sep{color:var(--line-2)}
.crumbs .current{color:var(--ink);font-weight:600}
.top-actions{display:flex;gap:var(--s-2);align-items:center}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:var(--r-sm);font-size:13px;font-weight:600;border:1px solid transparent}
.btn svg{width:14px;height:14px}
.btn-ghost{color:var(--muted);background:transparent}
.btn-ghost:hover{color:var(--ink);background:var(--surface)}
.btn-outline{color:var(--ink);border-color:var(--line);background:#fff}
.btn-outline:hover{border-color:var(--line-2);background:var(--surface)}
.btn-primary{color:#fff;background:var(--accent)}
.btn-primary:hover{background:var(--accent-ink)}
.btn-disabled{color:var(--muted-2);background:var(--surface);cursor:not-allowed}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--accent-soft);color:var(--accent-ink);display:grid;place-items:center;font-weight:700;font-size:12px}

/* SHELL */
.shell{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:calc(100vh - 57px)}
.sidebar{background:#fff;border-right:1px solid var(--line);padding:var(--s-4) var(--s-3);position:sticky;top:57px;height:calc(100vh - 57px);overflow:auto;display:flex;flex-direction:column}
.side-section{margin-bottom:var(--s-4)}
.side-label{padding:0 var(--s-3) var(--s-2);font-size:11px;font-weight:700;color:var(--muted-2);letter-spacing:.06em;text-transform:uppercase}
.nav-btn{width:100%;display:grid;grid-template-columns:18px 1fr auto;gap:var(--s-3);align-items:center;padding:8px var(--s-3);border-radius:var(--r-sm);color:var(--ink-2);font-size:13px;font-weight:500;text-align:left;margin-bottom:2px;border:0;background:transparent}
.nav-btn svg{width:16px;height:16px;color:var(--muted)}
.nav-btn:hover{background:var(--surface)}
.nav-btn.active{background:var(--accent-soft);color:var(--accent-ink);font-weight:600}
.nav-btn.active svg{color:var(--accent)}
.nav-badge{font-size:10px;font-weight:700;padding:2px 6px;border-radius:999px;background:var(--danger-soft);color:var(--danger)}
.side-foot{margin-top:auto;padding:var(--s-3);border-radius:var(--r);background:var(--surface);font-size:12px}
.side-foot strong{display:block;color:var(--ink);font-size:12px;font-weight:700;margin-bottom:2px}
.side-foot span{color:var(--muted)}

.main{padding:var(--s-5) var(--s-6) var(--s-6);max-width:1360px;margin:0 auto;width:100%}
.page-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:var(--s-5);gap:var(--s-4);flex-wrap:wrap}
.page-title h2{margin:0;font-size:24px;font-weight:700;letter-spacing:-.02em;color:var(--ink)}
.page-title p{margin:6px 0 0;color:var(--muted);font-size:13px;max-width:60ch}
.page-controls{display:flex;gap:var(--s-2);align-items:center;flex-wrap:wrap}
.seg{display:inline-flex;background:#fff;border:1px solid var(--line);border-radius:var(--r-sm);padding:3px;box-shadow:var(--shadow-sm)}
.seg button{padding:5px 11px;border-radius:5px;font-size:12px;font-weight:600;color:var(--muted)}
.seg button.active{background:var(--ink);color:#fff}

/* KPI tile (Promise vs Execution) */
.kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--s-3);margin-bottom:var(--s-5)}
.kpi{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:var(--s-4);box-shadow:var(--shadow-sm);position:relative;text-align:left;transition:all .15s;cursor:pointer}
.kpi:hover{border-color:var(--line-2);box-shadow:var(--shadow);transform:translateY(-1px)}
.kpi.active{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.kpi-head{display:flex;justify-content:space-between;align-items:center;gap:var(--s-2);margin-bottom:var(--s-2)}
.kpi-label{font-size:12px;font-weight:600;color:var(--muted);letter-spacing:.01em}
.kpi-quality{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;padding:2px 6px;border-radius:999px;white-space:nowrap}
.q-ok{background:var(--success-soft);color:var(--success)}
.q-warn{background:var(--warning-soft);color:var(--warning)}
.q-na{background:var(--neutral-soft);color:var(--muted)}
.kpi-value{font-size:24px;font-weight:700;letter-spacing:-.02em;color:var(--ink);line-height:1.1}
.kpi-unit{font-size:13px;font-weight:500;color:var(--muted-2);margin-left:2px}
.kpi-promise{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-top:6px;border-top:1px dashed var(--line);padding-top:6px}
.kpi-promise b{color:var(--ink-2);font-weight:600}
.kpi-foot{display:flex;justify-content:space-between;align-items:center;margin-top:var(--s-3);gap:var(--s-2)}
.delta{display:inline-flex;align-items:center;gap:3px;font-size:12px;font-weight:700;padding:3px 7px;border-radius:var(--r-sm)}
.delta svg{width:11px;height:11px}
.delta.up,.delta.green{background:var(--success-soft);color:var(--success)}
.delta.down,.delta.red{background:var(--danger-soft);color:var(--danger)}
.delta.flat,.delta.amber{background:var(--warning-soft);color:var(--warning)}
.delta.grey{background:var(--neutral-soft);color:var(--muted)}
.spark{height:28px;flex:0 0 80px}

/* Status accent on left edge */
.kpi[data-status="green"]{box-shadow:inset 3px 0 0 var(--success), var(--shadow-sm)}
.kpi[data-status="amber"]{box-shadow:inset 3px 0 0 var(--warning), var(--shadow-sm)}
.kpi[data-status="red"]{box-shadow:inset 3px 0 0 var(--danger), var(--shadow-sm)}
.kpi[data-status="grey"]{box-shadow:inset 3px 0 0 var(--muted-2), var(--shadow-sm)}

/* Panels */
.panel-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:var(--s-4);margin-bottom:var(--s-5)}
.panel{background:#fff;border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow-sm);overflow:hidden}
.panel-head{padding:var(--s-3) var(--s-4);border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:var(--s-3)}
.panel-head h3{margin:0;font-size:14px;font-weight:700;letter-spacing:-.01em}
.panel-head .meta{font-size:11px;color:var(--muted);font-weight:500}
.panel-body{padding:var(--s-4)}

/* Map */
.map-wrap{padding:var(--s-3) var(--s-4) var(--s-4);position:relative}
.map-svg{width:100%;height:280px}
.map-svg path{stroke:#fff;stroke-width:1.5;cursor:pointer;transition:fill .15s}
.map-svg path:hover{stroke:var(--accent);stroke-width:2}
.map-svg path.selected{stroke:var(--accent-ink);stroke-width:2.2}
.map-legend{display:flex;align-items:center;gap:var(--s-3);font-size:11px;color:var(--muted);margin-top:var(--s-2)}
.legend-scale{display:flex;height:8px;border-radius:4px;overflow:hidden;flex:1;max-width:200px}
.legend-scale span{flex:1}

/* Comparative strip */
.compare-strip{display:grid;grid-template-columns:repeat(14,1fr);gap:3px;padding:var(--s-3) var(--s-4)}
.region-bar{display:flex;flex-direction:column;align-items:center;gap:4px;padding:var(--s-2) 2px;border-radius:var(--r-sm);cursor:pointer;background:transparent;border:0}
.region-bar:hover{background:var(--surface)}
.region-bar.current{background:var(--accent-soft)}
.region-bar .bar-vis{width:18px;background:var(--neutral-soft);border-radius:2px;display:flex;align-items:flex-end;height:64px;overflow:hidden}
.region-bar .bar-vis span{width:100%;background:var(--accent);border-radius:2px 2px 0 0;display:block}
.region-bar.current .bar-vis span{background:var(--accent-ink)}
.region-bar .lbl{font-size:9px;font-weight:600;color:var(--muted);text-align:center;line-height:1.1}
.region-bar .val{font-size:10px;font-weight:700;color:var(--ink);font-variant-numeric:tabular-nums}

/* District table */
.dist-table{width:100%;border-collapse:collapse}
.dist-table th{font-size:10px;font-weight:700;color:var(--muted-2);text-transform:uppercase;letter-spacing:.06em;text-align:left;padding:8px var(--s-4);border-bottom:1px solid var(--line);background:var(--surface);position:sticky;top:0}
.dist-table td{padding:10px var(--s-4);border-bottom:1px solid var(--line);font-size:13px;vertical-align:middle}
.dist-table tr:hover{background:var(--surface);cursor:pointer}
.dist-table tr.selected{background:var(--accent-soft)}
.dist-name{font-weight:600;color:var(--ink)}
.dist-meta{font-size:11px;color:var(--muted);margin-top:2px}
.bar{width:90px;height:5px;background:var(--neutral-soft);border-radius:3px;overflow:hidden;display:inline-block;vertical-align:middle}
.bar span{display:block;height:100%;background:var(--accent);border-radius:3px}

/* Status pill */
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap}
.status-pill svg{width:10px;height:10px}
.s-green{background:var(--success-soft);color:var(--success)}
.s-amber{background:var(--warning-soft);color:var(--warning)}
.s-red{background:var(--danger-soft);color:var(--danger)}
.s-grey{background:var(--neutral-soft);color:var(--muted)}

/* Drawer */
.drawer-backdrop{position:fixed;inset:0;background:rgba(14,34,51,.42);backdrop-filter:blur(2px);z-index:40;opacity:0;pointer-events:none;transition:opacity .2s}
.drawer-backdrop.open{opacity:1;pointer-events:auto}
.drawer{position:fixed;top:0;right:0;width:520px;max-width:100vw;height:100vh;background:#fff;box-shadow:var(--shadow-lg);z-index:41;transform:translateX(100%);transition:transform .25s ease;display:flex;flex-direction:column}
.drawer.open{transform:translateX(0)}
.drawer-head{padding:var(--s-4);border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}
.drawer-head h3{margin:0;font-size:18px;font-weight:700;letter-spacing:-.01em}
.drawer-head .role{display:block;color:var(--muted);font-size:12px;margin-top:2px}
.drawer-body{padding:var(--s-4);overflow:auto;flex:1}
.drawer-foot{padding:var(--s-3) var(--s-4);border-top:1px solid var(--line);display:flex;gap:var(--s-2);justify-content:flex-end}

.profile-block{margin-bottom:var(--s-5)}
.profile-block h4{margin:0 0 var(--s-2);font-size:11px;font-weight:700;color:var(--muted-2);text-transform:uppercase;letter-spacing:.06em}
.profile-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--s-3)}
.profile-cell{padding:var(--s-3);border:1px solid var(--line);border-radius:var(--r-sm);background:var(--surface)}
.profile-cell .pl{font-size:11px;color:var(--muted);font-weight:600}
.profile-cell .pv{font-size:18px;color:var(--ink);font-weight:700;letter-spacing:-.01em;font-variant-numeric:tabular-nums}
.profile-cell .pp{font-size:11px;color:var(--muted);margin-top:3px}

/* Tasks page */
.kanban{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--s-3)}
.kan-col{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);min-height:300px;display:flex;flex-direction:column}
.kan-head{padding:var(--s-3) var(--s-4);border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}
.kan-head h4{margin:0;font-size:12px;font-weight:700;color:var(--ink-2);text-transform:uppercase;letter-spacing:.05em}
.kan-head span{font-size:11px;color:var(--muted);font-weight:600;background:#fff;padding:2px 7px;border-radius:999px}
.kan-body{padding:var(--s-2);display:flex;flex-direction:column;gap:var(--s-2);flex:1;overflow:auto}
.task-card{background:#fff;border:1px solid var(--line);border-radius:var(--r-sm);padding:var(--s-3);box-shadow:var(--shadow-sm);cursor:pointer}
.task-card:hover{border-color:var(--line-2);box-shadow:var(--shadow)}
.task-card.blocked{border-left:3px solid var(--danger)}
.task-id{font-size:10px;color:var(--muted-2);font-weight:700;letter-spacing:.04em}
.task-sector{display:inline-block;font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;background:var(--accent-soft);color:var(--accent-ink);margin-left:6px}
.task-title{font-size:13px;font-weight:600;color:var(--ink);margin-top:6px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.task-foot{display:flex;justify-content:space-between;align-items:center;margin-top:var(--s-2);gap:var(--s-2);font-size:11px;color:var(--muted)}
.task-foot .exec{display:flex;align-items:center;gap:6px;min-width:0}
.task-foot .exec .av{width:18px;height:18px;border-radius:50%;background:var(--accent-soft);color:var(--accent-ink);display:grid;place-items:center;font-size:9px;font-weight:700;flex-shrink:0}
.task-foot .exec .nm{font-weight:600;color:var(--ink-2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.task-foot .due{font-weight:600;color:var(--ink-2);white-space:nowrap}

/* Letter banner on tasks page */
.letter-banner{background:linear-gradient(135deg,#0a3a8c 0%,#1858c4 50%,#234d6b 100%);color:#fff;border-radius:var(--r);padding:var(--s-4) var(--s-5);margin-bottom:var(--s-5);display:grid;grid-template-columns:1fr auto;gap:var(--s-4);align-items:center;position:relative;overflow:hidden}
.letter-banner::after{content:"";position:absolute;top:-30%;right:-10%;width:300px;height:300px;background:radial-gradient(circle,rgba(255,255,255,.12),transparent 60%);pointer-events:none}
.letter-banner h3{margin:0 0 4px;font-size:18px;font-weight:700;letter-spacing:-.01em}
.letter-banner p{margin:0;font-size:13px;color:rgba(255,255,255,.85);max-width:50ch}
.letter-banner .lb-meta{display:flex;gap:var(--s-4);margin-top:var(--s-3);font-size:11px;color:rgba(255,255,255,.75);font-weight:600}
.letter-banner .lb-meta b{color:#fff;font-weight:700;letter-spacing:.02em}
.letter-banner .lb-actions{display:flex;flex-direction:column;gap:var(--s-2);position:relative;z-index:1}
.btn-light{background:#fff;color:var(--accent-ink);font-weight:700}
.btn-light:hover{background:rgba(255,255,255,.92)}
.btn-light-ghost{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.25)}
.btn-light-ghost:hover{background:rgba(255,255,255,.2)}

/* Tasks summary cards */
.task-summary{display:grid;grid-template-columns:repeat(5,1fr);gap:var(--s-3);margin-bottom:var(--s-4)}
.tsum{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:var(--s-3) var(--s-4);box-shadow:var(--shadow-sm)}
.tsum .lbl{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.tsum .val{font-size:24px;font-weight:700;color:var(--ink);letter-spacing:-.02em;margin-top:4px;font-variant-numeric:tabular-nums}
.tsum.assigned .val{color:var(--ink)}
.tsum.in_progress .val{color:var(--accent)}
.tsum.done .val{color:var(--success)}
.tsum.blocked .val{color:var(--danger)}
.tsum.total .val{color:var(--ink-2)}
.tsum .pct{font-size:11px;color:var(--muted);margin-top:2px;font-weight:500}

.toolbar{display:flex;justify-content:space-between;align-items:center;gap:var(--s-3);margin-bottom:var(--s-3);flex-wrap:wrap}
.search{display:flex;align-items:center;gap:var(--s-2);background:#fff;border:1px solid var(--line);border-radius:var(--r-sm);padding:6px 12px;min-width:240px}
.search svg{width:14px;height:14px;color:var(--muted)}
.search input{border:0;outline:0;background:transparent;font-size:13px;flex:1}

.filters{display:flex;gap:var(--s-2);flex-wrap:wrap}
.chip{font-size:11px;font-weight:600;padding:5px 10px;border-radius:999px;background:#fff;border:1px solid var(--line);color:var(--muted)}
.chip.active{background:var(--ink);color:#fff;border-color:var(--ink)}

@media (max-width:1100px){
  .kpi-grid{grid-template-columns:repeat(3,1fr)}
  .panel-grid{grid-template-columns:1fr}
  .kanban{grid-template-columns:repeat(2,1fr)}
  .task-summary{grid-template-columns:repeat(3,1fr)}
}
@media (max-width:760px){
  .shell{grid-template-columns:1fr}
  .sidebar{display:none}
  .main{padding:var(--s-4)}
  .kpi-grid{grid-template-columns:repeat(2,1fr)}
  .compare-strip{grid-template-columns:repeat(7,1fr)}
  .mast{grid-template-columns:auto 1fr auto;gap:var(--s-3);padding:var(--s-3)}
  .crumbs{display:none}
  .page-title h2{font-size:20px}
  .kanban{grid-template-columns:1fr}
  .task-summary{grid-template-columns:repeat(2,1fr)}
  .drawer{width:100%}
  .letter-banner{grid-template-columns:1fr}
}
@media print{
  .topbar,.sidebar,.page-controls,.toolbar,.drawer,.drawer-backdrop{display:none !important}
  .shell{grid-template-columns:1fr}
  .main{padding:0;max-width:none}
  .kpi-grid{grid-template-columns:repeat(4,1fr)}
  body{background:#fff}
}
</style>
</head>
<body>

<header class="topbar">
  <div class="mast">
    <div class="brand">
      <div class="brand-mark">CERR</div>
      <div>
        <h1>Худудлар мониторинги</h1>
        <small>Кафолат хатлари ижроси · v7</small>
      </div>
    </div>

    <button class="region-picker" aria-label="Вилоят танлаш">
      <span class="dot"></span>
      <span>Андижон вилояти</span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
    </button>

    <nav class="crumbs" aria-label="Йўналиш">
      <span class="muted">Республика</span>
      <span class="sep">/</span>
      <a href="#">Андижон</a>
      <span class="sep">/</span>
      <span class="current" id="crumb-page">Ваъда vs Ижро</span>
    </nav>

    <div class="top-actions">
      <button class="btn btn-disabled" title="Тайёрланмоқда" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7M5 9v11h14V9"/></svg>
        Республика
      </button>
      <button class="btn btn-outline" id="viewLetterBtn" title="Кафолат хатини очиш">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6"/></svg>
        Кафолат хати
      </button>
      <button class="btn btn-outline" id="exportBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Экспорт
      </button>
      <div class="avatar" title="Н. Ортиқов">НО</div>
    </div>
  </div>
</header>

<div class="shell">
  <aside class="sidebar" aria-label="Асосий меню">
    <div class="side-section">
      <div class="side-label">Кузатув</div>
      <button class="nav-btn active" data-page="promise" aria-current="page">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <span>Ваъда vs Ижро</span>
      </button>
      <button class="nav-btn" data-page="districts">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-7h6v7"/></svg>
        <span>Туманлар</span>
      </button>
      <button class="nav-btn" data-page="tasks">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3 8-8M3 12a9 9 0 1 0 9-9"/></svg>
        <span>Топшириқлар</span>
        <span class="nav-badge" id="taskBadge">—</span>
      </button>
    </div>
    <div class="side-section">
      <div class="side-label">Манба</div>
      <button class="nav-btn" id="qualityBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
        <span>Маълумот сифати</span>
      </button>
    </div>
    <div class="side-foot" id="sideFoot">
      <strong>Кузатув ҳолати</strong>
      <span id="snapshotLabel">—</span>
    </div>
  </aside>

  <main class="main">
    <!-- ========== PAGE: PROMISE vs EXECUTION ========== -->
    <section id="page-promise">
      <div class="page-head">
        <div class="page-title">
          <h2>Ваъда vs Ижро · I ярим йиллик 2026</h2>
          <p>Кафолат хатидаги мажбуриятлар ва уларнинг ҳозирги ижро ҳолати. Ҳар KPI ёнидаги маълумот сифати белгиси (✓ / ⚠ / ⊘) маълумот ишончлилигини кўрсатади.</p>
        </div>
        <div class="page-controls">
          <div class="seg" role="tablist" aria-label="Давр">
            <button class="active">I ярим йил</button>
            <button>9 ой</button>
            <button>Йиллик</button>
          </div>
        </div>
      </div>

      <div id="kpiGrid" class="kpi-grid"></div>

      <div class="panel-grid">
        <div class="panel">
          <div class="panel-head">
            <h3>Андижон туманлари — ЯҲМ ҳисса (%)</h3>
            <span class="meta">14 та туман · хариta кўриниши · босинг → профил</span>
          </div>
          <div class="map-wrap">
            <svg class="map-svg" id="kpiMap" viewBox="0 0 600 280" role="img" aria-label="Андижон вилояти туманлари харитаси"></svg>
            <div class="map-legend">
              <span>Кам ҳисса</span>
              <div class="legend-scale">
                <span style="background:#dee9f8"></span>
                <span style="background:#b9cef0"></span>
                <span style="background:#83a8e2"></span>
                <span style="background:#4d82d4"></span>
                <span style="background:#1858c4"></span>
                <span style="background:#0a3a8c"></span>
              </div>
              <span>Юқори ҳисса</span>
              <span style="margin-left:auto" id="mapSelectedLabel">Танланган: <strong style="color:var(--ink)">Андижон шаҳри</strong></span>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <h3>14 вилоят қиёси</h3>
            <span class="meta">ЯҲМ ўсиш · I ярим</span>
          </div>
          <div class="compare-strip" id="compareStrip"></div>
          <div class="panel-head" style="border-top:1px solid var(--line)">
            <h3>Кафолат хати манбалари</h3>
            <span class="meta">8 файл</span>
          </div>
          <div class="panel-body" id="sourcesList" style="font-size:12px;color:var(--muted);max-height:200px;overflow:auto"></div>
        </div>
      </div>
    </section>

    <!-- ========== PAGE: DISTRICTS ========== -->
    <section id="page-districts" class="hidden">
      <div class="page-head">
        <div class="page-title">
          <h2>Туманлар кесими</h2>
          <p>16 та туман бўйича жорий ҳолат. Жадвал қаторини босинг — ўнг тарафда туман профили очилади.</p>
        </div>
        <div class="page-controls">
          <div class="seg">
            <button class="active" data-metric="industry">Саноат</button>
            <button data-metric="budget">Бюджет</button>
            <button data-metric="investment">Инвестиция</button>
            <button data-metric="employment">Бандлик</button>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-bottom:var(--s-4)">
        <div class="map-wrap">
          <svg class="map-svg" id="distMap" viewBox="0 0 600 280" role="img"></svg>
          <div class="map-legend">
            <span>Заиф</span>
            <div class="legend-scale">
              <span style="background:#fde4e1"></span>
              <span style="background:#fdf0d6"></span>
              <span style="background:#dee9f8"></span>
              <span style="background:#b9cef0"></span>
              <span style="background:#83a8e2"></span>
              <span style="background:#1858c4"></span>
            </div>
            <span>Кучли</span>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h3>Туманлар жадвали</h3>
          <span class="meta" id="distTableMeta">—</span>
        </div>
        <div style="overflow:auto;max-height:600px">
          <table class="dist-table" id="distTable"></table>
        </div>
      </div>
    </section>

    <!-- ========== PAGE: TASKS / GUARANTEE LETTER ========== -->
    <section id="page-tasks" class="hidden">
      <div class="page-head">
        <div class="page-title">
          <h2>Кафолат хати топшириқлари</h2>
          <p>Кафолат хатида қайд этилган мажбуриятлар бўйича ажратилган топшириқлар. Ҳар топшириқ кимга бириктирилгани, муддати ва ҳолати кўрсатилади.</p>
        </div>
      </div>

      <div class="letter-banner">
        <div>
          <h3>Андижон вилояти Кафолат хати · 2026</h3>
          <p>Президентга вилоят раҳбари томонидан имзоланган мажбуриятлар. Бу платформадаги барча KPI ва топшириқлар шу ҳужжатдан келиб чиқади.</p>
          <div class="lb-meta">
            <span><b id="lbTotal">—</b> та мажбурият</span>
            <span><b id="lbDone">—</b> бажарилган</span>
            <span><b id="lbProgress">—</b> жараёнда</span>
            <span><b id="lbBlocked">—</b> блокда</span>
          </div>
        </div>
        <div class="lb-actions">
          <button class="btn btn-light" id="openLetter">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6"/></svg>
            Хатни очиш
          </button>
          <button class="btn btn-light-ghost" onclick="window.print()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
            Ҳисобот PDF
          </button>
        </div>
      </div>

      <div class="task-summary" id="taskSummary"></div>

      <div class="toolbar">
        <div class="filters" id="taskFilters"></div>
        <div class="search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          <input id="taskSearch" placeholder="Топшириқ ёки ижрочи бўйича қидиринг…">
        </div>
      </div>

      <div class="kanban" id="kanban"></div>
    </section>

  </main>
</div>

<!-- DRAWER (district profile / task detail) -->
<div class="drawer-backdrop" id="drawerBackdrop"></div>
<aside class="drawer" id="drawer" aria-hidden="true">
  <div class="drawer-head">
    <div>
      <h3 id="drawerTitle">—</h3>
      <span class="role" id="drawerRole">—</span>
    </div>
    <button class="btn btn-ghost" id="drawerClose" aria-label="Ёпиш">✕</button>
  </div>
  <div class="drawer-body" id="drawerBody"></div>
  <div class="drawer-foot">
    <button class="btn btn-outline" id="drawerExport">Профил экспорти</button>
  </div>
</aside>

<script>
const PAYLOAD = __PAYLOAD__;
window.__P = PAYLOAD;

const $ = (s, p=document) => p.querySelector(s);
const $$ = (s, p=document) => Array.from(p.querySelectorAll(s));

// ----- District map layout (hex grid; 16 cells for 16 districts) -----
const DIST_LAYOUT = [
  // 6 cells across × 3 rows roughly
  {cx:105,cy:80}, {cx:190,cy:80}, {cx:275,cy:80}, {cx:360,cy:80}, {cx:445,cy:80}, {cx:530,cy:80},
  {cx:65,cy:175}, {cx:150,cy:175}, {cx:235,cy:175}, {cx:320,cy:175}, {cx:405,cy:175}, {cx:490,cy:175},
  {cx:110,cy:255}, {cx:195,cy:255}, {cx:280,cy:255}, {cx:365,cy:255},
];
function hexPath(cx, cy, r=42) {
  const pts = [];
  for (let i=0; i<6; i++) {
    const a = Math.PI/3 * i + Math.PI/6;
    pts.push(`${(cx + r*Math.cos(a)).toFixed(1)},${(cy + r*Math.sin(a)).toFixed(1)}`);
  }
  return `M${pts.join(' L')} Z`;
}
function colorFor(value, scale=[0,5,15,30,50,70]) {
  if (value == null) return '#eef1f5';
  const colors = ['#fde4e1','#fdf0d6','#dee9f8','#b9cef0','#83a8e2','#1858c4','#0a3a8c'];
  for (let i=0; i<scale.length; i++) if (value < scale[i]) return colors[i];
  return colors[colors.length-1];
}

// ----- Side foot stats -----
function setSideFoot() {
  const ds = PAYLOAD.data_quality || [];
  const high = ds.filter(d => (d.severity || '').toLowerCase()==='high').length;
  const medium = ds.filter(d => (d.severity || '').toLowerCase()==='medium').length;
  $('#snapshotLabel').textContent = `${PAYLOAD.meta.snapshot_label} · ${ds.length} сифат изоҳи · ${high} жиддий`;
  $('#taskBadge').textContent = PAYLOAD.tasks.length;
}

// ----- KPI grid (Promise vs Execution) -----
function renderKpis() {
  const wrap = $('#kpiGrid');
  wrap.innerHTML = PAYLOAD.kpis.map((k,i) => {
    const sparkPath = sparkLine(k.spark);
    const sparkColor = k.delta_dir==='up' ? '#0e7a4d' : k.delta_dir==='down' ? '#b8281e' : '#5b7080';
    const qIcon = k.quality==='ok'
      ? '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 6l3 3 5-6"/></svg>'
      : k.quality==='warn'
      ? '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2v4M6 9v.5"/><path d="M1 10h10L6 2z"/></svg>'
      : '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="4"/></svg>';
    return `
      <button class="kpi" data-status="${k.status}" data-i="${i}" aria-label="${k.label}">
        <div class="kpi-head">
          <span class="kpi-label">${k.label}</span>
          <span class="kpi-quality q-${k.quality}" title="${k.source||''}">${qIcon}${k.quality_text}</span>
        </div>
        <div class="kpi-value tnum">${k.fact_text}</div>
        <div class="kpi-promise">
          <span>Ваъда (I ярим): <b>${k.promise_text}</b></span>
          <span>Йил: <b>${k.year_promise_text}</b></span>
        </div>
        <div class="kpi-foot">
          <span class="delta ${k.status}">${k.delta}</span>
          <svg class="spark" viewBox="0 0 80 28" preserveAspectRatio="none">
            <polyline points="${sparkPath}" fill="none" stroke="${sparkColor}" stroke-width="2"/>
          </svg>
        </div>
      </button>`;
  }).join('');
  // Click → expand detail (could open drawer with tasks for that KPI; for now toggle active)
  $$('.kpi', wrap).forEach(el => {
    el.addEventListener('click', () => {
      $$('.kpi', wrap).forEach(o => o.classList.remove('active'));
      el.classList.add('active');
      const i = +el.dataset.i;
      openKpiDrillDown(PAYLOAD.kpis[i]);
    });
  });
}
function sparkLine(values, w=80, h=28) {
  if (!values || !values.length) return '';
  const min = Math.min(...values), max = Math.max(...values), range = max-min || 1;
  return values.map((v,i) => {
    const x = (i/(values.length-1)) * w;
    const y = h - ((v-min)/range) * h * 0.85 - 2;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  }).join(' ');
}

function openKpiDrillDown(kpi) {
  // Simple: open drawer with related tasks (filtered by sector matching)
  const sectorMap = {
    'ЯҲМ ўсиш суръати':'Макро иқтисодиёт',
    'Саноат ўсиш суръати':'Макро иқтисодиёт',
    'Қишлоқ хўжалиги':'Макро иқтисодиёт',
    'Қурилиш':'Макро иқтисодиёт',
    'Бозор хизматлари':'Макро иқтисодиёт',
    'Бюджет тушуми ижроси':'Бюджет',
    'Хорижий инвестиция':'Хорижий инвестиция',
    'Экспорт ҳажми':'Экспорт',
    'Ишсизлик':'Бандлик ва камбағаллик',
    'Камбағаллик':'Бандлик ва камбағаллик',
  };
  const sector = sectorMap[kpi.label] || '';
  const related = PAYLOAD.tasks.filter(t => t.sector === sector);
  const stages = ['assigned','in_progress','done','blocked'];
  const stageLbl = {assigned:'Бириктирилган', in_progress:'Жараёнда', done:'Бажарилган', blocked:'Блок'};
  const counts = {};
  stages.forEach(s => counts[s] = related.filter(t => t.stage===s).length);

  $('#drawerTitle').textContent = kpi.label;
  $('#drawerRole').textContent = `${related.length} та боғлиқ топшириқ · сектор: ${sector || '—'}`;
  $('#drawerBody').innerHTML = `
    <div class="profile-block">
      <h4>Ваъда vs Факт</h4>
      <div class="profile-grid">
        <div class="profile-cell"><div class="pl">Ваъда (I ярим)</div><div class="pv">${kpi.promise_text}</div><div class="pp">Йил режа: ${kpi.year_promise_text}</div></div>
        <div class="profile-cell"><div class="pl">Жорий факт</div><div class="pv">${kpi.fact_text}</div><div class="pp">${kpi.delta} · сифат: ${kpi.quality_text}</div></div>
      </div>
    </div>
    <div class="profile-block">
      <h4>Топшириқлар бўйича ҳолат</h4>
      <div class="profile-grid">
        ${stages.map(s => `<div class="profile-cell"><div class="pl">${stageLbl[s]}</div><div class="pv">${counts[s]}</div><div class="pp">${related.length ? Math.round(counts[s]/related.length*100) : 0}%</div></div>`).join('')}
      </div>
    </div>
    <div class="profile-block">
      <h4>Манба</h4>
      <div style="font-size:12px;color:var(--muted);background:var(--surface);padding:var(--s-3);border-radius:var(--r-sm)">${kpi.source || '—'}</div>
    </div>
    <div class="profile-block">
      <h4>Боғлиқ топшириқлар (юқори 5)</h4>
      ${related.slice(0,5).map(t => `
        <div class="task-card" style="margin-bottom:8px">
          <div><span class="task-id">${t.id}</span><span class="task-sector">${t.sector}</span></div>
          <div class="task-title">${t.title}</div>
          <div class="task-foot">
            <span class="exec"><span class="av">${initials(t.executor)}</span><span class="nm">${t.executor}</span></span>
            <span class="due">${t.due}</span>
          </div>
        </div>
      `).join('') || '<p style="color:var(--muted);font-size:12px">Боғлиқ топшириқ топилмади</p>'}
    </div>
  `;
  openDrawer();
}

// ----- Map (KPI page: districts by industry growth) -----
function renderKpiMap() {
  const svg = $('#kpiMap');
  const distNames = PAYLOAD.districts.map(d => d.name).slice(0, 16);
  const values = PAYLOAD.districts.map(d => d.industry_pct ? (d.industry_pct - 100) : null);
  let s = '';
  for (let i=0; i<DIST_LAYOUT.length && i<distNames.length; i++) {
    const {cx, cy} = DIST_LAYOUT[i];
    const v = values[i];
    const fill = colorFor(v, [0,5,8,12,18,30]);
    const isLight = !v || v < 12;
    const textColor = isLight ? '#0a3a8c' : '#fff';
    s += `<g><path d="${hexPath(cx,cy)}" fill="${fill}" data-i="${i}"></path>`;
    s += `<text x="${cx}" y="${cy-3}" text-anchor="middle" font-size="9" font-weight="600" fill="${textColor}" pointer-events="none">${shortName(distNames[i])}</text>`;
    s += `<text x="${cx}" y="${cy+9}" text-anchor="middle" font-size="10" font-weight="700" fill="${textColor}" pointer-events="none">${v != null ? '+' + v.toFixed(1) + '%' : '—'}</text></g>`;
  }
  svg.innerHTML = s;
  $$('path', svg).forEach(p => {
    p.addEventListener('click', () => {
      $$('path', svg).forEach(o => o.classList.remove('selected'));
      p.classList.add('selected');
      const i = +p.dataset.i;
      $('#mapSelectedLabel').innerHTML = `Танланган: <strong style="color:var(--ink)">${distNames[i]}</strong>`;
      openDistrictDrawer(i);
    });
  });
}
function shortName(name) {
  if (!name) return '';
  return name.replace(' тумани', ' т.').replace(' шаҳри', ' ш.');
}

// ----- Comparative strip -----
function renderCompareStrip() {
  const wrap = $('#compareStrip');
  const max = Math.max(...PAYLOAD.republic.map(r => r.value));
  wrap.innerHTML = PAYLOAD.republic.map(r => {
    const isCurrent = r.name === PAYLOAD.meta.region;
    const pct = (r.value / max * 100).toFixed(0);
    return `
      <button class="region-bar ${isCurrent?'current':''}" title="${r.name}: ${r.value}%">
        <div class="bar-vis"><span style="height:${pct}%"></span></div>
        <span class="lbl">${r.abbr}</span>
        <span class="val tnum">${r.value.toFixed(1).replace('.',',')}</span>
      </button>`;
  }).join('');
}

// ----- Sources list -----
function renderSources() {
  const list = $('#sourcesList');
  const grouped = {};
  (PAYLOAD.sources || []).forEach(s => {
    if (!grouped[s.file]) grouped[s.file] = [];
    if (s.sheet) grouped[s.file].push(s.sheet);
  });
  list.innerHTML = Object.entries(grouped).map(([file, sheets]) => `
    <div style="padding:6px 0;border-bottom:1px solid var(--line)">
      <div style="font-weight:600;color:var(--ink-2);font-size:12px">${file}</div>
      <div style="font-size:11px;margin-top:2px">${sheets.length ? sheets.join(' · ') : 'ҳужжат'}</div>
    </div>
  `).join('') || '<p>Манба маълумоти йўқ</p>';
}

// ----- Districts page -----
let DIST_METRIC = 'industry';
function renderDistrictsPage() {
  renderDistMap();
  renderDistTable();
  $$('#page-districts .seg button').forEach(btn => {
    btn.addEventListener('click', () => {
      $$('#page-districts .seg button').forEach(o => o.classList.remove('active'));
      btn.classList.add('active');
      DIST_METRIC = btn.dataset.metric;
      renderDistMap();
      renderDistTable();
    });
  });
}
function valueForMetric(d) {
  if (DIST_METRIC==='industry') return d.industry_pct != null ? d.industry_pct - 100 : null;
  if (DIST_METRIC==='budget') return d.budget_exec_pct != null ? d.budget_exec_pct - 100 : null;
  if (DIST_METRIC==='investment') return d.investment_pct != null ? d.investment_pct : null;
  if (DIST_METRIC==='employment') return d.unemployment != null ? d.unemployment : null;
  return null;
}
function metricLabel() {
  return {industry:'Саноат ўсиши, %', budget:'Бюджет ижроси (планга нисбатан), %', investment:'Инвестиция ўзлаштириши (йил лимитидан), %', employment:'Ишсизлик, %'}[DIST_METRIC];
}
function renderDistMap() {
  const svg = $('#distMap');
  const dists = PAYLOAD.districts;
  let s = '';
  for (let i=0; i<DIST_LAYOUT.length && i<dists.length; i++) {
    const {cx, cy} = DIST_LAYOUT[i];
    const v = valueForMetric(dists[i]);
    let scale = [0,5,8,12,18,30];
    if (DIST_METRIC==='employment') scale = [2.5, 3, 3.5, 4, 4.5, 5];
    if (DIST_METRIC==='investment') scale = [10, 25, 40, 60, 80, 100];
    if (DIST_METRIC==='budget') scale = [-5, 0, 5, 10, 15, 25];
    const fill = colorFor(v, scale);
    s += `<g><path d="${hexPath(cx,cy)}" fill="${fill}" data-i="${i}"></path>`;
    s += `<text x="${cx}" y="${cy-3}" text-anchor="middle" font-size="9" font-weight="600" fill="#0a3a8c" pointer-events="none">${shortName(dists[i].name)}</text>`;
    s += `<text x="${cx}" y="${cy+10}" text-anchor="middle" font-size="10" font-weight="700" fill="#0a3a8c" pointer-events="none">${v != null ? v.toFixed(1) : '—'}</text></g>`;
  }
  svg.innerHTML = s;
  $$('path', svg).forEach(p => {
    p.addEventListener('click', () => openDistrictDrawer(+p.dataset.i));
  });
}
function renderDistTable() {
  const t = $('#distTable');
  const dists = PAYLOAD.districts;
  $('#distTableMeta').textContent = `${dists.length} та · кўрсаткич: ${metricLabel()}`;
  t.innerHTML = `
    <thead>
      <tr>
        <th>Туман</th>
        <th>${metricLabel()}</th>
        <th>Ҳолат</th>
        <th>Жавобгар</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    ${dists.map((d,i) => {
      const v = valueForMetric(d);
      const status = statusFromValue(v);
      return `
        <tr data-i="${i}">
          <td>
            <div class="dist-name">${d.name}</div>
            <div class="dist-meta">${d.owner || ''}</div>
          </td>
          <td>
            <div class="row" style="display:flex;align-items:center;gap:8px">
              <div class="bar"><span style="width:${barWidthFor(v)}%;background:${status==='red'?'var(--danger)':status==='amber'?'var(--warning)':'var(--accent)'}"></span></div>
              <span class="tnum" style="font-weight:700;color:var(--ink-2)">${v!=null ? v.toFixed(1) : '—'}</span>
            </div>
          </td>
          <td>${pillFor(status)}</td>
          <td style="font-size:12px;color:var(--muted)">${d.owner || '—'}</td>
          <td><button class="btn btn-ghost" aria-label="Профил">→</button></td>
        </tr>`;
    }).join('')}
    </tbody>`;
  $$('tr[data-i]', t).forEach(tr => {
    tr.addEventListener('click', () => openDistrictDrawer(+tr.dataset.i));
  });
}
function statusFromValue(v) {
  if (v == null) return 'grey';
  if (DIST_METRIC==='employment') return v < 3.5 ? 'green' : v < 4.0 ? 'amber' : 'red';
  if (DIST_METRIC==='investment') return v >= 50 ? 'green' : v >= 30 ? 'amber' : 'red';
  if (v >= 8) return 'green';
  if (v >= 0) return 'amber';
  return 'red';
}
function barWidthFor(v) {
  if (v == null) return 0;
  if (DIST_METRIC==='employment') return Math.min(100, v*15);
  if (DIST_METRIC==='investment') return Math.min(100, v);
  return Math.max(0, Math.min(100, (v + 5) * 4));
}
function pillFor(s) {
  const cls = `s-${s}`;
  const txt = {green:'Меъёрда',amber:'Огоҳ',red:'Заиф',grey:'Маълумот йўқ'}[s];
  const ic = {
    green:'<svg viewBox="0 0 10 10" fill="currentColor"><circle cx="5" cy="5" r="4"/></svg>',
    amber:'<svg viewBox="0 0 10 10" fill="currentColor"><path d="M5 1 9 8 1 8z"/></svg>',
    red:'<svg viewBox="0 0 10 10" fill="currentColor"><rect x="1" y="1" width="8" height="8" rx="1"/></svg>',
    grey:'<svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="5" cy="5" r="4"/><path d="M5 3v3M5 7.5v.1"/></svg>',
  }[s];
  return `<span class="status-pill ${cls}">${ic}${txt}</span>`;
}

// ----- District drawer -----
function openDistrictDrawer(i) {
  const d = PAYLOAD.districts[i];
  $('#drawerTitle').textContent = d.name;
  $('#drawerRole').textContent = d.owner || '—';
  $('#drawerBody').innerHTML = `
    <div class="profile-block">
      <h4>Асосий KPI</h4>
      <div class="profile-grid">
        <div class="profile-cell"><div class="pl">Саноат (I ярим)</div><div class="pv tnum">${fmt(d.industry_h1)}</div><div class="pp">${d.industry_pct?'+' + (d.industry_pct-100).toFixed(1) + '% ўсиш':'—'}</div></div>
        <div class="profile-cell"><div class="pl">Қишлоқ хўжалиги</div><div class="pv tnum">${fmt(d.agriculture_h1)}</div><div class="pp">млрд сўм</div></div>
        <div class="profile-cell"><div class="pl">Бюджет ижроси</div><div class="pv tnum">${d.budget_exec_pct?d.budget_exec_pct.toFixed(1):'—'}%</div><div class="pp">режа: ${fmt(d.budget_h1_plan)}</div></div>
        <div class="profile-cell"><div class="pl">Инвестиция</div><div class="pv tnum">${fmt(d.investment_h1)}</div><div class="pp">${d.investment_pct?d.investment_pct.toFixed(1)+'% ижро':'—'}</div></div>
        <div class="profile-cell"><div class="pl">Ишсизлик</div><div class="pv tnum">${d.unemployment?d.unemployment.toFixed(2):'—'}%</div><div class="pp">меъёр: 3,84%</div></div>
        <div class="profile-cell"><div class="pl">Камбағаллик</div><div class="pv tnum">${d.poverty?d.poverty.toFixed(2):'—'}%</div><div class="pp">ваъда: 4,50%</div></div>
      </div>
    </div>
    ${d.localization_projects ? `
      <div class="profile-block">
        <h4>Локализация драйвери</h4>
        <div class="profile-cell"><div class="pl">I ярим лойиҳалар</div><div class="pv tnum">${d.localization_projects} та</div><div class="pp">саноат буйича</div></div>
      </div>` : ''}
    ${d.debt && d.debt.length ? `
      <div class="profile-block">
        <h4>Тегишли мажбуриятлар (${d.debt.length})</h4>
        ${d.debt.slice(0,3).map(x => `<div class="profile-cell" style="margin-bottom:6px"><div class="pl">${x.indicator || x.kpi || ''}</div><div style="font-size:13px;color:var(--ink)">${x.note || x.text || ''}</div></div>`).join('')}
      </div>` : ''}
  `;
  openDrawer();
}
function fmt(v) { return v==null ? '—' : (typeof v==='number' ? v.toLocaleString('ru-RU').replace(/,/g,' ') : v); }

// ----- Tasks page -----
let TASK_FILTER = 'all';
let TASK_QUERY = '';
const STAGES = ['assigned','in_progress','done','blocked'];
const STAGE_LABELS = {assigned:'Бириктирилган', in_progress:'Жараёнда', done:'Бажарилган', blocked:'Блокда'};

function renderTasksPage() {
  // Summary
  const counts = {};
  STAGES.forEach(s => counts[s] = PAYLOAD.tasks.filter(t => t.stage===s).length);
  const total = PAYLOAD.tasks.length;
  $('#taskSummary').innerHTML = `
    <div class="tsum total"><div class="lbl">Жами топшириқлар</div><div class="val tnum">${total}</div><div class="pct">кафолат хатидан</div></div>
    <div class="tsum assigned"><div class="lbl">Бириктирилган</div><div class="val tnum">${counts.assigned}</div><div class="pct">${pct(counts.assigned,total)}%</div></div>
    <div class="tsum in_progress"><div class="lbl">Жараёнда</div><div class="val tnum">${counts.in_progress}</div><div class="pct">${pct(counts.in_progress,total)}%</div></div>
    <div class="tsum done"><div class="lbl">Бажарилган</div><div class="val tnum">${counts.done}</div><div class="pct">${pct(counts.done,total)}%</div></div>
    <div class="tsum blocked"><div class="lbl">Блокда</div><div class="val tnum">${counts.blocked}</div><div class="pct">${pct(counts.blocked,total)}%</div></div>
  `;

  $('#lbTotal').textContent = total;
  $('#lbDone').textContent = counts.done;
  $('#lbProgress').textContent = counts.in_progress;
  $('#lbBlocked').textContent = counts.blocked;

  // Filter chips by sector
  const sectors = Array.from(new Set(PAYLOAD.tasks.map(t => t.sector)));
  $('#taskFilters').innerHTML = `
    <button class="chip ${TASK_FILTER==='all'?'active':''}" data-f="all">Ҳаммаси (${total})</button>
    ${sectors.map(s => `<button class="chip ${TASK_FILTER===s?'active':''}" data-f="${s}">${s} (${PAYLOAD.tasks.filter(t=>t.sector===s).length})</button>`).join('')}
  `;
  $$('#taskFilters .chip').forEach(c => c.addEventListener('click', () => {
    TASK_FILTER = c.dataset.f;
    renderTasksPage();
  }));

  // Search
  const search = $('#taskSearch');
  if (search && search.value !== TASK_QUERY) search.value = TASK_QUERY;
  if (search && !search.dataset.bound) {
    search.addEventListener('input', e => { TASK_QUERY = e.target.value.toLowerCase(); renderTasksPage(); });
    search.dataset.bound = '1';
  }

  // Filtered set
  const filtered = PAYLOAD.tasks.filter(t => {
    if (TASK_FILTER !== 'all' && t.sector !== TASK_FILTER) return false;
    if (TASK_QUERY) {
      const hay = (t.title + ' ' + t.executor + ' ' + t.id).toLowerCase();
      if (!hay.includes(TASK_QUERY)) return false;
    }
    return true;
  });

  // Kanban
  $('#kanban').innerHTML = STAGES.map(stage => {
    const items = filtered.filter(t => t.stage===stage);
    return `
      <div class="kan-col">
        <div class="kan-head">
          <h4>${STAGE_LABELS[stage]}</h4>
          <span>${items.length}</span>
        </div>
        <div class="kan-body">
          ${items.map(t => taskCard(t)).join('') || '<p style="padding:var(--s-3);color:var(--muted-2);font-size:12px;text-align:center">Бўш</p>'}
        </div>
      </div>`;
  }).join('');

  $$('.task-card[data-id]').forEach(c => c.addEventListener('click', () => {
    const t = PAYLOAD.tasks.find(x => x.id === c.dataset.id);
    if (t) openTaskDrawer(t);
  }));
}
function pct(n, t) { return t ? Math.round(n*100/t) : 0; }
function initials(name) {
  if (!name) return '?';
  const parts = name.split(/[\s.]+/).filter(Boolean);
  return (parts[0]?.[0] || '') + (parts[parts.length-1]?.[0] || '');
}
function taskCard(t) {
  return `
    <div class="task-card ${t.stage==='blocked'?'blocked':''}" data-id="${t.id}">
      <div><span class="task-id">${t.id}</span><span class="task-sector">${t.sector}</span></div>
      <div class="task-title">${t.title}</div>
      <div class="task-foot">
        <span class="exec"><span class="av">${initials(t.executor)}</span><span class="nm">${t.executor}</span></span>
        <span class="due">${t.due}</span>
      </div>
    </div>`;
}
function openTaskDrawer(t) {
  $('#drawerTitle').textContent = t.id + ' · ' + t.sector;
  $('#drawerRole').textContent = t.period || '';
  $('#drawerBody').innerHTML = `
    <div class="profile-block">
      <h4>Топшириқ матни</h4>
      <div style="font-size:14px;line-height:1.55;color:var(--ink-2);background:var(--surface);padding:var(--s-3);border-radius:var(--r-sm)">${t.title}</div>
    </div>
    <div class="profile-block">
      <h4>Ижро ҳолати</h4>
      <div class="profile-grid">
        <div class="profile-cell"><div class="pl">Жорий босқич</div><div class="pv" style="font-size:14px">${STAGE_LABELS[t.stage] || '—'}</div></div>
        <div class="profile-cell"><div class="pl">Муддат</div><div class="pv" style="font-size:14px">${t.due || '—'}</div></div>
      </div>
    </div>
    <div class="profile-block">
      <h4>Жавобгар ва эгалари</h4>
      <div class="profile-grid">
        <div class="profile-cell"><div class="pl">Ижрочи</div><div class="pv" style="font-size:14px">${t.executor || '—'}</div><div class="pp">${t.executor_role || ''}</div></div>
        <div class="profile-cell"><div class="pl">Топширилган</div><div class="pv" style="font-size:14px">${t.owner || '—'}</div></div>
      </div>
    </div>
    <div class="profile-block">
      <h4>Манба</h4>
      <div style="font-size:12px;color:var(--muted);background:var(--surface);padding:var(--s-3);border-radius:var(--r-sm)">${t.source || '—'}</div>
    </div>
  `;
  openDrawer();
}

// ----- Drawer plumbing -----
function openDrawer() {
  $('#drawer').classList.add('open');
  $('#drawerBackdrop').classList.add('open');
  $('#drawer').setAttribute('aria-hidden','false');
}
function closeDrawer() {
  $('#drawer').classList.remove('open');
  $('#drawerBackdrop').classList.remove('open');
  $('#drawer').setAttribute('aria-hidden','true');
}
$('#drawerClose').addEventListener('click', closeDrawer);
$('#drawerBackdrop').addEventListener('click', closeDrawer);
document.addEventListener('keydown', e => { if (e.key==='Escape') closeDrawer(); });

// ----- Page navigation -----
const PAGES = ['promise','districts','tasks'];
const PAGE_TITLES = {promise:'Ваъда vs Ижро', districts:'Туманлар', tasks:'Топшириқлар'};
function goPage(page) {
  PAGES.forEach(p => $('#page-'+p).classList.toggle('hidden', p !== page));
  $$('.nav-btn[data-page]').forEach(b => b.classList.toggle('active', b.dataset.page === page));
  $('#crumb-page').textContent = PAGE_TITLES[page];
  if (page === 'districts') renderDistrictsPage();
  if (page === 'tasks') renderTasksPage();
  closeDrawer();
}
$$('.nav-btn[data-page]').forEach(b => b.addEventListener('click', () => goPage(b.dataset.page)));

// ----- Top actions -----
$('#viewLetterBtn').addEventListener('click', () => {
  const path = PAYLOAD.meta.letter_path;
  if (path) window.open('file:///' + path.replace(/^\//,''), '_blank');
});
$('#openLetter').addEventListener('click', () => $('#viewLetterBtn').click());
$('#exportBtn').addEventListener('click', () => window.print());
$('#qualityBtn').addEventListener('click', () => {
  const dq = PAYLOAD.data_quality || [];
  $('#drawerTitle').textContent = 'Маълумот сифати';
  $('#drawerRole').textContent = `${dq.length} та изоҳ`;
  $('#drawerBody').innerHTML = dq.length ? dq.map(d => `
    <div class="profile-cell" style="margin-bottom:var(--s-2);background:${d.severity==='high'?'var(--danger-soft)':d.severity==='medium'?'var(--warning-soft)':'var(--surface)'}">
      <div class="pl" style="text-transform:uppercase">${d.severity || ''} · ${d.code || ''}</div>
      <div style="font-size:13px;color:var(--ink);margin-top:4px">${d.note || d.message || ''}</div>
    </div>
  `).join('') : '<p>Изоҳ йўқ</p>';
  openDrawer();
});

// ----- Boot -----
setSideFoot();
renderKpis();
renderKpiMap();
renderCompareStrip();
renderSources();
const initialPage = (location.hash || '').replace('#','') || 'promise';
goPage(PAGES.includes(initialPage) ? initialPage : 'promise');
</script>

</body>
</html>
"""


if __name__ == "__main__":
    main()
