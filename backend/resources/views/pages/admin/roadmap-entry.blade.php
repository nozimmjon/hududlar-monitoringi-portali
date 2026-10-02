@extends('layouts.admin')

@section('title', 'Амалда киритиш')

@section('content')
  <script src="/js/ad-entry.js?v={{ filemtime(public_path('js/ad-entry.js')) }}"></script>
  <livewire:admin.roadmap-entry :code="$code" />
@endsection
