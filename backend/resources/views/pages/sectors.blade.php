<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Тармоқ корхоналари топшириқлари</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400..900&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css?v={{ filemtime(public_path('css/portal.css')) }}">
  <style> a { text-decoration: none; color: inherit; } </style>
  @livewireStyles
</head>
<body class="sectors-body">
  <livewire:sectors-dashboard />
  @livewireScripts
  <script>
  (() => {
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Top-bar progress line draw-in; Livewire morphs wipe client-added classes,
    // so re-assert .on after every morph (the inline --p is server-rendered).
    const topOn = () => document.getElementById('secTop')?.classList.add('on');
    setTimeout(topOn, 200);
    document.addEventListener('livewire:init', () => {
      Livewire.hook('morph.updated', () => queueMicrotask(topOn));
    });

    // Ring draw-in + count-up (server renders the final state; replay once).
    const val = document.getElementById('secRingVal');
    const fg  = document.getElementById('secRingFg');
    if (val && fg && !reduced) {
      const target = +val.dataset.p || 0;
      const finalOffset = fg.style.strokeDashoffset;
      fg.style.transition = 'none';
      fg.style.strokeDashoffset = fg.style.strokeDasharray; // start empty
      requestAnimationFrame(() => requestAnimationFrame(() => {
        fg.style.transition = '';
        fg.style.strokeDashoffset = finalOffset;            // CSS transition draws the arc
      }));
      const t0 = performance.now(), D = 900;
      (function tick(now) {
        const p = Math.min(1, (now - t0) / D), e = 1 - Math.pow(1 - p, 3);
        val.textContent = Math.round(target * e) + '%';
        if (p < 1) requestAnimationFrame(tick); else val.textContent = target + '%';
      })(t0);
    }

    // Card entrance on first load only (run-once; morphed cards render plainly).
    const cards = [...document.querySelectorAll('.sec-card')];
    if (cards.length && !reduced && 'IntersectionObserver' in window) {
      let batch = 0, lastT = 0, fired = false;
      const io = new IntersectionObserver(entries => {
        fired = true;
        const now = performance.now();
        if (now - lastT > 400) batch = 0;
        lastT = now;
        entries.forEach(e => {
          if (!e.isIntersecting) return;
          const c = e.target, d = Math.min(batch++ * 45, 400);
          c.style.setProperty('--d', d + 'ms');
          c.classList.add('in');
          io.unobserve(c);
          setTimeout(() => { c.classList.remove('pre', 'in'); c.style.removeProperty('--d'); }, d + 650);
        });
      }, { threshold: .12, rootMargin: '0px 0px -4% 0px' });
      cards.forEach(c => { c.classList.add('pre'); io.observe(c); });
      // Safety: if IO never fires, reveal everything.
      setTimeout(() => { if (!fired) { io.disconnect(); cards.forEach(c => c.classList.remove('pre', 'in')); } }, 900);
    }
  })();
  </script>
</body>
</html>
