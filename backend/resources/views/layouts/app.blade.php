<!doctype html>
<html lang="uz-Cyrl">
@php $currentRegion = \App\Support\CurrentRegion::current(); @endphp
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $currentRegion->name_full }} мониторинг платформаси · v7</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css">
  <style>
    a { text-decoration: none; color: inherit; }
  </style>
  @livewireStyles
</head>
<body>
  <header class="topbar">
    <div class="mast">
      <div class="brand">
        <div class="brand-mark">CERR</div>
        <div>
          <h1>{{ $currentRegion->name_full }} мониторинг платформаси</h1>
          <p>KPI · туманлар · ижро мониторинги</p>
        </div>
      </div>
    </div>
  </header>

  <div class="shell">
    <aside class="sidebar">
      <div class="side-title">
        <strong>Бошқарув маркази</strong>
      </div>
      <a class="nav-btn {{ Route::is('dashboard') ? 'active' : '' }}"
         href="{{ route('dashboard') }}" title="KPI">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-4H4v4Z"/>
        </svg>
        <span>KPI</span>
      </a>
      <a class="nav-btn {{ Route::is('tasks') ? 'active' : '' }}"
         href="{{ route('tasks') }}" title="Топшириқлар">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M9 11l2 2 4-5M5 4h14v16H5z"/>
        </svg>
        <span>Топшириқлар</span>
      </a>
      <a class="nav-btn {{ Route::is('districts') ? 'active' : '' }}"
         href="{{ route('districts') }}" title="Туманлар">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-7h6v7"/>
        </svg>
        <span>Туманлар</span>
      </a>
      <livewire:region-switcher />
    </aside>

    <main class="main">
      @yield('content')
    </main>
  </div>

  @livewireScripts
</body>
</html>
