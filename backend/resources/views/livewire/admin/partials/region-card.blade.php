{{-- One region's card on /admin/roadmaps. Expects $c (a row of RoadmapOverview's $cards) and $period. --}}
@php
    use App\Services\Roadmaps\ProgressSheet;
    $ct  = $c['counts'];
    $n   = max(1, $c['measures']);
    $pct = (int) round($ct['saved'] / $n * 100);
@endphp
<article class="ad-card" wire:key="ad-card-{{ $c['region']->code }}"
         data-region="{{ $c['region']->code }}" data-saved="{{ $ct['saved'] }}" data-carry="{{ $ct['carry'] }}" data-empty="{{ $ct['empty'] }}" data-overdue="{{ $c['overdue'] }}"
         title="Карточкани босинг — киритиш саҳифаси. «Кечиккан» — муддати ўтган, бажарилмаган чора-тадбирлар.">
  <a class="stretch" href="{{ route('admin.roadmaps.entry', ['code' => $c['region']->code, 'davr' => $period]) }}" aria-label="{{ $c['region']->name_full }} — киритиш"></a>
  <div class="top">
    <div class="gauge" style="background:conic-gradient(from 180deg, var(--ad-accent) 0 {{ $pct }}%, var(--ad-track) {{ $pct }}% 100%)" aria-hidden="true">
      <i class="ad-num">{{ $pct }}%</i>
    </div>
    <div class="who">
      <h3>{{ $c['region']->name_full }}</h3>
      <span class="n tnum">{{ $ct['saved'] }} / {{ $c['measures'] }} тўлдирилди · {{ $c['lines'] }} та индикатор</span>
    </div>
  </div>
  <div class="bar" aria-hidden="true">
    <i class="saved" style="width:{{ round($ct['saved'] / $n * 100, 1) }}%"></i><i class="carry" style="width:{{ round($ct['carry'] / $n * 100, 1) }}%"></i>
  </div>
  <div class="foot">
    @if($c['overdue'] > 0)
      <span class="ad-chip warn tnum">{{ $c['overdue'] }} кечиккан</span>
    @elseif($c['idle_days'] === null)
      <span class="ad-chip">ҳали ўзгариш йўқ</span>
    @else
      <span class="ad-chip tnum">{{ $c['idle_days'] }} кун ўзгариш йўқ</span>
    @endif
    <span class="last">
      @if($c['last'])
        {{ $c['last']->user?->name ?? 'терминал' }} · {{ $c['last']->created_at->timezone(ProgressSheet::TZ)->format('d.m.Y H:i') }}
      @endif
    </span>
    {{-- a browser event: the panel listens for it, and the overview is spared a round trip that would rebuild all 13 sheets --}}
    <button type="button" class="ad-btn ghost small" title="Тўлдирилган шаблонни юклаш"
            x-on:click="$dispatch('open-upload', { code: {{ $c['region']->code }} })">Excel’дан юклаш</button>
  </div>
</article>
