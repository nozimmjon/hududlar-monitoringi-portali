<!doctype html>
<html lang="uz-Cyrl">
@php $currentRegion = \App\Support\CurrentRegion::current(); @endphp
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $currentRegion->name_full }} мониторинг платформаси · v7</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css">
  <style>
    a { text-decoration: none; color: inherit; }
  </style>
  @livewireStyles
</head>
<body>
  <aside class="sidebar">
    <div class="side-brand">
      <img class="brand-logo" src="/logo.svg" alt="CERR">
    </div>
    <div class="side-title">
      <strong>Бошқарув маркази</strong>
    </div>
    <a class="nav-btn {{ Route::is('dashboard') ? 'active' : '' }}"
       href="{{ route('dashboard') }}" title="KPI">
      <svg viewBox="0 0 256 256" fill="currentColor">
        <path d="M224,200h-8V40a8,8,0,0,0-8-8H152a8,8,0,0,0-8,8V80H96a8,8,0,0,0-8,8v40H48a8,8,0,0,0-8,8v64H32a8,8,0,0,0,0,16H224a8,8,0,0,0,0-16ZM160,48h40V200H160ZM104,96h40V200H104ZM56,144H88v56H56Z"/>
      </svg>
      <span>KPI</span>
    </a>
    <a class="nav-btn {{ Route::is('tasks') ? 'active' : '' }}"
       href="{{ route('tasks') }}" title="Топшириқлар">
      <svg viewBox="0 0 256 256" fill="currentColor">
        <path d="M224,128a8,8,0,0,1-8,8H128a8,8,0,0,1,0-16h88A8,8,0,0,1,224,128ZM128,72h88a8,8,0,0,0,0-16H128a8,8,0,0,0,0,16Zm88,112H128a8,8,0,0,0,0,16h88a8,8,0,0,0,0-16ZM82.34,42.34,56,68.69,45.66,58.34A8,8,0,0,0,34.34,69.66l16,16a8,8,0,0,0,11.32,0l32-32A8,8,0,0,0,82.34,42.34Zm0,64L56,132.69,45.66,122.34a8,8,0,0,0-11.32,11.32l16,16a8,8,0,0,0,11.32,0l32-32a8,8,0,0,0-11.32-11.32Zm0,64L56,196.69,45.66,186.34a8,8,0,0,0-11.32,11.32l16,16a8,8,0,0,0,11.32,0l32-32a8,8,0,0,0-11.32-11.32Z"/>
      </svg>
      <span>Топшириқлар</span>
    </a>
    <a class="nav-btn {{ Route::is('districts') ? 'active' : '' }}"
       href="{{ route('districts') }}" title="Туманлар">
      <svg viewBox="0 0 256 256" fill="currentColor">
        <path d="M240,208H224V96a16,16,0,0,0-16-16H144V32a16,16,0,0,0-24.88-13.32L39.12,72A16,16,0,0,0,32,85.34V208H16a8,8,0,0,0,0,16H240a8,8,0,0,0,0-16ZM208,96V208H144V96ZM48,85.34,128,32V208H48ZM112,112v16a8,8,0,0,1-16,0V112a8,8,0,1,1,16,0Zm-32,0v16a8,8,0,0,1-16,0V112a8,8,0,1,1,16,0Zm0,56v16a8,8,0,0,1-16,0V168a8,8,0,0,1,16,0Zm32,0v16a8,8,0,0,1-16,0V168a8,8,0,0,1,16,0Z"/>
      </svg>
      <span>Туманлар</span>
    </a>
    <livewire:region-switcher />
  </aside>

  <div class="content-col">
    <header class="topbar">
      <div class="brand">
        <h1>{{ $currentRegion->name_full }} мониторинг платформаси</h1>
      </div>
      @if(request()->routeIs('dashboard'))
        <livewire:period-switcher />
      @endif
    </header>

    <main class="main">
      @yield('content')
    </main>
  </div>

  @livewireScripts
</body>
</html>
