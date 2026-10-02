@php
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
    <div class="ad-kpis">
      <div class="ad-kpi">
        <span class="k">Тўлдирилди</span>
        <b class="v ad-num">{{ $totals['saved'] }}</b>
        <span class="d">{{ $totals['measures'] }} тадан · {{ $totals['measures'] > 0 ? round($totals['saved'] / $totals['measures'] * 100, 1) : 0 }}%</span>
      </div>
      <div class="ad-kpi">
        <span class="k">Ўтган ойдан</span>
        <b class="v ad-num">{{ $totals['carry'] }}</b>
        <span class="d">тасдиқлашни кутмоқда</span>
      </div>
      <div class="ad-kpi {{ $totals['overdue'] > 0 ? 'warn' : '' }}">
        <span class="k">Кечиккан</span>
        <b class="v ad-num">{{ $totals['overdue'] }}</b>
        <span class="d">муддати ўтган, бажарилмаган</span>
      </div>
      <div class="ad-kpi">
        <span class="k">Тегилмаган вилоят</span>
        <b class="v ad-num">{{ $totals['untouched'] }}</b>
        <span class="d">ҳали ўзгариш йўқ</span>
      </div>
    </div>
  </header>

  @foreach([['Диққат талаб қилади', $attention], ['Қолганлари', $rest]] as [$heading, $group])
    @if($group !== [])
      <section class="ad-group-head" wire:key="grp-{{ $loop->index }}">
        <h3>{{ $heading }}</h3>
        <span class="rule"></span>
        <span class="n tnum">{{ count($group) }}</span>
      </section>
      <div class="ad-cards">
        @foreach($group as $c)
          @include('livewire.admin.partials.region-card', ['c' => $c])
        @endforeach
      </div>
    @endif
  @endforeach

  <livewire:admin.progress-upload :period="$period" />
</div>
