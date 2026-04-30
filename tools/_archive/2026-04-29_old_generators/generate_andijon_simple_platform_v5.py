from __future__ import annotations

import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DATA_JSON = ROOT / "platform prototypes" / "andijon_full_pilot_assets" / "andijon_full_pilot_data.json"
OUT_HTML = ROOT / "platform prototypes" / "andijon_simple_monitoring_platform_v5.html"


def main() -> None:
    data = json.loads(DATA_JSON.read_text(encoding="utf-8"))
    html = HTML.replace("__DATA__", json.dumps(data, ensure_ascii=False).replace("</", "<\\/"))
    OUT_HTML.write_text(html, encoding="utf-8")
    print(OUT_HTML)


HTML = r"""<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Андижон мониторинг платформаси · v5</title>
  <style>
    :root {
      --bg: #eef2f6;
      --paper: #ffffff;
      --ink: #152033;
      --muted: #657085;
      --line: #d8e0ea;
      --blue: #1f55c8;
      --blue-2: #173b7a;
      --green: #12805c;
      --amber: #ad6a00;
      --red: #bd3434;
      --soft-blue: #eaf1ff;
      --soft-green: #e7f5ef;
      --soft-amber: #fff3d7;
      --soft-red: #ffe8e8;
      --shadow: 0 18px 48px rgba(31, 48, 76, .10);
      --r: 10px;
      font-family: "Aptos", "Segoe UI", sans-serif;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      background:
        linear-gradient(180deg, #e6edf8 0, #eef2f6 280px),
        var(--bg);
      color: var(--ink);
      overflow-x: hidden;
    }

    button, select, input { font: inherit; }
    button { cursor: pointer; }

    .app {
      max-width: 1500px;
      margin: 0 auto;
      padding: 18px 22px 36px;
      width: 100%;
    }

    .topbar {
      min-height: 92px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 20px;
      align-items: center;
      margin-bottom: 18px;
    }

    .topbar > *, .summary > *, .workspace > * { min-width: 0; }

    .eyebrow {
      color: var(--blue);
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .04em;
      text-transform: uppercase;
      overflow-wrap: anywhere;
      white-space: normal;
      line-height: 1.25;
    }

    h1, h2, h3 { margin: 0; letter-spacing: 0; }
    h1 { margin-top: 5px; font-size: 30px; line-height: 1.1; overflow-wrap: anywhere; }
    h2, h3, p { overflow-wrap: anywhere; }
    .topbar p { margin: 6px 0 0; color: var(--muted); }

    .filters {
      display: flex;
      gap: 10px;
      align-items: center;
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    select, input {
      min-height: 42px;
      padding: 9px 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--paper);
      color: var(--ink);
      outline: none;
    }

    .summary {
      display: grid;
      grid-template-columns: 1.35fr repeat(3, minmax(160px, .55fr));
      gap: 12px;
      margin-bottom: 16px;
    }

    .hero-card, .stat-card, .panel, .kpi-button {
      background: var(--paper);
      border: 1px solid var(--line);
      border-radius: var(--r);
      box-shadow: var(--shadow);
    }

    .hero-card {
      padding: 18px 20px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 18px;
      align-items: center;
    }

    .hero-card h2 { font-size: 22px; }
    .hero-card p { margin: 7px 0 0; color: var(--muted); font-size: 13px; line-height: 1.45; }

    .pulse {
      width: 128px;
      height: 88px;
      border-radius: 10px;
      background:
        linear-gradient(145deg, rgba(31,85,200,.95), rgba(18,128,92,.9));
      color: white;
      display: grid;
      place-items: center;
      text-align: center;
      font-weight: 900;
      line-height: 1.1;
    }

    .stat-card {
      padding: 16px;
      min-height: 105px;
    }

    .stat-card span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; }
    .stat-card strong { display: block; margin-top: 6px; font-size: 26px; }
    .stat-card small { display: block; margin-top: 5px; color: var(--muted); }

    .workspace {
      display: grid;
      grid-template-columns: 310px minmax(0, 1fr) 360px;
      gap: 16px;
      align-items: start;
    }

    .panel {
      overflow: hidden;
    }

    .panel-head {
      padding: 15px 16px;
      border-bottom: 1px solid var(--line);
      background: #f8fafc;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
    }

    .panel-head h2 { font-size: 18px; }
    .panel-head p { margin: 5px 0 0; color: var(--muted); font-size: 13px; line-height: 1.35; }

    .kpi-list {
      display: grid;
      gap: 8px;
      padding: 12px;
      max-height: calc(100vh - 270px);
      overflow: auto;
    }

    .kpi-button {
      width: 100%;
      text-align: left;
      padding: 13px;
      box-shadow: none;
      display: grid;
      gap: 9px;
      background: var(--paper);
    }

    .kpi-button.active {
      border-color: var(--blue);
      background: var(--soft-blue);
      box-shadow: inset 4px 0 0 var(--blue);
    }

    .kpi-title {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: flex-start;
    }

    .kpi-title strong { font-size: 14px; line-height: 1.25; }
    .chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 3px 8px;
      font-size: 11px;
      font-weight: 900;
      white-space: nowrap;
      border: 1px solid transparent;
    }

    .green { color: var(--green); background: var(--soft-green); border-color: #adddc9; }
    .amber { color: var(--amber); background: var(--soft-amber); border-color: #e4c784; }
    .red { color: var(--red); background: var(--soft-red); border-color: #ebb5b5; }
    .grey { color: #737d8c; background: #eff2f5; border-color: #d9dee6; }
    .blue { color: var(--blue); background: var(--soft-blue); border-color: #bfd0f4; }

    .progress {
      display: grid;
      gap: 5px;
    }

    .bar {
      height: 9px;
      background: #e8edf4;
      border-radius: 999px;
      overflow: hidden;
    }

    .bar span {
      display: block;
      height: 100%;
      width: var(--w, 0%);
      background: var(--blue);
    }

    .kpi-meta {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      color: var(--muted);
      font-size: 12px;
    }

    .main-content {
      display: grid;
      gap: 16px;
    }

    .focus {
      padding: 18px;
    }

    .focus-top {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 18px;
      align-items: start;
      margin-bottom: 16px;
    }

    .focus-top h2 { font-size: 24px; }
    .focus-top p { margin: 7px 0 0; color: var(--muted); line-height: 1.45; }

    .big-status {
      min-width: 132px;
      border-radius: 10px;
      padding: 14px;
      background: var(--soft-blue);
      text-align: center;
      border: 1px solid #bfd0f4;
    }

    .big-status strong { display: block; font-size: 26px; }
    .big-status span { display: block; color: var(--muted); font-size: 12px; margin-top: 3px; }

    .measure-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .measure {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 13px;
      background: #fbfcfe;
      min-height: 92px;
    }

    .measure span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; }
    .measure strong { display: block; margin-top: 6px; font-size: 20px; line-height: 1.12; }
    .measure small { display: block; margin-top: 6px; color: var(--muted); }

    .timeline {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 8px;
    }

    .period {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: white;
    }

    .period span { color: var(--muted); font-size: 12px; font-weight: 900; }
    .period strong { display: block; margin-top: 6px; line-height: 1.2; }

    .tasks {
      display: grid;
      gap: 10px;
      padding: 14px;
    }

    .task {
      display: grid;
      grid-template-columns: 28px minmax(0, 1fr);
      gap: 10px;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 11px;
      background: #fff;
    }

    .task-num {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: var(--soft-blue);
      color: var(--blue);
      font-weight: 900;
      font-size: 12px;
    }

    .task strong { display: block; font-size: 13px; }
    .task p { margin: 4px 0 0; color: var(--muted); font-size: 12px; line-height: 1.4; }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    th {
      text-align: left;
      padding: 10px 12px;
      background: #f8fafc;
      color: #4b5567;
      border-bottom: 1px solid var(--line);
      white-space: nowrap;
    }

    td {
      padding: 11px 12px;
      border-bottom: 1px solid #ebf0f6;
      vertical-align: top;
    }

    tr:last-child td { border-bottom: 0; }
    .num { font-weight: 900; white-space: nowrap; }
    .sub { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; line-height: 1.35; }
    .table-wrap { width: 100%; max-width: 100%; overflow-x: auto; }

    .district-tools {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 10px;
      padding: 12px;
      border-bottom: 1px solid var(--line);
      background: #fbfcfe;
    }

    .district-tools input {
      min-height: 36px;
      background: white;
    }

    .mini-cards {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      padding: 12px;
      border-bottom: 1px solid var(--line);
    }

    .mini {
      border-radius: 8px;
      background: #fbfcfe;
      border: 1px solid var(--line);
      padding: 11px;
    }

    .mini span { color: var(--muted); font-size: 12px; display: block; }
    .mini strong { display: block; margin-top: 4px; font-size: 20px; }

    @media (max-width: 1240px) {
      .workspace { grid-template-columns: 280px minmax(0, 1fr); }
      .right-panel { grid-column: 1 / -1; }
      .summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 820px) {
      .app { padding: 12px; max-width: 100vw; }
      .topbar, .summary, .workspace, .focus-top { grid-template-columns: 1fr; }
      .hero-card, .stat-card, .panel, .kpi-button { min-width: 0; }
      .filters { justify-content: stretch; }
      select, input { width: 100%; }
      .hero-card { grid-template-columns: 1fr; }
      .pulse { width: 100%; height: 72px; }
      .measure-grid, .timeline, .mini-cards { grid-template-columns: 1fr; }
      .kpi-list { max-height: none; }
      table { min-width: 720px; }
    }
  </style>
</head>
<body>
  <div class="app">
    <header class="topbar">
      <div>
        <div class="eyebrow">Андижон вилояти</div>
        <h1>KPI, топшириқлар ва туманлар</h1>
        <p>Мақсад, ижро, топшириқ ва туманлар кесими бир экранда.</p>
      </div>
      <div class="filters">
        <select id="periodSelect">
          <option value="h1">I ярим йиллик</option>
          <option value="year">Йил якуни</option>
          <option value="q1">I чорак</option>
        </select>
        <input id="globalSearch" type="search" placeholder="KPI ёки туман қидириш">
      </div>
    </header>

    <section class="summary">
      <article class="hero-card">
        <div>
          <h2>Бош мақсад: ижрони тумангача кузатиш</h2>
          <p>Ҳар бир KPIга эришиш учун амалий топшириқлар ва ҳар бир туман/шаҳар бўйича мақсадли кўрсаткичлар бир экранда боғланади.</p>
        </div>
        <div class="pulse">KPI →<br>Топшириқ →<br>Туман</div>
      </article>
      <div class="stat-card"><span>Асосий KPI</span><strong id="statKpi">0</strong><small>monitoring йўналишлари</small></div>
      <div class="stat-card"><span>Топшириқлар</span><strong id="statTasks">0</strong><small>мақсадга етишиш чоралари</small></div>
      <div class="stat-card"><span>Туман/шаҳар</span><strong id="statDistricts">0</strong><small>кесимда мониторинг</small></div>
    </section>

    <main class="workspace">
      <aside class="panel">
        <div class="panel-head">
          <div>
            <h2>Умумий KPI</h2>
            <p>KPIни танланг, ўнгда топшириқ ва туман мониторинги очилади.</p>
          </div>
        </div>
        <div id="kpiList" class="kpi-list"></div>
      </aside>

      <section class="main-content">
        <article class="panel focus" id="focusPanel"></article>
        <section class="panel">
          <div class="panel-head">
            <div>
              <h2>Шу KPIга эришиш бўйича топшириқлар</h2>
              <p>Кафолат хатидаги чора-тадбирлар содда иш режасига айлантирилди.</p>
            </div>
          </div>
          <div id="taskList" class="tasks"></div>
        </section>
      </section>

      <aside class="panel right-panel">
        <div class="panel-head">
          <div>
            <h2>Туманлар кесими</h2>
            <p>Танланган KPI бўйича мақсадли кўрсаткичлар ва мониторинг.</p>
          </div>
        </div>
        <div class="mini-cards">
          <div class="mini"><span>Кўрсатилган туман</span><strong id="visibleDistricts">0</strong></div>
          <div class="mini"><span>Бажарилмаган топшириқ</span><strong id="unfinishedTasks">0</strong></div>
        </div>
        <div class="district-tools">
          <input id="districtSearch" type="search" placeholder="Туман қидириш">
          <span id="unitChip" class="chip blue">—</span>
        </div>
        <div id="districtTable" class="table-wrap"></div>
      </aside>
    </main>
  </div>

  <script>
    const DATA = __DATA__;
    const state = { kpi: "grp", period: "h1", search: "", districtSearch: "" };
    const $ = (s) => document.querySelector(s);
    const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch]));

    const KPI = [
      {
        id: "grp",
        title: "ЯҲМ ўсиши",
        sector: "Макро",
        unit: "млрд сўм",
        districtKey: null,
        description: "Вилоят иқтисодий ўсишининг умумий натижаси. Бу KPI асосан саноат, хизматлар, қишлоқ хўжалиги ва қурилиш ҳисобига таъминланади.",
        tasks: [
          ["Саноат, хизматлар, қишлоқ хўжалиги ва қурилиш режаларини биргаликда назорат қилиш", "Ҳар сектор ўз режасидан ортда қолса ЯҲМга таъсир қилади."],
          ["Паст ўсиш кўрсатган ҳудудларни алоҳида ишлаш", "Хонобод шаҳри ва Шаҳрихон тумани каби ҳудудлар алоҳида кузатилади."],
          ["Йил якуни прогнозини чоракма-чорак янгилаб бориш", "Факт киритилгани сари прогноз қайта ҳисобланади."]
        ]
      },
      {
        id: "industry",
        title: "Саноат маҳсулотлари",
        sector: "Макро",
        unit: "млрд сўм",
        districtKey: "industry",
        description: "Саноат ўсиши йирик корхоналарни тиклаш, саноат зоналари ва маҳаллийлаштириш лойиҳалари орқали таъминланади.",
        tasks: [
          ["6 та йирик корхонада ишлаб чиқариш ҳажмини тиклаш", "I ярим йилликда 55 млрд сўм, йил якунида 120 млрд сўм ҳажм."],
          ["307 та инвестиция лойиҳасини ишга тушириш", "Саноат зоналари ва махсус иқтисодий зоналардаги лойиҳалар устувор."],
          ["242 та вақтинча тўхтаган корхона фаолиятини қайта йўлга қўйиш", "Туманлар кесимида тиклаш режаси мониторинг қилинади."],
          ["20 та йирик саноат корхонаси билан манзилли ишлаш", "Ҳудудий саноатнинг асосий қисмига таъсир қилади."]
        ]
      },
      {
        id: "services",
        title: "Бозор хизматлари",
        sector: "Макро",
        unit: "млрд сўм",
        districtKey: "services",
        description: "Хизматлар ҳажми 24/7 кўчалар, соҳил бўйи хизматлари ва йўл бўйи сервис объектлари орқали оширилади.",
        tasks: [
          ["4 та шаҳар/туманда 6 та 24/7 кўча ташкил этиш", "77 та янги лойиҳа ва 99 та тадбиркорлик субъекти."],
          ["Соҳил бўйларида 3 та дам олиш ва хизмат кўрсатиш масканини ташкил этиш", "Қиймати 8,9 млрд сўмлик лойиҳалар."],
          ["Йўл бўйида 47 та хизмат кўрсатиш шохобчасини ташкил этиш", "4 та ҳудудда 12,1 млрд сўмлик лойиҳалар."]
        ]
      },
      {
        id: "agriculture",
        title: "Қишлоқ хўжалиги",
        sector: "Макро",
        unit: "млрд сўм",
        districtKey: "agriculture",
        description: "Қишлоқ хўжалиги ўсиши деҳқончилик ва чорвачилик лойиҳалари орқали таъминланади.",
        tasks: [
          ["Деҳқончилик йўналишида 8 та янги лойиҳани ишга тушириш", "424,5 млрд сўмлик лойиҳалар."],
          ["Чорвачилик йўналишида 38 та янги лойиҳани ишга тушириш", "268,1 млрд сўмлик лойиҳалар."],
          ["Туманлар бўйича прогноз ўсишини алоҳида кузатиш", "Қишлоқ хўжалиги мавсумий бўлгани учун даврий мониторинг зарур."]
        ]
      },
      {
        id: "budget",
        title: "Бюджет тушумлари",
        sector: "Бюджет",
        unit: "млрд сўм",
        districtKey: "budget",
        description: "Бюджет тушумлари прогноз, кутилма ва қўшимча тушум топшириқлари орқали назорат қилинади.",
        tasks: [
          ["Қурилиш соҳасида яширин иқтисодиёт улушини қисқартириш", "22 млрд сўм қўшимча тушум."],
          ["Саноатда солиқ маъмурчилигини яхшилаш", "36 млрд сўм қўшимча тушум."],
          ["Савдо ва хизматларда айланмаларни расмийлаштириш", "37 млрд сўм қўшимча тушум."],
          ["Транспорт хизматлари ва норасмий ишловчиларни легаллаштириш", "22 млрд сўм қўшимча тушум."]
        ]
      },
      {
        id: "budgetInvestment",
        title: "Бюджет инвестиция",
        sector: "Инвестиция",
        unit: "млн сўм",
        districtKey: "budget_investment",
        description: "Бюджет маблағлари ўзлаштирилиши ва объектларни фойдаланишга топшириш ҳолати.",
        tasks: [
          ["I ярим йилликда 444 млрд сўмлик бюджет маблағларини ўзлаштириш", "Йиллик лимитга нисбатан 45%."],
          ["Молиялаштириш очилмаган 16 та объект бўйича ҳужжатларни якунлаш", "Лойиҳа, экспертиза ва пудратчи масаласи."],
          ["Йил якуни билан 96 та объектни фойдаланишга топшириш", "Таълим, боғча, тиббиёт, сув, йўл ва канал объектлари."]
        ]
      },
      {
        id: "foreignInvestment",
        title: "Хорижий инвестициялар",
        sector: "Инвестиция",
        unit: "млн $",
        districtKey: "foreign_investment",
        description: "Хорижий инвестициялар ва кредитлар, ишга тушадиган лойиҳалар ва янги иш ўринлари.",
        tasks: [
          ["I ярим йилликда 1,783 млрд доллар инвестиция жалб қилиш", "Йил якуни билан 3,5 млрд доллар."],
          ["I ярим йилликда 155 та лойиҳани ишга тушириш", "398 млн долларлик лойиҳалар."],
          ["Йил якуни билан 307 та лойиҳани ишга тушириш", "1,335 млрд долларлик лойиҳалар."],
          ["Балиқчи туманида Tetratex лойиҳасини алоҳида назорат қилиш", "147 млн доллар, 1 500 иш ўрни."]
        ]
      },
      {
        id: "export",
        title: "Экспорт",
        sector: "Экспорт",
        unit: "млн $",
        districtKey: "export",
        description: "Экспорт ҳажми, экспортчи корхоналар сони ва ташқи бозорларни кенгайтириш бўйича мониторинг.",
        tasks: [
          ["Экспорт ҳажми камайган 46 та ва тўхтаган 11 та корхонани ўрганиш", "Корхоналар бўйича манзилли чоралар."],
          ["85 та корхонани экспортга жалб қилиш", "Йил якуни билан экспортчилар сони 400 тага етказилади."],
          ["4 та халқаро кўргазма ва бизнес-миссиялар орқали келишувларга эришиш", "100 млн $ + 50 млн $ экспорт келишувлари."],
          ["3 та янги бозорга экспортни йўлга қўйиш", "Канада, Буюк Британия, Озарбайжон."]
        ]
      },
      {
        id: "employment",
        title: "Ишсизлик ва бандлик",
        sector: "Бандлик",
        unit: "%",
        districtKey: "employment",
        description: "Ишсизликни камайтириш, аҳолини ишга жойлаштириш ва норасмий бандликни легаллаштириш.",
        tasks: [
          ["38,9 минг аҳолини I ярим йилликда доимий ишга жойлаштириш", "Йил якуни билан 86,7 минг нафар."],
          ["29,9 минг норасмий бандлар фаолиятини легаллаштириш", "Йил якуни билан 66,4 минг нафар."],
          ["3 834 та микролойиҳани ишга тушириш", "Йил якуни билан 8 790 та."],
          ["800 та субъект фаолиятини тиклаш", "Туманлар кесимида алоҳида мақсадлар бор."]
        ]
      },
      {
        id: "poverty",
        title: "Камбағаллик",
        sector: "Бандлик",
        unit: "%",
        districtKey: "employment",
        description: "Камбағаллик даражасини қисқартириш ва камбағаллик/ишсизликдан холи маҳаллалар сонини ошириш.",
        tasks: [
          ["12 минг камбағал оилага индивидуал режалар асосида хизмат кўрсатиш", "I ярим йилликда 14,5 минг хизмат."],
          ["246 та маҳаллани камбағаллик ва ишсизликдан холи ҳудудга айлантириш", "Йил якуни билан 442 та."],
          ["Камбағал оила аъзоларини ишга жойлаштириш, кредит ва субсидия билан қамраб олиш", "Иш, кредит, тадбиркорлик ва касб-ҳунар йўналишлари."],
          ["Оғир туманлар ва Янги Ўзбекистон қиёфасидаги ҳудудларда инфратузилма лойиҳаларини якунлаш", "Бўстон, Улуғнор, Пахтаобод, Асака, Шаҳрихон."]
        ]
      }
    ];

    function fmt(value, digits = 1) {
      if (value === null || value === undefined || value === "" || Number.isNaN(Number(value))) return "—";
      return Number(value).toLocaleString("uz-UZ", { maximumFractionDigits: digits, minimumFractionDigits: 0 });
    }

    function ratio(value) {
      if (value === null || value === undefined || Number.isNaN(Number(value))) return null;
      const n = Number(value);
      return n <= 2 ? n * 100 : n;
    }

    function status(exec, hasFact = true) {
      if (!hasFact || exec === null || exec === undefined || Number.isNaN(Number(exec))) return "grey";
      if (exec >= 100) return "green";
      if (exec >= 80) return "amber";
      return "red";
    }

    function statusLabel(s) {
      return { green: "режада", amber: "назоратда", red: "ортда", grey: "факт йўқ" }[s] || "факт йўқ";
    }

    function selectedKpi() {
      return KPI.find((x) => x.id === state.kpi) || KPI[0];
    }

    function macroIndicator(name) {
      return DATA.regional.macro.find((x) => x.indicator === name);
    }

    function kpiValue(k, period) {
      const r = DATA.regional;
      if (k.id === "grp") {
        const x = macroIndicator("ЯҲМ");
        return {
          fact: period === "q1" ? `${fmt(x.q1_value)} млрд / +${fmt(x.q1_growth - 100)}%` : "—",
          plan: period === "year" ? `${fmt(x.year_value)} млрд / +${fmt(x.year_growth - 100)}%` : `${fmt(x.h1_value)} млрд / +${fmt(x.h1_growth - 100)}%`,
          exec: null,
          hasFact: period === "q1"
        };
      }
      if (k.id === "industry") return macroValue("Саноат маҳсулотлари", period);
      if (k.id === "services") return macroValue("Бозор хизматлари", period);
      if (k.id === "agriculture") return macroValue("Қишлоқ хўжалиги маҳсулотлари", period);
      if (k.id === "budget") {
        const v = period === "year"
          ? [r.budget.year_expected, r.budget.year_plan, r.budget.year_expected / r.budget.year_plan * 100]
          : [r.budget.h1_expected, r.budget.h1_plan, r.budget.h1_execution_pct];
        return { fact: `${fmt(v[0])} млрд`, plan: `${fmt(v[1])} млрд`, exec: v[2], hasFact: true };
      }
      if (k.id === "budgetInvestment") {
        const v = period === "year"
          ? [r.budget_investment.year_absorption, r.budget_investment.limit, r.budget_investment.year_pct]
          : period === "q1"
            ? [r.budget_investment.q1_absorption, r.budget_investment.limit, r.budget_investment.q1_pct]
            : [r.budget_investment.h1_absorption, r.budget_investment.limit, r.budget_investment.h1_pct];
        return { fact: `${fmt(v[0])} млн`, plan: `${fmt(v[1])} млн`, exec: v[2], hasFact: true };
      }
      if (k.id === "foreignInvestment") {
        const v = period === "year"
          ? [r.foreign_investment.year_expected, r.foreign_investment.year_forecast, ratio(r.foreign_investment.year_pct)]
          : period === "q1"
            ? [r.foreign_investment.q1_actual, r.foreign_investment.q1_plan, ratio(r.foreign_investment.q1_pct)]
            : [r.foreign_investment.h1_expected, r.foreign_investment.h1_plan, ratio(r.foreign_investment.h1_pct)];
        return { fact: `${fmt(v[0])} млн $`, plan: `${fmt(v[1])} млн $`, exec: v[2], hasFact: true };
      }
      if (k.id === "export") {
        const v = period === "year"
          ? [r.export.year_expected / 1000, r.export.year_forecast / 1000, r.export.year_expected / r.export.year_forecast * 100, r.export.year_growth]
          : period === "q1"
            ? [r.export.q1_value / 1000, null, null, r.export.q1_growth]
            : [r.export.h1_expected / 1000, null, null, r.export.h1_growth];
        return { fact: `${fmt(v[0])} млн $ / +${fmt(v[3] - 100)}%`, plan: v[1] ? `${fmt(v[1])} млн $` : "мақсад матнда", exec: v[2], hasFact: true };
      }
      if (k.id === "employment") {
        return { fact: "—", plan: period === "year" ? `${fmt(r.employment.unemployment_year)}%` : `${fmt(r.employment.unemployment_h1)}%`, exec: null, hasFact: false };
      }
      if (k.id === "poverty") {
        return { fact: "—", plan: period === "year" ? `${fmt(r.employment.poverty_year)}%` : `${fmt(r.employment.poverty_h1)}%`, exec: null, hasFact: false };
      }
      return { fact: "—", plan: "—", exec: null, hasFact: false };
    }

    function macroValue(name, period) {
      const x = macroIndicator(name);
      return {
        fact: period === "q1" ? `${fmt(x.q1_value)} млрд / +${fmt(x.q1_growth - 100)}%` : "—",
        plan: period === "year" ? `${fmt(x.year_value)} млрд / +${fmt(x.year_growth - 100)}%` : `${fmt(x.h1_value)} млрд / +${fmt(x.h1_growth - 100)}%`,
        exec: null,
        hasFact: period === "q1"
      };
    }

    function periodValues(k) {
      return ["q1", "h1", "year"].map((p) => [p, kpiValue(k, p)]);
    }

    function periodName(p) {
      return { q1: "I чорак", h1: "I ярим йиллик", year: "Йил якуни" }[p];
    }

    function renderKpiList() {
      const q = state.search.trim().toLowerCase();
      const list = KPI.filter((k) => !q || `${k.title} ${k.sector}`.toLowerCase().includes(q));
      $("#kpiList").innerHTML = list.map((k) => {
        const v = kpiValue(k, state.period);
        const s = status(v.exec, v.hasFact);
        const w = v.exec === null ? 0 : Math.max(0, Math.min(100, v.exec));
        return `<button class="kpi-button ${k.id === state.kpi ? "active" : ""}" data-kpi="${k.id}" type="button">
          <span class="kpi-title"><strong>${esc(k.title)}</strong><span class="chip ${s}">${statusLabel(s)}</span></span>
          <span class="progress"><span class="bar"><span style="--w:${w}%"></span></span></span>
          <span class="kpi-meta"><span>${esc(k.sector)}</span><span>${v.exec === null ? "ижро ҳисобланмайди" : `${fmt(v.exec)}%`}</span></span>
        </button>`;
      }).join("");
      document.querySelectorAll("[data-kpi]").forEach((btn) => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.kpi;
        render();
      }));
    }

    function renderFocus() {
      const k = selectedKpi();
      const v = kpiValue(k, state.period);
      const s = status(v.exec, v.hasFact);
      $("#focusPanel").innerHTML = `
        <div class="focus-top">
          <div>
            <h2>${esc(k.title)}</h2>
            <p>${esc(k.description)}</p>
          </div>
          <div class="big-status">
            <strong>${v.exec === null ? "—" : `${fmt(v.exec)}%`}</strong>
            <span>${statusLabel(s)}</span>
          </div>
        </div>
        <div class="measure-grid">
          <div class="measure"><span>Факт / кутилма</span><strong>${esc(v.fact)}</strong><small>${periodName(state.period)}</small></div>
          <div class="measure"><span>Режа / мақсад</span><strong>${esc(v.plan)}</strong><small>${esc(k.unit)}</small></div>
          <div class="measure"><span>Ижро ҳолати</span><strong>${v.exec === null ? "ҳисобланмайди" : `${fmt(v.exec)}%`}</strong><small>${statusLabel(s)}</small></div>
        </div>
        <div class="timeline">
          ${periodValues(k).map(([p, pv]) => `<div class="period">
            <span>${periodName(p)}</span>
            <strong>${esc(pv.fact !== "—" ? pv.fact : pv.plan)}</strong>
            <small class="sub">${pv.exec === null ? "режа/факт ажратилган" : `ижро ${fmt(pv.exec)}%`}</small>
          </div>`).join("")}
        </div>`;
    }

    function renderTasks() {
      const k = selectedKpi();
      $("#taskList").innerHTML = k.tasks.map((task, idx) => `<div class="task">
        <span class="task-num">${idx + 1}</span>
        <span><strong>${esc(task[0])}</strong><p>${esc(task[1])}</p></span>
      </div>`).join("");
    }

    function districtMetric(d, k) {
      const key = k.districtKey;
      if (!key || !d.data[key]) return { fact: "—", plan: "—", exec: null, hasFact: false };
      const x = d.data[key];
      if (["industry", "services", "agriculture"].includes(key)) {
        const fact = `${fmt(x.q1_value)} / +${fmt(x.q1_growth - 100)}%`;
        const plan = state.period === "year" ? `${fmt(x.year_value)} / +${fmt(x.year_growth - 100)}%` : `${fmt(x.h1_value)} / +${fmt(x.h1_growth - 100)}%`;
        return { fact, plan, exec: null, hasFact: state.period === "q1" };
      }
      if (key === "budget") {
        const fact = state.period === "year" ? x.year_expected : x.h1_expected;
        const plan = state.period === "year" ? x.year_plan : x.h1_plan;
        const exec = state.period === "year" ? fact / plan * 100 : x.h1_execution_pct;
        return { fact: fmt(fact), plan: fmt(plan), exec, hasFact: true };
      }
      if (key === "budget_investment") {
        const fact = state.period === "year" ? x.year_absorption : state.period === "q1" ? x.q1_absorption : x.h1_absorption;
        const exec = state.period === "year" ? x.year_pct : state.period === "q1" ? x.q1_pct : x.h1_pct;
        return { fact: fmt(fact), plan: fmt(x.limit), exec, hasFact: true };
      }
      if (key === "foreign_investment") {
        const fact = state.period === "year" ? x.year_expected : state.period === "q1" ? x.q1_actual : x.h1_expected;
        const plan = state.period === "year" ? x.year_forecast : state.period === "q1" ? x.q1_plan : x.h1_plan;
        const exec = state.period === "year" ? ratio(x.year_pct) : state.period === "q1" ? ratio(x.q1_pct) : ratio(x.h1_pct);
        return { fact: fmt(fact), plan: fmt(plan), exec, hasFact: true };
      }
      if (key === "export") {
        const fact = state.period === "year" ? x.year_expected / 1000 : state.period === "q1" ? x.q1_value / 1000 : x.h1_expected / 1000;
        const growth = state.period === "year" ? x.year_growth : state.period === "q1" ? x.q1_growth : x.h1_growth;
        const plan = state.period === "year" ? fmt(x.year_forecast / 1000) : "мақсад";
        const exec = state.period === "year" ? x.year_expected / x.year_forecast * 100 : null;
        return { fact: `${fmt(fact)} / +${fmt(growth - 100)}%`, plan, exec, hasFact: true };
      }
      if (key === "employment") {
        if (state.kpi === "poverty") {
          return { fact: "—", plan: state.period === "year" ? `${fmt(x.poverty_year)}%` : `${fmt(x.poverty_h1)}%`, exec: null, hasFact: false };
        }
        return { fact: "—", plan: state.period === "year" ? `${fmt(x.unemployment_year)}%` : `${fmt(x.unemployment_h1)}%`, exec: null, hasFact: false };
      }
      return { fact: "—", plan: "—", exec: null, hasFact: false };
    }

    function renderDistricts() {
      const k = selectedKpi();
      const q = state.districtSearch.trim().toLowerCase();
      const districts = DATA.districts.filter((d) => !q || d.name.toLowerCase().includes(q));
      const unfinished = districts.reduce((sum, d) => sum + (d.debt?.task_unfinished || 0), 0);
      $("#visibleDistricts").textContent = districts.length;
      $("#unfinishedTasks").textContent = unfinished;
      $("#unitChip").textContent = k.unit;
      $("#districtTable").innerHTML = `<table>
        <thead><tr><th>Туман/шаҳар</th><th>Факт / кутилма</th><th>Режа / мақсад</th><th>Ижро</th><th>Топшириқ</th></tr></thead>
        <tbody>${districts.map((d) => {
          const m = districtMetric(d, k);
          const s = status(m.exec, m.hasFact);
          return `<tr>
            <td class="num">${esc(d.name)}<span class="sub">${esc(d.owner)}</span></td>
            <td>${esc(m.fact)}</td>
            <td>${esc(m.plan)}</td>
            <td><span class="chip ${s}">${m.exec === null ? statusLabel(s) : `${fmt(m.exec)}%`}</span></td>
            <td>${d.debt.task_unfinished} / ${d.debt.task_total}<span class="sub">бажарилмаган / жами</span></td>
          </tr>`;
        }).join("")}</tbody>
      </table>`;
    }

    function renderStats() {
      $("#statKpi").textContent = KPI.length;
      $("#statTasks").textContent = KPI.reduce((sum, k) => sum + k.tasks.length, 0);
      $("#statDistricts").textContent = DATA.districts.length;
    }

    function render() {
      renderStats();
      renderKpiList();
      renderFocus();
      renderTasks();
      renderDistricts();
    }

    $("#periodSelect").addEventListener("change", (e) => { state.period = e.target.value; render(); });
    $("#globalSearch").addEventListener("input", (e) => { state.search = e.target.value; renderKpiList(); });
    $("#districtSearch").addEventListener("input", (e) => { state.districtSearch = e.target.value; renderDistricts(); });
    render();
  </script>
</body>
</html>
"""


if __name__ == "__main__":
    main()
