{{-- Inside the panel the portal shell would be the wrong way out: no admin nav, no logout. --}}
@php $inPanel = request()->is('admin/*') && auth()->check(); @endphp
@extends($inPanel ? 'layouts.admin' : 'layouts.app')

@section('content')
  @php
      // Only our own abort() message is shown. The framework writes its own English text for a missing route
      // («The route … could not be found.») and for a missing model («No query results for model …») — internals
      // the visitor must not read, so anything without Cyrillic falls back to the generic line.
      $message = $exception->getMessage();
  @endphp
  <div class="wr-empty">
    <h2>{{ preg_match('/\p{Cyrillic}/u', $message) === 1 ? $message : 'Саҳифа топилмади' }}</h2>
    <p><a href="{{ $inPanel ? route('admin.roadmaps') : route('home') }}">{{ $inPanel ? 'Вилоятлар рўйхатига қайтиш' : 'Бош саҳифага қайтиш' }}</a></p>
  </div>
@endsection
