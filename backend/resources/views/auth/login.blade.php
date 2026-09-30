@extends('layouts.auth')

@section('content')
<main class="auth-wrap">
  <form method="post" action="{{ route('login.store') }}" class="auth-card" novalidate>
    @csrf
    <div class="auth-brand"><img src="/logo.svg" alt="CERR"></div>
    <h1>Бошқарув маркази</h1>
    <p class="sub">Йўл хариталари мониторинги · кириш</p>
    @error('email')
      <div class="auth-error" role="alert">{{ $message }}</div>
    @enderror
    @error('password')
      <div class="auth-error" role="alert">{{ $message }}</div>
    @enderror
    <label for="email">Электрон почта</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    <label for="password">Парол</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Кириш</button>
  </form>
</main>
@endsection
