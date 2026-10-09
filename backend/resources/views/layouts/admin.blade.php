<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Йўл хариталари') · Админ панели</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Manrope:wght@600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css?v={{ filemtime(public_path('css/portal.css')) }}">
  <style>
    a { text-decoration: none; color: inherit; }
  </style>
  @livewireStyles
</head>
{{-- The panel has its own shell: no portal sidebar, no region switcher, no public nav. --}}
<body class="adm-body">
  <header class="adm-top">
    <a class="adm-brand" href="{{ route('admin.roadmaps') }}" title="Йўл хариталари · админ панели">
      {{-- logo.svg is all white, so it sits on a navy tile like it does on the portal sidebar --}}
      <span class="mark"><img src="/logo.svg" alt="CERR"></span>
    </a>
    <nav class="adm-nav" aria-label="Админ бўлимлари">
      <a class="{{ Route::is('admin.roadmaps') ? 'on' : '' }}" href="{{ route('admin.roadmaps') }}" @if(Route::is('admin.roadmaps')) aria-current="page" @endif>Вилоятлар</a>
      @if(Route::is('admin.roadmaps.entry'))
        <span class="sep" aria-hidden="true">/</span>
        <span class="crumb" aria-current="page">@yield('crumb', 'Амалда киритиш')</span>
      @endif
    </nav>
    <div class="adm-side">
      <a class="adm-ext" href="{{ route('home') }}" target="_blank" rel="noopener" title="Порталнинг оммавий қисми — янги ойнада">
        Портал
        <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><path d="M6 3h7v7M13 3 5.5 10.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      {{-- an unmatched /admin/* URL 404s before the auth middleware, so the user can be absent here --}}
      @auth
        <span class="adm-user" title="{{ auth()->user()->email }}">
          <i class="av" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</i>
          <span>{{ auth()->user()->name }}</span>
        </span>
        <form method="post" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="adm-out">Чиқиш</button>
        </form>
      @endauth
    </div>
  </header>

  <main class="adm-main">
    @yield('content')
  </main>

  @livewireScripts
</body>
</html>
