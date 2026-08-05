<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $sector->org_full }} — топшириқлар</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400..900&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css?v={{ filemtime(public_path('css/portal.css')) }}">
  <style> a { text-decoration: none; color: inherit; } </style>
  @livewireStyles
</head>
<body class="sectors-body">
  <livewire:sector-detail :code="$sector->code" />
  @livewireScripts
</body>
</html>
