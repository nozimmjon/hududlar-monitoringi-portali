from __future__ import annotations

import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DATA_JSON = ROOT / "platform prototypes" / "andijon_full_pilot_assets" / "andijon_full_pilot_data.json"
OUT_HTML = ROOT / "platform prototypes" / "andijon_full_pilot_v2.html"


def main() -> None:
    data = json.loads(DATA_JSON.read_text(encoding="utf-8"))
    payload = json.dumps(data, ensure_ascii=False)
    html = HTML.replace("__DATA__", payload.replace("</", "<\\/"))
    OUT_HTML.write_text(html, encoding="utf-8")
    print(OUT_HTML)


HTML = r"""<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Андижон вилояти мониторинг панели · v2</title>
  <style>
    :root {
      --bg: #f4f7fb;
      --panel: #ffffff;
      --ink: #172033;
      --muted: #667085;
      --line: #dbe3ef;
      --blue: #2458d8;
      --blue-2: #1747ad;
      --cyan: #0f8fb8;
      --green: #16834a;
      --amber: #b96a00;
      --red: #c73535;
      --grey: #7a8392;
      --shadow: 0 12px 32px rgba(20, 42, 80, .10);
      --radius: 8px;
      font-family: "Segoe UI", Arial, sans-serif;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: var(--ink);
      background: var(--bg);
      min-height: 100vh;
      overflow-x: hidden;
    }

    button, input, select { font: inherit; }
    button { cursor: pointer; }

    .app {
      display: grid;
      grid-template-columns: 276px minmax(0, 1fr);
      min-height: 100vh;
    }

    .sidebar {
      background: #111c35;
      color: #eef4ff;
      padding: 18px;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow: auto;
    }

    .brand {
      display: flex;
      gap: 12px;
      align-items: center;
      padding: 8px 4px 18px;
      border-bottom: 1px solid rgba(255,255,255,.12);
    }

    .brand-mark {
      width: 42px;
      height: 42px;
      border-radius: 8px;
      display: grid;
      place-items: center;
      font-weight: 800;
      background: linear-gradient(145deg, #2b66ff, #0e93c9);
      box-shadow: 0 8px 18px rgba(0,0,0,.25);
    }

    .brand h1 {
      margin: 0;
      font-size: 17px;
      line-height: 1.15;
      letter-spacing: 0;
    }

    .brand p {
      margin: 4px 0 0;
      color: #aebbd2;
      font-size: 12px;
    }

    .nav {
      display: grid;
      gap: 6px;
      margin-top: 18px;
    }

    .nav button {
      border: 0;
      color: #d8e3f8;
      background: transparent;
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      padding: 11px 12px;
      border-radius: 8px;
      text-align: left;
    }

    .nav button:hover { background: rgba(255,255,255,.08); }
    .nav button.active {
      background: #ffffff;
      color: #14306d;
      font-weight: 700;
    }

    .nav .dot {
      width: 9px;
      height: 9px;
      border-radius: 99px;
      background: currentColor;
      opacity: .75;
      flex: 0 0 auto;
    }

    .sidebar-note {
      margin-top: 24px;
      padding: 14px;
      border-radius: 8px;
      background: rgba(255,255,255,.08);
      color: #cbd8ed;
      font-size: 13px;
      line-height: 1.45;
    }

    main { min-width: 0; }

    .topbar {
      background: linear-gradient(90deg, #1c48b8, #2458d8);
      color: white;
      padding: 18px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .topbar h2 {
      margin: 0;
      font-size: 24px;
      line-height: 1.2;
      letter-spacing: 0;
    }

    .topbar p {
      margin: 4px 0 0;
      color: rgba(255,255,255,.78);
      font-size: 13px;
    }

    .toolbar {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .toolbar select,
    .toolbar input {
      border: 1px solid rgba(255,255,255,.35);
      color: white;
      background: rgba(255,255,255,.12);
      border-radius: 8px;
      padding: 9px 11px;
      min-height: 38px;
      outline: none;
    }

    .toolbar input::placeholder { color: rgba(255,255,255,.7); }

    .content {
      padding: 24px 28px 40px;
      max-width: 1500px;
      margin: 0 auto;
    }

    .view { display: none; }
    .view.active { display: block; }

    .section-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      margin: 0 0 14px;
    }

    .section-head h3 {
      margin: 0;
      font-size: 20px;
      letter-spacing: 0;
    }

    .section-head p {
      margin: 5px 0 0;
      color: var(--muted);
      font-size: 13px;
    }

    .grid {
      display: grid;
      gap: 14px;
    }

    .grid.cols-2 { grid-template-columns: 1.2fr .8fr; }
    .grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .grid.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

    .panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .panel.pad { padding: 18px; }

    .kpi-strip {
      display: grid;
      grid-template-columns: repeat(6, minmax(180px, 1fr));
      gap: 0;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      overflow: hidden;
      background: white;
      box-shadow: var(--shadow);
      margin-bottom: 18px;
    }

    .big-kpi {
      min-height: 132px;
      padding: 18px;
      border-right: 1px solid var(--line);
      display: grid;
      grid-template-columns: 52px minmax(0, 1fr);
      gap: 13px;
      align-items: start;
    }

    .big-kpi:last-child { border-right: 0; }
    .kpi-icon {
      width: 52px;
      height: 52px;
      border-radius: 8px;
      display: grid;
      place-items: center;
      background: linear-gradient(150deg, var(--blue), #3e86ff);
      color: white;
      font-weight: 800;
      box-shadow: 0 8px 18px rgba(36, 88, 216, .24);
    }

    .big-kpi .label {
      color: var(--muted);
      font-weight: 800;
      font-size: 13px;
      text-transform: uppercase;
      line-height: 1.15;
    }

    .big-kpi .value {
      margin-top: 4px;
      color: #1e55d6;
      font-size: 28px;
      line-height: 1.05;
      font-weight: 800;
      letter-spacing: 0;
      overflow-wrap: anywhere;
    }

    .big-kpi .meta {
      margin-top: 8px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.25;
    }

    .status {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 76px;
      padding: 4px 9px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      border: 1px solid transparent;
      white-space: nowrap;
    }

    .status.green { color: var(--green); background: #e8f6ef; border-color: #bae5cd; }
    .status.amber { color: var(--amber); background: #fff5df; border-color: #f2d394; }
    .status.red { color: var(--red); background: #fff0f0; border-color: #f4c2c2; }
    .status.grey { color: var(--grey); background: #f1f3f6; border-color: #d6dbe3; }

    .tabs {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }

    .tabs button {
      border: 1px solid var(--line);
      background: white;
      color: #344054;
      border-radius: 8px;
      padding: 9px 12px;
      min-height: 38px;
      font-weight: 700;
    }

    .tabs button.active {
      background: #1f57d2;
      border-color: #1f57d2;
      color: white;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    th {
      background: #f7f9fd;
      color: #4b5567;
      font-weight: 800;
      text-align: left;
      padding: 11px 12px;
      border-bottom: 1px solid var(--line);
      white-space: nowrap;
    }

    td {
      padding: 11px 12px;
      border-bottom: 1px solid #edf1f7;
      vertical-align: top;
    }

    tr:last-child td { border-bottom: 0; }
    tbody tr:hover td { background: #fbfdff; }

    .num { font-weight: 800; color: #1f2a44; white-space: nowrap; }
    .caption { display: block; color: var(--muted); font-size: 12px; margin-top: 3px; }
    .source { color: var(--muted); font-size: 12px; line-height: 1.35; }

    .summary-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 18px;
    }

    .mini {
      background: white;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 14px;
      min-height: 92px;
    }

    .mini .label { color: var(--muted); font-size: 12px; font-weight: 700; }
    .mini .value { margin-top: 6px; font-size: 25px; font-weight: 800; letter-spacing: 0; }
    .mini .meta { margin-top: 4px; color: var(--muted); font-size: 12px; }

    .district-layout {
      display: grid;
      grid-template-columns: 360px minmax(0, 1fr);
      gap: 16px;
      align-items: start;
    }

    .district-list {
      display: grid;
      gap: 8px;
      max-height: calc(100vh - 190px);
      overflow: auto;
      padding-right: 4px;
    }

    .district-button {
      width: 100%;
      border: 1px solid var(--line);
      background: white;
      border-radius: 8px;
      padding: 12px;
      text-align: left;
      display: grid;
      gap: 8px;
    }

    .district-button.active {
      border-color: #2458d8;
      box-shadow: 0 0 0 2px rgba(36,88,216,.12);
    }

    .district-button strong {
      font-size: 14px;
      color: var(--ink);
    }

    .progress {
      display: grid;
      gap: 5px;
    }

    .progress-line {
      height: 8px;
      border-radius: 99px;
      background: #edf1f7;
      overflow: hidden;
    }

    .progress-line span {
      display: block;
      height: 100%;
      background: linear-gradient(90deg, #c73535, #f5b744);
      width: var(--w, 0%);
    }

    .profile-head {
      padding: 18px;
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
    }

    .profile-head h3 { margin: 0; font-size: 22px; }
    .profile-head p { margin: 5px 0 0; color: var(--muted); font-size: 13px; }

    .callout {
      background: #eef5ff;
      border: 1px solid #cfe0ff;
      color: #193a7d;
      border-radius: 8px;
      padding: 13px 14px;
      font-size: 13px;
      line-height: 1.45;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 12px;
    }

    .task-card {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: white;
      padding: 13px;
      min-height: 136px;
    }

    .task-card strong { display: block; margin-bottom: 7px; }
    .task-card p { margin: 0; color: var(--muted); font-size: 13px; line-height: 1.45; }

    .empty {
      padding: 28px;
      text-align: center;
      color: var(--muted);
      background: white;
      border: 1px dashed var(--line);
      border-radius: 8px;
    }

    .hidden { display: none !important; }

    @media (max-width: 1200px) {
      .kpi-strip { grid-template-columns: repeat(3, minmax(180px, 1fr)); }
      .big-kpi:nth-child(3n) { border-right: 0; }
      .grid.cols-2, .district-layout { grid-template-columns: 1fr; }
    }

    @media (max-width: 780px) {
      .app { grid-template-columns: 1fr; }
      .sidebar {
        position: relative;
        height: auto;
        padding: 18px 34px 12px 16px;
        width: 100%;
        max-width: 100vw;
        overflow-x: hidden;
        box-sizing: border-box;
      }
      main { max-width: 100vw; overflow-x: hidden; }
      .nav { grid-template-columns: 1fr; }
      .nav button { min-width: 0; width: auto; max-width: calc(100vw - 50px); }
      .sidebar-note { max-width: calc(100vw - 50px); }
      .nav button span:last-child {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .topbar {
        padding: 16px 34px 16px 16px;
        align-items: flex-start;
        flex-direction: column;
      }
      .toolbar {
        display: grid;
        grid-template-columns: 1fr;
        justify-content: stretch;
        width: 100%;
      }
      .toolbar select, .toolbar input { width: 100%; }
      .content { padding: 16px 34px 16px 16px; }
      .kpi-strip, .summary-row, .grid.cols-3, .grid.cols-4 { grid-template-columns: 1fr; }
      .big-kpi { border-right: 0; border-bottom: 1px solid var(--line); }
      .big-kpi:last-child { border-bottom: 0; }
      .section-head { align-items: flex-start; flex-direction: column; }
      table { min-width: 760px; }
      .table-wrap { overflow-x: auto; }
      .district-list { max-height: none; }
    }
  </style>
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">AM</div>
        <div>
          <h1>Ҳудудлар мониторинги</h1>
          <p>Андижон вилояти пилоти</p>
        </div>
      </div>
      <nav class="nav" id="nav"></nav>
      <div class="sidebar-note">
        Бу версияда бош саҳифа KPI маркази сифатида қайта қурилди. Қолган бўлимларда фақат детал маълумотлар ва иш жараёни кўрсатилади.
      </div>
    </aside>

    <main>
      <header class="topbar">
        <div>
          <h2 id="pageTitle">Бошқарув панели</h2>
          <p id="pageSubtitle">Йиллик ва даврий KPI, режа-факт-ижро логикаси билан</p>
        </div>
        <div class="toolbar">
          <select id="periodSelect" aria-label="Давр">
            <option value="q1">I чорак</option>
            <option value="h1" selected>I ярим йиллик</option>
            <option value="m9">9 ой</option>
            <option value="year">Йил якуни</option>
          </select>
          <input id="searchInput" type="search" placeholder="Қидириш">
        </div>
      </header>

      <div class="content">
        <section id="view-dashboard" class="view active"></section>
        <section id="view-districts" class="view"></section>
        <section id="view-monitoring" class="view"></section>
        <section id="view-tasks" class="view"></section>
        <section id="view-sources" class="view"></section>
        <section id="view-quality" class="view"></section>
      </div>
    </main>
  </div>

  <script>
    const DATA = __DATA__;

    const PERIODS = {
      q1: "I чорак",
      h1: "I ярим йиллик",
      m9: "9 ой",
      year: "Йил якуни"
    };

    const VIEWS = [
      ["dashboard", "Бошқарув панели", "Йиллик ва даврий KPI, режа-факт-ижро логикаси билан"],
      ["districts", "Туман ва шаҳарлар", "Ҳар бир туман ичида вилоят KPI услубидаги кўрсаткичлар"],
      ["monitoring", "Мониторинг", "Манба жадваллардан йиғилган режа, факт ва ижро қаторлари"],
      ["tasks", "Топшириқлар", "Кафолат хати ва жадваллардан олинган амалий топшириқлар"],
      ["sources", "Манбалар", "Андижон пилоти учун фойдаланилган файллар ва варақлар"],
      ["quality", "Аниқлаштиришлар", "Прототипда очиқ қолган маълумот масалалари"]
    ];

    const state = {
      view: "dashboard",
      period: "h1",
      search: "",
      district: DATA.districts[0]?.name || ""
    };

    const $ = (selector) => document.querySelector(selector);
    const esc = (value) => String(value ?? "").replace(/[&<>"']/g, (ch) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[ch]));

    function num(value, digits = 1) {
      if (value === null || value === undefined || value === "" || Number.isNaN(Number(value))) return "—";
      return Number(value).toLocaleString("uz-UZ", { maximumFractionDigits: digits, minimumFractionDigits: 0 });
    }

    function pct(value, digits = 1) {
      if (value === null || value === undefined || value === "" || Number.isNaN(Number(value))) return "—";
      return `${num(value, digits)}%`;
    }

    function ratioPct(value) {
      if (value === null || value === undefined || Number.isNaN(Number(value))) return null;
      const n = Number(value);
      return n <= 2 ? n * 100 : n;
    }

    function execStatus(value) {
      if (value === null || value === undefined || Number.isNaN(Number(value))) return "grey";
      if (value >= 100) return "green";
      if (value >= 80) return "amber";
      return "red";
    }

    function statusText(status) {
      return { green: "Яшил", amber: "Сариқ", red: "Қизил", grey: "Маълумот йўқ" }[status] || "Маълумот йўқ";
    }

    function statusPill(status) {
      return `<span class="status ${status}">${statusText(status)}</span>`;
    }

    function valueBlock(value, unit = "", caption = "") {
      const text = value === null || value === undefined || value === "" ? "—" : value;
      const unitText = unit ? ` ${unit}` : "";
      const cap = caption ? `<span class="caption">${esc(caption)}</span>` : "";
      return `<span class="num">${esc(text)}${esc(unitText)}</span>${cap}`;
    }

    function valueWithGrowth(value, growth, unit, caption = "") {
      if (value === null || value === undefined) return valueBlock(null, "", caption);
      const growthText = growth === null || growth === undefined ? "" : ` / +${num(Number(growth) - 100, 1)}%`;
      return valueBlock(`${num(value, 1)}${growthText}`, unit, caption);
    }

    function executionCell(value) {
      const n = ratioPct(value);
      return n === null ? "—" : `<span class="num">${pct(n, 1)}</span>`;
    }

    function simpleExecution(fact, plan) {
      if (!Number.isFinite(Number(fact)) || !Number.isFinite(Number(plan)) || Number(plan) === 0) return null;
      return Number(fact) / Number(plan) * 100;
    }

    function annualKpis() {
      const r = DATA.regional;
      const yahm = r.macro.find((x) => x.indicator === "ЯҲМ") || r.macro[0];
      const budgetExec = simpleExecution(r.budget.year_expected, r.budget.year_plan);
      const exportExec = simpleExecution(r.export.year_expected, r.export.year_forecast);
      const investmentExec = ratioPct(r.foreign_investment.year_pct);
      return [
        {
          code: "ЯҲМ",
          label: "ЯҲМ",
          value: `+${num(yahm.year_growth - 100, 1)}%`,
          meta: `${num(yahm.year_value, 1)} ${yahm.unit}`,
          fact: valueBlock(null),
          plan: valueWithGrowth(yahm.year_value, yahm.year_growth, yahm.unit, "йиллик мақсад"),
          exec: null,
          source: yahm.source
        },
        {
          code: "ИНФ",
          label: "Инфляция",
          value: "6,6%",
          meta: "кафолат хатида йиллик чегара",
          fact: valueBlock(null),
          plan: valueBlock("6,6", "%", "йиллик мақсад"),
          exec: null,
          source: "0. Кафолат хати (Андижон).docx"
        },
        {
          code: "БЮД",
          label: "Бюджет",
          value: `+${num((budgetExec || 100) - 100, 1)}%`,
          meta: `${num(r.budget.year_expected, 1)} ${r.budget.unit}`,
          fact: valueBlock(num(r.budget.year_expected, 1), r.budget.unit, "кутилма"),
          plan: valueBlock(num(r.budget.year_plan, 1), r.budget.unit, "йиллик режа"),
          exec: budgetExec,
          source: r.budget.source
        },
        {
          code: "ИНВ",
          label: "Инвестиция",
          value: `${num(r.foreign_investment.year_expected / 1000, 2)} млрд $`,
          meta: `ижро ${pct(investmentExec, 1)}`,
          fact: valueBlock(num(r.foreign_investment.year_expected, 1), "млн $", "кутилма"),
          plan: valueBlock(num(r.foreign_investment.year_forecast, 1), "млн $", "йиллик прогноз"),
          exec: investmentExec,
          source: r.foreign_investment.source
        },
        {
          code: "ЭКС",
          label: "Экспорт",
          value: `+${num(r.export.year_growth - 100, 1)}%`,
          meta: `${num(r.export.year_expected / 1000, 1)} млн $`,
          fact: valueBlock(num(r.export.year_expected / 1000, 1), "млн $", "кутилма"),
          plan: valueBlock(num(r.export.year_forecast / 1000, 1), "млн $", "йиллик прогноз"),
          exec: exportExec,
          source: r.export.source
        },
        {
          code: "ИШС",
          label: "Ишсизлик",
          value: `${num(r.employment.unemployment_year, 1)}%`,
          meta: "йил якуни мақсади",
          fact: valueBlock(null),
          plan: valueBlock(num(r.employment.unemployment_year, 1), "%", "йиллик мақсад"),
          exec: null,
          source: r.employment.source
        }
      ];
    }

    function periodKpis(period) {
      const r = DATA.regional;
      const yahm = r.macro.find((x) => x.indicator === "ЯҲМ") || r.macro[0];
      const industry = r.macro.find((x) => x.indicator === "Саноат маҳсулотлари") || r.macro[1];
      const result = [];

      const macroCaption = period === "q1" ? "I чорак факт" : `${PERIODS[period]} мақсад`;
      result.push({
        sector: "Макро",
        indicator: "ЯҲМ",
        fact: period === "q1" ? valueWithGrowth(yahm.q1_value, yahm.q1_growth, yahm.unit, "факт") : valueBlock(null),
        plan: period === "q1" ? valueBlock(null) : valueWithGrowth(yahm[`${period}_value`], yahm[`${period}_growth`], yahm.unit, macroCaption),
        exec: null,
        source: yahm.source
      });
      result.push({
        sector: "Макро",
        indicator: "Саноат маҳсулотлари",
        fact: period === "q1" ? valueWithGrowth(industry.q1_value, industry.q1_growth, industry.unit, "факт") : valueBlock(null),
        plan: period === "q1" ? valueBlock(null) : valueWithGrowth(industry[`${period}_value`], industry[`${period}_growth`], industry.unit, macroCaption),
        exec: null,
        source: industry.source
      });

      if (period === "h1") {
        result.push({
          sector: "Бюджет",
          indicator: "Бюджет тушумлари",
          fact: valueBlock(num(r.budget.h1_expected, 1), r.budget.unit, "кутилма"),
          plan: valueBlock(num(r.budget.h1_plan, 1), r.budget.unit, "ярим йиллик режа"),
          exec: r.budget.h1_execution_pct,
          source: r.budget.source
        });
      }
      if (period === "year") {
        result.push({
          sector: "Бюджет",
          indicator: "Бюджет тушумлари",
          fact: valueBlock(num(r.budget.year_expected, 1), r.budget.unit, "кутилма"),
          plan: valueBlock(num(r.budget.year_plan, 1), r.budget.unit, "йиллик режа"),
          exec: simpleExecution(r.budget.year_expected, r.budget.year_plan),
          source: r.budget.source
        });
      }

      const binv = r.budget_investment;
      const binvValue = period === "q1" ? binv.q1_absorption : period === "h1" ? binv.h1_absorption : period === "year" ? binv.year_absorption : null;
      const binvExec = period === "q1" ? binv.q1_pct : period === "h1" ? binv.h1_pct : period === "year" ? binv.year_pct : null;
      result.push({
        sector: "Бюджет инвест",
        indicator: "Бюджет маблағлари ўзлаштирилиши",
        fact: valueBlock(binvValue === null ? null : num(binvValue, 1), binv.unit, period === "m9" ? "" : "жорий ҳисоб"),
        plan: valueBlock(num(binv.limit, 1), binv.unit, "йиллик лимит"),
        exec: binvExec,
        source: binv.source
      });

      const inv = r.foreign_investment;
      const invMap = {
        q1: [inv.q1_actual, inv.q1_plan, ratioPct(inv.q1_pct), "факт"],
        h1: [inv.h1_expected, inv.h1_plan, ratioPct(inv.h1_pct), "кутилма"],
        m9: [null, null, null, ""],
        year: [inv.year_expected, inv.year_forecast, ratioPct(inv.year_pct), "кутилма"]
      }[period];
      result.push({
        sector: "Инвестиция",
        indicator: "Хорижий инвестициялар",
        fact: valueBlock(invMap[0] === null ? null : num(invMap[0], 1), "млн $", invMap[3]),
        plan: valueBlock(invMap[1] === null ? null : num(invMap[1], 1), "млн $", period === "year" ? "йиллик прогноз" : "режа"),
        exec: invMap[2],
        source: inv.source
      });

      const exp = r.export;
      const expMap = {
        q1: [exp.q1_value, null, null, exp.q1_growth, "факт"],
        h1: [exp.h1_expected, null, null, exp.h1_growth, "кутилма"],
        m9: [null, null, null, null, ""],
        year: [exp.year_expected, exp.year_forecast, simpleExecution(exp.year_expected, exp.year_forecast), exp.year_growth, "кутилма"]
      }[period];
      result.push({
        sector: "Экспорт",
        indicator: "Экспорт ҳажми",
        fact: expMap[0] === null ? valueBlock(null) : valueBlock(`${num(expMap[0] / 1000, 1)} / +${num(expMap[3] - 100, 1)}%`, "млн $", expMap[4]),
        plan: expMap[1] === null ? valueBlock(null) : valueBlock(num(expMap[1] / 1000, 1), "млн $", "йиллик прогноз"),
        exec: expMap[2],
        source: exp.source
      });

      const emp = r.employment;
      result.push({
        sector: "Бандлик",
        indicator: "Ишсизлик даражаси",
        fact: valueBlock(null),
        plan: valueBlock(num(period === "year" ? emp.unemployment_year : emp.unemployment_h1, 1), "%", period === "year" ? "йиллик мақсад" : "ярим йиллик мақсад"),
        exec: null,
        source: emp.source
      });

      return result;
    }

    function renderNav() {
      $("#nav").innerHTML = VIEWS.map(([id, label]) => `
        <button class="${state.view === id ? "active" : ""}" data-view="${id}" type="button">
          <span class="dot"></span><span>${label}</span>
        </button>
      `).join("");
      $("#nav").querySelectorAll("button").forEach((btn) => {
        btn.addEventListener("click", () => {
          state.view = btn.dataset.view;
          render();
        });
      });
    }

    function renderDashboard() {
      const annual = annualKpis();
      const periodRows = periodKpis(state.period);
      const statusCounts = periodRows.reduce((acc, row) => {
        const key = execStatus(ratioPct(row.exec));
        acc[key] = (acc[key] || 0) + 1;
        return acc;
      }, {});
      $("#view-dashboard").innerHTML = `
        <div class="section-head">
          <div>
            <h3>Йиллик асосий KPI</h3>
            <p>Бу йирик KPI блок фақат бошқарув панелида туради. Ҳар бир KPI учун факт, режа ва ижро алоҳида жадвалда берилган.</p>
          </div>
        </div>
        <div class="kpi-strip">
          ${annual.map((item) => `
            <article class="big-kpi">
              <div class="kpi-icon">${esc(item.code)}</div>
              <div>
                <div class="label">${esc(item.label)}</div>
                <div class="value">${esc(item.value)}</div>
                <div class="meta">${esc(item.meta)}</div>
              </div>
            </article>
          `).join("")}
        </div>

        <div class="grid cols-2">
          <section class="panel">
            <div class="profile-head">
              <div>
                <h3>Йиллик KPI режа-факт-ижро</h3>
                <p>Йил якуни бўйича мавжуд режа, факт ёки кутилма ва ижро ҳисоблари.</p>
              </div>
            </div>
            <div class="table-wrap">${kpiTable(annual)}</div>
          </section>

          <section class="panel pad">
            <div class="summary-row" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-bottom: 0;">
              <div class="mini"><div class="label">Яшил</div><div class="value">${statusCounts.green || 0}</div><div class="meta">${PERIODS[state.period]} бўйича</div></div>
              <div class="mini"><div class="label">Сариқ</div><div class="value">${statusCounts.amber || 0}</div><div class="meta">кузатув талаб қилади</div></div>
              <div class="mini"><div class="label">Қизил</div><div class="value">${statusCounts.red || 0}</div><div class="meta">ижро паст</div></div>
              <div class="mini"><div class="label">Маълумот йўқ</div><div class="value">${statusCounts.grey || 0}</div><div class="meta">факт киритилмаган</div></div>
            </div>
          </section>
        </div>

        <section class="panel" style="margin-top: 16px;">
          <div class="profile-head">
            <div>
              <h3>Даврлар бўйича KPI</h3>
              <p>Бошқарув панелида ҳар бир чорак/давр алоҳида кўрилади.</p>
            </div>
          </div>
          <div style="padding: 16px 16px 0;">${periodTabs()}</div>
          <div class="table-wrap">${kpiTable(periodRows)}</div>
        </section>

        <section class="grid cols-3" style="margin-top: 16px;">
          <div class="callout">Макро жадвалларда I чорак фактлари ва кейинги давр мақсадлари бор. Шунинг учун факт йўқ даврларда ижро автоматик ҳисобланмайди.</div>
          <div class="callout">Бюджет, инвестиция ва айрим экспорт қаторларида кутилма берилган. Прототипда улар факт устунида алоҳида изоҳ билан кўрсатилди.</div>
          <div class="callout">Туманлар кесимида рейтинг ўрнига бажарилмаган топшириқлар сони умумий топшириқлар сонига нисбатан берилди.</div>
        </section>
      `;
      wirePeriodTabs();
    }

    function periodTabs() {
      return `<div class="tabs">${Object.entries(PERIODS).map(([id, label]) => `
        <button class="${state.period === id ? "active" : ""}" data-period="${id}" type="button">${label}</button>
      `).join("")}</div>`;
    }

    function wirePeriodTabs() {
      document.querySelectorAll("[data-period]").forEach((btn) => {
        btn.addEventListener("click", () => {
          state.period = btn.dataset.period;
          $("#periodSelect").value = state.period;
          render();
        });
      });
    }

    function kpiTable(rows) {
      return `<table>
        <thead>
          <tr>
            <th>KPI</th>
            <th>Факт</th>
            <th>Режа</th>
            <th>Ижро</th>
            <th>Ҳолат</th>
            <th>Манба</th>
          </tr>
        </thead>
        <tbody>
          ${rows.map((row) => {
            const exec = ratioPct(row.exec);
            const status = execStatus(exec);
            return `<tr>
              <td><strong>${esc(row.indicator || row.label)}</strong>${row.sector ? `<span class="caption">${esc(row.sector)}</span>` : ""}</td>
              <td>${row.fact}</td>
              <td>${row.plan}</td>
              <td>${executionCell(exec)}</td>
              <td>${statusPill(status)}</td>
              <td class="source">${esc(row.source || "")}</td>
            </tr>`;
          }).join("")}
        </tbody>
      </table>`;
    }

    function districtRows(district) {
      const d = district.data;
      const period = state.period;
      const rows = [];
      ["industry", "agriculture", "services"].forEach((key) => {
        const item = d[key];
        if (!item) return;
        rows.push({
          sector: "Макро",
          indicator: item.label,
          fact: period === "q1" ? valueWithGrowth(item.q1_value, item.q1_growth, item.unit, "факт") : valueBlock(null),
          plan: period === "q1" ? valueBlock(null) : valueWithGrowth(item[`${period}_value`], item[`${period}_growth`], item.unit, `${PERIODS[period]} мақсад`),
          exec: null,
          source: item.source
        });
      });
      if (d.budget && (period === "h1" || period === "year")) {
        rows.push({
          sector: "Бюджет",
          indicator: "Бюджет тушумлари",
          fact: valueBlock(num(period === "h1" ? d.budget.h1_expected : d.budget.year_expected, 1), d.budget.unit, "кутилма"),
          plan: valueBlock(num(period === "h1" ? d.budget.h1_plan : d.budget.year_plan, 1), d.budget.unit, "режа"),
          exec: period === "h1" ? d.budget.h1_execution_pct : simpleExecution(d.budget.year_expected, d.budget.year_plan),
          source: d.budget.source
        });
      }
      if (d.budget_investment) {
        const b = d.budget_investment;
        const fact = period === "q1" ? b.q1_absorption : period === "h1" ? b.h1_absorption : period === "year" ? b.year_absorption : null;
        const exec = period === "q1" ? b.q1_pct : period === "h1" ? b.h1_pct : period === "year" ? b.year_pct : null;
        rows.push({
          sector: "Бюджет инвест",
          indicator: "Бюджет маблағлари ўзлаштирилиши",
          fact: valueBlock(fact === null ? null : num(fact, 1), b.unit, fact === null ? "" : "жорий ҳисоб"),
          plan: valueBlock(num(b.limit, 1), b.unit, "йиллик лимит"),
          exec,
          source: b.source
        });
      }
      if (d.foreign_investment) {
        const i = d.foreign_investment;
        const map = {
          q1: [i.q1_actual, i.q1_plan, ratioPct(i.q1_pct), "факт"],
          h1: [i.h1_expected, i.h1_plan, ratioPct(i.h1_pct), "кутилма"],
          m9: [null, null, null, ""],
          year: [i.year_expected, i.year_forecast, ratioPct(i.year_pct), "кутилма"]
        }[period];
        rows.push({
          sector: "Инвестиция",
          indicator: "Хорижий инвестициялар",
          fact: valueBlock(map[0] === null ? null : num(map[0], 1), "млн $", map[3]),
          plan: valueBlock(map[1] === null ? null : num(map[1], 1), "млн $", "режа"),
          exec: map[2],
          source: i.source
        });
      }
      if (d.export) {
        const e = d.export;
        const map = {
          q1: [e.q1_value, null, null, e.q1_growth, "факт"],
          h1: [e.h1_expected, null, null, e.h1_growth, "кутилма"],
          m9: [null, null, null, null, ""],
          year: [e.year_expected, e.year_forecast, simpleExecution(e.year_expected, e.year_forecast), e.year_growth, "кутилма"]
        }[period];
        rows.push({
          sector: "Экспорт",
          indicator: "Экспорт ҳажми",
          fact: map[0] === null ? valueBlock(null) : valueBlock(`${num(map[0] / 1000, 1)} / +${num(map[3] - 100, 1)}%`, "млн $", map[4]),
          plan: map[1] === null ? valueBlock(null) : valueBlock(num(map[1] / 1000, 1), "млн $", "йиллик прогноз"),
          exec: map[2],
          source: e.source
        });
      }
      if (d.employment) {
        rows.push({
          sector: "Бандлик",
          indicator: "Иш ўринлари",
          fact: valueBlock(null),
          plan: valueBlock(num(period === "year" ? d.employment.jobs_year : d.employment.jobs_h1, 3), "минг нафар", "мақсад"),
          exec: null,
          source: d.employment.source
        });
        rows.push({
          sector: "Бандлик",
          indicator: "Ишсизлик даражаси",
          fact: valueBlock(null),
          plan: valueBlock(num(period === "year" ? d.employment.unemployment_year : d.employment.unemployment_h1, 1), "%", "мақсад"),
          exec: null,
          source: d.employment.source
        });
      }
      return rows;
    }

    function renderDistricts() {
      const selected = DATA.districts.find((d) => d.name === state.district) || DATA.districts[0];
      const unfinished = selected.debt?.task_unfinished || 0;
      const total = selected.debt?.task_total || 0;
      const pctUnfinished = total ? Math.round(unfinished / total * 100) : 0;
      $("#view-districts").innerHTML = `
        <div class="district-layout">
          <aside class="district-list">
            ${DATA.districts.map((d) => {
              const u = d.debt?.task_unfinished || 0;
              const t = d.debt?.task_total || 0;
              const w = t ? Math.round(u / t * 100) : 0;
              return `<button class="district-button ${d.name === selected.name ? "active" : ""}" data-district="${esc(d.name)}" type="button">
                <strong>${esc(d.name)}</strong>
                <span class="caption">Бажарилмаган топшириқлар: ${u} / ${t}</span>
                <span class="progress"><span class="progress-line"><span style="--w:${w}%"></span></span></span>
              </button>`;
            }).join("")}
          </aside>
          <section class="panel">
            <div class="profile-head">
              <div>
                <h3>${esc(selected.name)}</h3>
                <p>${esc(selected.owner)} · ${PERIODS[state.period]}</p>
              </div>
              <span class="status ${unfinished ? "amber" : "green"}">${unfinished} / ${total} бажарилмаган</span>
            </div>
            <div class="summary-row" style="padding: 16px; margin-bottom: 0;">
              <div class="mini"><div class="label">Бажарилмаган</div><div class="value">${unfinished}</div><div class="meta">умумий ${total} топшириқдан</div></div>
              <div class="mini"><div class="label">Улуш</div><div class="value">${pctUnfinished}%</div><div class="meta">эътибор талаб қилади</div></div>
              <div class="mini"><div class="label">Давр</div><div class="value" style="font-size:20px;">${PERIODS[state.period]}</div><div class="meta">юқоридаги танлов</div></div>
              <div class="mini"><div class="label">Манба қамрови</div><div class="value">${Object.keys(selected.data || {}).length}</div><div class="meta">жадвал блоклари</div></div>
            </div>
            <div class="table-wrap">${kpiTable(districtRows(selected))}</div>
          </section>
        </div>
      `;
      document.querySelectorAll("[data-district]").forEach((btn) => {
        btn.addEventListener("click", () => {
          state.district = btn.dataset.district;
          renderDistricts();
        });
      });
    }

    function filteredMonitoring() {
      const q = state.search.trim().toLowerCase();
      return DATA.monitoring.filter((row) => {
        const periodLabel = PERIODS[state.period];
        const periodOk = row.period === periodLabel || state.period === "year" && row.period === "2026 йил";
        const haystack = `${row.region} ${row.indicator} ${row.sector} ${row.source}`.toLowerCase();
        return periodOk && (!q || haystack.includes(q));
      });
    }

    function renderMonitoring() {
      const rows = filteredMonitoring().slice(0, 220);
      $("#view-monitoring").innerHTML = `
        <div class="section-head">
          <div>
            <h3>${PERIODS[state.period]} мониторинг қаторлари</h3>
            <p>Бу жадвал импорт қилинган манба қаторларини сақлайди. Бош саҳифада фақат тушунарли KPI агрегатлари қолдирилди.</p>
          </div>
        </div>
        <section class="panel table-wrap">
          <table>
            <thead><tr><th>ID</th><th>Ҳудуд</th><th>KPI</th><th>Давр</th><th>Факт</th><th>Режа</th><th>Ижро</th><th>Ҳолат</th><th>Манба</th></tr></thead>
            <tbody>
              ${rows.map((row) => `<tr>
                <td>${esc(row.id)}</td>
                <td>${esc(row.region)}</td>
                <td><strong>${esc(row.indicator)}</strong><span class="caption">${esc(row.sector)}</span></td>
                <td>${esc(row.period)}</td>
                <td>${valueBlock(row.actual_value === null ? null : num(row.actual_value, 1), row.unit || "")}</td>
                <td>${valueBlock(row.target_value === null ? null : num(row.target_value, 1), row.unit || "")}</td>
                <td>${executionCell(row.execution_pct)}</td>
                <td>${statusPill(row.status)}</td>
                <td class="source">${esc(row.source)}</td>
              </tr>`).join("")}
            </tbody>
          </table>
        </section>
        ${rows.length ? "" : `<div class="empty">Бу давр учун қатор топилмади.</div>`}
      `;
    }

    function renderTasks() {
      const q = state.search.trim().toLowerCase();
      const tasks = DATA.tasks.filter((task) => !q || `${task.title} ${task.detail} ${task.sector}`.toLowerCase().includes(q)).slice(0, 80);
      $("#view-tasks").innerHTML = `
        <div class="section-head">
          <div>
            <h3>Топшириқлар реестри</h3>
            <p>Кафолат хати матнидан ва жадваллардан чиқадиган вазифалар шу ерда иш жараёнига айланади.</p>
          </div>
        </div>
        <div class="cards">
          ${tasks.map((task) => `<article class="task-card">
            <strong>${esc(task.title)}</strong>
            <p>${esc(task.detail || "")}</p>
            <span class="caption">${esc(task.sector || "Сектор")} · ${esc(task.period || "давр аниқланади")}</span>
          </article>`).join("")}
        </div>
      `;
    }

    function renderSources() {
      $("#view-sources").innerHTML = `
        <div class="section-head">
          <div>
            <h3>Манба реестри</h3>
            <p>${DATA.meta.source_file_count} та файл, ${DATA.sources.length} та варақ/блок аудит қилинган.</p>
          </div>
        </div>
        <section class="panel table-wrap">
          <table>
            <thead><tr><th>Файл</th><th>Тур</th><th>Варақ</th><th>Қатор</th><th>Устун</th><th>Тўлдирилган қатор</th></tr></thead>
            <tbody>
              ${DATA.sources.map((src) => `<tr>
                <td><strong>${esc(src.file)}</strong></td>
                <td>${esc(src.type)}</td>
                <td>${esc(src.sheet || "—")}</td>
                <td>${esc(src.rows || "—")}</td>
                <td>${esc(src.cols || "—")}</td>
                <td>${esc(src.nonempty_rows || "—")}</td>
              </tr>`).join("")}
            </tbody>
          </table>
        </section>
      `;
    }

    function renderQuality() {
      const items = [
        "Макро файлларда I чорак фактлари бор, кейинги даврлар кўп ҳолатда мақсад сифатида келган. Шу сабабли ижро фақат факт ва режа жуфти бор қаторларда ҳисобланади.",
        "Экспорт бўйича I ярим йиллик ва йил якунида кутилма/прогноз бор; айрим даврларда алоҳида режа устуни йўқ. UI бундай қаторларда ижрони мажбуран ҳисобламайди.",
        "Инфляция рақамлари кафолат хатидан олинган: II чорак 2,9%, йил якуни 6,6%. Факт манбаси берилмагунча ижро бўш қолади.",
        "Туманлар учун рейтинг ишлатилмайди. Ҳар бир туманда бажарилмаган топшириқлар сони умумий топшириқларга нисбатан кўрсатилади.",
        "Кейинги қадамда ҳар бир панел алоҳида текширилиб, қайси устун факт, қайси устун режа, қайси бири кутилма экани маълумот луғатига ёзилади."
      ];
      $("#view-quality").innerHTML = `
        <div class="section-head">
          <div>
            <h3>Аниқлаштиришлар ва дизайн қарорлари</h3>
            <p>Бу бўлим прототипдаги ноаниқ жойларни очиқ кўрсатиш учун қўшилди.</p>
          </div>
        </div>
        <section class="grid">
          ${items.map((item) => `<div class="callout">${esc(item)}</div>`).join("")}
        </section>
      `;
    }

    function render() {
      const meta = VIEWS.find(([id]) => id === state.view) || VIEWS[0];
      $("#pageTitle").textContent = meta[1];
      $("#pageSubtitle").textContent = meta[2];
      document.querySelectorAll(".view").forEach((view) => view.classList.remove("active"));
      $(`#view-${state.view}`).classList.add("active");
      renderNav();
      if (state.view === "dashboard") renderDashboard();
      if (state.view === "districts") renderDistricts();
      if (state.view === "monitoring") renderMonitoring();
      if (state.view === "tasks") renderTasks();
      if (state.view === "sources") renderSources();
      if (state.view === "quality") renderQuality();
    }

    $("#periodSelect").addEventListener("change", (event) => {
      state.period = event.target.value;
      render();
    });

    $("#searchInput").addEventListener("input", (event) => {
      state.search = event.target.value;
      if (["monitoring", "tasks"].includes(state.view)) render();
    });

    render();
  </script>
</body>
</html>
"""


if __name__ == "__main__":
    main()
