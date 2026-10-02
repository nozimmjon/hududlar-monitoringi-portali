<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Йўл хариталари') · Админ панели</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css?v={{ filemtime(public_path('css/portal.css')) }}">
  <style>
    a { text-decoration: none; color: inherit; }
  </style>
  @livewireStyles
</head>
{{-- The panel has its own shell: no portal sidebar, no region switcher, no public nav. --}}
<body class="adm-body">
  <header class="adm-top">
    <a class="adm-brand" href="{{ route('admin.roadmaps') }}" title="Админ панели">
      <img src="/logo.svg" alt="CERR">
      <span>
        <strong>Админ панели</strong>
        <em>Сув хўжалиги йўл хариталари</em>
      </span>
    </a>
    <nav class="adm-nav" aria-label="Админ бўлимлари">
      <a class="{{ Route::is('admin.roadmaps') ? 'on' : '' }}" href="{{ route('admin.roadmaps') }}">Вилоятлар</a>
      @if(Route::is('admin.roadmaps.entry'))
        <span class="adm-crumb" aria-current="page">@yield('crumb', 'Амалда киритиш')</span>
      @endif
    </nav>
    <div class="adm-side">
      <a class="adm-ext" href="{{ route('home') }}" target="_blank" rel="noopener" title="Порталнинг оммавий қисми — янги ойнада">Портал</a>
      {{-- an unmatched /admin/* URL 404s before the auth middleware, so the user can be absent here --}}
      @auth
        <span class="adm-user" title="{{ auth()->user()->email }}">{{ auth()->user()->name }}</span>
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
