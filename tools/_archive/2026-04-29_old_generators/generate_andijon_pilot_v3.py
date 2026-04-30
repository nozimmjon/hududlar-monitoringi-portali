from __future__ import annotations

import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DATA_JSON = ROOT / "platform prototypes" / "andijon_full_pilot_assets" / "andijon_full_pilot_data.json"
OUT_HTML = ROOT / "platform prototypes" / "andijon_full_pilot_v3.html"


def main() -> None:
    data = json.loads(DATA_JSON.read_text(encoding="utf-8"))
    payload = json.dumps(data, ensure_ascii=False)
    OUT_HTML.write_text(HTML.replace("__DATA__", payload.replace("</", "<\\/")), encoding="utf-8")
    print(OUT_HTML)


HTML = r"""<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Андижон вилояти мониторинги · v3</title>
  <style>
    :root {
      --paper: #f6f4ef;
      --surface: #fffdf8;
      --surface-2: #ebe8df;
      --ink: #1e2430;
      --soft: #5f6876;
      --line: #d8d2c4;
      --navy: #163765;
      --blue: #2456b8;
      --green: #187149;
      --amber: #9a6100;
      --red: #b83232;
      --muted: #78818e;
      --space-1: 4px;
      --space-2: 8px;
      --space-3: 12px;
      --space-4: 16px;
      --space-5: 24px;
      --space-6: 32px;
      --radius: 6px;
      --shadow: 0 16px 40px rgba(31, 37, 51, .08);
      font-family: "Aptos", "Segoe UI", sans-serif;
    }

    * { box-sizing: border-box; }
    html { background: var(--paper); }
    body {
      margin: 0;
      color: var(--ink);
      background:
        linear-gradient(90deg, rgba(22,55,101,.05) 1px, transparent 1px) 0 0 / 64px 64px,
        var(--paper);
      min-height: 100vh;
    }

    button, input, select { font: inherit; }
    button { cursor: pointer; }

    .shell {
      max-width: 1540px;
      margin: 0 auto;
      padding: var(--space-4) var(--space-5) var(--space-6);
    }

    .masthead {
      background: var(--navy);
      color: #f7fbff;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
    }

    .mast-main {
      display: grid;
      grid-template-columns: minmax(260px, 1fr) auto;
      gap: var(--space-5);
      padding: var(--space-5);
      align-items: end;
      border-bottom: 1px solid rgba(255,255,255,.16);
    }

    .eyebrow {
      font-size: 12px;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: #b9c9df;
      font-weight: 700;
    }

    h1, h2, h3 { margin: 0; letter-spacing: 0; }
    h1 { font-size: 28px; line-height: 1.08; margin-top: 5px; }
    .mast-main p { margin: 7px 0 0; color: #d9e4f2; font-size: 14px; }

    .controls {
      display: flex;
      gap: var(--space-3);
      align-items: center;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    select, input {
      border: 1px solid rgba(255,255,255,.32);
      border-radius: var(--radius);
      background: rgba(255,255,255,.08);
      color: inherit;
      padding: 10px 12px;
      min-height: 40px;
      outline: none;
    }

    input::placeholder { color: rgba(255,255,255,.66); }

    .mast-facts {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      border-bottom: 1px solid rgba(255,255,255,.16);
    }

    .fact {
      padding: var(--space-4) var(--space-5);
      border-right: 1px solid rgba(255,255,255,.16);
    }

    .fact:last-child { border-right: 0; }
    .fact span { display: block; color: #b9c9df; font-size: 12px; margin-bottom: 4px; }
    .fact strong { display: block; color: white; font-size: 18px; }

    .nav {
      display: flex;
      gap: 0;
      overflow-x: auto;
      background: rgba(255,255,255,.06);
    }

    .nav button {
      border: 0;
      border-right: 1px solid rgba(255,255,255,.13);
      color: #dce8f7;
      background: transparent;
      padding: 13px 18px;
      min-height: 48px;
      white-space: nowrap;
      font-weight: 700;
    }

    .nav button.active {
      background: var(--surface);
      color: var(--navy);
    }

    .view { display: none; padding-top: var(--space-5); }
    .view.active { display: block; }

    .intro-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.45fr) minmax(360px, .55fr);
      gap: var(--space-5);
      align-items: start;
    }

    .block {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .block-head {
      padding: var(--space-4) var(--space-5);
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: var(--space-4);
      background: color-mix(in oklch, var(--surface), var(--surface-2) 38%);
    }

    .block-head h2 { font-size: 20px; }
    .block-head p { margin: 5px 0 0; color: var(--soft); font-size: 13px; line-height: 1.35; }

    .period-tabs {
      display: inline-flex;
      gap: 2px;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      overflow: hidden;
      background: var(--surface);
    }

    .period-tabs button {
      border: 0;
      border-right: 1px solid var(--line);
      background: transparent;
      color: var(--soft);
      padding: 8px 11px;
      font-size: 13px;
      font-weight: 700;
      white-space: nowrap;
    }

    .period-tabs button:last-child { border-right: 0; }
    .period-tabs button.active { color: white; background: var(--blue); }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    th {
      text-align: left;
      color: #485160;
      background: #f2f0e8;
      border-bottom: 1px solid var(--line);
      padding: 10px 12px;
      font-weight: 800;
      white-space: nowrap;
    }

    td {
      padding: 12px;
      border-bottom: 1px solid #e8e3d8;
      vertical-align: top;
    }

    tr:last-child td { border-bottom: 0; }
    tbody tr:hover td { background: #fbfaf5; }

    .kpi-name {
      font-weight: 800;
      color: #111827;
      min-width: 155px;
    }

    .sector-label {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-weight: 500;
      font-size: 12px;
    }

    .number {
      font-weight: 800;
      color: #111827;
      white-space: nowrap;
    }

    .sub {
      display: block;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
      margin-top: 3px;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 3px 8px;
      font-size: 11px;
      font-weight: 800;
      border: 1px solid transparent;
      white-space: nowrap;
    }

    .badge.fact { color: var(--green); background: #e6f3eb; border-color: #b9dec8; }
    .badge.expected { color: var(--amber); background: #fff2d6; border-color: #e6c987; }
    .badge.missing { color: var(--muted); background: #eeece5; border-color: #dad4c8; }
    .badge.green { color: var(--green); background: #e6f3eb; border-color: #b9dec8; }
    .badge.amber { color: var(--amber); background: #fff2d6; border-color: #e6c987; }
    .badge.red { color: var(--red); background: #fde7e7; border-color: #efb5b5; }
    .badge.grey { color: var(--muted); background: #eeece5; border-color: #dad4c8; }

    .priority-list {
      display: grid;
      gap: 0;
    }

    .priority-item {
      padding: 13px var(--space-5);
      border-bottom: 1px solid var(--line);
      display: grid;
      grid-template-columns: 9px minmax(0, 1fr);
      gap: 11px;
    }

    .priority-item:last-child { border-bottom: 0; }
    .stripe { width: 9px; border-radius: 99px; background: var(--muted); }
    .stripe.red { background: var(--red); }
    .stripe.amber { background: var(--amber); }
    .stripe.grey { background: var(--muted); }
    .priority-item strong { display: block; font-size: 13px; }
    .priority-item span { display: block; color: var(--soft); font-size: 12px; margin-top: 4px; line-height: 1.35; }

    .metric-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      border-top: 1px solid var(--line);
    }

    .metric {
      padding: var(--space-4) var(--space-5);
      border-right: 1px solid var(--line);
      background: var(--surface);
    }

    .metric:last-child { border-right: 0; }
    .metric span { display: block; color: var(--muted); font-size: 12px; font-weight: 700; }
    .metric strong { display: block; margin-top: 4px; font-size: 23px; letter-spacing: 0; }

    .district-layout {
      display: grid;
      grid-template-columns: 330px minmax(0, 1fr);
      gap: var(--space-5);
      align-items: start;
    }

    .district-list {
      display: grid;
      gap: var(--space-2);
      max-height: calc(100vh - 230px);
      overflow: auto;
      padding-right: 4px;
    }

    .district-btn {
      border: 1px solid var(--line);
      background: var(--surface);
      border-radius: var(--radius);
      padding: 12px;
      text-align: left;
      display: grid;
      gap: 7px;
    }

    .district-btn.active {
      border-color: var(--blue);
      box-shadow: 0 0 0 2px rgba(36,86,184,.14);
    }

    .district-btn strong { font-size: 14px; }
    .debt-line {
      height: 7px;
      background: #e6e1d7;
      border-radius: 999px;
      overflow: hidden;
    }

    .debt-line span {
      display: block;
      height: 100%;
      width: var(--w);
      background: linear-gradient(90deg, var(--red), var(--amber));
    }

    .split {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--space-5);
    }

    .table-wrap { overflow-x: auto; }
    .note {
      padding: var(--space-4) var(--space-5);
      color: var(--soft);
      font-size: 13px;
      line-height: 1.45;
      background: #f6f2e8;
      border-top: 1px solid var(--line);
    }

    .empty {
      padding: var(--space-6);
      text-align: center;
      color: var(--muted);
    }

    @media (max-width: 1100px) {
      .mast-main, .intro-grid, .district-layout, .split { grid-template-columns: 1fr; }
      .controls { justify-content: flex-start; }
      .mast-facts, .metric-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 680px) {
      .shell { padding: 10px; }
      .mast-main { padding: var(--space-4); }
      h1 { font-size: 23px; }
      .controls { display: grid; grid-template-columns: 1fr; }
      select, input { width: 100%; }
      .mast-facts, .metric-row { grid-template-columns: 1fr; }
      .fact, .metric { border-right: 0; border-bottom: 1px solid rgba(255,255,255,.16); }
      .metric { border-bottom-color: var(--line); }
      .block-head { flex-direction: column; align-items: flex-start; }
      table { min-width: 780px; }
      .district-list { max-height: none; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <header class="masthead">
      <div class="mast-main">
        <div>
          <div class="eyebrow">Ҳудудлар мониторинги портали · пилот</div>
          <h1>Андижон вилояти ижро ҳолати</h1>
          <p>Режа, факт, кутилма ва бажарилмаган топшириқлар бир жойда.</p>
        </div>
        <div class="controls">
          <select id="periodSelect" aria-label="Давр">
            <option value="q1">I чорак</option>
            <option value="h1" selected>I ярим йиллик</option>
            <option value="m9">9 ой</option>
            <option value="year">Йил якуни</option>
          </select>
          <input id="searchInput" type="search" placeholder="Кўрсаткич, туман ёки манба">
        </div>
      </div>
      <div class="mast-facts" id="mastFacts"></div>
      <nav class="nav" id="nav"></nav>
    </header>

    <section id="view-dashboard" class="view active"></section>
    <section id="view-districts" class="view"></section>
    <section id="view-sectors" class="view"></section>
    <section id="view-tasks" class="view"></section>
    <section id="view-sources" class="view"></section>
  </div>

  <script>
    const DATA = __DATA__;

    const PERIODS = { q1: "I чорак", h1: "I ярим йиллик", m9: "9 ой", year: "Йил якуни" };
    const NAV = [
      ["dashboard", "Бошқарув панели"],
      ["districts", "Туманлар"],
      ["sectors", "Секторлар"],
      ["tasks", "Топшириқлар"],
      ["sources", "Манбалар"]
    ];

    const state = {
      view: "dashboard",
      period: "h1",
      district: DATA.districts[0]?.name || "",
      search: ""
    };

    const $ = (selector) => document.querySelector(selector);
    const esc = (value) => String(value ?? "").replace(/[&<>"']/g, (ch) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[ch]));

    function fmt(value, digits = 1) {
      if (value === null || value === undefined || value === "" || Number.isNaN(Number(value))) return "—";
      return Number(value).toLocaleString("uz-UZ", { maximumFractionDigits: digits, minimumFractionDigits: 0 });
    }

    function ratio(value) {
      if (value === null || value === undefined || Number.isNaN(Number(value))) return null;
      const n = Number(value);
      return n <= 2 ? n * 100 : n;
    }

    function calc(fact, plan) {
      if (!Number.isFinite(Number(fact)) || !Number.isFinite(Number(plan)) || Number(plan) === 0) return null;
      return Number(fact) / Number(plan) * 100;
    }

    function statusFor(exec, factKind = "missing") {
      if (exec === null || exec === undefined || Number.isNaN(Number(exec))) {
        return factKind === "missing" ? "grey" : "amber";
      }
      if (exec >= 100) return "green";
      if (exec >= 80) return "amber";
      return "red";
    }

    function statusText(status) {
      return { green: "Яшил", amber: "Назорат", red: "Ортда", grey: "Факт йўқ" }[status] || "Факт йўқ";
    }

    function badge(text, cls) {
      return `<span class="badge ${cls}">${esc(text)}</span>`;
    }

    function val(value, unit = "", hint = "", kind = "") {
      if (value === null || value === undefined || value === "") {
        return `<span class="number">—</span><span class="sub">факт киритилмаган</span>`;
      }
      const kindBadge = kind ? ` ${badge(kind === "expected" ? "Кутилма" : "Факт", kind)}` : "";
      const hintText = hint ? `<span class="sub">${esc(hint)}${kindBadge}</span>` : `<span class="sub">${kindBadge}</span>`;
      return `<span class="number">${esc(value)}${unit ? ` ${esc(unit)}` : ""}</span>${hintText}`;
    }

    function withGrowth(value, growth, unit, hint, kind) {
      if (value === null || value === undefined) return val(null);
      const g = growth === null || growth === undefined ? "" : ` / +${fmt(Number(growth) - 100, 1)}%`;
      return val(`${fmt(value, 1)}${g}`, unit, hint, kind);
    }

    function execCell(exec) {
      const n = ratio(exec);
      if (n === null) return `<span class="number">—</span><span class="sub">ҳисобланмайди</span>`;
      return `<span class="number">${fmt(n, 1)}%</span>`;
    }

    function sourceText(source) {
      return `<span class="sub">${esc(source || "")}</span>`;
    }

    function regionalRows(period = state.period) {
      const r = DATA.regional;
      const yahm = r.macro.find((x) => x.indicator === "ЯҲМ") || r.macro[0];
      const industry = r.macro.find((x) => x.indicator === "Саноат маҳсулотлари") || r.macro[1];
      const emp = r.employment;
      const rows = [];

      const macroFact = period === "q1";
      rows.push({
        sector: "Макро",
        kpi: "ЯҲМ",
        fact: macroFact ? withGrowth(yahm.q1_value, yahm.q1_growth, yahm.unit, "I чорак", "fact") : val(null),
        plan: period === "q1" ? val(null) : withGrowth(yahm[`${period}_value`], yahm[`${period}_growth`], yahm.unit, PERIODS[period]),
        exec: null,
        factKind: macroFact ? "fact" : "missing",
        source: yahm.source
      });
      rows.push({
        sector: "Макро",
        kpi: "Саноат маҳсулотлари",
        fact: macroFact ? withGrowth(industry.q1_value, industry.q1_growth, industry.unit, "I чорак", "fact") : val(null),
        plan: period === "q1" ? val(null) : withGrowth(industry[`${period}_value`], industry[`${period}_growth`], industry.unit, PERIODS[period]),
        exec: null,
        factKind: macroFact ? "fact" : "missing",
        source: industry.source
      });

      if (period === "h1") {
        rows.push({
          sector: "Бюджет",
          kpi: "Бюджет тушумлари",
          fact: val(fmt(r.budget.h1_expected, 1), r.budget.unit, "I ярим йиллик", "expected"),
          plan: val(fmt(r.budget.h1_plan, 1), r.budget.unit, "режа"),
          exec: r.budget.h1_execution_pct,
          factKind: "expected",
          source: r.budget.source
        });
      }
      if (period === "year") {
        rows.push({
          sector: "Бюджет",
          kpi: "Бюджет тушумлари",
          fact: val(fmt(r.budget.year_expected, 1), r.budget.unit, "йил якуни", "expected"),
          plan: val(fmt(r.budget.year_plan, 1), r.budget.unit, "режа"),
          exec: calc(r.budget.year_expected, r.budget.year_plan),
          factKind: "expected",
          source: r.budget.source
        });
      }

      const binv = r.budget_investment;
      const bFact = period === "q1" ? binv.q1_absorption : period === "h1" ? binv.h1_absorption : period === "year" ? binv.year_absorption : null;
      const bExec = period === "q1" ? binv.q1_pct : period === "h1" ? binv.h1_pct : period === "year" ? binv.year_pct : null;
      rows.push({
        sector: "Бюджет инвестиция",
        kpi: "Бюджет маблағлари ўзлаштирилиши",
        fact: bFact === null ? val(null) : val(fmt(bFact, 1), binv.unit, PERIODS[period], period === "year" ? "expected" : "fact"),
        plan: val(fmt(binv.limit, 1), binv.unit, "йиллик лимит"),
        exec: bExec,
        factKind: bFact === null ? "missing" : "fact",
        source: binv.source
      });

      const inv = r.foreign_investment;
      const invMap = {
        q1: [inv.q1_actual, inv.q1_plan, ratio(inv.q1_pct), "fact"],
        h1: [inv.h1_expected, inv.h1_plan, ratio(inv.h1_pct), "expected"],
        m9: [null, null, null, "missing"],
        year: [inv.year_expected, inv.year_forecast, ratio(inv.year_pct), "expected"]
      }[period];
      rows.push({
        sector: "Инвестиция",
        kpi: "Хорижий инвестициялар",
        fact: invMap[0] === null ? val(null) : val(fmt(invMap[0], 1), "млн $", PERIODS[period], invMap[3]),
        plan: invMap[1] === null ? val(null) : val(fmt(invMap[1], 1), "млн $", "режа"),
        exec: invMap[2],
        factKind: invMap[3],
        source: inv.source
      });

      const exp = r.export;
      const expMap = {
        q1: [exp.q1_value, null, null, exp.q1_growth, "fact"],
        h1: [exp.h1_expected, null, null, exp.h1_growth, "expected"],
        m9: [null, null, null, null, "missing"],
        year: [exp.year_expected, exp.year_forecast, calc(exp.year_expected, exp.year_forecast), exp.year_growth, "expected"]
      }[period];
      rows.push({
        sector: "Экспорт",
        kpi: "Экспорт ҳажми",
        fact: expMap[0] === null ? val(null) : val(`${fmt(expMap[0] / 1000, 1)} / +${fmt(expMap[3] - 100, 1)}%`, "млн $", PERIODS[period], expMap[4]),
        plan: expMap[1] === null ? val(null) : val(fmt(expMap[1] / 1000, 1), "млн $", "прогноз"),
        exec: expMap[2],
        factKind: expMap[4],
        source: exp.source
      });

      rows.push({
        sector: "Инфляция",
        kpi: "Инфляция",
        fact: val(null),
        plan: period === "year" ? val("6,6", "%", "кафолат хати") : period === "h1" ? val("2,9", "%", "II чорак чегара") : val(null),
        exec: null,
        factKind: "missing",
        source: "0. Кафолат хати (Андижон).docx"
      });
      rows.push({
        sector: "Бандлик",
        kpi: "Ишсизлик",
        fact: val(null),
        plan: val(fmt(period === "year" ? emp.unemployment_year : emp.unemployment_h1, 1), "%", "мақсад"),
        exec: null,
        factKind: "missing",
        source: emp.source
      });
      rows.push({
        sector: "Камбағаллик",
        kpi: "Камбағаллик",
        fact: val(null),
        plan: val(fmt(period === "year" ? emp.poverty_year : emp.poverty_h1, 1), "%", "мақсад"),
        exec: null,
        factKind: "missing",
        source: emp.source
      });
      return rows;
    }

    function kpiTable(rows, showSource = true) {
      return `<div class="table-wrap"><table>
        <thead>
          <tr>
            <th>KPI</th>
            <th>Факт</th>
            <th>Режа</th>
            <th>Ижро</th>
            <th>Ҳолат</th>
            ${showSource ? "<th>Манба</th>" : ""}
          </tr>
        </thead>
        <tbody>
          ${rows.map((row) => {
            const exec = ratio(row.exec);
            const st = statusFor(exec, row.factKind);
            return `<tr>
              <td class="kpi-name">${esc(row.kpi)}<span class="sector-label">${esc(row.sector)}</span></td>
              <td>${row.fact}</td>
              <td>${row.plan}</td>
              <td>${execCell(exec)}</td>
              <td>${badge(statusText(st), st)}</td>
              ${showSource ? `<td>${sourceText(row.source)}</td>` : ""}
            </tr>`;
          }).join("")}
        </tbody>
      </table></div>`;
    }

    function priorityRows(rows) {
      return rows
        .map((row) => ({ ...row, status: statusFor(ratio(row.exec), row.factKind) }))
        .filter((row) => row.status !== "green")
        .slice(0, 7);
    }

    function renderMastFacts() {
      const tasks = DATA.districts.reduce((acc, d) => {
        acc.total += d.debt?.task_total || 0;
        acc.unfinished += d.debt?.task_unfinished || 0;
        return acc;
      }, { total: 0, unfinished: 0 });
      $("#mastFacts").innerHTML = `
        <div class="fact"><span>Манба қамрови</span><strong>${DATA.meta.source_file_count} файл · ${DATA.sources.length} жадвал</strong></div>
        <div class="fact"><span>Ҳудуд қамрови</span><strong>${DATA.districts.length} туман/шаҳар</strong></div>
        <div class="fact"><span>Топшириқлар ҳолати</span><strong>${tasks.unfinished} / ${tasks.total} бажарилмаган</strong></div>
        <div class="fact"><span>Давр</span><strong>${PERIODS[state.period]}</strong></div>
      `;
    }

    function renderDashboard() {
      const rows = regionalRows();
      const priority = priorityRows(rows);
      $("#view-dashboard").innerHTML = `
        <div class="intro-grid">
          <section class="block">
            <div class="block-head">
              <div>
                <h2>Асосий KPI матрицаси</h2>
                <p>Факт, режа ва ижро битта қаторда. Кутилма рақамлари алоҳида белги билан ажратилган.</p>
              </div>
              ${periodTabs()}
            </div>
            ${kpiTable(rows)}
            <div class="note">Ижро фақат факт/кутилма ва режа жуфти ишончли ажратилган қаторларда ҳисобланади.</div>
          </section>
          <aside class="block">
            <div class="block-head">
              <div>
                <h2>Биринчи навбатда</h2>
                <p>Автоматик сигнал: факт йўқ, ижро ортда ёки ҳисоблаш учун режа етишмайди.</p>
              </div>
            </div>
            <div class="priority-list">
              ${priority.map((row) => `<div class="priority-item">
                <div class="stripe ${row.status}"></div>
                <div>
                  <strong>${esc(row.kpi)}</strong>
                  <span>${esc(row.sector)} · ${statusText(row.status)} · ${esc(PERIODS[state.period])}</span>
                </div>
              </div>`).join("") || `<div class="empty">Бу даврда очиқ сигнал йўқ.</div>`}
            </div>
          </aside>
        </div>

        <section class="block" style="margin-top: 24px;">
          <div class="block-head">
            <div>
              <h2>Даврлар кесимида кўриниш</h2>
              <p>Бир KPI бир неча даврда қандай кўринишини тез солиштириш учун.</p>
            </div>
          </div>
          ${periodComparison()}
        </section>
      `;
      bindPeriodTabs();
    }

    function periodComparison() {
      const rows = ["q1", "h1", "m9", "year"].flatMap((period) =>
        regionalRows(period).filter((row) => ["ЯҲМ", "Бюджет тушумлари", "Хорижий инвестициялар", "Экспорт ҳажми", "Ишсизлик"].includes(row.kpi))
          .map((row) => ({ ...row, period }))
      );
      return `<div class="table-wrap"><table>
        <thead><tr><th>Давр</th><th>KPI</th><th>Факт</th><th>Режа</th><th>Ижро</th><th>Ҳолат</th></tr></thead>
        <tbody>${rows.map((row) => {
          const st = statusFor(ratio(row.exec), row.factKind);
          return `<tr>
            <td class="number">${PERIODS[row.period]}</td>
            <td class="kpi-name">${esc(row.kpi)}<span class="sector-label">${esc(row.sector)}</span></td>
            <td>${row.fact}</td>
            <td>${row.plan}</td>
            <td>${execCell(row.exec)}</td>
            <td>${badge(statusText(st), st)}</td>
          </tr>`;
        }).join("")}</tbody>
      </table></div>`;
    }

    function periodTabs() {
      return `<div class="period-tabs">${Object.entries(PERIODS).map(([id, label]) =>
        `<button type="button" data-period="${id}" class="${id === state.period ? "active" : ""}">${label}</button>`
      ).join("")}</div>`;
    }

    function bindPeriodTabs() {
      document.querySelectorAll("[data-period]").forEach((button) => {
        button.addEventListener("click", () => {
          state.period = button.dataset.period;
          $("#periodSelect").value = state.period;
          render();
        });
      });
    }

    function districtRows(district, period = state.period) {
      const d = district.data || {};
      const rows = [];
      ["industry", "agriculture", "services"].forEach((key) => {
        const item = d[key];
        if (!item) return;
        const fact = period === "q1";
        rows.push({
          sector: "Макро",
          kpi: item.label,
          fact: fact ? withGrowth(item.q1_value, item.q1_growth, item.unit, "I чорак", "fact") : val(null),
          plan: period === "q1" ? val(null) : withGrowth(item[`${period}_value`], item[`${period}_growth`], item.unit, PERIODS[period]),
          exec: null,
          factKind: fact ? "fact" : "missing",
          source: item.source
        });
      });
      if (d.budget && ["h1", "year"].includes(period)) {
        const actual = period === "h1" ? d.budget.h1_expected : d.budget.year_expected;
        const plan = period === "h1" ? d.budget.h1_plan : d.budget.year_plan;
        rows.push({
          sector: "Бюджет",
          kpi: "Бюджет тушумлари",
          fact: val(fmt(actual, 1), d.budget.unit, PERIODS[period], "expected"),
          plan: val(fmt(plan, 1), d.budget.unit, "режа"),
          exec: period === "h1" ? d.budget.h1_execution_pct : calc(actual, plan),
          factKind: "expected",
          source: d.budget.source
        });
      }
      if (d.foreign_investment) {
        const i = d.foreign_investment;
        const map = {
          q1: [i.q1_actual, i.q1_plan, ratio(i.q1_pct), "fact"],
          h1: [i.h1_expected, i.h1_plan, ratio(i.h1_pct), "expected"],
          m9: [null, null, null, "missing"],
          year: [i.year_expected, i.year_forecast, ratio(i.year_pct), "expected"]
        }[period];
        rows.push({
          sector: "Инвестиция",
          kpi: "Хорижий инвестициялар",
          fact: map[0] === null ? val(null) : val(fmt(map[0], 1), "млн $", PERIODS[period], map[3]),
          plan: map[1] === null ? val(null) : val(fmt(map[1], 1), "млн $", "режа"),
          exec: map[2],
          factKind: map[3],
          source: i.source
        });
      }
      if (d.export) {
        const e = d.export;
        const map = {
          q1: [e.q1_value, null, null, e.q1_growth, "fact"],
          h1: [e.h1_expected, null, null, e.h1_growth, "expected"],
          m9: [null, null, null, null, "missing"],
          year: [e.year_expected, e.year_forecast, calc(e.year_expected, e.year_forecast), e.year_growth, "expected"]
        }[period];
        rows.push({
          sector: "Экспорт",
          kpi: "Экспорт ҳажми",
          fact: map[0] === null ? val(null) : val(`${fmt(map[0] / 1000, 1)} / +${fmt(map[3] - 100, 1)}%`, "млн $", PERIODS[period], map[4]),
          plan: map[1] === null ? val(null) : val(fmt(map[1] / 1000, 1), "млн $", "прогноз"),
          exec: map[2],
          factKind: map[4],
          source: e.source
        });
      }
      if (d.employment) {
        rows.push({
          sector: "Бандлик",
          kpi: "Ишсизлик",
          fact: val(null),
          plan: val(fmt(period === "year" ? d.employment.unemployment_year : d.employment.unemployment_h1, 1), "%", "мақсад"),
          exec: null,
          factKind: "missing",
          source: d.employment.source
        });
        rows.push({
          sector: "Камбағаллик",
          kpi: "Камбағаллик",
          fact: val(null),
          plan: val(fmt(period === "year" ? d.employment.poverty_year : d.employment.poverty_h1, 1), "%", "мақсад"),
          exec: null,
          factKind: "missing",
          source: d.employment.source
        });
      }
      return rows;
    }

    function renderDistricts() {
      const selected = DATA.districts.find((d) => d.name === state.district) || DATA.districts[0];
      const unfinished = selected.debt?.task_unfinished || 0;
      const total = selected.debt?.task_total || 0;
      $("#view-districts").innerHTML = `
        <div class="district-layout">
          <aside class="district-list">
            ${DATA.districts.map((d) => {
              const u = d.debt?.task_unfinished || 0;
              const t = d.debt?.task_total || 0;
              const w = t ? Math.round(u / t * 100) : 0;
              return `<button type="button" class="district-btn ${d.name === selected.name ? "active" : ""}" data-district="${esc(d.name)}">
                <strong>${esc(d.name)}</strong>
                <span class="sub">${u} / ${t} бажарилмаган топшириқ</span>
                <span class="debt-line"><span style="--w:${w}%"></span></span>
              </button>`;
            }).join("")}
          </aside>
          <section class="block">
            <div class="block-head">
              <div>
                <h2>${esc(selected.name)}</h2>
                <p>${esc(selected.owner)} · ${PERIODS[state.period]} · KPI вилоят матрицаси билан бир хил форматда.</p>
              </div>
              ${periodTabs()}
            </div>
            <div class="metric-row">
              <div class="metric"><span>Бажарилмаган</span><strong>${unfinished}</strong></div>
              <div class="metric"><span>Жами топшириқ</span><strong>${total}</strong></div>
              <div class="metric"><span>Фактсиз KPI</span><strong>${districtRows(selected).filter((r) => r.factKind === "missing").length}</strong></div>
              <div class="metric"><span>Манба блоклари</span><strong>${Object.keys(selected.data || {}).length}</strong></div>
            </div>
            ${kpiTable(districtRows(selected))}
          </section>
        </div>
      `;
      bindPeriodTabs();
      document.querySelectorAll("[data-district]").forEach((button) => {
        button.addEventListener("click", () => {
          state.district = button.dataset.district;
          renderDistricts();
        });
      });
    }

    function renderSectors() {
      const sectors = {};
      regionalRows().forEach((row) => {
        if (!sectors[row.sector]) sectors[row.sector] = [];
        sectors[row.sector].push(row);
      });
      $("#view-sectors").innerHTML = `
        <div class="split">
          ${Object.entries(sectors).map(([sector, rows]) => `<section class="block">
            <div class="block-head"><div><h2>${esc(sector)}</h2><p>${PERIODS[state.period]} бўйича режа-факт ҳолати.</p></div></div>
            ${kpiTable(rows, false)}
          </section>`).join("")}
        </div>
      `;
    }

    function renderTasks() {
      const q = state.search.trim().toLowerCase();
      const tasks = DATA.tasks.filter((task) => !q || `${task.title} ${task.sector} ${task.owner}`.toLowerCase().includes(q)).slice(0, 120);
      $("#view-tasks").innerHTML = `
        <section class="block">
          <div class="block-head">
            <div><h2>Топшириқлар реестри</h2><p>Кафолат хати ва жадваллардан олинган вазифалар. Кейинги босқичда масъул, муддат ва далиллар тўлиқ нормаллаштирилади.</p></div>
          </div>
          <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Сектор</th><th>Топшириқ</th><th>Давр</th><th>Масъул</th><th>Ҳолат</th><th>Манба</th></tr></thead>
            <tbody>${tasks.map((task) => `<tr>
              <td class="number">${esc(task.id)}</td>
              <td>${esc(task.sector)}</td>
              <td class="kpi-name">${esc(task.title)}${task.detail ? `<span class="sub">${esc(task.detail)}</span>` : ""}</td>
              <td>${esc(task.period || "—")}</td>
              <td>${esc(task.owner || "—")}</td>
              <td>${badge("Киритилмаган", "grey")}</td>
              <td>${sourceText(task.source)}</td>
            </tr>`).join("")}</tbody>
          </table></div>
        </section>
      `;
    }

    function renderSources() {
      $("#view-sources").innerHTML = `
        <section class="block">
          <div class="block-head">
            <div><h2>Манбалар ва маълумот сифати</h2><p>Прототипдаги ҳар бир рақам мана шу файл ва варақлардан келади.</p></div>
          </div>
          <div class="table-wrap"><table>
            <thead><tr><th>Файл</th><th>Тур</th><th>Варақ</th><th>Қатор</th><th>Устун</th><th>Тўлдирилган қатор</th></tr></thead>
            <tbody>${DATA.sources.map((src) => `<tr>
              <td class="kpi-name">${esc(src.file)}</td>
              <td>${esc(src.type)}</td>
              <td>${esc(src.sheet || "—")}</td>
              <td>${esc(src.rows || "—")}</td>
              <td>${esc(src.cols || "—")}</td>
              <td>${esc(src.nonempty_rows || "—")}</td>
            </tr>`).join("")}</tbody>
          </table></div>
          <div class="note">${DATA.data_quality.map((x) => esc(x.detail)).join(" · ")}</div>
        </section>
      `;
    }

    function renderNav() {
      $("#nav").innerHTML = NAV.map(([id, label]) => `<button type="button" class="${id === state.view ? "active" : ""}" data-view="${id}">${label}</button>`).join("");
      document.querySelectorAll("[data-view]").forEach((button) => {
        button.addEventListener("click", () => {
          state.view = button.dataset.view;
          render();
        });
      });
    }

    function render() {
      renderMastFacts();
      renderNav();
      document.querySelectorAll(".view").forEach((view) => view.classList.remove("active"));
      $(`#view-${state.view}`).classList.add("active");
      if (state.view === "dashboard") renderDashboard();
      if (state.view === "districts") renderDistricts();
      if (state.view === "sectors") renderSectors();
      if (state.view === "tasks") renderTasks();
      if (state.view === "sources") renderSources();
    }

    $("#periodSelect").addEventListener("change", (event) => {
      state.period = event.target.value;
      render();
    });
    $("#searchInput").addEventListener("input", (event) => {
      state.search = event.target.value;
      if (state.view === "tasks") renderTasks();
    });

    render();
  </script>
</body>
</html>
"""


if __name__ == "__main__":
    main()
