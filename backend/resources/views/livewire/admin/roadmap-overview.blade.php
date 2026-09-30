@php
    use App\Services\Roadmaps\ProgressSheet;
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
@endphp
<div class="ad-shell">
  <header class="ad-head">
    <div class="ad-head-row">
      <div class="ad-titles">
        <h2 class="ad-title">Йўл хариталари · мониторинг</h2>
        <p class="ad-sub">{{ $periodLabel }} · {{ $totals['measures'] }} та чора-тадбир · {{ $totals['lines'] }} та индикатор</p>
      </div>
      <label class="ad-select">
        <span>Ой</span>
        <select wire:change="selectPeriod($event.target.value)" aria-label="Ҳисобот ойи">
          @foreach($periods as $p)
            <option value="{{ $p }}" @selected($p === $period)>{{ RoadmapPeriod::label($p) }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <div class="ad-strip">
      <span class="k saved"><i></i><b class="tnum">{{ $totals['saved'] }}</b> сақланган</span>
      <span class="k carry"><i></i><b class="tnum">{{ $totals['carry'] }}</b> ўтган ойдан</span>
      <span class="k empty"><i></i><b class="tnum">{{ $totals['empty'] }}</b> бўш</span>
    </div>
  </header>

  <div class="ad-cards">
    @foreach($cards as $c)
      @if(! $c['roadmap'])
        <article class="ad-card none" wire:key="ad-card-{{ $c['region']->code }}">
          <h3>{{ $c['region']->name_full }}</h3>
          <p class="muted">йўл харитаси йўқ</p>
        </article>
      @else
        @php $n = max(1, $c['measures']); $ct = $c['counts']; @endphp
        <article class="ad-card" wire:key="ad-card-{{ $c['region']->code }}"
                 data-region="{{ $c['region']->code }}" data-saved="{{ $ct['saved'] }}" data-carry="{{ $ct['carry'] }}" data-empty="{{ $ct['empty'] }}"
                 title="Карточкани босинг — киритиш саҳифаси. Ҳолатлар ҳар бир чора-тадбирнинг сўнгги ҳисоботи бўйича.">
          <a class="stretch" href="{{ route('admin.roadmaps.entry', ['code' => $c['region']->code, 'davr' => $period]) }}" aria-label="{{ $c['region']->name_full }} — киритиш"></a>
          <div class="top">
            <h3>{{ $c['region']->name_full }}</h3>
            <span class="n">{{ $c['measures'] }} та чора-тадбир · {{ $c['lines'] }} та индикатор</span>
          </div>
          <div class="bar" aria-hidden="true">
            <i class="saved" style="width:{{ round($ct['saved'] / $n * 100, 1) }}%"></i><i class="carry" style="width:{{ round($ct['carry'] / $n * 100, 1) }}%"></i><i class="empty" style="width:{{ round($ct['empty'] / $n * 100, 1) }}%"></i>
          </div>
          <div class="legend tnum">
            <span class="saved">{{ $ct['saved'] }} сақланган</span>
            <span class="carry">{{ $ct['carry'] }} ўтган ойдан</span>
            <span class="empty">{{ $ct['empty'] }} бўш</span>
          </div>
          <div class="pills">
            @foreach(['done', 'in_progress', 'open'] as $s)
              <span class="wr-status {{ MeasureDisplay::statusChip($s)['cls'] }}"><i></i>{{ MeasureDisplay::statusChip($s)['label'] }} <b class="tnum">{{ $c['statuses'][$s] }}</b></span>
            @endforeach
          </div>
          <div class="foot">
            <span class="last">
              @if($c['last'])
                {{ $c['last']->user?->name ?? 'терминал' }} · {{ $c['last']->created_at->timezone(ProgressSheet::TZ)->format('d.m.Y H:i') }}
              @else
                ҳали ўзгариш йўқ
              @endif
            </span>
            <button type="button" class="ad-btn ghost small" title="Тўлдирилган шаблонни юклаш"
                    wire:click="$dispatch('open-upload', { code: {{ $c['region']->code }} })">Excel’дан юклаш</button>
          </div>
        </article>
      @endif
    @endforeach
  </div>
  <livewire:admin.progress-upload :period="$period" />
</div>
