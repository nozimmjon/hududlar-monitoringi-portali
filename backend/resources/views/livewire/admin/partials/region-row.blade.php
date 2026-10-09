{{-- One region's row on /admin/roadmaps. Expects $c (a row of RoadmapOverview's $cards) and $period. --}}
@php
    use App\Services\Roadmaps\ProgressSheet;
    $ct    = $c['counts'];
    $n     = max(1, $c['measures']);
    $pct   = (int) round($c['done'] / $n * 100);
    $entry = route('admin.roadmaps.entry', ['code' => $c['region']->code, 'davr' => $period]);
@endphp
<div class="ad-trow" role="row" wire:key="ad-card-{{ $c['region']->code }}"
     data-region="{{ $c['region']->code }}" data-saved="{{ $ct['saved'] }}" data-carry="{{ $ct['carry'] }}" data-empty="{{ $ct['empty'] }}" data-overdue="{{ $c['overdue'] }}" data-done="{{ $c['done'] }}">
  <div class="c-name" role="cell">
    <a class="nm" href="{{ $entry }}">{{ $c['region']->name_full }}</a>
    <span class="sub">{{ $c['measures'] }} та чора-тадбир · {{ $c['lines'] }} та индикатор</span>
  </div>
  <div class="c-prog" role="cell" title="Барча режалаштирилган индикаторлари режага етган чора-тадбирлар">
    <span class="bar" aria-hidden="true"><i class="saved" style="width:{{ round($c['done'] / $n * 100, 1) }}%"></i></span>
    <span class="n tnum">{{ $c['done'] }} / {{ $c['measures'] }} бажарилди</span>
    <b class="ad-num">{{ $pct }}%</b>
  </div>
  <div class="c-over ad-num {{ $c['overdue'] === 0 ? 'zero' : 'warn' }}" role="cell" title="Муддати ўтган, бажарилмаган чора-тадбирлар">{{ $c['overdue'] === 0 ? '—' : $c['overdue'] . ' та' }}</div>
  <div class="c-last" role="cell">
    @if($c['last'])
      <span class="who">{{ $c['last']->user?->name ?? 'терминал' }}</span>
      <span class="when">{{ $c['last']->created_at->timezone(ProgressSheet::TZ)->format('d.m.Y H:i') }}</span>
    @else
      <span class="none">ҳали ўзгариш йўқ</span>
    @endif
  </div>
  <div class="c-act" role="cell">
    <a class="ad-btn small" href="{{ $entry }}">Киритиш</a>
    {{-- a browser event: the panel listens for it, and the overview is spared a round trip that would rebuild all 13 sheets --}}
    <button type="button" class="ad-btn ghost small" title="Тўлдирилган шаблонни юклаш"
            x-on:click="$dispatch('open-upload', { code: {{ $c['region']->code }} })">Excel</button>
  </div>
</div>
