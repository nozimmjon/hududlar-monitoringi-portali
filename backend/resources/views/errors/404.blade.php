@extends('layouts.app')

@section('content')
  @php
      // Only our own abort() message is shown. The framework writes its own English text for a missing route
      // («The route … could not be found.») and for a missing model («No query results for model …») — internals
      // the visitor must not read, so anything without Cyrillic falls back to the generic line.
      $message = $exception->getMessage();
  @endphp
  <div class="wr-empty">
    <h2>{{ preg_match('/\p{Cyrillic}/u', $message) === 1 ? $message : 'Саҳифа топилмади' }}</h2>
    <p><a href="{{ route('home') }}">Бош саҳифага қайтиш</a></p>
  </div>
@endsection
