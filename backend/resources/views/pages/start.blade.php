@php
    // Narrow no-break space thousands separator, matching the prototype's JS fmt.
    $fmt = fn (?int $n) => $n === null ? '—' : number_format($n, 0, ',', "\u{202f}");
@endphp
<!doctype html>
<html lang="uz-Cyrl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Ҳудудлар мониторинги портали — бошланғич саҳифа: Вилоятлар ва Тармоқлар модулини танлаш">
<title>Ҳудудлар мониторинги портали</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..900&display=swap" rel="stylesheet">
<style>
/* Hallmark · macrostructure: Diptych split · theme: custom (light paper · navy engaged ink · Inter) · brief: starter module chooser · variant A */

:root {
  color-scheme: light;

  /* — color tokens (OKLCH only) — */
  --brand:        oklch(50% 0.13 262);
  --brand-deep:   oklch(38% 0.11 255);
  --paper:        oklch(97.6% 0.005 80);
  --paper-2:      oklch(96.6% 0.007 70);
  --ink:          oklch(24% 0.028 260);
  --ink-soft:     oklch(43% 0.022 258);
  --hairline:     oklch(88% 0.012 255);
  --hairline-0:   oklch(88% 0.012 255 / 0);
  --seam-mark:    oklch(70% 0.06 258);
  --wash-a1:      oklch(95.4% 0.016 258);
  --wash-a2:      oklch(91.8% 0.034 256);
  --wash-b1:      oklch(95.6% 0.014 252);
  --wash-b2:      oklch(92.2% 0.03 250);
  --motif-line:   oklch(79% 0.045 255);
  --motif-fill:   oklch(90.5% 0.024 252);
  --pending:      oklch(71% 0.13 75);
  --pending-halo: oklch(91% 0.05 80);
  --chip-shadow:  oklch(38% 0.11 255 / .28);

  /* — 4pt spacing scale — */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 24px;
  --space-6: 32px;
  --space-7: 48px;
  --space-8: 64px;
  --space-9: 96px;

  /* — motion — */
  --ease-out: cubic-bezier(.22,.61,.36,1);
  --ease-io:  cubic-bezier(.45,0,.2,1);
  --seam-ms: 380ms;

  --font: "Inter", "Inter Fallback", system-ui, sans-serif;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

html, body { overflow-x: clip; }

body {
  font-family: var(--font);
  background: var(--paper);
  color: var(--ink);
  -webkit-text-size-adjust: 100%;
}

::selection { background: var(--brand); color: var(--paper); }

.sr-only {
  position: absolute; width: 1px; height: 1px;
  overflow: hidden; clip-path: inset(50%); white-space: nowrap;
}

/* ————— wordmark row ————— */

.masthead {
  position: absolute;
  inset-inline: 0;
  top: 0;
  z-index: 20;
  display: flex;
  justify-content: center;
  padding-top: var(--space-5);
  pointer-events: none;
}

.wordmark {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.wordmark .chip {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: linear-gradient(180deg, var(--brand), var(--brand-deep));
  box-shadow: 0 2px 8px var(--chip-shadow);
}

.wordmark .chip img { width: 22px; height: 22px; display: block; }

.wordmark .name {
  font-size: 0.95rem;
  font-weight: 650;
  letter-spacing: 0.005em;
  color: var(--ink);
}

/* ————— diptych ————— */

.diptych {
  display: flex;
  min-height: 100vh;
  min-height: 100dvh;
  isolation: isolate;
}

.half {
  position: relative;
  overflow: hidden;
  flex: 1 1 0%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: var(--space-8) clamp(var(--space-6), 4vw, var(--space-8));
  color: var(--ink);
  text-decoration: none;
  transition: flex-grow var(--seam-ms) var(--ease-out);
}

.half-a { background: var(--paper); }
.half-b { background: var(--paper-2); }

/* engaged wash — deepens the half, animated via opacity only */
.half::before {
  content: "";
  position: absolute;
  inset: 0;
  z-index: 0;
  opacity: 0;
  transition: opacity var(--seam-ms) var(--ease-out);
}
.half-a::before { background: linear-gradient(165deg, var(--wash-a1), var(--wash-a2)); }
.half-b::before { background: linear-gradient(195deg, var(--wash-b1), var(--wash-b2)); }

.half:hover::before,
.half:focus-visible::before { opacity: 1; }

.half:focus-visible {
  outline: 3px solid var(--brand);
  outline-offset: -6px;
}

/* ————— inner content column ————— */

.inner {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: min(100%, 30rem);
  margin-inline: auto;
  transition: opacity var(--seam-ms) var(--ease-out),
              transform var(--seam-ms) var(--ease-out);
}

.half:hover .inner { transform: translateY(-4px); }
.half:active .inner { transform: translateY(-1px) scale(.995); }

/* polite recession of the disengaged half */
.diptych:hover .half:not(:hover) .inner,
.diptych:has(:focus-visible) .half:not(:focus-visible) .inner { opacity: .62; }
.diptych:hover .half:not(:hover) .motif,
.diptych:has(:focus-visible) .half:not(:focus-visible) .motif { opacity: .3; }

.rule {
  width: 56px;
  height: var(--space-1);
  border-radius: 2px;
  background: linear-gradient(90deg, var(--brand), var(--brand-deep));
}

.module-title {
  margin-top: var(--space-4);
  font-size: clamp(2.7rem, 5vw, 4.4rem);
  font-weight: 800;
  letter-spacing: -0.022em;
  line-height: 1.02;
  color: var(--ink);
}

.module-desc {
  margin-top: var(--space-4);
  max-width: 26rem;
  font-size: 0.98rem;
  line-height: 1.55;
  color: var(--ink-soft);
  text-wrap: balance;
}

/* ————— stats block ————— */

.stats {
  margin-top: var(--space-7);
  width: 100%;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-5) var(--space-6);
}

.stat {
  border-top: 1px solid var(--hairline);
  padding-top: var(--space-3);
}

.num {
  display: block;
  font-size: clamp(1.85rem, 2.3vw, 2.4rem);
  font-weight: 760;
  letter-spacing: -0.012em;
  line-height: 1.05;
  font-variant-numeric: tabular-nums;
  color: var(--brand-deep);
  opacity: .85;
  transition: opacity var(--seam-ms) var(--ease-out);
}

.half:hover .num,
.half:focus-visible .num { opacity: 1; }

.num .unit { font-size: .72em; font-weight: 700; }

.stat .label {
  display: block;
  margin-top: var(--space-1);
  font-size: 0.8125rem;
  letter-spacing: 0.01em;
  color: var(--ink-soft);
}

/* pending-report state cell (Тармоқлар) */
.state-val {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  min-height: clamp(1.85rem, 2.3vw, 2.4rem);
  font-size: 1rem;
  font-weight: 650;
  line-height: 1.3;
  color: var(--ink);
}

.state-val .dot {
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--pending);
  box-shadow: 0 0 0 4px var(--pending-halo);
}

/* ————— enter affordance ————— */

.enter {
  margin-top: var(--space-7);
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 1rem;
  font-weight: 650;
  color: var(--brand-deep);
  opacity: 0;
  transform: translateX(-6px);
  transition: opacity var(--seam-ms) var(--ease-out),
              transform var(--seam-ms) var(--ease-out);
}

.enter .arr {
  display: inline-block;
  transition: transform var(--seam-ms) var(--ease-out);
}

.half:hover .enter,
.half:focus-visible .enter { opacity: 1; transform: none; }
.half:hover .enter .arr,
.half:focus-visible .enter .arr { transform: translateX(4px); }

/* ————— motifs ————— */

.motif {
  position: absolute;
  z-index: 1;
  pointer-events: none;
  opacity: .55;
  transition: opacity var(--seam-ms) var(--ease-out),
              transform var(--seam-ms) var(--ease-out);
}

/* Вилоятлар — territorial contours */
.motif-contours {
  right: -12%;
  bottom: -20%;
  width: min(74%, 560px);
  height: auto;
  transform-origin: 70% 80%;
}
.half-a:hover .motif-contours,
.half-a:focus-visible .motif-contours { opacity: 1; transform: scale(1.035); }

.c-ring { fill: none; stroke: var(--motif-line); stroke-width: 1.4; }
.c-dot  { fill: var(--motif-line); }
.c-core { fill: var(--motif-fill); stroke: var(--motif-line); stroke-width: 1.4; }

/* Тармоқлар — vertical bar rhythm */
.motif-bars {
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  height: auto;
}
.half-b:hover .motif-bars,
.half-b:focus-visible .motif-bars { opacity: 1; transform: translateY(-8px); }

.b-fill { fill: var(--motif-fill); }
.b-line { fill: none; stroke: var(--motif-line); stroke-width: 1.4; }
.b-base { stroke: var(--hairline); stroke-width: 1; }

/* ————— seam ————— */

.seam {
  flex: 0 0 auto;
  width: 1px;
  position: relative;
  z-index: 5;
  pointer-events: none;
  background: linear-gradient(180deg,
    var(--hairline-0) 0%,
    var(--hairline) 18%,
    var(--hairline) 82%,
    var(--hairline-0) 100%);
}

.seam::after {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 10px;
  height: 10px;
  background: var(--paper);
  border: 1px solid var(--seam-mark);
  transform: translate(-50%, -50%) rotate(45deg);
}

/* ————— seam shift (pointer devices, wide screens) ————— */

@media (hover: hover) and (min-width: 760px) {
  .half:hover { flex-grow: 1.4; }
}
@media (min-width: 760px) {
  .half:focus-visible { flex-grow: 1.4; }
}

/* ————— entrance choreography (3 primitives: rise · draw · fade) ————— */

@keyframes rise {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: none; }
}
@keyframes draw {
  from { opacity: 0; transform: scaleY(0); }
  to   { opacity: 1; transform: none; }
}
@keyframes fade {
  from { opacity: 0; }
}

.anim {
  animation: rise 480ms var(--ease-out) both;
  animation-delay: var(--d, 0ms);
}
.motif {
  animation: fade 600ms var(--ease-io) both;
  animation-delay: 250ms;
}
.seam {
  transform-origin: center;
  animation: draw 500ms var(--ease-out) both;
  animation-delay: 300ms;
}

/* ————— stacked diptych (narrow) ————— */

@media (max-width: 759px) {
  .diptych { flex-direction: column; }
  .half {
    min-height: 50vh;
    min-height: 50svh;
    padding: var(--space-8) var(--space-5);
  }
  .half-a { padding-top: var(--space-9); }
  .module-title { font-size: clamp(2.4rem, 11vw, 3.2rem); }
  .stats { gap: var(--space-4) var(--space-5); }
  .seam {
    width: auto;
    height: 1px;
    background: linear-gradient(90deg,
      var(--hairline-0) 0%,
      var(--hairline) 12%,
      var(--hairline) 88%,
      var(--hairline-0) 100%);
    animation-name: fade;
  }
  .wordmark .name { font-size: 0.875rem; }
  .motif-contours { right: -18%; bottom: -24%; width: 78%; }
}

/* enter affordance is always present without hover */
@media (hover: none), (max-width: 759px) {
  .enter { opacity: 1; transform: none; }
}

/* ————— reduced motion: everything collapses to ≤150ms opacity ————— */

@media (prefers-reduced-motion: reduce) {
  .anim, .motif, .seam {
    animation: fade 120ms linear both !important;
    animation-delay: 0ms !important;
  }
  * {
    transition-property: opacity !important;
    transition-duration: 120ms !important;
  }
  .half:hover,
  .half:focus-visible { flex-grow: 1; }
  .half:hover .inner,
  .half:active .inner,
  .half-a:hover .motif-contours,
  .half-b:hover .motif-bars { transform: none; }
  .enter { transform: none; }
}
</style>
</head>
<body>

<h1 class="sr-only">Ҳудудлар мониторинги портали — йўналишни танланг</h1>

<header class="masthead">
  <div class="wordmark anim" style="--d: 0ms">
    <span class="chip"><img src="/logo.svg" alt="" width="22" height="22"></span>
    <span class="name">Ҳудудлар мониторинги платформаси</span>
  </div>
</header>

<main class="diptych">

  <a class="half half-a" href="{{ route('regions') }}">
    <svg class="motif motif-contours" viewBox="0 0 640 640" aria-hidden="true" focusable="false">
      <path class="c-ring" d="M330 96C458 96 566 176 588 292 610 408 548 512 436 556 330 597 196 580 118 500 44 424 40 300 102 212 158 132 238 96 330 96Z"/>
      <path class="c-ring" d="M332 152C434 152 522 218 540 312 558 404 508 488 418 524 332 558 224 542 162 478 102 416 100 316 152 244 198 180 258 152 332 152Z"/>
      <path class="c-ring" d="M334 208C412 208 478 260 492 332 506 402 468 466 400 494 334 520 252 508 206 460 160 412 158 336 198 280 234 230 278 208 334 208Z"/>
      <path class="c-ring" d="M336 264C390 264 436 300 446 352 456 400 430 444 382 464 336 482 280 474 248 440 216 406 216 354 244 316 268 282 298 264 336 264Z"/>
      <path class="c-ring" d="M338 320C370 320 396 342 402 372 408 400 392 426 364 438 338 448 306 444 288 424 270 404 270 374 286 352 300 332 316 320 338 320Z"/>
      <circle class="c-core" cx="344" cy="380" r="7"/>
      <circle class="c-dot" cx="140" cy="180" r="2.5"/>
      <circle class="c-dot" cx="420" cy="118" r="2.5"/>
      <circle class="c-dot" cx="548" cy="182" r="2.5"/>
      <circle class="c-dot" cx="582" cy="430" r="2.5"/>
      <circle class="c-dot" cx="118" cy="452" r="2.5"/>
      <circle class="c-dot" cx="236" cy="584" r="2.5"/>
      <circle class="c-dot" cx="470" cy="590" r="2.5"/>
    </svg>
    <span class="inner">
      <span class="rule anim" style="--d: 100ms"></span>
      <span class="module-title anim" role="heading" aria-level="2" style="--d: 100ms">Вилоятлар</span>
      <span class="module-desc anim" style="--d: 180ms">Кафолат хатларидаги ваъдалар ижроси — ҳудудлар кесимида режа ва амал солиштируви, вилоятдан туман топшириғигача.</span>
      @if($regions !== null)
      <span class="stats anim" style="--d: 260ms">
        <span class="stat"><span class="num" data-count="{{ $regions['regions'] }}">{{ $fmt($regions['regions']) }}</span><span class="label">ҳудуд</span></span>
        <span class="stat"><span class="num" data-count="{{ $regions['total'] }}">{{ $fmt($regions['total']) }}</span><span class="label">топшириқ</span></span>
        <span class="stat"><span class="num" data-count="{{ $regions['done'] }}">{{ $fmt($regions['done']) }}</span><span class="label">бажарилди</span></span>
        @if($regions['pct'] !== null)
        <span class="stat"><span class="num"><span data-count="{{ $regions['pct'] }}">{{ $regions['pct'] }}</span><span class="unit">%</span></span><span class="label">ижро</span></span>
        @endif
      </span>
      @endif
      <span class="enter anim" style="--d: 340ms">Кириш <span class="arr">&#8594;</span></span>
    </span>
  </a>

  <div class="seam" aria-hidden="true"></div>

  <a class="half half-b" href="{{ route('sectors') }}">
    <svg class="motif motif-bars" viewBox="0 0 1280 320" aria-hidden="true" focusable="false">
      <rect class="b-fill" x="16" y="210" width="28" height="110" rx="3"/>
      <rect class="b-fill" x="64" y="140" width="28" height="180" rx="3"/>
      <rect class="b-fill" x="112" y="236" width="28" height="84" rx="3"/>
      <rect class="b-line" x="160" y="84" width="28" height="236" rx="3"/>
      <rect class="b-fill" x="208" y="170" width="28" height="150" rx="3"/>
      <rect class="b-fill" x="256" y="52" width="28" height="268" rx="3"/>
      <rect class="b-fill" x="304" y="200" width="28" height="120" rx="3"/>
      <rect class="b-fill" x="352" y="116" width="28" height="204" rx="3"/>
      <rect class="b-line" x="400" y="228" width="28" height="92" rx="3"/>
      <rect class="b-fill" x="448" y="74" width="28" height="246" rx="3"/>
      <rect class="b-fill" x="496" y="152" width="28" height="168" rx="3"/>
      <rect class="b-fill" x="544" y="104" width="28" height="216" rx="3"/>
      <rect class="b-fill" x="592" y="216" width="28" height="104" rx="3"/>
      <rect class="b-line" x="640" y="134" width="28" height="186" rx="3"/>
      <rect class="b-fill" x="688" y="180" width="28" height="140" rx="3"/>
      <rect class="b-fill" x="736" y="62" width="28" height="258" rx="3"/>
      <rect class="b-fill" x="784" y="204" width="28" height="116" rx="3"/>
      <rect class="b-fill" x="832" y="124" width="28" height="196" rx="3"/>
      <rect class="b-line" x="880" y="232" width="28" height="88" rx="3"/>
      <rect class="b-fill" x="928" y="92" width="28" height="228" rx="3"/>
      <rect class="b-fill" x="976" y="164" width="28" height="156" rx="3"/>
      <rect class="b-fill" x="1024" y="48" width="28" height="272" rx="3"/>
      <rect class="b-fill" x="1072" y="192" width="28" height="128" rx="3"/>
      <rect class="b-line" x="1120" y="112" width="28" height="208" rx="3"/>
      <rect class="b-fill" x="1168" y="224" width="28" height="96" rx="3"/>
      <rect class="b-fill" x="1216" y="144" width="28" height="176" rx="3"/>
      <line class="b-base" x1="0" y1="319.5" x2="1280" y2="319.5"/>
    </svg>
    <span class="inner">
      <span class="rule anim" style="--d: 160ms"></span>
      <span class="module-title anim" role="heading" aria-level="2" style="--d: 160ms">Тармоқлар</span>
      <span class="module-desc anim" style="--d: 240ms">@if($sectors !== null && ! $sectors['reported'])Тармоқ корхоналарининг кафолат хатларидаги вазифалари — режалар киритилган, амал ҳисоботи кутилмоқда.@else Тармоқ корхоналарининг кафолат хатларидаги вазифалари — режа ва факт мониторинги.@endif</span>
      @if($sectors !== null)
      <span class="stats anim" style="--d: 320ms">
        <span class="stat"><span class="num" data-count="{{ $sectors['sectors'] }}">{{ $fmt($sectors['sectors']) }}</span><span class="label">корхона</span></span>
        <span class="stat"><span class="num" data-count="{{ $sectors['tasks'] }}">{{ $fmt($sectors['tasks']) }}</span><span class="label">топшириқ</span></span>
        <span class="stat"><span class="num" data-count="{{ $sectors['lines'] }}">{{ $fmt($sectors['lines']) }}</span><span class="label">индикатор</span></span>
        @if($sectors['pct'] !== null)
        <span class="stat"><span class="num"><span data-count="{{ $sectors['pct'] }}">{{ $sectors['pct'] }}</span><span class="unit">%</span></span><span class="label">ижро</span></span>
        @else
        <span class="stat"><span class="state-val"><span class="dot"></span>Ҳисобот кутилмоқда</span><span class="label">жорий давр</span></span>
        @endif
      </span>
      @endif
      <span class="enter anim" style="--d: 400ms">Кириш <span class="arr">&#8594;</span></span>
    </span>
  </a>

</main>

@if($regions !== null || $sectors !== null)
<script>
(function () {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var els = [].slice.call(document.querySelectorAll('[data-count]'));
  var fmt = function (n) {
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f');
  };
  var t0 = performance.now(), dur = 800;
  function tick(now) {
    var p = Math.min((now - t0) / dur, 1);
    var e = 1 - Math.pow(1 - p, 3);
    // Throttled/occluded tabs keep the markup's final numbers untouched.
    if (p <= 0.02) { requestAnimationFrame(tick); return; }
    for (var i = 0; i < els.length; i++) els[i].textContent = fmt(+els[i].getAttribute('data-count') * e);
    if (p < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
})();
</script>
@endif

</body>
</html>
