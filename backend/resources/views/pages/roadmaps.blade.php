<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Сув хўжалиги йўл харитаси</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400..900&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css?v={{ filemtime(public_path('css/portal.css')) }}">
  <style> a { text-decoration: none; color: inherit; } </style>
  @livewireStyles
</head>
{{-- Standalone shell on purpose: this page is handed out separately from the portal,
     so no sidebar and no portal topbar. The region switcher lives in its own top bar. --}}
<body class="wr-body">
  <header class="wr-topbar">
    <span class="mark"><img src="/logo.svg" alt="CERR"></span>
    <h1>Сув хўжалиги йўл харитаси</h1>
    <livewire:region-switcher />
  </header>
  <main class="wr-page">
    <livewire:roadmaps-page />
  </main>
  @livewireScripts
</body>
</html>
