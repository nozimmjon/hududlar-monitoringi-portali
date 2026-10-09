@php
    $pctAll = $totals['measures'] > 0 ? round($totals['saved'] / $totals['measures'] * 100, 1) : 0;
@endphp
<div class="ad-shell ad-overview">
  <header class="ad-head">
    <div class="ad-head-row">
      <div class="ad-titles">
        <h2 class="ad-title">Йўл хариталари · мониторинг</h2>
        <p class="ad-sub">{{ $periodLabel }} · {{ count($attention) + count($rest) }} вилоят · {{ $totals['measures'] }} та чора-тадбир · {{ $totals['lines'] }} та индикатор</p>
      </div>
      {{-- no month control here for now: the list shows the current month, and ?davr= still opens another;
           the entry page and the upload drawer keep their own month selects --}}
    </div>
  </header>

  {{-- the month in four numbers, one bar: what is in, what is waiting, what is late, who has not started --}}
  <dl class="ad-stats">
    <div class="st">
      <dt>Тўлдирилди</dt>
      <dd><b class="ad-num">{{ $totals['saved'] }}</b><span>{{ $totals['measures'] }} тадан · {{ $pctAll }}%</span></dd>
    </div>
    <div class="st">
      <dt>Ўтган ойдан</dt>
      <dd><b class="ad-num">{{ $totals['carry'] }}</b><span>тасдиқлашни кутмоқда</span></dd>
    </div>
    <div class="st {{ $totals['overdue'] > 0 ? 'warn' : '' }}">
      <dt>Кечиккан</dt>
      <dd><b class="ad-num">{{ $totals['overdue'] }}</b><span>муддати ўтган, бажарилмаган</span></dd>
    </div>
    <div class="st">
      <dt>Тегилмаган вилоят</dt>
      <dd><b class="ad-num">{{ $totals['untouched'] }}</b><span>ҳали ўзгариш йўқ</span></dd>
    </div>
  </dl>

  {{-- one table; the two groups are bands inside it, so the columns line up across them --}}
  <div class="ad-table" role="table" aria-label="Вилоятлар бўйича ҳолат">
    <div class="ad-trow head" role="row">
      <span class="c-name" role="columnheader">Вилоят</span>
      <span class="c-prog" role="columnheader">Тўлдирилди</span>
      <span class="c-carry" role="columnheader">Ўтган ойдан</span>
      <span class="c-over" role="columnheader">Кечиккан</span>
      <span class="c-last" role="columnheader">Охирги ўзгариш</span>
      <span class="c-act" role="columnheader"></span>
    </div>
    @foreach([['Диққат талаб қилади', $attention], ['Қолганлари', $rest]] as [$heading, $group])
      @if($group !== [])
        <div class="ad-tgroup" role="row" wire:key="grp-{{ $loop->index }}">
          <span role="cell">{{ $heading }} <b class="tnum">{{ count($group) }}</b></span>
        </div>
        @foreach($group as $c)
          @include('livewire.admin.partials.region-row', ['c' => $c])
        @endforeach
      @endif
    @endforeach
  </div>

  <livewire:admin.progress-upload :period="$period" />
</div>
