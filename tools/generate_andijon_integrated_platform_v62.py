from __future__ import annotations

import json
from pathlib import Path

import openpyxl


ROOT = Path(__file__).resolve().parents[1]
DATA_JSON = ROOT / "platform prototypes" / "andijon_full_pilot_assets" / "andijon_full_pilot_data.json"
OUT_HTML = ROOT / "platform prototypes" / "andijon_integrated_platform_v6_2.html"
MACRO_XLSX = ROOT / "data_source" / "Кафолат хатлар имзога" / "2. Андижон" / "1.1-1.5-жадваллар (макро).xlsx"


def norm_name(value: object) -> str:
    return " ".join(str(value or "").split()).casefold()


def num(value: object) -> float | int | None:
    if value is None or value == "":
        return None
    if isinstance(value, (int, float)):
        return int(value) if float(value).is_integer() else float(value)
    try:
        parsed = float(str(value).replace(",", "."))
    except ValueError:
        return None
    return int(parsed) if parsed.is_integer() else parsed


def enrich_industry_drivers(data: dict) -> None:
    """Attach district-level industry driver fields from macro workbook sheet 1.3."""
    if not MACRO_XLSX.exists():
        return
    wb = openpyxl.load_workbook(MACRO_XLSX, read_only=True, data_only=True)
    ws = wb["1.3. Ҳудудий саноат"]
    by_name = {norm_name(d.get("name")): d for d in data.get("districts", [])}
    totals = {
        "localization_h1_projects": 0,
        "localization_h1_value_mln": 0,
        "localization_year_projects": 0,
        "localization_year_value_mln": 0,
        "energy_electricity_h1": 0,
        "energy_gas_h1": 0,
        "energy_electricity_year": 0,
        "energy_gas_year": 0,
    }

    for row in ws.iter_rows(min_row=8, max_col=20, values_only=True):
        name = norm_name(row[1])
        district = by_name.get(name)
        if not district:
            continue
        ddata = district.setdefault("data", {})
        localization = {
            "h1_projects": num(row[10]),
            "h1_value_mln": num(row[11]),
            "year_projects": num(row[13]),
            "year_value_mln": num(row[14]),
            "unit": "та / млн сўм",
            "source": "1.1-1.5-жадваллар (макро).xlsx · 1.3. Ҳудудий саноат",
        }
        energy = {
            "electricity_h1": num(row[16]),
            "gas_h1": num(row[17]),
            "electricity_year": num(row[18]),
            "gas_year": num(row[19]),
            "unit": "млн кВт·с / млн куб метр",
            "source": "1.1-1.5-жадваллар (макро).xlsx · 1.3. Ҳудудий саноат",
        }
        ddata["localization"] = localization
        ddata["energy_efficiency"] = energy

        # Keep legacy flat keys used by earlier prototype code.
        ddata["localization_projects_h1"] = localization["h1_projects"]
        ddata["localization_projects_year"] = localization["year_projects"]
        ddata["localization_value_h1_mln"] = localization["h1_value_mln"]
        ddata["localization_value_year_mln"] = localization["year_value_mln"]
        ddata["energy_electricity_h1"] = energy["electricity_h1"]
        ddata["energy_gas_h1"] = energy["gas_h1"]
        ddata["energy_electricity_year"] = energy["electricity_year"]
        ddata["energy_gas_year"] = energy["gas_year"]

        for key, value in [
            ("localization_h1_projects", localization["h1_projects"]),
            ("localization_h1_value_mln", localization["h1_value_mln"]),
            ("localization_year_projects", localization["year_projects"]),
            ("localization_year_value_mln", localization["year_value_mln"]),
            ("energy_electricity_h1", energy["electricity_h1"]),
            ("energy_gas_h1", energy["gas_h1"]),
            ("energy_electricity_year", energy["electricity_year"]),
            ("energy_gas_year", energy["gas_year"]),
        ]:
            totals[key] += value or 0

    data.setdefault("regional", {})["industry_drivers"] = {
        **totals,
        "source": "1.1-1.5-жадваллар (макро).xlsx · 1.3. Ҳудудий саноат",
    }


def main() -> None:
    data = json.loads(DATA_JSON.read_text(encoding="utf-8"))
    enrich_industry_drivers(data)
    html = HTML.replace("__DATA__", json.dumps(data, ensure_ascii=False).replace("</", "<\\/"))
    OUT_HTML.write_text(html, encoding="utf-8")
    print(OUT_HTML)


HTML = r"""<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Андижон вилояти мониторинг платформаси · v6.2</title>
  <style>
    :root {
      --bg: #eef4fa;
      --paper: #ffffff;
      --surface: #f8fbff;
      --ink: #0b2238;
      --muted: #66778b;
      --line: #d6e1ee;
      --line-strong: #b9cada;
      --nav: #061a2d;
      --nav-2: #092542;
      --blue: #175fc1;
      --blue-2: #234d6b;
      --blue-soft: #e9f2ff;
      --green: #087b53;
      --green-soft: #e3f6ee;
      --amber: #a96400;
      --amber-soft: #fff0d1;
      --red: #bd3126;
      --red-soft: #ffe5e1;
      --grey: #667789;
      --grey-soft: #edf2f7;
      --shadow: 0 18px 50px rgba(16, 48, 82, .12);
      --nav-w: 232px;
      --r: 8px;
      font-family: "Segoe UI", Arial, sans-serif;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      color: var(--ink);
      background:
        linear-gradient(rgba(183, 204, 225, .44) 1px, transparent 1px),
        linear-gradient(90deg, rgba(183, 204, 225, .44) 1px, transparent 1px),
        var(--bg);
      background-size: 34px 34px;
      overflow-x: hidden;
    }

    button, input, select { font: inherit; }
    button { cursor: pointer; }
    :focus-visible { outline: 3px solid rgba(23, 95, 193, .35); outline-offset: 2px; }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 30;
      color: #f7fbff;
      background:
        radial-gradient(circle at 78% 0, rgba(255,255,255,.16), transparent 32%),
        linear-gradient(135deg, #224b68, #638aa2);
      box-shadow: 0 12px 30px rgba(7, 26, 45, .18);
    }

    .mast {
      min-height: 92px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
      align-items: center;
      gap: 24px;
      padding: 16px clamp(18px, 3vw, 40px) 12px;
      position: relative;
      overflow: hidden;
    }

    .mast::after {
      content: "";
      position: absolute;
      right: -24px;
      top: -42px;
      width: 430px;
      height: 145px;
      opacity: .22;
      background:
        linear-gradient(45deg, transparent 46%, rgba(255,255,255,.75) 47% 53%, transparent 54%) 0 0 / 74px 74px,
        linear-gradient(-45deg, transparent 46%, rgba(255,255,255,.75) 47% 53%, transparent 54%) 0 0 / 74px 74px;
      pointer-events: none;
    }

    .brand {
      display: grid;
      grid-template-columns: 74px minmax(0, 1fr);
      gap: 14px;
      align-items: center;
      min-width: 0;
      position: relative;
      z-index: 1;
    }

    .brand > div {
      min-width: 0;
    }

    .brand-mark {
      height: 54px;
      border: 1px solid rgba(255,255,255,.35);
      border-radius: 8px;
      display: grid;
      place-items: center;
      font-weight: 950;
      font-size: 20px;
      letter-spacing: .04em;
      background: rgba(255,255,255,.10);
    }

    .brand h1 {
      margin: 0;
      font-size: clamp(19px, 2.1vw, 27px);
      line-height: 1.08;
      letter-spacing: 0;
      overflow-wrap: anywhere;
    }

    .brand p {
      margin: 5px 0 0;
      color: rgba(247,251,255,.78);
      font-size: 13px;
      font-weight: 650;
      overflow-wrap: anywhere;
    }

    .kpi-mark {
      color: rgba(247,251,255,.72);
      font-size: clamp(42px, 5.5vw, 64px);
      font-weight: 950;
      letter-spacing: 8px;
      line-height: 1;
      position: relative;
      z-index: 1;
    }

    .year-box {
      justify-self: end;
      text-align: right;
      font-weight: 900;
      position: relative;
      z-index: 1;
    }

    .year-box strong {
      display: block;
      font-size: clamp(33px, 4.2vw, 50px);
      line-height: .95;
    }

    .year-box span {
      display: block;
      margin-top: 5px;
      color: rgba(247,251,255,.76);
      font-size: clamp(18px, 2vw, 26px);
    }

    .shell {
      display: grid;
      grid-template-columns: var(--nav-w) minmax(0, 1fr);
      min-height: calc(100vh - 92px);
    }

    .sidebar {
      position: sticky;
      top: 92px;
      height: calc(100vh - 92px);
      background: linear-gradient(180deg, var(--nav), var(--nav-2));
      color: rgba(255,255,255,.78);
      padding: 18px 12px;
      overflow: auto;
      box-shadow: 16px 0 42px rgba(7, 26, 45, .16);
    }

    .side-title {
      padding: 0 10px 14px;
      border-bottom: 1px solid rgba(255,255,255,.12);
      margin-bottom: 12px;
    }

    .side-title strong {
      display: block;
      color: #fff;
      font-size: 15px;
    }

    .side-title span {
      display: block;
      margin-top: 5px;
      font-size: 12px;
      line-height: 1.3;
      color: rgba(255,255,255,.58);
    }

    .nav-btn {
      width: 100%;
      min-height: 46px;
      border: 0;
      border-radius: 8px;
      padding: 10px 12px;
      margin-bottom: 6px;
      color: rgba(255,255,255,.76);
      background: transparent;
      display: grid;
      grid-template-columns: 26px minmax(0, 1fr);
      align-items: center;
      gap: 8px;
      text-align: left;
      font-weight: 800;
    }

    .nav-btn svg {
      width: 20px;
      height: 20px;
      stroke-width: 2.2;
    }

    .nav-btn.active,
    .nav-btn:hover {
      color: #fff;
      background: rgba(255,255,255,.12);
    }

    .main {
      min-width: 0;
      padding: 20px clamp(16px, 2.4vw, 34px) 34px;
    }

    .page-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 16px;
    }

    .eyebrow {
      color: var(--blue);
      font-size: 12px;
      font-weight: 950;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    h2, h3, p { margin: 0; letter-spacing: 0; }
    .page-head h2 { margin-top: 4px; font-size: clamp(22px, 2.2vw, 30px); line-height: 1.08; }
    .page-head p { margin-top: 6px; color: var(--muted); font-size: 14px; }

    .toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      flex-wrap: wrap;
    }

    .segmented {
      display: inline-flex;
      gap: 4px;
      padding: 4px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 999px;
      box-shadow: 0 10px 24px rgba(16, 48, 82, .08);
    }

    .segmented button {
      min-height: 44px;
      border: 0;
      border-radius: 999px;
      padding: 9px 14px;
      color: var(--muted);
      background: transparent;
      font-size: 12px;
      font-weight: 900;
      white-space: nowrap;
    }

    .segmented button.active {
      color: #fff;
      background: var(--blue);
    }

    input, select {
      min-height: 44px;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      background: #fff;
      color: var(--ink);
      outline: none;
    }

    .front-kpis {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      background: rgba(255,255,255,.98);
      border: 1px solid var(--line);
      box-shadow: var(--shadow);
      border-radius: var(--r);
      overflow: hidden;
      margin-bottom: 16px;
    }

    .front-kpi {
      border: 0;
      border-right: 1px solid var(--line);
      background: transparent;
      min-height: 154px;
      padding: 15px clamp(10px, 1.35vw, 18px);
      display: grid;
      grid-template-columns: 48px minmax(0, 1fr);
      gap: 12px;
      align-items: center;
      text-align: left;
      color: inherit;
    }

    .front-kpi:nth-child(4n) { border-right: 0; }
    .front-kpi:last-child { border-right: 0; }
    .front-kpi:nth-child(n+5) { border-top: 1px solid var(--line); }
    .front-kpi.active,
    .front-kpi:hover { background: #f5f9ff; box-shadow: inset 0 -4px 0 var(--blue); }

    .yhm-focus-bar {
      margin-bottom: 16px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: center;
      border: 1px solid var(--line);
      border-radius: var(--r);
      background: #fff;
      box-shadow: var(--shadow);
      padding: 14px 16px;
    }

    .yhm-focus-bar strong {
      display: block;
      color: var(--blue);
      font-size: clamp(28px, 3vw, 42px);
      line-height: 1;
    }

    .yhm-focus-bar span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 13px;
      font-weight: 850;
    }

    .command-summary {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin: 0 0 16px;
    }

    .command-card {
      border: 1px solid var(--line);
      border-radius: var(--r);
      background: rgba(255,255,255,.96);
      padding: 12px;
      min-width: 0;
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
    }

    .command-card span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .command-card strong {
      display: block;
      margin-top: 5px;
      color: var(--ink);
      font-size: 22px;
      line-height: 1;
    }

    .command-card small {
      display: block;
      margin-top: 6px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
    }

    .kpi-route {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
      gap: 10px;
      align-items: center;
      padding: 12px 16px;
      border-bottom: 1px solid var(--line);
      background: #fff;
    }

    .route-cell {
      min-width: 0;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px 12px;
      background: #fbfdff;
    }

    .route-cell span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .route-cell strong {
      display: block;
      margin-top: 4px;
      color: var(--ink);
      font-size: 15px;
      line-height: 1.2;
      overflow: hidden;
      overflow-wrap: anywhere;
    }

    .route-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .kpi-icon {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      color: #fff;
      background: linear-gradient(145deg, #173f9c, #347cef);
      box-shadow: 0 9px 18px rgba(23, 95, 193, .28);
    }

    .kpi-icon svg { width: 25px; height: 25px; stroke-width: 2.2; }
    .front-kpi h3 {
      color: #68788c;
      font-size: 12px;
      line-height: 1.15;
      font-weight: 950;
      text-transform: uppercase;
    }

    .front-kpi .big {
      display: block;
      margin-top: 3px;
      color: #1d55ca;
      font-size: clamp(24px, 2vw, 31px);
      line-height: 1;
      font-weight: 950;
      letter-spacing: 0;
    }

    .mini-row {
      margin-top: 8px;
      display: grid;
      gap: 3px;
      color: var(--muted);
      font-size: 10.5px;
      line-height: 1.15;
    }

    .mini-row span {
      min-width: 0;
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 5px;
      align-items: baseline;
    }

    .mini-row b {
      display: block;
      color: var(--ink);
      font-size: 11px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .grid-3 {
      display: grid;
      grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr) minmax(320px, .7fr);
      gap: 16px;
      align-items: start;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: minmax(0, 1.25fr) minmax(360px, .75fr);
      gap: 16px;
      align-items: start;
    }

    .panel {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: var(--r);
      box-shadow: var(--shadow);
      overflow: hidden;
      min-width: 0;
    }

    .panel-head {
      min-height: 60px;
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
      background: #f8fbff;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }

    .panel-head h3 { font-size: 17px; line-height: 1.2; }
    .panel-head p { margin-top: 4px; color: var(--muted); font-size: 12px; line-height: 1.3; }
    .panel-body { padding: 14px 16px 16px; }

    .workflow {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .flow-step {
      position: relative;
      min-height: 78px;
      border: 1px solid var(--line);
      border-radius: var(--r);
      background: #fff;
      padding: 12px 14px;
      box-shadow: 0 10px 24px rgba(16, 48, 82, .08);
    }

    .flow-step::after {
      content: "";
      position: absolute;
      right: -15px;
      top: 50%;
      width: 20px;
      height: 2px;
      background: var(--line-strong);
    }

    .flow-step:last-child::after { display: none; }
    .flow-step.active { border-color: var(--blue); box-shadow: inset 4px 0 0 var(--blue), 0 10px 24px rgba(16, 48, 82, .08); }
    .flow-step span { color: var(--blue); font-size: 12px; font-weight: 950; }
    .flow-step strong { display: block; margin-top: 4px; font-size: 15px; }
    .flow-step small { display: block; margin-top: 4px; color: var(--muted); line-height: 1.25; }

    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    th, td {
      padding: 10px 10px;
      border-bottom: 1px solid var(--line);
      text-align: left;
      vertical-align: middle;
      font-size: 13px;
      line-height: 1.25;
      overflow-wrap: anywhere;
    }

    th {
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
      background: #fbfdff;
    }

    tr.clickable { cursor: pointer; }
    tr.clickable:hover { background: #f7fbff; }
    tr.active-row { background: var(--blue-soft); }

    .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .muted { color: var(--muted); }

    .chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 4px 8px;
      font-size: 11px;
      line-height: 1;
      font-weight: 950;
      white-space: nowrap;
    }

    .chip.green { color: var(--green); background: var(--green-soft); }
    .chip.amber { color: var(--amber); background: var(--amber-soft); }
    .chip.red { color: var(--red); background: var(--red-soft); }
    .chip.grey { color: var(--grey); background: var(--grey-soft); }
    .chip.blue { color: var(--blue); background: var(--blue-soft); }

    .progress {
      width: 100%;
      height: 8px;
      border-radius: 999px;
      background: #e8eef6;
      overflow: hidden;
      margin-top: 6px;
    }

    .progress i {
      display: block;
      height: 100%;
      width: var(--w, 0%);
      background: var(--c, var(--blue));
      border-radius: inherit;
    }

    .detail-hero {
      display: grid;
      gap: 10px;
    }

    .detail-main {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 12px;
      align-items: start;
    }

    .detail-main strong {
      font-size: 28px;
      color: var(--blue);
      line-height: 1;
    }

    .kv {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 8px;
      margin-top: 8px;
    }

    .kv div {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px;
      background: #fbfdff;
      min-width: 0;
    }

    .kv span { display: block; color: var(--muted); font-size: 11px; font-weight: 900; }
    .kv b { display: block; margin-top: 4px; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .district-workspace {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(330px, .38fr);
      gap: 16px;
      align-items: start;
    }

    .district-context {
      margin-bottom: 14px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: end;
      border: 1px solid var(--line);
      border-radius: var(--r);
      background: #fff;
      box-shadow: var(--shadow);
      padding: 14px 16px;
    }

    .district-context h3 {
      margin: 4px 0 0;
      font-size: clamp(22px, 2vw, 30px);
      line-height: 1.05;
    }

    .district-context p {
      margin-top: 5px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.35;
    }

    .district-context-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    .district-table-wrap {
      overflow-x: auto;
    }

    .district-table {
      min-width: 920px;
    }

    .district-table td:first-child,
    .district-table th:first-child {
      width: 19%;
    }

    .district-table .row-title strong {
      display: block;
      font-size: 13px;
      color: var(--ink);
    }

    .district-table .row-title span,
    .district-table small {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .district-table tr.active-row td:first-child {
      box-shadow: inset 4px 0 0 var(--blue);
    }

    .district-preview {
      position: sticky;
      top: 118px;
    }

    .preview-score {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: start;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #f8fbff;
    }

    .preview-score strong {
      display: block;
      color: var(--blue);
      font-size: 28px;
      line-height: 1;
    }

    .preview-score span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
    }

    .task-list, .district-list {
      display: grid;
      gap: 10px;
    }

    .task-card {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fff;
      display: grid;
      gap: 8px;
      min-width: 0;
    }

    .task-card header {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      align-items: flex-start;
    }

    .task-card strong {
      font-size: 13px;
      line-height: 1.3;
      overflow-wrap: anywhere;
    }

    .task-meta {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      color: var(--muted);
      font-size: 11px;
    }

    .district-card {
      display: grid;
      grid-template-columns: minmax(170px, .75fr) repeat(4, minmax(115px, 1fr)) 128px;
      gap: 8px;
      align-items: stretch;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px;
      background: #fff;
      cursor: pointer;
    }

    .district-card:hover,
    .district-card.active { border-color: var(--blue); background: #f7fbff; }

    .district-card.active {
      box-shadow: inset 4px 0 0 var(--blue), 0 10px 24px rgba(16, 48, 82, .07);
    }

    .district-name strong { display: block; font-size: 14px; }
    .district-name span { display: block; margin-top: 4px; color: var(--muted); font-size: 12px; }

    .metric-cell {
      border-left: 1px solid var(--line);
      padding-left: 8px;
      min-width: 0;
    }

    .metric-cell span { display: block; color: var(--muted); font-size: 11px; font-weight: 900; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .metric-cell b { display: block; margin-top: 3px; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .metric-cell small { display: block; margin-top: 2px; color: var(--muted); font-size: 11px; }

    .debt-cell {
      border-left: 1px solid var(--line);
      padding-left: 8px;
      display: grid;
      align-content: center;
      justify-items: end;
    }

    .debt-cell strong { font-size: 20px; color: var(--red); line-height: 1; }
    .debt-cell span { margin-top: 4px; color: var(--muted); font-size: 11px; text-align: right; }

    .district-controls {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(160px, .7fr) minmax(180px, .8fr);
      gap: 10px;
      margin-bottom: 16px;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.9);
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
    }

    .district-controls label {
      display: grid;
      gap: 5px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
      min-width: 0;
    }

    .district-controls select,
    .district-controls input {
      width: 100%;
      min-width: 0;
    }

    .district-preview {
      position: sticky;
      top: 112px;
    }

    .preview-score {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 10px;
      align-items: start;
      margin-bottom: 12px;
    }

    .preview-score strong {
      display: block;
      color: var(--blue);
      font-size: 30px;
      line-height: 1;
    }

    .preview-score span {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 12px;
    }

    .preview-metrics {
      display: grid;
      gap: 9px;
    }

    .preview-metric {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: start;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px 11px;
      background: #fff;
    }

    .preview-metric strong {
      display: block;
      font-size: 12px;
      line-height: 1.25;
    }

    .preview-metric small {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .preview-task-summary {
      margin-top: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fbfdff;
    }

    .preview-task-summary strong {
      display: block;
      font-size: 13px;
      line-height: 1.25;
    }

    .preview-task-summary span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .cards-3 {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }

    .context-strip {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .context-step {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 11px 12px;
      background: rgba(255,255,255,.88);
      min-width: 0;
    }

    .context-step.active {
      border-color: var(--blue);
      background: #f5f9ff;
      box-shadow: inset 4px 0 0 var(--blue);
    }

    .context-step span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .context-step strong {
      display: block;
      margin-top: 4px;
      font-size: 14px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .action-row {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      margin-top: 12px;
    }

    .mini-button {
      border: 1px solid var(--line);
      border-radius: 8px;
      min-height: 44px;
      padding: 10px 12px;
      background: #fff;
      color: var(--ink);
      font-size: 12px;
      font-weight: 900;
    }

    .mini-button.primary {
      border-color: var(--blue);
      background: var(--blue);
      color: #fff;
    }

    .kpi-monitor-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
      align-items: start;
    }

    .kpi-monitor-grid.single {
      grid-template-columns: minmax(0, 1fr);
    }

    .kpi-monitor-card {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: var(--r);
      box-shadow: var(--shadow);
      overflow: hidden;
      min-width: 0;
    }

    .kpi-monitor-head {
      min-height: 78px;
      display: grid;
      grid-template-columns: 46px minmax(0, 1fr) auto;
      gap: 12px;
      align-items: center;
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
      background: #fbfdff;
    }

    .kpi-monitor-head h3 {
      font-size: 16px;
      line-height: 1.2;
    }

    .kpi-monitor-head p {
      margin-top: 4px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.3;
    }

    .small-icon {
      width: 46px;
      height: 46px;
      border-radius: 13px;
      display: grid;
      place-items: center;
      color: #fff;
      background: linear-gradient(145deg, #173f9c, #347cef);
      box-shadow: 0 8px 18px rgba(23, 95, 193, .22);
    }

    .small-icon svg {
      width: 24px;
      height: 24px;
      stroke-width: 2.2;
    }

    .annual-plan {
      text-align: right;
      min-width: 94px;
    }

    .annual-plan span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
    }

    .annual-plan strong {
      display: block;
      margin-top: 4px;
      color: var(--blue);
      font-size: 20px;
      line-height: 1.05;
    }

    .quarter-matrix {
      padding: 12px 16px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .quarter-row {
      display: grid;
      gap: 8px;
      align-content: start;
      min-height: 132px;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      font-size: 12px;
    }

    .quarter-row.actual { border-color: #a9c8ef; background: #f7fbff; }
    .quarter-row.planned { background: #fff; }
    .quarter-row.empty { background: #f7f9fc; }

    .quarter-row h4 {
      margin: 0;
      font-size: 14px;
      line-height: 1.15;
    }

    .quarter-row .q-metrics {
      display: grid;
      gap: 5px;
    }

    .quarter-row .q-metrics span {
      display: grid;
      grid-template-columns: 64px minmax(0, 1fr);
      gap: 6px;
      color: var(--muted);
      align-items: baseline;
    }

    .quarter-row .q-metrics b {
      color: var(--ink);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .quarter-main {
      display: block;
      color: var(--blue);
      font-size: clamp(24px, 2.4vw, 34px);
      line-height: 1;
    }

    .quarter-note {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
      min-height: 16px;
    }

    .kpi-signal {
      padding: 14px 16px 4px;
      display: grid;
      grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
      gap: 12px;
      border-bottom: 1px solid var(--line);
      background: #fbfdff;
    }

    .signal-main,
    .signal-side {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 12px;
      min-width: 0;
    }

    .signal-main span,
    .signal-side span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .signal-main strong {
      display: block;
      margin-top: 6px;
      color: var(--blue);
      font-size: clamp(28px, 3vw, 42px);
      line-height: 1;
    }

    .signal-main small,
    .signal-side small {
      display: block;
      margin-top: 7px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.3;
    }

    .signal-track {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 8px;
      margin-top: 10px;
    }

    .signal-step {
      min-width: 0;
    }

    .signal-step b {
      display: block;
      color: var(--ink);
      font-size: 12px;
      line-height: 1.2;
    }

    .signal-step i {
      display: block;
      height: 7px;
      margin-top: 6px;
      border-radius: 999px;
      background: var(--grey-soft);
      overflow: hidden;
    }

    .signal-step i::after {
      content: "";
      display: block;
      width: var(--w, 0%);
      height: 100%;
      background: var(--c, var(--blue));
      border-radius: inherit;
    }

    .lagging {
      border-top: 1px solid var(--line);
      padding: 12px 16px 14px;
      background: #fbfdff;
    }

    .lagging-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }

    .lagging-title strong {
      font-size: 13px;
    }

    .lagging-list {
      display: grid;
      gap: 7px;
    }

    .lagging-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
      align-items: center;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      background: #fff;
      cursor: pointer;
    }

    .lagging-item:hover {
      border-color: var(--blue);
      background: #f7fbff;
    }

    .lagging-item strong {
      display: block;
      font-size: 12px;
      line-height: 1.2;
    }

    .lagging-item span {
      display: block;
      margin-top: 2px;
      color: var(--muted);
      font-size: 11px;
    }

    .composition {
      border-top: 1px solid var(--line);
      padding: 12px 16px 14px;
      background: #fbfdff;
    }

    .composition-grid,
    .driver-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .inflation-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .component-card,
    .driver-card,
    .food-card {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 11px 12px;
      min-width: 0;
    }

    .component-card {
      cursor: pointer;
    }

    .component-card:hover,
    .component-card.active {
      border-color: var(--blue);
      background: #f7fbff;
      box-shadow: inset 0 -3px 0 var(--blue);
    }

    .macro-composition {
      background: #f7fbff;
      padding: 16px;
    }

    .macro-composition .composition-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
    }

    .macro-composition .component-card {
      min-height: 148px;
      padding: 16px;
      display: grid;
      align-content: space-between;
    }

    .macro-composition .component-card strong {
      font-size: clamp(30px, 3vw, 46px);
      line-height: .95;
      color: #154eb5;
    }

    .macro-composition .component-card small {
      font-size: 13px;
      line-height: 1.25;
    }

    .component-card span,
    .driver-card span,
    .food-card span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 900;
    }

    .component-card strong,
    .driver-card strong,
    .food-card strong {
      display: block;
      margin-top: 5px;
      color: var(--blue);
      font-size: 18px;
      line-height: 1.08;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .component-card small,
    .driver-card small,
    .food-card small {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .drivers {
      border-top: 1px solid var(--line);
      padding: 12px 16px 14px;
      background: #fbfdff;
    }

    .small-stat {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fff;
      min-width: 0;
    }

    .small-stat span { display: block; color: var(--muted); font-size: 11px; font-weight: 900; }
    .small-stat strong { display: block; margin-top: 4px; color: var(--blue); font-size: 24px; line-height: 1; }
    .small-stat small { display: block; margin-top: 5px; color: var(--muted); font-size: 12px; }

    .task-board {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }

    .board-col {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fbfdff;
      min-height: 420px;
      overflow: hidden;
    }

    .board-col h3 {
      padding: 12px 12px 10px;
      font-size: 15px;
      border-bottom: 1px solid var(--line);
      background: #fff;
    }

    .board-col .task-list { padding: 10px; }

    .task-workspace {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(300px, .38fr);
      gap: 16px;
      align-items: start;
    }

    .task-filter {
      display: grid;
      grid-template-columns: minmax(260px, .7fr) minmax(220px, .3fr) auto;
      gap: 12px;
      align-items: end;
      margin-bottom: 16px;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.92);
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
    }

    .task-filter label {
      display: grid;
      gap: 4px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
      min-width: 0;
    }

    .task-filter select,
    .task-filter input {
      width: 100%;
      min-width: 0;
    }

    .task-summary-strip {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .task-focus {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 14px;
      box-shadow: 0 16px 34px rgba(16, 48, 82, .08);
    }

    .task-focus h3 {
      font-size: 19px;
      line-height: 1.15;
    }

    .task-focus p {
      margin-top: 7px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.4;
    }

    .task-groups {
      display: grid;
      gap: 12px;
    }

    .task-group {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fbfdff;
      overflow: hidden;
    }

    .task-group-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      padding: 12px 14px;
      border-bottom: 1px solid var(--line);
      background: #fff;
    }

    .task-group-head h3 {
      font-size: 15px;
      line-height: 1.2;
    }

    .task-group .task-list {
      padding: 12px;
    }

    .task-card.compact {
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: start;
    }

    .task-card.compact header {
      display: grid;
      gap: 6px;
    }

    .task-card.compact .task-actions {
      display: grid;
      gap: 6px;
      justify-items: end;
    }

    .profile-top {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 300px;
      gap: 16px;
      align-items: start;
      margin-bottom: 16px;
    }

    .profile-filter {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.9);
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
    }

    .profile-filter label {
      display: grid;
      gap: 4px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .profile-filter select {
      min-width: min(360px, 100%);
      text-transform: none;
      font-weight: 700;
    }

    .district-kpis {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .district-kpi {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 12px;
      min-width: 0;
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
      color: inherit;
      text-align: left;
    }

    .district-kpi.active,
    .district-kpi:hover {
      border-color: var(--blue);
      background: #f5f9ff;
    }

    .district-kpi span { display: block; color: var(--muted); font-size: 11px; font-weight: 950; text-transform: uppercase; }
    .district-kpi strong { display: block; margin-top: 5px; color: var(--blue); font-size: 21px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .district-kpi small { display: block; margin-top: 5px; color: var(--muted); font-size: 11px; line-height: 1.25; }

    .profile-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
      gap: 16px;
      align-items: start;
    }

    .profile-focus {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 16px 36px rgba(16, 48, 82, .08);
    }

    .profile-hero {
      padding: 18px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 14px;
      align-items: start;
      border-bottom: 1px solid var(--line);
      background:
        linear-gradient(135deg, rgba(34, 105, 214, .1), rgba(255,255,255,0) 58%),
        #fff;
    }

    .profile-hero h3 {
      font-size: 25px;
      line-height: 1.05;
      color: var(--ink);
    }

    .profile-hero p {
      margin-top: 7px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.4;
      max-width: 76ch;
    }

    .profile-main-value {
      text-align: right;
      min-width: 150px;
    }

    .profile-main-value strong {
      display: block;
      color: var(--blue);
      font-size: 34px;
      line-height: .95;
      white-space: nowrap;
    }

    .profile-main-value span {
      display: block;
      margin-top: 6px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 900;
      text-transform: uppercase;
    }

    .profile-metrics {
      padding: 14px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .profile-metric {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      min-width: 0;
      background: #fbfdff;
    }

    .profile-metric span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .profile-metric strong {
      display: block;
      margin-top: 6px;
      color: var(--ink);
      font-size: 22px;
      line-height: 1.05;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .profile-metric small {
      display: block;
      margin-top: 6px;
      min-height: 28px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .profile-actions {
      display: grid;
      gap: 10px;
    }

    .profile-side-stat {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: center;
      padding: 11px 0;
      border-bottom: 1px solid var(--line);
    }

    .profile-side-stat:last-child { border-bottom: 0; }
    .profile-side-stat span { display: block; color: var(--muted); font-size: 12px; }
    .profile-side-stat strong { color: var(--ink); font-size: 16px; }

    .profile-secondary {
      margin-top: 16px;
    }

    .profile-secondary .district-kpis {
      grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .link-grid {
      display: grid;
      grid-template-columns: minmax(210px, .8fr) minmax(0, 1.15fr) minmax(240px, .85fr);
      gap: 12px;
      align-items: stretch;
    }

    .link-box {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 13px;
      min-width: 0;
    }

    .link-box.active { border-color: var(--blue); box-shadow: inset 4px 0 0 var(--blue); }
    .link-box h3 { font-size: 15px; }
    .link-box p { margin-top: 7px; color: var(--muted); font-size: 12px; line-height: 1.35; }

    .hidden { display: none !important; }

    @media (max-width: 1180px) {
      :root { --nav-w: 86px; }
      .side-title span, .side-title strong, .nav-btn span { display: none; }
      .nav-btn { grid-template-columns: 1fr; justify-items: center; }
      .grid-3, .grid-2, .profile-top { grid-template-columns: 1fr; }
      .profile-filter { align-items: stretch; flex-direction: column; }
      .district-workspace, .district-context { grid-template-columns: 1fr; }
      .task-workspace, .task-filter, .task-summary-strip, .kpi-route { grid-template-columns: 1fr; }
      .route-actions { justify-content: flex-start; }
      .profile-grid { grid-template-columns: 1fr; }
      .profile-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .profile-secondary .district-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .district-context-actions { justify-content: flex-start; }
      .district-preview { position: static; }
      .front-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .front-kpi:nth-child(3) { border-right: 0; }
      .front-kpi:nth-child(n+4) { border-top: 1px solid var(--line); }
      .district-card { grid-template-columns: minmax(150px, .8fr) repeat(2, minmax(110px, 1fr)); }
      .debt-cell { justify-items: start; border-left: 0; border-top: 1px solid var(--line); padding: 8px 0 0; }
      .metric-cell { border-left: 0; border-top: 1px solid var(--line); padding: 8px 0 0; }
      .district-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 760px) {
      .mast { grid-template-columns: 1fr; gap: 10px; }
      .brand { grid-template-columns: 66px minmax(0, 1fr); gap: 10px; }
      .brand-mark { width: 66px; height: 50px; font-size: 17px; }
      .brand h1 { font-size: 18px; line-height: 1.12; }
      .brand p { display: none; }
      .kpi-mark { display: none; }
      .year-box { justify-self: start; text-align: left; }
      .shell { grid-template-columns: 1fr; }
      .sidebar {
        position: static;
        height: auto;
        display: flex;
        gap: 6px;
        overflow-x: auto;
        padding: 10px;
      }
      .side-title { display: none; }
      .nav-btn { min-width: 54px; margin: 0; }
      .main { padding: 14px; }
      .page-head { display: grid; }
      .toolbar { justify-content: flex-start; }
      .front-kpis, .workflow, .task-board, .cards-3, .command-summary, .context-strip, .district-controls, .link-grid, .district-kpis, .kpi-route { grid-template-columns: 1fr; }
      .profile-grid, .profile-metrics, .profile-secondary .district-kpis { grid-template-columns: 1fr; }
      .profile-hero { grid-template-columns: 1fr; }
      .profile-main-value { text-align: left; }
      .kpi-signal { grid-template-columns: 1fr; }
      .kpi-monitor-grid { grid-template-columns: 1fr; }
      .kpi-monitor-head { grid-template-columns: 46px minmax(0, 1fr); }
      .kpi-monitor-head .mini-button { grid-column: 1 / -1; justify-self: start; }
      .annual-plan { grid-column: 1 / -1; text-align: left; }
      .quarter-matrix { grid-template-columns: 1fr; }
      .quarter-row { min-height: 0; }
      .composition-grid, .driver-grid, .macro-composition .composition-grid { grid-template-columns: 1fr; }
      .front-kpi { border-right: 0; border-bottom: 1px solid var(--line); }
      .district-card { grid-template-columns: 1fr; }
      .metric-cell, .debt-cell { border-left: 0; border-top: 1px solid var(--line); padding: 8px 0 0; justify-items: start; }
      .task-card.compact { grid-template-columns: 1fr; }
      .task-card.compact .task-actions { justify-items: start; }
      table { min-width: 720px; }
      .table-scroll { overflow-x: auto; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="mast">
      <div class="brand">
        <div class="brand-mark">CERR</div>
        <div>
          <h1>Андижон вилояти мониторинг платформаси</h1>
          <p>мақсадли KPI · топшириқлар · туманлар кесимида ижро</p>
        </div>
      </div>
      <div class="kpi-mark">KPI</div>
      <div class="year-box">
        <strong>2026</strong>
        <span id="headerPeriod">Йиллик</span>
      </div>
    </div>
  </header>

  <div class="shell">
    <aside class="sidebar">
      <div class="side-title">
        <strong>Бошқарув маркази</strong>
        <span>Кўрсаткичдан топшириққа, топшириқдан туман ижросига ўтиш.</span>
      </div>
      <button class="nav-btn active" data-page="dashboard" title="KPI"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-4H4v4Z"/></svg><span>KPI</span></button>
      <button class="nav-btn" data-page="tasks" title="Топшириқлар"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 11l2 2 4-5M5 4h14v16H5z"/></svg><span>Топшириқлар</span></button>
      <button class="nav-btn" data-page="districts" title="Туманлар"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-7h6v7"/></svg><span>Туманлар</span></button>
      <button class="nav-btn" data-page="profile" title="Туман профили"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 5h16v14H4zM8 9h8M8 13h4"/></svg><span>Туман профили</span></button>
      <button class="nav-btn" data-page="report" title="Ҳисобот"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3h9l3 3v15H6zM14 3v4h4M9 13h6M9 17h6M9 9h2"/></svg><span>Ҳисобот</span></button>
    </aside>

    <main class="main">
      <section class="page-head">
        <div>
          <div class="eyebrow" id="pageEyebrow">Андижон вилояти</div>
          <h2 id="pageTitle">KPI</h2>
          <p id="pageSubtitle">Асосий кўрсаткичлар, уларга боғланган аниқ топшириқлар ва туманлар кесимида ҳолат.</p>
        </div>
        <div class="toolbar">
          <div class="segmented" id="periodTabs"></div>
          <select id="sectorFilter" aria-label="Сектор фильтри"></select>
          <input id="searchBox" type="search" placeholder="Қидириш">
        </div>
      </section>

      <section id="dashboardPage"></section>
      <section id="tasksPage" class="hidden"></section>
      <section id="districtsPage" class="hidden"></section>
      <section id="profilePage" class="hidden"></section>
      <section id="reportPage" class="hidden"></section>
    </main>
  </div>

  <script>
    const DATA = __DATA__;

    const periods = [
      { id: "h1", label: "II чорак", suffix: "ярим йил" },
      { id: "m9", label: "III чорак", suffix: "9 ой" },
      { id: "year", label: "Йиллик", suffix: "йил" }
    ];

    const kpiDefs = [
      { id: "grp", label: "ЯҲМ", short: "ЯҲМ", sector: "Макро иқтисодиёт", icon: "trend" },
      { id: "inflation", label: "Инфляция ва асосий озиқ-овқат нархлари", short: "Инфляция", sector: "Инфляция", icon: "price" },
      { id: "budget", label: "Бюджет тушумлари", short: "Бюджет", sector: "Бюджет", icon: "bank" },
      { id: "investment", label: "Хорижий инвестициялар", short: "Инвестиция", sector: "Хорижий инвестиция", icon: "rocket" },
      { id: "export", label: "Экспорт ҳажми", short: "Экспорт", sector: "Экспорт", icon: "globe" },
      { id: "unemployment", label: "Ишсизлик даражаси", short: "Ишсизлик", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "poverty", label: "Камбағаллик даражаси", short: "Камбағаллик", sector: "Бандлик ва камбағаллик", icon: "users" }
    ];

    const macroComponentDefs = [
      { id: "industry", label: "Саноат маҳсулотлари", short: "Саноат", sector: "ЯҲМ таркиби", icon: "factory", macroIndex: 1 },
      { id: "agriculture", label: "Қишлоқ хўжалиги маҳсулотлари", short: "Қишлоқ хўжалиги", sector: "ЯҲМ таркиби", icon: "trend", macroIndex: 2 },
      { id: "construction", label: "Қурилиш ишлари", short: "Қурилиш", sector: "ЯҲМ таркиби", icon: "bank", macroIndex: 3 },
      { id: "services", label: "Бозор хизматлари", short: "Хизматлар", sector: "ЯҲМ таркиби", icon: "globe", macroIndex: 4 }
    ];

    const districtOnlyDefs = [
      { id: "localization", label: "Маҳаллийлаштириш дастури", short: "Маҳаллийлаштириш", sector: "Саноат", icon: "factory" },
      { id: "energy_electricity", label: "Электр энергиясини тежаш", short: "Электр тежаш", sector: "Саноат", icon: "trend" },
      { id: "energy_gas", label: "Табиий газни тежаш", short: "Газ тежаш", sector: "Саноат", icon: "trend" },
      { id: "budget_investment", label: "Бюджет инвестициялари ўзлаштирилиши", short: "Бюджет инвест", sector: "Бюджет инвестициялари", icon: "bank" },
      { id: "jobs", label: "Доимий ишга жойлаштириш", short: "Ишга жойлаштириш", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "legalization", label: "Норасмий бандларни легаллаштириш", short: "Легаллаштириш", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "mfy_clear", label: "Камбағаллик ва ишсизликдан холи МФЙлар", short: "Холи МФЙ", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "microprojects", label: "Микролойиҳалар", short: "Микролойиҳа", sector: "Бандлик ва камбағаллик", icon: "users" }
    ];

    const state = {
      page: "dashboard",
      period: "h1",
      kpi: "export",
      district: "Асака тумани",
      sector: "all",
      search: "",
      districtSort: "attention",
      taskStatus: "open"
    };
    let renderedPage = null;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    function n(value) {
      if (value === null || value === undefined || value === "" || Number.isNaN(Number(value))) return null;
      return Number(value);
    }

    function fmt(value, digits = 1) {
      const num = n(value);
      if (num === null) return "—";
      return new Intl.NumberFormat("uz-Cyrl-UZ", { maximumFractionDigits: digits, minimumFractionDigits: num % 1 ? 1 : 0 }).format(num);
    }

    function displayValue(value, unit = "", compact = true) {
      const num = n(value);
      if (num === null) return "—";
      if (unit.includes("минг доллар")) return `${fmt(num / 1000, compact ? 1 : 2)} млн $`;
      if (unit.includes("млн доллар")) {
        if (compact && Math.abs(num) >= 1000) return `${fmt(num / 1000, 1)} млрд $`;
        return `${fmt(num, compact ? 1 : 2)} млн $`;
      }
      if (unit.includes("млрд сўм")) {
        if (compact && Math.abs(num) >= 1000) return `${fmt(num / 1000, 1)} трлн сўм`;
        return `${fmt(num, 1)} млрд сўм`;
      }
      if (unit.includes("млн сўм")) return `${fmt(num / 1000, 1)} млрд сўм`;
      if (unit.includes("%")) return `${fmt(num, 1)}%`;
      if (unit.includes("минг нафар")) return `${fmt(num, 1)} минг`;
      return `${fmt(num, compact ? 1 : 2)} ${unit}`.trim();
    }

    function planValue(row, compact = true) {
      if (!row) return "—";
      if (row.planText) return row.planText;
      const value = displayValue(row.plan, row.unit, compact);
      return row.max && value !== "—" ? `≤ ${value}` : value;
    }

    function factValue(row, compact = true) {
      if (!row) return "—";
      if (row.factText) return row.factText;
      return displayValue(row.fact, row.unit, compact);
    }

    function tinyValue(value, unit = "") {
      const num = n(value);
      if (num === null) return "—";
      if (unit.includes("минг доллар")) return `${fmt(num / 1000, 1)}м$`;
      if (unit.includes("млн доллар")) return Math.abs(num) >= 1000 ? `${fmt(num / 1000, 1)}млрд$` : `${fmt(num, 0)}м$`;
      if (unit.includes("млрд сўм")) return Math.abs(num) >= 1000 ? `${fmt(num / 1000, 1)}трлн` : `${fmt(num, 0)}млрд`;
      if (unit.includes("млн сўм")) return `${fmt(num / 1000, 1)}млрд`;
      if (unit.includes("%")) return `${fmt(num, 1)}%`;
      if (unit.includes("минг нафар")) return `${fmt(num, 1)}м`;
      return fmt(num, 1);
    }

    function growthValue(value) {
      const num = n(value);
      if (num === null) return "—";
      const delta = Math.abs(num) > 50 ? num - 100 : num;
      return `${delta >= 0 ? "+" : ""}${fmt(delta, 1)}%`;
    }

    function primaryMetric(row) {
      if (!row) return "—";
      if (row.main) return row.main;
      if (n(row.growth) !== null) return growthValue(row.growth);
      if (n(row.execution) !== null) return `${fmt(row.execution, 1)}%`;
      return planValue(row);
    }

    function actualText(row, growthOnly = false) {
      if (!row) return "Амалда: —";
      if (growthOnly && n(row.growth) !== null) return `Амалда: ${growthValue(row.growth)}`;
      if (n(row.fact) !== null) return `Амалда: ${growthOnly ? "—" : displayValue(row.fact, row.unit)}`;
      return "Амалда: —";
    }

    function q1ActualText(id) {
      const row = dashboardPeriodKpi(id, "q1");
      const growthOnly = id === "grp" || macroComponentDefs.some(item => item.id === id) || n(row.growth) !== null;
      return `I чорак ${actualText(row, growthOnly).toLowerCase()}`;
    }

    function hasPeriodValue(row) {
      return n(row.fact) !== null || n(row.plan) !== null || n(row.growth) !== null || n(row.execution) !== null || row.planText;
    }

    function periodState(def, period, row) {
      if (period === "q1" && (n(row.fact) !== null || n(row.growth) !== null)) return { cls: "actual", chip: "blue", label: "Амалда бор" };
      if (period === "q1") return { cls: "empty", chip: "grey", label: "I чорак белгиланмаган" };
      if (hasPeriodValue(row)) return { cls: "planned", chip: "grey", label: "Режа / прогноз" };
      return { cls: "empty", chip: "grey", label: "Давр белгиланмаган" };
    }

    function pct(actual, target, direction = "higher") {
      const a = n(actual), t = n(target);
      if (a === null || t === null || t === 0 || a === 0) return null;
      const value = direction === "lower" ? (t / a) * 100 : (a / t) * 100;
      return Math.round(value * 10) / 10;
    }

    function statusFor(execution) {
      const v = n(execution);
      if (v === null) return "grey";
      if (v >= 100) return "green";
      if (v >= 80) return "amber";
      return "red";
    }

    function statusLabel(status) {
      return {
        green: "Бажарилган",
        amber: "Эътибор",
        red: "Кечиккан",
        grey: "Кутилмоқда"
      }[status] || "Маълумот кутилмоқда";
    }

    function colorFor(status) {
      return { green: "var(--green)", amber: "var(--amber)", red: "var(--red)", grey: "var(--grey)" }[status] || "var(--grey)";
    }

    function icon(name) {
      const icons = {
        trend: '<path d="M4 17h16M6 14l4-5 4 3 4-7"/><path d="M16 5h4v4"/>',
        factory: '<path d="M4 20V9l5 3V9l5 3h6v8H4Z"/><path d="M8 16h1M12 16h1M16 16h1"/>',
        bank: '<path d="M4 10h16M6 10v8M10 10v8M14 10v8M18 10v8M3 20h18M12 4l8 4H4l8-4Z"/>',
        price: '<path d="M12 3v18"/><path d="M17 7.5C16.2 6 14.7 5 12.3 5H11a3 3 0 0 0 0 6h2a3 3 0 0 1 0 6h-1.3C9.3 17 7.8 16 7 14.5"/>',
        rocket: '<path d="M12 15l-3-3c1-5 4-8 10-9-1 6-4 9-9 10Z"/><path d="M9 12l-4 1-2 4 4-2 1-4M12 15l-1 4-4 2 2-4 4-1"/><circle cx="15" cy="8" r="1.5"/>',
        globe: '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2 2 3 5 3 8s-1 6-3 8M12 4c-2 2-3 5-3 8s1 6 3 8"/>',
        users: '<path d="M16 19v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="3"/><path d="M20 19v-2a3 3 0 0 0-2-2.8M16 4.2a3 3 0 0 1 0 5.6"/>'
      };
      return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor">${icons[name] || icons.trend}</svg>`;
    }

    function macroByIndex(index, period) {
      const row = DATA.regional.macro[index];
      const key = period === "year" ? "year" : period;
      const value = row?.[`${key}_value`];
      const growth = row?.[`${key}_growth`];
      const fact = null;
      const plan = value;
      return {
        label: row.indicator,
        fact,
        plan,
        unit: row.unit,
        growth,
        execution: null,
        main: growth ? growthValue(growth) : displayValue(value, row.unit),
        status: "grey",
        note: `${periodLabel()} режа ва ўсиш`
      };
    }

    function inflationPeriodKpi(period) {
      const map = {
        q1: { plan: null, note: "I чорак учун алоҳида чегара кафолат хатида белгиланмаган" },
        h1: { plan: 2.9, note: "II чорак якуни билан 2,9 фоиздан ошмаслик" },
        m9: { plan: null, note: "III чорак учун алоҳида чегара кафолат хатида белгиланмаган" },
        year: { plan: 6.6, note: "йил якуни билан 6,6 фоиздан ошмаслик" }
      };
      const row = map[period] || map.year;
      const planText = row.plan === null ? "—" : `≤ ${fmt(row.plan, 1)}%`;
      return {
        label: "Инфляция даражаси",
        fact: null,
        plan: row.plan,
        planText,
        unit: "%",
        growth: null,
        execution: null,
        main: period === "h1" ? "≤2,9%" : period === "year" ? "≤6,6%" : planText,
        max: true,
        status: "grey",
        note: row.note
      };
    }

    function regionalKpi(id, period = state.period) {
      if (id === "grp") return macroByIndex(0, period);
      if (id === "industry") return macroByIndex(1, period);
      if (id === "agriculture") return macroByIndex(2, period);
      if (id === "construction") return macroByIndex(3, period);
      if (id === "services") return macroByIndex(4, period);
      if (id === "inflation") return inflationPeriodKpi(period);
      if (id === "budget") {
        const b = DATA.regional.budget;
        const map = {
          year: [null, b.year_plan],
          h1: [null, b.h1_plan],
          m9: [null, null]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Бюджет тушумлари", fact, plan, unit: b.unit, growth: null, execution, main: execution ? `${fmt(execution, 1)}%` : displayValue(fact ?? plan, b.unit), status: statusFor(execution), note: "режа" };
      }
      if (id === "investment") {
        const x = DATA.regional.foreign_investment;
        const map = {
          year: [null, x.year_forecast],
          h1: [null, x.h1_plan],
          m9: [null, null]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Хорижий инвестициялар", fact, plan, unit: x.unit, growth: null, execution, main: displayValue(fact ?? plan, x.unit), status: statusFor(execution), note: `${x.h1_projects} та лойиҳа, ${fmt(x.h1_jobs, 0)} иш ўрни` };
      }
      if (id === "export") {
        const e = DATA.regional.export;
        const map = {
          year: [null, e.year_forecast, e.year_growth],
          h1: [null, e.h1_expected, e.h1_growth],
          m9: [null, null, null]
        };
        const [fact, plan, growth] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Экспорт ҳажми", fact, plan, unit: e.unit, growth, execution, main: growth ? growthValue(growth) : displayValue(fact, e.unit), status: execution ? statusFor(execution) : "grey", note: `${e.year_exporters} экспортёр режада` };
      }
      if (id === "unemployment") {
        const emp = DATA.regional.employment;
        const target = period === "year" ? emp.unemployment_year : period === "h1" ? emp.unemployment_h1 : null;
        return { label: "Ишсизлик даражаси", fact: null, plan: target, unit: "%", growth: null, execution: null, main: target ? `${fmt(target, 1)}%` : "—", status: "grey", note: "мақсадли кўрсаткич" };
      }
      if (id === "poverty") {
        const emp = DATA.regional.employment;
        const target = period === "year" ? emp.poverty_year : period === "h1" ? emp.poverty_h1 : null;
        return { label: "Камбағаллик даражаси", fact: null, plan: target, unit: "%", growth: null, execution: null, main: target ? `${fmt(target, 1)}%` : "—", status: "grey", note: `${fmt(emp.mfy_h1, 0)} та МФЙ H1, ${fmt(emp.mfy_year, 0)} та йиллик` };
      }
      return regionalKpi("export", period);
    }

    function dashboardPeriodKpi(id, period) {
      const macroIndexes = { grp: 0, industry: 1, agriculture: 2, construction: 3, services: 4 };
      if (id in macroIndexes) {
        const index = macroIndexes[id];
        const row = DATA.regional.macro[index];
        const key = period === "year" ? "year" : period;
        const value = row?.[`${key}_value`];
        const growth = row?.[`${key}_growth`];
        return {
          fact: period === "q1" ? value : null,
          plan: period === "q1" ? null : value,
          unit: row.unit,
          growth,
          execution: null,
          status: "grey"
        };
      }
      if (id === "inflation") return inflationPeriodKpi(period);
      if (id === "budget") {
        const b = DATA.regional.budget;
        const map = {
          q1: [null, null],
          h1: [null, b.h1_plan],
          m9: [null, null],
          year: [null, b.year_plan]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { fact, plan, unit: b.unit, growth: null, execution, status: statusFor(execution) };
      }
      if (id === "investment") {
        const x = DATA.regional.foreign_investment;
        const map = {
          q1: [x.q1_actual, x.q1_plan],
          h1: [null, x.h1_plan],
          m9: [null, null],
          year: [null, x.year_forecast]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { fact, plan, unit: x.unit, growth: null, execution, status: statusFor(execution) };
      }
      if (id === "export") {
        const e = DATA.regional.export;
        const map = {
          q1: [e.q1_value, null, e.q1_growth],
          h1: [null, e.h1_expected, e.h1_growth],
          m9: [null, null, null],
          year: [null, e.year_forecast, e.year_growth]
        };
        const [fact, plan, growth] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { fact, plan, unit: e.unit, growth, execution, status: statusFor(execution) };
      }
      if (id === "unemployment") {
        const emp = DATA.regional.employment;
        const map = {
          q1: [null, null],
          h1: [null, emp.unemployment_h1],
          m9: [null, null],
          year: [null, emp.unemployment_year]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan, "lower");
        return { fact, plan, unit: "%", growth: null, execution, status: statusFor(execution) };
      }
      if (id === "poverty") {
        const emp = DATA.regional.employment;
        const map = {
          q1: [null, null],
          h1: [null, emp.poverty_h1],
          m9: [null, null],
          year: [null, emp.poverty_year]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan, "lower");
        return { fact, plan, unit: "%", growth: null, execution, status: statusFor(execution) };
      }
      return dashboardPeriodKpi("export", period);
    }

    function annualPlanKpi(id) {
      return dashboardPeriodKpi(id, "year");
    }

    function laggingDistrictsFor(kpiId) {
      if (kpiId === "grp" || kpiId === "construction") return [];
      if (kpiId === "poverty") {
        return [...DATA.districts]
          .map(d => ({ d, k: districtKpi(d, kpiId, "h1") }))
          .sort((a, b) => (n(b.k.plan) || 0) - (n(a.k.plan) || 0))
          .slice(0, 3);
      }
      return [...DATA.districts]
        .map(d => ({ d, k: districtKpi(d, kpiId, "h1") }))
        .sort((a, b) => {
          const ax = n(a.k.execution);
          const bx = n(b.k.execution);
          if (ax !== null && bx !== null) return ax - bx;
          if (ax !== null) return -1;
          if (bx !== null) return 1;
          return (b.d.debt?.task_unfinished || 0) - (a.d.debt?.task_unfinished || 0);
        })
        .slice(0, 3);
    }

    function districtKpi(d, id, period = state.period) {
      const data = d.data || {};
      const key = period === "year" ? "year" : period;
      if (id === "grp") {
        return { label: "Таркибий кўрсаткичлар", fact: null, plan: null, unit: "", growth: null, execution: null, main: "Таркиб", note: "ЯҲМ туман кесимида берилмаган" };
      }
      if (id === "inflation") {
        const w = data.warehouses || {};
        const capacity = (n(w.reserve_capacity_t) || 0) + (n(w.cold_storage_capacity_t) || 0);
        const count = (n(w.reserve_warehouses) || 0) + (n(w.cold_storage_count) || 0);
        const newCount = (n(w.new_small_cold_storage_count) || 0) + (n(w.new_large_cold_storage_count) || 0);
        return { label: "Озиқ-овқат омборлари", fact: null, plan: capacity || null, unit: "тонна", growth: null, execution: null, main: capacity ? `${fmt(capacity, 0)} т` : "—", note: `${fmt(count, 0)} та омбор · ${fmt(newCount, 0)} та янги режа` };
      }
      if (id === "industry") {
        const row = data.industry;
        const value = row?.[`${key}_value`];
        const growth = row?.[`${key}_growth`];
        return { label: "Саноат", fact: period === "q1" ? value : null, plan: period === "q1" ? null : value, unit: row?.unit || "млрд сўм", growth, execution: null, main: growth ? growthValue(growth) : displayValue(value, row?.unit) };
      }
      if (id === "localization") {
        const row = data.localization || {};
        const projects = period === "year" ? row.year_projects : period === "h1" ? row.h1_projects : null;
        const value = period === "year" ? row.year_value_mln : period === "h1" ? row.h1_value_mln : null;
        return { label: "Маҳаллийлаштириш", fact: null, plan: projects, unit: "та", execution: null, main: displayValue(projects, "та"), note: value ? `қиймати ${displayValue(value, "млн сўм")}` : "1.3-жадвал" };
      }
      if (id === "energy_electricity") {
        const row = data.energy_efficiency || {};
        const value = period === "year" ? row.electricity_year : period === "h1" ? row.electricity_h1 : null;
        return { label: "Электр тежаш", fact: null, plan: value, unit: "млн кВт·с", execution: null, main: displayValue(value, "млн кВт·с"), note: "энергия самарадорлиги" };
      }
      if (id === "energy_gas") {
        const row = data.energy_efficiency || {};
        const value = period === "year" ? row.gas_year : period === "h1" ? row.gas_h1 : null;
        return { label: "Газ тежаш", fact: null, plan: value, unit: "млн куб метр", execution: null, main: displayValue(value, "млн куб метр"), note: "энергия самарадорлиги" };
      }
      if (id === "agriculture") {
        const row = data.agriculture;
        const value = row?.[`${key}_value`];
        const growth = row?.[`${key}_growth`];
        return { label: "Қишлоқ хўжалиги", fact: period === "q1" ? value : null, plan: period === "q1" ? null : value, unit: row?.unit || "млрд сўм", growth, execution: null, main: growth ? growthValue(growth) : displayValue(value, row?.unit) };
      }
      if (id === "services") {
        const row = data.services;
        const value = row?.[`${key}_value`];
        const growth = row?.[`${key}_growth`];
        return { label: "Хизматлар", fact: period === "q1" ? value : null, plan: period === "q1" ? null : value, unit: row?.unit || "млрд сўм", growth, execution: null, main: growth ? growthValue(growth) : displayValue(value, row?.unit) };
      }
      if (id === "construction") {
        return { label: "Қурилиш", fact: null, plan: null, unit: "млрд сўм", growth: null, execution: null, main: "—" };
      }
      if (id === "budget") {
        const row = data.budget || {};
        const map = { q2: [row.q2_expected, row.q2_plan], year: [row.year_expected, row.year_plan], h1: [row.h1_expected, row.h1_plan], m9: [null, null], q1: [null, null] };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan) || (period === "h1" ? row.h1_execution_pct : period === "q2" ? row.q2_execution_pct : null);
        return { label: "Бюджет", fact, plan, unit: row.unit || "млрд сўм", execution, main: execution ? `${fmt(execution)}%` : displayValue(fact ?? plan, row.unit) };
      }
      if (id === "budget_investment") {
        const row = data.budget_investment || {};
        const map = { q1: [row.q1_absorption, row.limit, row.q1_pct], h1: [row.h1_absorption, row.limit, row.h1_pct], year: [row.year_absorption, row.limit, row.year_pct] };
        const [fact, plan, execution] = map[period] || map.year;
        return { label: "Бюджет инвестициялари", fact, plan, unit: row.unit || "млн сўм", execution, main: execution ? `${fmt(execution)}%` : displayValue(fact, row.unit || "млн сўм"), note: `${fmt(row.objects, 0)} та объект · лимит ${displayValue(row.limit, row.unit || "млн сўм")}` };
      }
      if (id === "investment") {
        const row = data.foreign_investment || {};
        const map = { year: [row.year_expected, row.year_forecast], h1: [row.h1_expected, row.h1_plan], q1: [row.q1_actual, row.q1_plan], m9: [null, null] };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Инвестиция", fact, plan, unit: row.unit || "млн доллар", execution, main: displayValue(fact, row.unit || "млн доллар") };
      }
      if (id === "export") {
        const row = data.export || {};
        const map = { year: [row.year_expected, row.year_forecast, row.year_growth], h1: [row.h1_expected, row.h1_expected, row.h1_growth], q1: [row.q1_value, null, row.q1_growth], m9: [null, null, null] };
        const [fact, plan, growth] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Экспорт", fact, plan, unit: row.unit || "минг доллар", growth, execution, main: growth ? growthValue(growth) : displayValue(fact, row.unit || "минг доллар") };
      }
      if (id === "unemployment") {
        const row = data.employment || {};
        const target = period === "year" ? row.unemployment_year : period === "h1" ? row.unemployment_h1 : null;
        return { label: "Ишсизлик", fact: null, plan: target, unit: "%", execution: null, main: target ? `${fmt(target, 1)}%` : "—" };
      }
      if (id === "poverty") {
        const row = data.employment || {};
        const target = period === "year" ? row.poverty_year : period === "h1" ? row.poverty_h1 : null;
        const numeric = n(target);
        return { label: "Камбағаллик", fact: null, plan: numeric, planText: numeric === null && target ? String(target) : null, unit: "%", execution: null, main: numeric !== null ? `${fmt(numeric, 1)}%` : target || "—" };
      }
      if (id === "jobs") {
        const row = data.employment || {};
        const target = period === "year" ? row.jobs_year : period === "h1" ? row.jobs_h1 : null;
        return { label: "Бандлик", fact: null, plan: target, unit: "минг нафар", execution: null, main: displayValue(target, "минг нафар") };
      }
      if (id === "legalization") {
        const row = data.employment || {};
        const target = period === "year" ? row.legalization_year : period === "h1" ? row.legalization_h1 : null;
        return { label: "Легаллаштириш", fact: null, plan: target, unit: "минг нафар", execution: null, main: displayValue(target, "минг нафар") };
      }
      if (id === "mfy_clear") {
        const row = data.employment || {};
        const target = period === "year" ? row.mfy_year : period === "h1" ? row.mfy_h1 : null;
        return { label: "Холи МФЙ", fact: null, plan: target, unit: "та", execution: null, main: displayValue(target, "та") };
      }
      if (id === "microprojects") {
        const row = data.employment || {};
        const target = period === "year" ? row.microprojects_year : period === "h1" ? row.microprojects_h1 : null;
        return { label: "Микролойиҳа", fact: null, plan: target, unit: "та", execution: null, main: displayValue(target, "та") };
      }
      return districtKpi(d, "export", period);
    }

    function periodLabel() {
      return periods.find(p => p.id === state.period)?.label || "Йиллик";
    }

    function currentKpiDef() {
      return [...kpiDefs, ...macroComponentDefs, ...districtOnlyDefs].find(k => k.id === state.kpi) || kpiDefs[3];
    }

    function districtSelectorDefs() {
      return [
        { ...kpiDefs.find(k => k.id === "grp"), label: "ЯҲМ таркиби", short: "ЯҲМ таркиби" },
        ...macroComponentDefs.filter(k => k.id !== "construction"),
        kpiDefs.find(k => k.id === "inflation"),
        kpiDefs.find(k => k.id === "budget"),
        kpiDefs.find(k => k.id === "investment"),
        kpiDefs.find(k => k.id === "export"),
        kpiDefs.find(k => k.id === "unemployment"),
        kpiDefs.find(k => k.id === "poverty"),
        ...districtOnlyDefs
      ].filter(Boolean);
    }

    function currentDistrict() {
      return DATA.districts.find(d => d.name === state.district) || DATA.districts[0];
    }

    function contextStrip(active = "kpi") {
      const kpi = currentKpiDef();
      const districtLabel = kpi.id === "grp" ? "ЯҲМ таркиби" : kpi.short;
      return `<div class="context-strip" aria-label="KPI workflow">
        <div class="context-step ${active === "kpi" ? "active" : ""}"><span>1. KPI</span><strong>${kpi.short}</strong></div>
        <div class="context-step ${active === "districts" ? "active" : ""}"><span>2. Туманлар кесими</span><strong>${districtLabel} бўйича 16 ҳудуд</strong></div>
        <div class="context-step ${active === "profile" ? "active" : ""}"><span>3. Туман профили</span><strong>${state.district}</strong></div>
      </div>`;
    }

    function valueLine(row) {
      const fact = factValue(row);
      const plan = planValue(row);
      const growth = row.growth ? growthValue(row.growth) : "—";
      const execution = row.execution ? `${fmt(row.execution, 1)}%` : "—";
      return { fact, plan, growth, execution };
    }

    function districtPeriodRows(d, kpiId) {
      return [
        ["I чорак", "q1"],
        ["II чорак", "h1"],
        ["III чорак", "m9"],
        ["Йиллик", "year"]
      ].map(([label, period]) => ({ label, period, row: districtKpi(d, kpiId, period) }));
    }

    function barValue(row, kpiId) {
      if (n(row.execution) !== null) return Math.min(100, Math.max(0, n(row.execution)));
      if (n(row.growth) !== null) return Math.min(100, Math.max(0, n(row.growth) - 80) / 40 * 100);
      if (kpiId === "inflation" && n(row.plan) !== null) return Math.min(100, n(row.plan) / 20000 * 100);
      return 0;
    }

    function rowStatus(row) {
      if (n(row.execution) !== null) return statusFor(row.execution);
      if (n(row.growth) !== null) {
        if (row.growth >= 105) return "green";
        if (row.growth >= 100) return "amber";
        return "red";
      }
      return "grey";
    }

    function districtPrimaryLabel(kpiId) {
      if (kpiId === "grp") return "Таркиб";
      if (["industry", "agriculture", "services"].includes(kpiId)) return "Ўсиш";
      if (kpiId === "inflation") return "Омбор сиғими";
      if (kpiId === "localization") return "Лойиҳа";
      if (["energy_electricity", "energy_gas"].includes(kpiId)) return "Тежаш";
      if (kpiId === "unemployment") return "Мақсад";
      if (["jobs", "legalization"].includes(kpiId)) return "Минг нафар";
      if (["mfy_clear", "microprojects"].includes(kpiId)) return "Мақсад";
      return "Ижро";
    }

    function districtPrimaryValue(row, kpiId) {
      if (kpiId === "grp") return "Саноат / ҚХ / Хизматлар";
      if (["industry", "agriculture", "services", "export"].includes(kpiId) && row.growth) return growthValue(row.growth);
      if (row.execution) return `${fmt(row.execution, 1)}%`;
      if (kpiId === "inflation") return row.main || planValue(row);
      return row.main || factValue(row) || planValue(row);
    }

    function districtAllMetrics(d) {
      const defs = [
        { id: "industry", label: "Саноат" },
        { id: "localization", label: "Маҳаллийлаштириш" },
        { id: "energy_electricity", label: "Электр тежаш" },
        { id: "energy_gas", label: "Газ тежаш" },
        { id: "agriculture", label: "Қишлоқ хўжалиги" },
        { id: "services", label: "Хизматлар" },
        { id: "budget", label: "Бюджет" },
        { id: "budget_investment", label: "Бюджет инвест" },
        { id: "investment", label: "Инвестиция" },
        { id: "export", label: "Экспорт" },
        { id: "unemployment", label: "Ишсизлик" },
        { id: "poverty", label: "Камбағаллик" },
        { id: "jobs", label: "Бандлик" },
        { id: "legalization", label: "Легаллаштириш" },
        { id: "mfy_clear", label: "Холи МФЙ" },
        { id: "microprojects", label: "Микролойиҳа" },
        { id: "inflation", label: "Омборлар" }
      ];
      return defs.map(def => ({ def, row: districtKpi(d, def.id, state.period) }));
    }

    function filteredTasks() {
      const kpi = currentKpiDef();
      const macroTaskKpis = ["grp", "industry", "agriculture", "construction", "services", "localization", "energy_electricity", "energy_gas", "inflation"];
      const defaultSector = macroTaskKpis.includes(kpi.id) ? "Макро иқтисодиёт" : kpi.sector;
      const sector = state.sector === "all" ? defaultSector : state.sector;
      const q = state.search.trim().toLowerCase();
      return DATA.tasks.filter(t => {
        const sectorOk = sector === "all" || t.sector === sector;
        const qOk = !q || `${t.title} ${t.sector} ${t.owner}`.toLowerCase().includes(q);
        const specificOk = isSpecificTaskForKpi(t, kpi.id);
        return sectorOk && qOk && specificOk;
      });
    }

    function isSpecificTaskForKpi(task, kpiId) {
      const title = cleanTaskTitle(task.title);
      const lower = title.toLowerCase();
      if (lower.includes("ялпи ҳудудий маҳсулотнинг 7,2") || title === "1. Саноат маҳсулотларини ишлаб чиқариш.") {
        return false;
      }
      if (kpiId === "localization") {
        return /маҳаллийлаштириш|локализация|маҳаллий контент/i.test(title);
      }
      if (kpiId === "energy_electricity") {
        return /энергия|электр/i.test(title);
      }
      if (kpiId === "energy_gas") {
        return /энергия|газ/i.test(title);
      }
      if (kpiId === "industry") {
        return /саноат|ишлаб чиқариш|корхона|локализация|энергия|электр|газ|Хонобод|Шаҳрихон/i.test(title);
      }
      if (kpiId === "inflation") {
        return /инфляц|нарх|озиқ|овқат|омбор|захира|бозор/i.test(title);
      }
      if (kpiId === "poverty") {
        return /камбағал|холи|оила|индивидуал|хизмат|кредит|субсид|тадбиркор|касб|оғир|маҳалла|инфратузилма/i.test(title);
      }
      if (kpiId === "mfy_clear") {
        return /холи|мфй|маҳалла|камбағал|ишсиз/i.test(title);
      }
      if (kpiId === "microprojects") {
        return /микролойиҳа|тадбиркор|кредит|субсид|камбағал/i.test(title);
      }
      if (kpiId === "legalization") {
        return /легаллаш|норасмий|банд/i.test(title);
      }
      if (kpiId === "budget_investment") {
        return /бюджет|инвест|объект|ўзлаштир|қурилиш/i.test(title);
      }
      if (kpiId === "unemployment" || kpiId === "jobs" || kpiId === "legalization" || kpiId === "mfy_clear" || kpiId === "microprojects") {
        return /ишсиз|банд|ишга жойлаш|легаллаш|тадбиркор|субъект|микролойиҳа/i.test(title) && !/камбағаллик даражаси|камбағал оила/i.test(title);
      }
      return true;
    }

    function filteredDistricts() {
      const q = state.search.trim().toLowerCase();
      return DATA.districts.filter(d => !q || d.name.toLowerCase().includes(q) || d.owner.toLowerCase().includes(q));
    }

    function renderPeriodTabs() {
      $("#periodTabs").innerHTML = periods.map(p => `<button class="${p.id === state.period ? "active" : ""}" data-period="${p.id}">${p.label}</button>`).join("");
      $("#headerPeriod").textContent = periodLabel();
      $$("#periodTabs button").forEach(btn => btn.addEventListener("click", () => {
        state.period = btn.dataset.period;
        render();
      }));
    }

    function renderSectorFilter() {
      const sectors = ["all", ...new Set(DATA.tasks.map(t => t.sector))];
      $("#sectorFilter").innerHTML = sectors.map(s => `<option value="${s}" ${state.sector === s ? "selected" : ""}>${s === "all" ? "Барча секторлар" : s}</option>`).join("");
    }

    function kpiCard(def) {
      const row = annualPlanKpi(def.id);
      const q1Text = q1ActualText(def.id);
      const active = def.id === state.kpi || (def.id === "grp" && macroComponentDefs.some(c => c.id === state.kpi)) ? "active" : "";
      return `<button class="front-kpi ${active}" data-kpi="${def.id}" aria-label="${def.label}">
        <div class="kpi-icon">${icon(def.icon)}</div>
        <div>
          <h3>${def.short}</h3>
          <span class="big">${primaryMetric(row)}</span>
          <div class="mini-row">
            <span><b>${q1Text}</b></span>
          </div>
        </div>
      </button>`;
    }

    function bindKpiCards(root = document) {
      $$(".front-kpi", root).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.kpi;
        render();
      }));
    }

    function overviewCommandSummary(selected) {
      const q1Ready = kpiDefs.filter(def => {
        const row = dashboardPeriodKpi(def.id, "q1");
        return n(row.fact) !== null || n(row.growth) !== null;
      }).length;
      const selectedTasks = tasksForKpi(selected.id);
      const openTasks = selectedTasks.filter(t => (t.status || "grey") !== "green").length;
      const districtReady = selected.id === "grp" ? "таркибий" : districtSelectorDefs().some(def => def.id === selected.id) ? "бор" : "йўқ";
      const q1Missing = kpiDefs.length - q1Ready;
      return `<div class="command-summary">
        <div class="command-card"><span>I чорак факт</span><strong>${q1Ready}/${kpiDefs.length}</strong><small>${q1Missing} та KPIда факт эмас, режа ёки чеклов кўрсатилган.</small></div>
        <div class="command-card"><span>Танланган KPI</span><strong>${selected.short}</strong><small>${selected.label}</small></div>
        <div class="command-card"><span>Боғланган топшириқлар</span><strong>${openTasks}/${selectedTasks.length}</strong><small>бажарилмаган / жами топшириқ.</small></div>
        <div class="command-card"><span>Туман кесими</span><strong>${districtReady}</strong><small>KPIдан туманлар экранига drill-down мавжуд.</small></div>
      </div>`;
    }

    function kpiRouteBar(def) {
      const tasks = tasksForKpi(def.id);
      const openTasks = tasks.filter(t => (t.status || "grey") !== "green").length;
      const hasDistricts = def.id === "grp" || districtSelectorDefs().some(item => item.id === def.id);
      return `<div class="kpi-route">
        <div class="route-cell">
          <span>Кафолат KPI</span>
          <strong>${def.short}: чораклик режа / амалда / ижро</strong>
        </div>
        <div class="route-cell">
          <span>Боғланган топшириқлар</span>
          <strong>${openTasks}/${tasks.length} бажарилмаган / жами</strong>
        </div>
        <div class="route-actions">
          <button class="mini-button" data-open-tasks="${def.id}">Топшириқлар</button>
          ${hasDistricts ? `<button class="mini-button primary" data-open-districts="${def.id}">Туманлар кесими</button>` : ""}
        </div>
      </div>`;
    }

    function renderDashboard() {
      if (state.page === "dashboard" && !kpiDefs.some(def => def.id === state.kpi)) state.kpi = "industry";
      const selected = currentKpiDef();
      const yhmFocus = selected.id === "grp" || macroComponentDefs.some(def => def.id === selected.id);
      const grpAnnual = annualPlanKpi("grp");
      const grpQ1Text = q1ActualText("grp");
      $("#dashboardPage").innerHTML = `
        ${yhmFocus ? `<div class="yhm-focus-bar">
          <div><strong>${growthValue(grpAnnual.growth)}</strong><span>ЯҲМ · ${grpQ1Text} · фақат ўсиш кўрсаткичи ва унинг таркиби</span></div>
          <button class="mini-button" data-show-all-kpis="true">Барча KPI</button>
        </div>` : `<div class="front-kpis">${kpiDefs.map(kpiCard).join("")}</div>`}
        <div class="kpi-monitor-grid single">${kpiDashboardCard(selected)}</div>`;
      bindKpiCards($("#dashboardPage"));
      $$("[data-show-all-kpis]", $("#dashboardPage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = "export";
        render();
      }));
      $$("[data-component]", $("#dashboardPage")).forEach(card => card.addEventListener("click", () => {
        state.kpi = card.dataset.component;
        render();
      }));
      $$("[data-district]", $("#dashboardPage")).forEach(card => card.addEventListener("click", () => {
        state.district = card.dataset.district;
        state.page = "profile";
        render();
      }));
      $$("[data-open-districts]", $("#dashboardPage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.openDistricts;
        if (btn.dataset.period) state.period = btn.dataset.period;
        state.page = "districts";
        render();
      }));
      $$("[data-open-tasks]", $("#dashboardPage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.openTasks;
        state.page = "tasks";
        render();
      }));
      $$("[data-open-profile]", $("#dashboardPage")).forEach(btn => btn.addEventListener("click", () => {
        state.district = btn.dataset.openProfile;
        state.page = "profile";
        render();
      }));
    }

    function signalBarWidth(def, row) {
      if (n(row.execution) !== null) return Math.max(4, Math.min(100, n(row.execution)));
      if (n(row.growth) !== null) return Math.max(4, Math.min(100, Math.abs(n(row.growth) - 100) / 20 * 100));
      if (n(row.plan) !== null) return 72;
      return 8;
    }

    function renderKpiSignal(def) {
      const periodsForSignal = [["I чорак", "q1"], ["II чорак", "h1"], ["III чорак", "m9"], ["Йиллик", "year"]];
      const annual = dashboardPeriodKpi(def.id, "year");
      const q1 = dashboardPeriodKpi(def.id, "q1");
      const growthOnly = def.id === "grp" || macroComponentDefs.some(item => item.id === def.id) || ["export"].includes(def.id);
      const main = growthOnly && n(annual.growth) !== null ? growthValue(annual.growth) : primaryMetric(annual);
      const decision = (() => {
        if (def.id === "inflation") return "Чеклов KPI: факт киритилганда ≤2,9% ва ≤6,6% чегараларига нисбатан баҳоланади.";
        if (def.id === "poverty") return "Асосий мақсад камбағалликни қисқартириш; драйверлар 6-жадвал ва кафолат хатидан олинади.";
        if (def.id === "unemployment") return "Паст кўрсаткич мақсад; факт киритилмагунча ҳолат режа сифатида қолади.";
        if (["budget", "investment"].includes(def.id)) return "Факт/кутилиш киритилганда режага нисбатан ижро фоизи асосий сигнал бўлади.";
        if (growthOnly) return "Асосий сигнал ўсиш суръати; ҳажмлар детал экранларда ва туманлар кесимида очилади.";
        return "Режа, факт ва ижро биргаликда кузатилади.";
      })();
      return `<div class="kpi-signal">
        <div class="signal-main">
          <span>Бошқарув сигнали</span>
          <strong>${main}</strong>
          <small>${decision}</small>
        </div>
        <div class="signal-side">
          <span>Даврлар кесими</span>
          <div class="signal-track">
            ${periodsForSignal.map(([label, period]) => {
              const row = dashboardPeriodKpi(def.id, period);
              const stateInfo = periodState(def, period, row);
              const value = growthOnly && n(row.growth) !== null ? growthValue(row.growth) : primaryMetric(row);
              return `<div class="signal-step">
                <b>${label}</b>
                <small>${value}</small>
                <i style="--w:${signalBarWidth(def, row)}%;--c:${stateInfo.cls === "actual" ? "var(--blue)" : "var(--line-strong)"}"></i>
              </div>`;
            }).join("")}
          </div>
        </div>
      </div>`;
    }

    function kpiDashboardCard(def) {
      const growthOnly = def.id === "grp" || macroComponentDefs.some(item => item.id === def.id);
      const districtDrilldown = districtSelectorDefs().some(item => item.id === def.id);
      const quarters = [
        ["I чорак", "q1"],
        ["II чорак", "h1"],
        ["III чорак", "m9"],
        ["Йиллик", "year"]
      ];
      return `<article class="kpi-monitor-card">
        <div class="kpi-monitor-head">
          <div class="small-icon">${icon(def.icon)}</div>
          <div>
            <h3>${def.short}</h3>
            <p>${def.label}</p>
          </div>
          ${districtDrilldown ? `<button class="mini-button primary" data-open-districts="${def.id}">Туманлар кесими</button>` : ""}
        </div>
        ${kpiRouteBar(def)}
        <div class="quarter-matrix">
          ${quarters.map(([label, period]) => {
            const row = dashboardPeriodKpi(def.id, period);
            const stateInfo = periodState(def, period, row);
            const main = n(row.growth) !== null ? growthValue(row.growth) : n(row.execution) !== null ? `${fmt(row.execution, 1)}%` : primaryMetric(row);
            const fact = factValue(row);
            const plan = planValue(row);
            const measureLabel = n(row.growth) !== null ? "Ўсиш" : n(row.execution) !== null ? "Ижро" : "Кўрсаткич";
            const metricRows = growthOnly
              ? `<span>${stateInfo.cls === "actual" ? "Амалда" : "Прогноз"} <b>${main}</b></span>`
              : `<span>Режа <b>${plan}</b></span>
                <span>Амалда <b>${fact}</b></span>
                <span>${measureLabel} <b>${main}</b></span>`;
            return `<div class="quarter-row ${stateInfo.cls}">
              <h4>${label}</h4>
              <div class="q-metrics">
                ${metricRows}
              </div>
              <span class="chip ${stateInfo.chip}">${stateInfo.label}</span>
            </div>`;
          }).join("")}
        </div>
        ${def.id === "grp" ? renderMacroComposition() : ""}
        ${def.id === "industry" ? renderIndustryDrivers() : ""}
        ${def.id === "inflation" ? renderInflationDetails() : ""}
        ${def.id === "poverty" ? renderPovertyDetails() : ""}
        ${["inflation", "poverty", "industry"].includes(def.id) ? "" : `
        <div class="lagging">
          <div class="lagging-title"><strong>Туманлар кесими</strong><span class="chip grey">алоҳида экран</span></div>
          <div class="action-row"><button class="mini-button primary" data-open-districts="${def.id}">Туманлар кесимига ўтиш</button></div>
        </div>
        `}
      </article>`;
    }

    function renderMacroComposition() {
      return `<div class="composition macro-composition">
        <div class="lagging-title"><strong>ЯҲМ таркиби</strong><span class="chip blue">фақат ўсиш кўрсаткичлари</span></div>
        <div class="composition-grid">
          ${macroComponentDefs.map(def => {
            const annual = annualPlanKpi(def.id);
            const h1 = dashboardPeriodKpi(def.id, "h1");
            const q1 = dashboardPeriodKpi(def.id, "q1");
            return `<button class="component-card ${state.kpi === def.id ? "active" : ""}" data-component="${def.id}">
              <span>${def.short}</span>
              <strong>${growthValue(annual.growth)}</strong>
              <small>I чорак амалда: ${growthValue(q1.growth)} · II чорак: ${growthValue(h1.growth)}</small>
            </button>`;
          }).join("")}
        </div>
      </div>`;
    }

    function renderInflationDetails() {
      const priceCaps = [
        ["Гўшт ва гўшт маҳсулотлари", "6–7%дан ошмаслик"],
        ["Тухум", "5–6%дан ошмаслик"],
        ["Сут ва сут маҳсулотлари", "6–7%дан ошмаслик"],
        ["Картошка", "4–5%дан ошмаслик"],
        ["Пиёз", "5%дан ошмаслик"],
        ["Сабзи", "5%дан ошмаслик"],
        ["Гуруч", "2025 йил даражасида"],
        ["Ун", "2025 йил даражасида"]
      ];
      const foods = (DATA.regional.food_balance || [])
        .filter(row => row.product && row.product !== "шундан:" && n(row.resource_total) !== null);
      const sensitiveFoods = [...foods]
        .filter(row => n(row.local_supply_ratio) !== null)
        .sort((a, b) => (n(a.local_supply_ratio) || 0) - (n(b.local_supply_ratio) || 0))
        .slice(0, 4);
      const districts = DATA.districts || [];
      const warehouseTotals = districts.reduce((acc, d) => {
        const w = d.data?.warehouses || {};
        acc.reserveCount += n(w.reserve_warehouses) || 0;
        acc.reserveCap += n(w.reserve_capacity_t) || 0;
        acc.coldCount += n(w.cold_storage_count) || 0;
        acc.coldCap += n(w.cold_storage_capacity_t) || 0;
        acc.newCount += (n(w.new_small_cold_storage_count) || 0) + (n(w.new_large_cold_storage_count) || 0);
        return acc;
      }, { reserveCount: 0, reserveCap: 0, coldCount: 0, coldCap: 0, newCount: 0 });
      return `<div class="drivers">
        <div class="lagging-title"><strong>Инфляцияга боғланган аниқ вазифалар</strong><span class="chip blue">кафолат хати II-бўлим</span></div>
        <div class="driver-grid">
          <div class="driver-card"><span>II чорак инфляция чегараси</span><strong>≤2,9%</strong><small>белгиланган прогноз даражасидан оширмаслик</small></div>
          <div class="driver-card"><span>Йиллик инфляция чегараси</span><strong>≤6,6%</strong><small>йил якуни бўйича асосий KPI</small></div>
          <div class="driver-card"><span>Совутгичли омборлар</span><strong>33 та</strong><small>II чорак: 4 та, 1 300 т · йил: 8 810 т</small></div>
          <div class="driver-card"><span>Захира жамғармаси</span><strong>50 млрд сўм</strong><small>2026 йил якунигача</small></div>
        </div>
        <div class="composition">
          <div class="lagging-title"><strong>Асосий озиқ-овқат нархлари</strong><span class="chip grey">йиллик чегара</span></div>
          <div class="composition-grid">
            ${priceCaps.map(([name, cap]) => `<button class="component-card" type="button">
              <span>${name}</span>
              <strong>${cap}</strong>
              <small>нархлар барқарорлигини сақлаш вазифаси</small>
            </button>`).join("")}
          </div>
        </div>
        <div class="composition">
          <div class="lagging-title"><strong>Озиқ-овқат балансида эътибор талаб қиладиган маҳсулотлар</strong><span class="chip grey">2.1-жадвал</span></div>
          <div class="composition-grid">
            ${sensitiveFoods.map(row => `<button class="component-card" type="button">
              <span>${row.product}</span>
              <strong>${fmt((n(row.local_supply_ratio) || 0) * 100, 1)}%</strong>
              <small>маҳаллий таъминланиш · ресурс ${fmt(row.resource_total, 1)} минг т · импорт ${fmt(row.import, 1)} минг т</small>
            </button>`).join("")}
          </div>
        </div>
        <div class="lagging">
          <div class="lagging-title"><strong>Омборлар туманлар кесимида</strong><span class="chip grey">2.2-жадвал</span></div>
          <div class="driver-grid">
            <div class="driver-card"><span>Жами омборлар</span><strong>${fmt(warehouseTotals.reserveCount + warehouseTotals.coldCount, 0)} та</strong><small>${fmt(warehouseTotals.reserveCap + warehouseTotals.coldCap, 0)} тонна сиғим</small></div>
            <div class="driver-card"><span>Совутгичли омборлар</span><strong>${fmt(warehouseTotals.coldCount, 0)} та</strong><small>${fmt(warehouseTotals.coldCap, 0)} тонна сиғим</small></div>
            <div class="driver-card"><span>Захира омборлари</span><strong>${fmt(warehouseTotals.reserveCount, 0)} та</strong><small>${fmt(warehouseTotals.reserveCap, 0)} тонна сиғим</small></div>
            <div class="driver-card"><span>Янги омборлар режаси</span><strong>${fmt(warehouseTotals.newCount, 0)} та</strong><small>кафолат хатида йиллик 33 та, 8 810 тонна</small></div>
          </div>
          <div class="action-row"><button class="mini-button primary" data-open-districts="inflation">Омборлар бўйича туманлар кесими</button></div>
        </div>
      </div>`;
    }

    function renderPovertyDetails() {
      const emp = DATA.regional.employment || {};
      const districts = DATA.districts || [];
      const clearTerritories = districts.filter(d => String(d.data?.employment?.poverty_year || "").includes("холи"));
      const letterTasks = [
        ["Индивидуал режалар", "12 минг оила / 26 минг йиллик", "14,5 минг хизмат / 31,5 минг йиллик"],
        ["Камбағал оила аъзоларини ишга жойлаштириш", "4,9 минг", "10,7 минг йиллик"],
        ["Кредит ва субсидиялар", "3,1 минг", "6,8 минг йиллик"],
        ["Тадбиркорликка жалб қилиш", "5,2 минг", "11,3 минг йиллик"],
        ["Касб-ҳунарга ўқитиш", "1,1 минг", "2,6 минг йиллик"],
        ["Оғир туманлар инфратузилмаси", "3 та лойиҳа / 22,2 млрд сўм", "24 та / 180 млрд сўм"]
      ];
      return `<div class="drivers">
        <div class="lagging-title"><strong>Камбағаллик KPI ва унинг драйверлари</strong><span class="chip blue">6-жадвал + кафолат хати</span></div>
        <div class="driver-grid">
          <div class="driver-card"><span>II чорак мақсад</span><strong>${fmt(emp.poverty_h1, 1)}%</strong><small>6-жадвал: камбағаллик даражаси</small></div>
          <div class="driver-card"><span>Йиллик мақсад</span><strong>${fmt(emp.poverty_year, 1)}%</strong><small>кафолат хати 101-параграф</small></div>
          <div class="driver-card"><span>Холи МФЙ</span><strong>${fmt(emp.mfy_h1, 0)} / ${fmt(emp.mfy_year, 0)}</strong><small>II чорак / йиллик</small></div>
          <div class="driver-card"><span>Микролойиҳалар</span><strong>${fmt(emp.microprojects_h1, 0)} та</strong><small>йиллик ${fmt(emp.microprojects_year, 0)} та</small></div>
        </div>
        <div class="composition">
          <div class="lagging-title"><strong>Excel 6-жадвалдаги ўлчанадиган драйверлар</strong><span class="chip grey">туман кесими бор</span></div>
          <div class="composition-grid">
            <button class="component-card" type="button"><span>Доимий ишга жойлаштириш</span><strong>${fmt(emp.jobs_h1, 1)} минг</strong><small>йиллик ${fmt(emp.jobs_year, 1)} минг</small></button>
            <button class="component-card" type="button"><span>Норасмий бандларни легаллаштириш</span><strong>${fmt(emp.legalization_h1, 1)} минг</strong><small>йиллик ${fmt(emp.legalization_year, 1)} минг</small></button>
            <button class="component-card" type="button"><span>Холи МФЙлар</span><strong>${fmt(emp.mfy_h1, 0)} та</strong><small>йиллик ${fmt(emp.mfy_year, 0)} та</small></button>
            <button class="component-card" type="button"><span>Микролойиҳалар</span><strong>${fmt(emp.microprojects_h1, 0)} та</strong><small>йиллик ${fmt(emp.microprojects_year, 0)} та</small></button>
          </div>
        </div>
        <div class="composition">
          <div class="lagging-title"><strong>Кафолат хатидан келган вазифалар</strong><span class="chip grey">параграф 104-108</span></div>
          <div class="composition-grid">
            ${letterTasks.map(([name, h1, year]) => `<button class="component-card" type="button">
              <span>${name}</span>
              <strong>${h1}</strong>
              <small>${year}</small>
            </button>`).join("")}
          </div>
        </div>
        <div class="lagging">
          <div class="lagging-title"><strong>Камбағаллик бўйича туманлар мониторинги</strong><span class="chip grey">${fmt(clearTerritories.length, 0)} та холи ҳудуд режада</span></div>
          <div class="action-row"><button class="mini-button primary" data-open-districts="poverty">Камбағаллик бўйича туманлар кесими</button></div>
        </div>
      </div>`;
    }

    function renderIndustryDrivers() {
      const districts = DATA.districts || [];
      const totals = DATA.regional.industry_drivers || {};
      const h1Projects = n(totals.localization_h1_projects) ?? districts.reduce((s, d) => s + (n(d.data?.localization?.h1_projects) || 0), 0);
      const yearProjects = n(totals.localization_year_projects) ?? districts.reduce((s, d) => s + (n(d.data?.localization?.year_projects) || 0), 0);
      const h1LocalValue = n(totals.localization_h1_value_mln) ?? districts.reduce((s, d) => s + (n(d.data?.localization?.h1_value_mln) || 0), 0);
      const yearLocalValue = n(totals.localization_year_value_mln) ?? districts.reduce((s, d) => s + (n(d.data?.localization?.year_value_mln) || 0), 0);
      const h1Electricity = n(totals.energy_electricity_h1) ?? districts.reduce((s, d) => s + (n(d.data?.energy_efficiency?.electricity_h1) || 0), 0);
      const yearElectricity = n(totals.energy_electricity_year) ?? districts.reduce((s, d) => s + (n(d.data?.energy_efficiency?.electricity_year) || 0), 0);
      const h1Gas = n(totals.energy_gas_h1) ?? districts.reduce((s, d) => s + (n(d.data?.energy_efficiency?.gas_h1) || 0), 0);
      const yearGas = n(totals.energy_gas_year) ?? districts.reduce((s, d) => s + (n(d.data?.energy_efficiency?.gas_year) || 0), 0);
      const taskLinks = [
        ["Маҳаллийлаштириш дастури", "12,4 трлн сўмлик маҳсулот ва маҳаллий контент вазифалари", "localization"],
        ["Энергия самарадорлиги", "электр ва табиий газ тежаш бўйича туман кесими", "energy_electricity"],
        ["Корхоналарда ишлаб чиқаришни тиклаш", "6 та йирик корхона, 242 та вақтинча тўхтаган корхона", "industry"],
        ["307 та лойиҳа ва зоналар", "лойиҳалар қисми инвестиция мониторингига боғланади", "investment"]
      ];
      return `<div class="drivers">
        <div class="lagging-title"><strong>Саноат драйверлари</strong><span class="chip blue">1.3-жадвал · туман кесими бор</span></div>
        <div class="driver-grid">
          <button class="component-card" type="button" data-open-districts="industry" data-period="h1"><span>Ҳудудий саноат</span><strong>${growthValue(dashboardPeriodKpi("industry", "h1").growth)}</strong><small>II чорак ўсиш · туманлар бўйича ҳажм ва ўсиш алоҳида экранда</small></button>
          <button class="component-card" type="button" data-open-districts="localization" data-period="h1"><span>Маҳаллийлаштириш</span><strong>${fmt(h1Projects, 0)} та</strong><small>II чорак қиймати ${displayValue(h1LocalValue, "млн сўм")} · йиллик ${fmt(yearProjects, 0)} та / ${displayValue(yearLocalValue, "млн сўм")}</small></button>
          <button class="component-card" type="button" data-open-districts="energy_electricity" data-period="h1"><span>Электр тежаш</span><strong>${fmt(h1Electricity, 1)} млн кВт·с</strong><small>йиллик ${fmt(yearElectricity, 1)} млн кВт·с</small></button>
          <button class="component-card" type="button" data-open-districts="energy_gas" data-period="h1"><span>Газ тежаш</span><strong>${fmt(h1Gas, 1)} млн м³</strong><small>йиллик ${fmt(yearGas, 1)} млн м³</small></button>
        </div>
        <div class="composition">
          <div class="lagging-title"><strong>Кафолат хати топшириқларини индикаторга боғлаш</strong><span class="chip grey">босилганда туман кесими очилади</span></div>
          <div class="composition-grid">
            ${taskLinks.map(([name, desc, target]) => `<button class="component-card" type="button" data-open-districts="${target}" data-period="h1">
              <span>${name}</span>
              <strong>${target === "investment" ? "Инвестиция" : target === "industry" ? "Саноат" : target === "localization" ? "Маҳаллийлаштириш" : "Энергия"}</strong>
              <small>${desc}</small>
            </button>`).join("")}
          </div>
        </div>
        <div class="action-row"><button class="mini-button primary" data-open-districts="industry">Саноат бўйича туманлар кесимига ўтиш</button></div>
      </div>`;
    }

    function taskCard(t) {
      const status = t.status || "grey";
      return `<article class="task-card">
        <header><strong>${cleanTaskTitle(t.title)}</strong><span class="chip ${status}">${statusLabel(status)}</span></header>
        <div class="task-meta"><span>${t.sector}</span><span>${t.period}</span><span>${t.owner}</span></div>
      </article>`;
    }

    function taskSelectorDefs() {
      return [
        ...kpiDefs,
        ...macroComponentDefs.filter(def => def.id !== "construction"),
        ...districtOnlyDefs
      ].filter((def, idx, arr) => def && arr.findIndex(item => item.id === def.id) === idx);
    }

    function taskGroupLabel(task, kpiId) {
      const title = cleanTaskTitle(task.title).toLowerCase();
      if (/биринчи ярим йилликда|йил якуни|даражаси|экспорт таъминланади|етказилади|оширмаслик|ўсиши таъминланади/i.test(title)) {
        return "Асосий KPI вазифаси";
      }
      if (/туман|шаҳар|маҳалла|мфй|корхона|хонобод|шаҳрихон|андижон шаҳри|хўжаобод|жалақудуқ/i.test(title)) {
        return "Манзилли ҳудуд / корхона иши";
      }
      if (/лойиҳа|омбор|энергия|маҳаллийлаштириш|кредит|субсид|кўргазма|бизнес-миссия|инфратузилма/i.test(title)) {
        return "Драйвер ва лойиҳалар";
      }
      if (["poverty", "jobs", "unemployment", "legalization", "mfy_clear", "microprojects"].includes(kpiId)) {
        return "Бандлик ва камбағаллик чоралари";
      }
      return "Қўшимча вазифалар";
    }

    function taskGroupOrder(label) {
      return {
        "Асосий KPI вазифаси": 0,
        "Драйвер ва лойиҳалар": 1,
        "Манзилли ҳудуд / корхона иши": 2,
        "Бандлик ва камбағаллик чоралари": 3,
        "Қўшимча вазифалар": 4
      }[label] ?? 9;
    }

    function taskActionTarget(kpiId) {
      if (districtSelectorDefs().some(def => def.id === kpiId)) return "districts";
      return "dashboard";
    }

    function compactTaskCard(t, kpiId) {
      const status = t.status || "grey";
      return `<article class="task-card compact">
        <header>
          <strong>${cleanTaskTitle(t.title)}</strong>
          <div class="task-meta"><span>${t.period}</span><span>${t.owner}</span><span>${t.sector}</span></div>
        </header>
        <div class="task-actions">
          <span class="chip ${status}">${statusLabel(status)}</span>
        </div>
      </article>`;
    }

    function cleanTaskTitle(text) {
      return (text || "").replace(/\s+/g, " ").trim();
    }

    function renderKpiPage() {
      const selected = currentKpiDef();
      const rows = kpiDefs.map(def => {
        const r = regionalKpi(def.id);
        return { def, ...r };
      });
      const activeRow = regionalKpi(selected.id);
      const relatedDistricts = filteredDistricts().map(d => ({ d, k: districtKpi(d, selected.id) }));
      $("#kpiPage").innerHTML = `
        <div class="grid-2">
          <article class="panel">
            <div class="panel-head">
              <div><h3>KPI мониторинг</h3><p>Ҳар бир кўрсаткич бўйича фақат факт, режа ва ижро кўрсатилади.</p></div>
              <span class="chip blue">${periodLabel()}</span>
            </div>
            <div class="table-scroll">
              <table>
                <thead><tr><th style="width:25%">KPI</th><th class="num">Факт</th><th class="num">Режа</th><th class="num">Ижро</th><th style="width:16%">Ҳолат</th></tr></thead>
                <tbody>${rows.map(r => `<tr class="clickable ${r.def.id === state.kpi ? "active-row" : ""}" data-kpi="${r.def.id}">
                  <td><strong>${r.def.label}</strong><br><span class="muted">${r.def.sector}</span></td>
                  <td class="num">${displayValue(r.fact, r.unit)}</td>
                  <td class="num">${displayValue(r.plan, r.unit)}</td>
                  <td class="num">${r.execution ? `${fmt(r.execution)}%` : "—"}<div class="progress"><i style="--w:${Math.min(100, r.execution || 0)}%;--c:${colorFor(statusFor(r.execution))}"></i></div></td>
                  <td><span class="chip ${r.status || statusFor(r.execution)}">${statusLabel(r.status || statusFor(r.execution))}</span></td>
                </tr>`).join("")}</tbody>
              </table>
            </div>
          </article>
          <article class="panel">
            <div class="panel-head">
              <div><h3>${selected.short}: KPI паспорти</h3><p>Танланган кўрсаткичнинг давр, режа, факт ва туманлар кесими.</p></div>
              <span class="chip ${activeRow.status || "grey"}">${statusLabel(activeRow.status || "grey")}</span>
            </div>
            <div class="panel-body">
              <div class="detail-main">
                <div><div class="eyebrow">${selected.sector}</div><strong>${activeRow.main || displayValue(activeRow.fact ?? activeRow.plan, activeRow.unit)}</strong></div>
                <span class="chip blue">${periodLabel()}</span>
              </div>
              <div class="kv">
                <div><span>Факт</span><b>${displayValue(activeRow.fact, activeRow.unit)}</b></div>
                <div><span>Режа</span><b>${displayValue(activeRow.plan, activeRow.unit)}</b></div>
                <div><span>Ижро</span><b>${activeRow.execution ? `${fmt(activeRow.execution)}%` : "—"}</b></div>
              </div>
              <h3 style="font-size:15px;margin:16px 0 8px">Туманлар кесимида режа</h3>
              <div class="task-list">${relatedDistricts.slice(0, 7).map(({d, k}) => `<article class="task-card" data-district="${d.name}">
                <header><strong>${d.name}</strong><span class="chip ${d.debt.task_unfinished ? "red" : "green"}">${d.debt.task_unfinished}/${d.debt.task_total}</span></header>
                <div class="task-meta"><span>Факт: ${displayValue(k.fact, k.unit)}</span><span>Режа: ${displayValue(k.plan, k.unit)}</span><span>Ижро: ${k.execution ? `${fmt(k.execution)}%` : "—"}</span></div>
              </article>`).join("")}</div>
            </div>
          </article>
        </div>`;
      $$("tr[data-kpi]", $("#kpiPage")).forEach(row => row.addEventListener("click", () => { state.kpi = row.dataset.kpi; render(); }));
      $$("[data-district]", $("#kpiPage")).forEach(card => card.addEventListener("click", () => { state.district = card.dataset.district; state.page = "profile"; render(); }));
    }

    function renderTasksPage() {
      if (!taskSelectorDefs().some(def => def.id === state.kpi)) state.kpi = "export";
      const kpi = currentKpiDef();
      const q = state.search.trim().toLowerCase();
      const allTasks = tasksForKpi(kpi.id).filter(t => !q || `${t.title} ${t.sector} ${t.owner} ${t.period}`.toLowerCase().includes(q));
      const notDone = allTasks.filter(t => (t.status || "grey") !== "green");
      const done = allTasks.filter(t => t.status === "green");
      const visibleTasks = state.taskStatus === "all" ? allTasks : state.taskStatus === "done" ? done : notDone;
      const grouped = visibleTasks.reduce((acc, task) => {
        const label = taskGroupLabel(task, kpi.id);
        if (!acc[label]) acc[label] = [];
        acc[label].push(task);
        return acc;
      }, {});
      const groupEntries = Object.entries(grouped).sort((a, b) => taskGroupOrder(a[0]) - taskGroupOrder(b[0]));
      const districtReady = districtSelectorDefs().some(def => def.id === kpi.id);
      const annual = annualPlanKpi(kpi.id);
      $("#tasksPage").innerHTML = `
        <div class="task-filter">
          <label>KPI / топшириқ йўналиши
            <select id="taskKpiSelect">
              ${taskSelectorDefs().map(def => `<option value="${def.id}" ${def.id === kpi.id ? "selected" : ""}>${def.short} — ${def.label}</option>`).join("")}
            </select>
          </label>
          <label>Ҳолат
            <select id="taskStatusSelect">
              <option value="open" ${state.taskStatus === "open" ? "selected" : ""}>Бажарилмаган</option>
              <option value="all" ${state.taskStatus === "all" ? "selected" : ""}>Барчаси</option>
              <option value="done" ${state.taskStatus === "done" ? "selected" : ""}>Бажарилган</option>
            </select>
          </label>
          <div class="action-row" style="margin-top:0">
            <button class="mini-button" data-task-page="dashboard">KPI экрани</button>
            ${districtReady ? `<button class="mini-button primary" data-open-districts="${kpi.id}">Туманлар кесими</button>` : ""}
          </div>
        </div>
        <div class="task-summary-strip">
          <div class="small-stat"><span>KPI</span><strong>${kpi.short}</strong><small>${kpi.sector}</small></div>
          <div class="small-stat"><span>Йиллик кўрсаткич</span><strong>${primaryMetric(annual)}</strong><small>биринчи экрандаги KPI билан боғланган</small></div>
          <div class="small-stat"><span>Топшириқлар</span><strong>${notDone.length}/${allTasks.length}</strong><small>бажарилмаган / жами</small></div>
          <div class="small-stat"><span>Гуруҳлар</span><strong>${groupEntries.length}</strong><small>вазифа мазмунига кўра</small></div>
        </div>
        <div class="task-workspace">
          <div class="task-groups">
            ${groupEntries.map(([label, items]) => `<section class="task-group">
              <div class="task-group-head"><h3>${label}</h3><span class="chip grey">${items.length} та</span></div>
              <div class="task-list">${items.map(task => compactTaskCard(task, kpi.id)).join("")}</div>
            </section>`).join("") || `<article class="panel"><div class="panel-body"><p class="muted">Бу KPI бўйича топшириқ топилмади.</p></div></article>`}
          </div>
          <aside class="task-focus">
            <div class="eyebrow">KPI → Топшириқлар</div>
            <h3>${kpi.short}: вазифалар қандай боғланган?</h3>
            <p>Бу экран танланган KPIга тегишли кафолат хати вазифаларини кўрсатади. Бажарилиш мониторинги туманлар кесими ва туман профилида давом этади.</p>
            <div class="task-list" style="margin-top:14px">
              <article class="task-card"><header><strong>1. KPIни текшириш</strong><span class="chip blue">${primaryMetric(annual)}</span></header><div class="task-meta"><span>KPI экрани</span></div></article>
              <article class="task-card"><header><strong>2. Топшириқларни ёпиш</strong><span class="chip ${notDone.length ? "red" : "green"}">${notDone.length}/${allTasks.length}</span></header><div class="task-meta"><span>кафолат хати вазифалари</span></div></article>
              <article class="task-card"><header><strong>3. Туманлар кесимида назорат</strong><span class="chip ${districtReady ? "green" : "grey"}">${districtReady ? "бор" : "йўқ"}</span></header><div class="task-meta"><span>${districtReady ? "drill-down тайёр" : "туман кўрсаткичи берилмаган"}</span></div></article>
            </div>
          </aside>
        </div>`;
      $("#taskKpiSelect").addEventListener("change", event => {
        state.kpi = event.target.value;
        state.taskStatus = "open";
        render();
      });
      $("#taskStatusSelect").addEventListener("change", event => {
        state.taskStatus = event.target.value;
        render();
      });
      $$("[data-task-page]", $("#tasksPage")).forEach(btn => btn.addEventListener("click", () => {
        state.page = btn.dataset.taskPage;
        render();
      }));
      $$("[data-open-districts]", $("#tasksPage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.openDistricts;
        state.page = "districts";
        render();
      }));
    }

    function districtContextDefs() {
      const all = districtSelectorDefs();
      const groups = {
        grp: ["grp", "industry", "agriculture", "services"],
        industry: ["industry", "localization", "energy_electricity", "energy_gas", "investment"],
        agriculture: ["grp", "industry", "agriculture", "services"],
        services: ["grp", "industry", "agriculture", "services"],
        localization: ["industry", "localization", "energy_electricity", "energy_gas"],
        energy_electricity: ["industry", "localization", "energy_electricity", "energy_gas"],
        energy_gas: ["industry", "localization", "energy_electricity", "energy_gas"],
        poverty: ["poverty", "unemployment", "jobs", "legalization", "mfy_clear", "microprojects"],
        unemployment: ["unemployment", "poverty", "jobs", "legalization", "mfy_clear", "microprojects"],
        jobs: ["jobs", "unemployment", "poverty", "legalization", "mfy_clear", "microprojects"],
        legalization: ["legalization", "jobs", "unemployment", "poverty", "mfy_clear", "microprojects"],
        mfy_clear: ["mfy_clear", "poverty", "unemployment", "jobs", "microprojects"],
        microprojects: ["microprojects", "poverty", "jobs", "legalization"],
        inflation: ["inflation"],
        export: ["export"],
        investment: ["investment"],
        budget: ["budget", "budget_investment"],
        budget_investment: ["budget_investment", "budget"]
      };
      const ids = groups[state.kpi] || [state.kpi];
      const scoped = ids.map(id => all.find(def => def.id === id)).filter(Boolean);
      return scoped.some(def => def.id === state.kpi) ? scoped : [currentKpiDef(), ...scoped].filter(Boolean);
    }

    function taskScopeForKpi(kpiId) {
      const def = districtSelectorDefs().find(item => item.id === kpiId) || currentKpiDef();
      const macroTaskKpis = ["grp", "industry", "agriculture", "construction", "services", "localization", "energy_electricity", "energy_gas"];
      if (kpiId === "inflation") return "Макро иқтисодиёт";
      if (kpiId === "budget_investment") return "Бюджет";
      return macroTaskKpis.includes(kpiId) ? "Макро иқтисодиёт" : def.sector;
    }

    function tasksForKpi(kpiId) {
      const sector = taskScopeForKpi(kpiId);
      return DATA.tasks.filter(t => (sector === "all" || t.sector === sector) && isSpecificTaskForKpi(t, kpiId));
    }

    function districtTasksFor(d, kpiId = state.kpi) {
      const all = tasksForKpi(kpiId);
      const districtStem = d.name.replace(/\s+(шаҳри|тумани)$/i, "").toLowerCase();
      const related = all.filter(t => cleanTaskTitle(t.title).toLowerCase().includes(districtStem));
      return related.length ? related : all;
    }

    function districtTaskSummary(d, kpiId = state.kpi) {
      const tasks = districtTasksFor(d, kpiId);
      const unfinished = tasks.filter(t => (t.status || "grey") !== "green").length;
      return { total: tasks.length, unfinished, tasks };
    }

    function districtMeasure(row, kpiId, period = state.period) {
      if (!row) return "—";
      if (["industry", "agriculture", "services", "export"].includes(kpiId) && n(row.growth) !== null) return growthValue(row.growth);
      if (n(row.execution) !== null) return `${fmt(row.execution, 1)}%`;
      if (period === "q1" && factValue(row) !== "—") return factValue(row);
      if (row.main && row.main !== "—") return row.main;
      return planValue(row);
    }

    function districtMeasureNote(row, kpiId) {
      if (!row) return "";
      if (["industry", "agriculture", "services", "export"].includes(kpiId) && n(row.growth) !== null) return planValue(row);
      if (row.note) return row.note;
      const fact = factValue(row);
      const plan = planValue(row);
      if (fact !== "—" && plan !== "—") return `факт ${fact} / режа ${plan}`;
      if (plan !== "—") return `режа ${plan}`;
      return "маълумот кутилмоқда";
    }

    function pathValue(obj, path) {
      return path.split(".").reduce((acc, key) => acc && acc[key] !== undefined ? acc[key] : null, obj);
    }

    function fieldColumn(label, path, unit = "", notePath = null, noteUnit = "") {
      return {
        label,
        value: d => displayValue(pathValue(d, path), unit),
        note: d => notePath ? displayValue(pathValue(d, notePath), noteUnit) : "",
        status: () => "grey"
      };
    }

    function metricColumn(label, kpiId, period, mode = "auto", note = null) {
      return {
        label,
        period,
        value: d => {
          const row = districtKpi(d, kpiId, period);
          if (mode === "growth") return n(row.growth) !== null ? growthValue(row.growth) : "—";
          if (mode === "execution") return n(row.execution) !== null ? `${fmt(row.execution, 1)}%` : "—";
          if (mode === "fact") return factValue(row);
          if (mode === "plan") return planValue(row);
          return districtMeasure(row, kpiId, period);
        },
        note: d => {
          const row = districtKpi(d, kpiId, period);
          if (note) return note(row, d);
          return districtMeasureNote(row, kpiId);
        },
        status: d => rowStatus(districtKpi(d, kpiId, period))
      };
    }

    function districtTableConfig(kpi) {
      const id = kpi.id;
      const growthCols = ["industry", "agriculture", "services"].includes(id)
        ? [
            metricColumn("I чорак амалда", id, "q1", "growth", row => `ҳажм ${factValue(row)}`),
            metricColumn("I ярим йиллик прогноз", id, "h1", "growth", row => `режа ${planValue(row)}`),
            metricColumn("9 ойлик прогноз", id, "m9", "growth", row => `режа ${planValue(row)}`),
            metricColumn("Йиллик прогноз", id, "year", "growth", row => `режа ${planValue(row)}`)
          ]
        : null;
      const configs = {
        grp: {
          title: "ЯҲМ таркиби: туманлар кесими",
          description: "ЯҲМ туман кесимида берилмаган; солиштириш саноат, қишлоқ хўжалиги ва хизматлар ўсиши орқали берилади.",
          source: "1.1-1.5-жадваллар: 1.2, 1.4, 1.5",
          primaryPeriod: state.period,
          columns: [
            metricColumn("Саноат ўсиши", "industry", state.period, "growth", row => `ҳажм ${planValue(row)}`),
            metricColumn("ҚХ ўсиши", "agriculture", state.period, "growth", row => `ҳажм ${planValue(row)}`),
            metricColumn("Хизматлар ўсиши", "services", state.period, "growth", row => `ҳажм ${planValue(row)}`),
            { label: "Изоҳ", value: () => "ЯҲМ туман кесимида йўқ", note: () => "таркибий кўрсаткичлар", status: () => "grey" }
          ]
        },
        industry: { title: "Саноат: туманлар кесими", description: "Туманлар бўйича саноат маҳсулотлари ҳажми ва ўсиш суръати.", source: "1.2-жадвал", primaryPeriod: state.period, columns: growthCols },
        agriculture: { title: "Қишлоқ хўжалиги: туманлар кесими", description: "Туманлар бўйича қишлоқ хўжалиги маҳсулотлари ҳажми ва ўсиш суръати.", source: "1.4-жадвал", primaryPeriod: state.period, columns: growthCols },
        services: { title: "Хизматлар: туманлар кесими", description: "Туманлар бўйича бозор хизматлари ҳажми ва ўсиш суръати.", source: "1.5-жадвал", primaryPeriod: state.period, columns: growthCols },
        localization: {
          title: "Маҳаллийлаштириш дастури: туманлар кесими",
          description: "Бу кўрсаткичда I чорак/9 ойлик йўқ; Excelда I ярим йиллик ва йиллик режа берилган.",
          source: "1.3-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 лойиҳа", "localization", "h1", "plan", row => row.note),
            fieldColumn("H1 қиймат", "data.localization.h1_value_mln", "млн сўм"),
            metricColumn("Йиллик лойиҳа", "localization", "year", "plan", row => row.note),
            fieldColumn("Йиллик қиймат", "data.localization.year_value_mln", "млн сўм")
          ]
        },
        energy_electricity: {
          title: "Электр энергиясини тежаш: туманлар кесими",
          description: "Энергия самарадорлиги бўйича I ярим йиллик ва йиллик мақсадли ҳажмлар.",
          source: "1.3-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 тежаш", "energy_electricity", "h1", "plan"),
            metricColumn("Йиллик тежаш", "energy_electricity", "year", "plan")
          ]
        },
        energy_gas: {
          title: "Табиий газни тежаш: туманлар кесими",
          description: "Газ тежаш бўйича I ярим йиллик ва йиллик мақсадли ҳажмлар.",
          source: "1.3-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 тежаш", "energy_gas", "h1", "plan"),
            metricColumn("Йиллик тежаш", "energy_gas", "year", "plan")
          ]
        },
        inflation: {
          title: "Озиқ-овқат захира инфратузилмаси: туманлар кесими",
          description: "Туманлар бўйича инфляция фоизи берилмаган; шу ерда нарх барқарорлигига хизмат қилувчи омборлар кўрсатилади.",
          source: "2.2-жадвал",
          primaryPeriod: "year",
          columns: [
            fieldColumn("Захира омбори", "data.warehouses.reserve_warehouses", "та", "data.warehouses.reserve_capacity_t", "тонна"),
            fieldColumn("Совутгичли омбор", "data.warehouses.cold_storage_count", "та", "data.warehouses.cold_storage_capacity_t", "тонна"),
            { label: "Янги омбор режаси", value: d => displayValue((n(d.data?.warehouses?.new_small_cold_storage_count) || 0) + (n(d.data?.warehouses?.new_large_cold_storage_count) || 0), "та"), note: () => "100 тоннагача / 100 тоннадан юқори", status: () => "grey" },
            metricColumn("Жами сиғим", "inflation", "year", "plan", row => row.note)
          ]
        },
        budget: {
          title: "Бюджет тушумлари: туманлар кесими",
          description: "Манбада II чорак, I ярим йиллик ва йиллик прогноз/кутилиш берилган.",
          source: "3-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("II чорак ижро", "budget", "q2", "execution", row => `факт ${factValue(row)} / режа ${planValue(row)}`),
            metricColumn("I ярим йиллик ижро", "budget", "h1", "execution", row => `факт ${factValue(row)} / режа ${planValue(row)}`),
            metricColumn("Йиллик кутилиш", "budget", "year", "execution", row => `кутилиш ${factValue(row)} / режа ${planValue(row)}`)
          ]
        },
        budget_investment: {
          title: "Бюджет инвестициялари: туманлар кесими",
          description: "Объектлар, лимит ва ўзлаштириш динамикаси алоҳида кўрсатилади.",
          source: "4.1-жадвал",
          primaryPeriod: "h1",
          columns: [
            fieldColumn("Объектлар", "data.budget_investment.objects", "та", "data.budget_investment.limit", "млн сўм"),
            metricColumn("I чорак ўзлаштириш", "budget_investment", "q1", "execution", row => `амалда ${factValue(row)}`),
            metricColumn("H1 ўзлаштириш", "budget_investment", "h1", "execution", row => `амалда ${factValue(row)}`),
            metricColumn("Йиллик ўзлаштириш", "budget_investment", "year", "execution", row => `амалда ${factValue(row)}`)
          ]
        },
        investment: {
          title: "Хорижий инвестициялар: туманлар кесими",
          description: "I чорак факт/режа, I ярим йиллик кутилиш ва йиллик прогноз кесимида.",
          source: "4.2-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("I чорак ижро", "investment", "q1", "execution", row => `факт ${factValue(row)} / режа ${planValue(row)}`),
            metricColumn("H1 ижро", "investment", "h1", "execution", row => `кутилиш ${factValue(row)} / режа ${planValue(row)}`),
            metricColumn("Йиллик ижро", "investment", "year", "execution", row => `кутилиш ${factValue(row)} / прогноз ${planValue(row)}`),
            fieldColumn("H1 лойиҳа / иш ўрни", "data.foreign_investment.h1_projects", "та", "data.foreign_investment.h1_jobs", "та")
          ]
        },
        export: {
          title: "Экспорт: туманлар кесими",
          description: "Экспорт ҳажми, ўсиш суръати ва экспортчи корхоналар сони.",
          source: "5.1-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("I чорак амалда", "export", "q1", "growth", row => `ҳажм ${factValue(row)}`),
            metricColumn("H1 кутилиш", "export", "h1", "growth", row => `ҳажм ${factValue(row)}`),
            metricColumn("Йиллик кутилиш", "export", "year", "growth", row => `ҳажм ${factValue(row)}`),
            fieldColumn("Экспортчилар", "data.export.year_exporters", "та", "data.export.h1_exporters", "та")
          ]
        },
        unemployment: {
          title: "Ишсизлик даражаси: туманлар кесими",
          description: "6-жадвалда I ярим йиллик ва йиллик мақсадли даражалар берилган.",
          source: "6-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 мақсад", "unemployment", "h1", "plan"),
            metricColumn("Йиллик мақсад", "unemployment", "year", "plan"),
            metricColumn("Ишга жойлаштириш H1", "jobs", "h1", "plan"),
            metricColumn("Легаллаштириш H1", "legalization", "h1", "plan")
          ]
        },
        poverty: {
          title: "Камбағаллик даражаси: туманлар кесими",
          description: "Камбағаллик даражаси ва унга боғланган драйверлар бир жадвалда.",
          source: "6-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 камбағаллик", "poverty", "h1", "plan"),
            metricColumn("Йиллик камбағаллик", "poverty", "year", "plan"),
            metricColumn("Холи МФЙ H1", "mfy_clear", "h1", "plan"),
            metricColumn("Микролойиҳа H1", "microprojects", "h1", "plan")
          ]
        },
        jobs: {
          title: "Доимий ишга жойлаштириш: туманлар кесими",
          description: "Ишга жойлаштириш мақсадлари I ярим йиллик ва йиллик кесимда.",
          source: "6-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 ишга жойлаштириш", "jobs", "h1", "plan"),
            metricColumn("Йиллик ишга жойлаштириш", "jobs", "year", "plan"),
            metricColumn("H1 легаллаштириш", "legalization", "h1", "plan"),
            metricColumn("H1 микролойиҳа", "microprojects", "h1", "plan")
          ]
        },
        legalization: { title: "Норасмий бандларни легаллаштириш: туманлар кесими", description: "Легаллаштириш мақсадлари I ярим йиллик ва йиллик кесимда.", source: "6-жадвал", primaryPeriod: "h1", columns: [metricColumn("H1 мақсад", "legalization", "h1", "plan"), metricColumn("Йиллик мақсад", "legalization", "year", "plan"), metricColumn("Ишга жойлаштириш H1", "jobs", "h1", "plan")] },
        mfy_clear: { title: "Холи МФЙлар: туманлар кесими", description: "Камбағаллик ва ишсизликдан холи ҳудудга айлантириладиган МФЙлар.", source: "6-жадвал", primaryPeriod: "h1", columns: [metricColumn("H1 МФЙ", "mfy_clear", "h1", "plan"), metricColumn("Йиллик МФЙ", "mfy_clear", "year", "plan"), metricColumn("Камбағаллик H1", "poverty", "h1", "plan")] },
        microprojects: { title: "Микролойиҳалар: туманлар кесими", description: "Камбағалликни қисқартиришга боғланган микролойиҳалар.", source: "6-жадвал", primaryPeriod: "h1", columns: [metricColumn("H1 микролойиҳа", "microprojects", "h1", "plan"), metricColumn("Йиллик микролойиҳа", "microprojects", "year", "plan"), metricColumn("Ишга жойлаштириш H1", "jobs", "h1", "plan")] }
      };
      return configs[id] || configs.export;
    }

    function districtRowValue(item, kpiId) {
      const row = item.k;
      if (n(row.execution) !== null) return n(row.execution);
      if (n(row.growth) !== null) return n(row.growth);
      if (n(row.plan) !== null) return n(row.plan);
      if (n(row.fact) !== null) return n(row.fact);
      return 0;
    }

    function districtRowsForKpi(kpiId) {
      const cfg = districtTableConfig(districtSelectorDefs().find(def => def.id === kpiId) || currentKpiDef());
      const rows = filteredDistricts().map(d => ({ d, k: districtKpi(d, kpiId, cfg.primaryPeriod || state.period), task: districtTaskSummary(d, kpiId) }));
      return rows.sort((a, b) => {
        if (state.districtSort === "name") return a.d.name.localeCompare(b.d.name, "uz-Cyrl-UZ");
        if (state.districtSort === "tasks") return (b.task.unfinished || 0) - (a.task.unfinished || 0);
        if (state.districtSort === "plan") return (n(b.k.plan) || n(b.k.fact) || 0) - (n(a.k.plan) || n(a.k.fact) || 0);
        if (state.districtSort === "execution") return districtRowValue(b, kpiId) - districtRowValue(a, kpiId);
        const ast = rowStatus(a.k), bst = rowStatus(b.k);
        const order = { red: 0, amber: 1, grey: 2, green: 3 };
        if (order[ast] !== order[bst]) return order[ast] - order[bst];
        return (b.task.unfinished || 0) - (a.task.unfinished || 0);
      });
    }

    function districtCellHtml(d, column) {
      const status = column.status ? column.status(d) : "grey";
      return `<td><strong>${column.value(d)}</strong><small>${column.note ? column.note(d) : ""}</small></td>`;
    }

    function districtTableRow(item, kpi, cfg) {
      const d = item.d;
      const active = d.name === state.district ? "active-row" : "";
      const task = item.task || districtTaskSummary(d, kpi.id);
      const taskClass = task.total && task.unfinished ? "red" : task.total ? "green" : "grey";
      return `<tr class="clickable ${active}" data-select-district="${d.name}">
        <td class="row-title"><strong>${d.name}</strong><span>${d.owner}</span></td>
        ${cfg.columns.map(col => districtCellHtml(d, col)).join("")}
        <td class="num"><span class="chip ${taskClass}">${task.unfinished}/${task.total}</span></td>
        <td><button class="mini-button" data-profile-district="${d.name}">Профиль</button></td>
      </tr>`;
    }

    function renderDistrictTable(rows, kpi, cfg) {
      const head = `<tr><th>Туман/шаҳар</th>${cfg.columns.map(col => `<th>${col.label}</th>`).join("")}<th class="num">KPI топшириқ</th><th>Амал</th></tr>`;
      return `<div class="district-table-wrap"><table class="district-table"><thead>${head}</thead><tbody>${rows.map(row => districtTableRow(row, kpi, cfg)).join("")}</tbody></table></div>`;
    }

    function renderDistrictPreview(d, kpi, cfg) {
      const row = districtKpi(d, kpi.id, cfg.primaryPeriod || state.period);
      const status = rowStatus(row);
      const summary = districtTaskSummary(d, kpi.id);
      const taskClass = summary.total && summary.unfinished ? "red" : summary.total ? "green" : "grey";
      return `<article class="panel district-preview">
        <div class="panel-head">
          <div><h3>${d.name}</h3><p>${kpi.short} бўйича танланган ҳудуднинг қисқа KPI кўриниши.</p></div>
          <span class="chip ${taskClass}">${summary.unfinished}/${summary.total}</span>
        </div>
        <div class="panel-body">
          <div class="preview-score">
            <div><strong>${districtPrimaryValue(row, kpi.id)}</strong><span>${districtPrimaryLabel(kpi.id)} · ${cfg.source}</span></div>
            <span class="chip ${status}">${statusLabel(status)}</span>
          </div>
          <div class="preview-metrics">
            ${cfg.columns.map(col => `<div class="preview-metric">
              <div><strong>${col.label}</strong><small>${col.note ? col.note(d) : ""}</small></div>
              <span class="chip ${col.status ? col.status(d) : "grey"}">${col.value(d)}</span>
            </div>`).join("")}
          </div>
          <div class="preview-task-summary">
            <strong>KPI топшириқлари: ${summary.unfinished}/${summary.total}</strong>
            <span>Бу сон танланган KPIга боғланган бажарилмаган топшириқлар улушини кўрсатади. Матнли топшириқлар алоҳида “Топшириқлар” экранида очилади.</span>
          </div>
          <div class="action-row">
            <button class="mini-button primary" data-profile-district="${d.name}">Туман профилига ўтиш</button>
            <button class="mini-button" data-page-jump="tasks">Топшириқларни кўриш</button>
          </div>
        </div>
      </article>`;
    }

    function renderDistrictsPage() {
      if (!districtSelectorDefs().some(def => def.id === state.kpi)) state.kpi = "grp";
      const kpi = currentKpiDef();
      const cfg = districtTableConfig(kpi);
      const ranked = districtRowsForKpi(kpi.id);
      const districts = ranked.map(x => x.d);
      const selectedDistrict = districts.find(d => d.name === state.district) || districts[0] || currentDistrict();
      if (selectedDistrict?.name) state.district = selectedDistrict.name;
      const taskSet = tasksForKpi(kpi.id);
      const taskUnfinished = taskSet.filter(t => (t.status || "grey") !== "green").length;
      const measurable = ranked.filter(item => cfg.columns.some(col => col.value(item.d) !== "—")).length;
      const primaryValues = ranked.map(x => districtRowValue(x, kpi.id)).filter(v => v !== null && v !== 0);
      const growthRange = ["grp", "industry", "agriculture", "services", "export"].includes(kpi.id);
      const rangeText = primaryValues.length
        ? `Асосий қийматлар оралиғи: ${growthRange ? `${growthValue(Math.min(...primaryValues))} – ${growthValue(Math.max(...primaryValues))}` : `${fmt(Math.min(...primaryValues), 1)} – ${fmt(Math.max(...primaryValues), 1)}`}`
        : "Маълумот киритилиши кутилмоқда.";
      $("#districtsPage").innerHTML = `
        <div class="district-context">
          <div>
            <div class="eyebrow">KPI → Туманлар кесими</div>
            <h3>${cfg.title}</h3>
            <p>${cfg.description}</p>
          </div>
          <div class="district-context-actions">
            <button class="mini-button" data-page-jump="dashboard">KPI экрани</button>
            <button class="mini-button primary" data-profile-district="${state.district}">Туман профили</button>
          </div>
        </div>
        <div class="district-controls">
          <label>KPI / маълумот тури
            <select id="districtKpiSelect">
              ${districtSelectorDefs().map(def => `<option value="${def.id}" ${def.id === state.kpi ? "selected" : ""}>${def.short} — ${def.label}</option>`).join("")}
            </select>
          </label>
          <label>Саралаш
            <select id="districtSortSelect">
              <option value="attention" ${state.districtSort === "attention" ? "selected" : ""}>Эътибор талаб қиладиганлар</option>
              <option value="execution" ${state.districtSort === "execution" ? "selected" : ""}>Ижро/ўсиш юқоридан</option>
              <option value="plan" ${state.districtSort === "plan" ? "selected" : ""}>Режа каттадан</option>
              <option value="tasks" ${state.districtSort === "tasks" ? "selected" : ""}>KPI топшириқлари</option>
              <option value="name" ${state.districtSort === "name" ? "selected" : ""}>Номи бўйича</option>
            </select>
          </label>
          <label>Туман/шаҳар қидириш
            <input id="districtSearchBox" value="${state.search}" placeholder="Қидириш">
          </label>
        </div>
        <div class="task-summary-strip">
          <div class="small-stat"><span>Ҳудудлар</span><strong>${districts.length}/${DATA.districts.length}</strong><small>жорий фильтр бўйича</small></div>
          <div class="small-stat"><span>Маълумот бор</span><strong>${measurable}/${DATA.districts.length}</strong><small>${cfg.source}</small></div>
          <div class="small-stat"><span>KPI топшириқлари</span><strong>${taskUnfinished}/${taskSet.length}</strong><small>бажарилмаган / жами</small></div>
          <div class="small-stat"><span>Танланган ҳудуд</span><strong>${state.district}</strong><small>профилга ўтиш мумкин</small></div>
        </div>
        <div class="district-workspace">
          <article class="panel">
            <div class="panel-head">
              <div><h3>Туманлар солиштируви</h3><p>${rangeText}</p></div>
              <span class="chip blue">${cfg.source}</span>
            </div>
            <div class="panel-body">${renderDistrictTable(ranked, kpi, cfg)}</div>
          </article>
          ${renderDistrictPreview(selectedDistrict, kpi, cfg)}
        </div>`;
      $("#districtKpiSelect").addEventListener("change", event => {
        state.kpi = event.target.value;
        render();
      });
      $("#districtSortSelect").addEventListener("change", event => {
        state.districtSort = event.target.value;
        render();
      });
      $("#districtSearchBox").addEventListener("input", event => {
        state.search = event.target.value;
        render();
      });
      $$("[data-select-district]", $("#districtsPage")).forEach(el => el.addEventListener("click", event => {
        if (event.target.closest("button")) return;
        state.district = el.dataset.selectDistrict;
        render();
      }));
      $$("[data-profile-district]", $("#districtsPage")).forEach(el => el.addEventListener("click", event => {
        event.stopPropagation();
        state.district = el.dataset.profileDistrict;
        state.page = "profile";
        render();
      }));
      $$("[data-page-jump]", $("#districtsPage")).forEach(btn => btn.addEventListener("click", () => {
        state.page = btn.dataset.pageJump;
        render();
      }));
    }

    function renderProfilePage() {
      const d = currentDistrict();
      const kpi = currentKpiDef();
      const cfg = districtTableConfig(kpi);
      const metrics = districtAllMetrics(d);
      const summary = districtTaskSummary(d, kpi.id);
      const tasks = summary.tasks;
      const selected = districtKpi(d, state.kpi, cfg.primaryPeriod || state.period);
      const selectedStatus = rowStatus(selected);
      const taskClass = summary.total && summary.unfinished ? "red" : summary.total ? "green" : "grey";
      $("#profilePage").innerHTML = `
        ${contextStrip("profile")}
        <div class="profile-filter">
          <label>Туман/шаҳар танлаш
            <select id="profileDistrictSelect">
              ${DATA.districts.map(item => `<option value="${item.name}" ${item.name === d.name ? "selected" : ""}>${item.name}</option>`).join("")}
            </select>
          </label>
          <label>KPI / маълумот тури
            <select id="profileKpiSelect">
              ${districtSelectorDefs().map(def => `<option value="${def.id}" ${def.id === state.kpi ? "selected" : ""}>${def.short} — ${def.label}</option>`).join("")}
            </select>
          </label>
          <div class="action-row" style="margin-top:0">
            <button class="mini-button" data-open-districts="${kpi.id}">Туманлар кесимига қайтиш</button>
            <button class="mini-button primary" data-page-jump="dashboard">KPI экрани</button>
          </div>
        </div>
        <div class="profile-grid">
          <article class="profile-focus">
            <div class="profile-hero">
              <div>
                <div class="eyebrow">${kpi.sector}</div>
                <h3>${d.name}: ${kpi.short}</h3>
                <p>${cfg.description} Бу профил туманлар жадвалида танланган KPIни очиб, шу туман бўйича кўрсаткичлар ва боғланган топшириқларни бир жойда кўрсатади.</p>
                <div class="action-row">
                  <span class="chip blue">${cfg.source}</span>
                  <span class="chip ${selectedStatus}">${statusLabel(selectedStatus)}</span>
                  <span class="chip ${taskClass}">${summary.unfinished}/${summary.total} KPI топшириқ</span>
                </div>
              </div>
              <div class="profile-main-value">
                <strong>${districtPrimaryValue(selected, kpi.id)}</strong>
                <span>${districtPrimaryLabel(kpi.id)}</span>
              </div>
            </div>
            <div class="profile-metrics">
              ${cfg.columns.map(col => `<div class="profile-metric">
                <span>${col.label}</span>
                <strong>${col.value(d)}</strong>
                <small>${col.note ? col.note(d) : ""}</small>
              </div>`).join("")}
            </div>
          </article>
          <article class="panel">
            <div class="panel-head"><div><h3>KPI бўйича ҳаракат</h3><p>Профилдан кейинги амаллар танланган KPIга боғланган.</p></div></div>
            <div class="panel-body">
              <div class="profile-side-stat"><span>Масъул</span><strong>${d.owner}</strong></div>
              <div class="profile-side-stat"><span>Жорий маълумот</span><strong>${districtPrimaryValue(selected, kpi.id)}</strong></div>
              <div class="profile-side-stat"><span>Бажарилмаган топшириқ</span><strong>${summary.unfinished}/${summary.total}</strong></div>
              <div class="profile-actions" style="margin-top:12px">
                <button class="mini-button primary" data-open-districts="${kpi.id}">Туманлар жадвалида кўриш</button>
                <button class="mini-button" data-page-jump="tasks">Топшириқлар рўйхати</button>
              </div>
              <div class="task-list" style="margin-top:14px">
                ${tasks.slice(0, 4).map(taskCard).join("") || `<p class="muted">Бу KPI бўйича топшириқ топилмади.</p>`}
              </div>
            </div>
          </article>
        </div>
        <article class="panel profile-secondary">
          <div class="panel-head">
            <div><h3>Шу туман бўйича бошқа KPIлар</h3><p>Бу блок ёрдамчи кўриниш: асосий таҳлил юқорида танланган KPI бўйича қолади.</p></div>
            <span class="chip blue">${d.name}</span>
          </div>
          <div class="panel-body">
            <div class="district-kpis">
              ${metrics.map(({def, row}) => `<button class="district-kpi ${def.id === state.kpi ? "active" : ""}" data-kpi="${def.id}" type="button">
                <span>${def.label}</span>
                <strong>${districtPrimaryValue(row, def.id)}</strong>
                <small>${districtPrimaryLabel(def.id)} · ${districtMeasureNote(row, def.id)}</small>
              </button>`).join("")}
            </div>
          </div>
        </article>
        <article class="panel" style="margin-top:16px">
          <div class="panel-head">
            <div><h3>${kpi.short}га боғланган топшириқлар</h3><p>Кафолат хатидан олинган вазифалар шу туман ва шу KPI контекстида кўринади.</p></div>
            <span class="chip grey">${tasks.length} та</span>
          </div>
          <div class="panel-body">
            <div class="link-grid">
              <div class="link-box active"><h3>KPI</h3><p>${kpi.label}<br>Туман: ${d.name}<br>Давр: ${periodLabel()}</p></div>
              <div class="link-box"><h3>Топшириқлар</h3><div class="task-list" style="margin-top:10px">${tasks.slice(0, 5).map(taskCard).join("") || `<p class="muted">Бу KPI бўйича топшириқ топилмади.</p>`}</div></div>
              <div class="link-box"><h3>Мониторинг</h3><p>${d.name} бўйича ${summary.unfinished} та KPI топшириқ ҳали ёпилмаган. Профил KPI, вазифа ва туман маълумотини бир жойга йиғади.</p><div class="progress"><i style="--w:${summary.total ? Math.max(0, 100 - summary.unfinished / summary.total * 100) : 0}%;--c:var(--green)"></i></div></div>
            </div>
          </div>
        </article>`;
      $$("[data-kpi]", $("#profilePage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.kpi;
        render();
      }));
      $("#profileDistrictSelect").addEventListener("change", event => {
        state.district = event.target.value;
        render();
      });
      $("#profileKpiSelect").addEventListener("change", event => {
        state.kpi = event.target.value;
        render();
      });
      $$("[data-open-districts]", $("#profilePage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.openDistricts;
        if (btn.dataset.period) state.period = btn.dataset.period;
        state.page = "districts";
        render();
      }));
      $$("[data-page-jump]", $("#profilePage")).forEach(btn => btn.addEventListener("click", () => {
        state.page = btn.dataset.pageJump;
        render();
      }));
    }

    function renderReportPage() {
      const totalTasks = DATA.tasks.length;
      const districtTasks = DATA.districts.reduce((s, d) => s + d.debt.task_total, 0);
      const unfinished = DATA.districts.reduce((s, d) => s + d.debt.task_unfinished, 0);
      $("#reportPage").innerHTML = `
        <div class="cards-3" style="margin-bottom:16px">
          <div class="small-stat"><span>Кафолат хати топшириқлари</span><strong>${totalTasks}</strong><small>секторларга боғланган</small></div>
          <div class="small-stat"><span>Туманлар бўйича топшириқ</span><strong>${districtTasks}</strong><small>${DATA.districts.length} ҳудуд</small></div>
          <div class="small-stat"><span>Ёпилмаган</span><strong>${unfinished}</strong><small>бажарилмаган / жами ҳисобидан</small></div>
        </div>
        <article class="panel">
          <div class="panel-head"><div><h3>Ҳисобот учун қисқа хулоса</h3><p>Бу экран KPI, топшириқ ва туман мониторингини умумлаштиради.</p></div></div>
          <div class="panel-body">
            <div class="table-scroll">
              <table><thead><tr><th>Сектор</th><th class="num">Топшириқлар</th><th class="num">Мониторинг қаторлари</th><th>Асосий экран</th></tr></thead>
              <tbody>${[...new Set(DATA.tasks.map(t => t.sector))].map(sector => `<tr><td><strong>${sector}</strong></td><td class="num">${DATA.tasks.filter(t => t.sector === sector).length}</td><td class="num">${DATA.monitoring.filter(m => m.sector === sector).length}</td><td>KPI → топшириқ → туман ижроси</td></tr>`).join("")}</tbody></table>
            </div>
          </div>
        </article>`;
    }

    function setPageMeta() {
      const meta = {
        dashboard: ["KPI", "Йиллик режа, 4 чорак кесимида режа-факт-ижро ва орқада қолаётган туманлар."],
        tasks: ["Топшириқлар", "Кафолат хатидаги вазифалар KPIлар билан боғланган ҳолда кўринади."],
        districts: ["Туманлар кесими", `${currentKpiDef().short} бўйича туман/шаҳарлар кесими: режа, факт/кутилма, ижро ва топшириқлар.`],
        profile: ["Туман профили", `${state.district}: ${currentKpiDef().short} KPI, топшириқлар ва чораклик мониторинг боғланиши.`],
        report: ["Ҳисобот", "Бошқарув учун қисқа умумлашма."]
      };
      $("#pageTitle").textContent = meta[state.page][0];
      $("#pageSubtitle").textContent = meta[state.page][1];
    }

    function render() {
      if (state.page === "kpi") state.page = "dashboard";
      const pageChanged = renderedPage !== state.page;
      renderPeriodTabs();
      renderSectorFilter();
      $("#periodTabs").closest(".segmented").classList.toggle("hidden", ["dashboard", "tasks"].includes(state.page));
      $("#sectorFilter").classList.toggle("hidden", ["dashboard", "tasks", "districts", "profile"].includes(state.page));
      $("#searchBox").classList.toggle("hidden", ["dashboard", "districts", "profile"].includes(state.page));
      if (state.page === "dashboard") $("#headerPeriod").textContent = "Йиллик KPI";
      setPageMeta();
      $$(".nav-btn").forEach(btn => btn.classList.toggle("active", btn.dataset.page === state.page));
      ["dashboard", "tasks", "districts", "profile", "report"].forEach(page => {
        $(`#${page}Page`).classList.toggle("hidden", page !== state.page);
      });
      renderDashboard();
      renderTasksPage();
      renderDistrictsPage();
      renderProfilePage();
      renderReportPage();
      if (pageChanged) {
        renderedPage = state.page;
        if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
        window.scrollTo({ top: 0, left: 0, behavior: "auto" });
        requestAnimationFrame(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }));
        setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }), 0);
        setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }), 80);
        setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }), 180);
        setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }), 320);
      }
    }

    $$(".nav-btn").forEach(btn => btn.addEventListener("click", () => {
      state.page = btn.dataset.page;
      render();
    }));

    $("#sectorFilter").addEventListener("change", event => {
      state.sector = event.target.value;
      render();
    });

    $("#searchBox").addEventListener("input", event => {
      state.search = event.target.value;
      render();
    });

    render();
  </script>
</body>
</html>
"""


if __name__ == "__main__":
    main()
