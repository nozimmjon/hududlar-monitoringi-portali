@php
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
    use Illuminate\Support\Str;
@endphp
<div class="wr-shell">
  @if(! $roadmap)
    <div class="wr-empty">
      <h2>{{ $region->name_full }} учун сув хўжалиги йўл харитаси ҳали юкланмаган</h2>
      <p>Импорт: <code>php artisan import:roadmap --region={{ $region->code }}</code></p>
    </div>
  @else
    <aside class="wr-rail">
      <div class="wr-search">
        <input type="search" placeholder="Чора-тадбир, масъул, манба…"
               wire:model.live.debounce.500ms="q" aria-label="Чора-тадбирлар бўйича қидирув">
      </div>

      <div class="wr-hero">
        <div class="ht"><span class="kt">Умумий ижро</span><span class="per">{{ $hero['period'] }}</span></div>
        <div class="hb">
          <div class="wr-ring big" role="img" aria-label="Умумий ижро {{ $hero['pct'] === null ? 'маълумот йўқ' : $hero['pct'] . '%' }}">
            <svg viewBox="0 0 104 104">
              <circle class="tr" cx="52" cy="52" r="45"/>
              <circle class="fg" cx="52" cy="52" r="45"
                      style="stroke-dasharray:282.7;stroke-dashoffset:{{ number_format(282.7 * (1 - min(100, $hero['pct'] ?? 0) / 100), 1, '.', '') }}"/>
            </svg>
            <div class="cv"><b class="tnum">{{ $hero['pct'] === null ? '—' : $hero['pct'] . '%' }}</b></div>
          </div>
          <div class="hs">
            <div><b class="tnum">{{ $counts['done'] }}<small>/{{ $counts['all'] }}</small></b><span>тадбир бажарилди</span></div>
            <div><b class="tnum">{{ $hero['lines_done'] }}<small>/{{ $hero['lines_total'] }}</small></b><span>индикатор</span></div>
            @if($hero['no_lines'] > 0)<div class="nl">{{ $hero['no_lines'] }} тадбирда индикатор йўқ</div>@endif
          </div>
        </div>
      </div>

      <nav class="wr-kcard wr-fbtns" aria-label="Ҳолат бўйича фильтр">
        @foreach(['all' => 'Барчаси', 'done' => 'Бажарилди', 'in_progress' => 'Бажарилмоқда', 'open' => 'Бажарилмаган'] as $key => $label)
          <button type="button" class="f-{{ $key }} {{ $status === $key ? 'on' : '' }}"
                  aria-pressed="{{ $status === $key ? 'true' : 'false' }}"
                  wire:click="selectStatus('{{ $key }}')"><i></i>{{ $label }}<span class="n tnum">{{ $counts[$key] }}</span></button>
        @endforeach
      </nav>

      <nav class="wr-kcard" aria-label="Бўлимлар">
        <div class="kt">Бўлимлар</div>
        @php $allOn = $section === 'all' && $district === 'all'; @endphp
        <button type="button" class="{{ $allOn ? 'on' : '' }}" aria-pressed="{{ $allOn ? 'true' : 'false' }}"
                wire:click="selectSection('all')">Барчаси<span class="n tnum">{{ $counts['all'] }}</span></button>
        @foreach($sections as $s)
          @php $on = $district === 'all' ? $section === (string) $s['no'] : $districtSectionNo === $s['no']; @endphp
          <button type="button" class="{{ $on ? 'on' : '' }}" title="{{ $s['title'] }}"
                  aria-pressed="{{ $on ? 'true' : 'false' }}"
                  wire:click="selectSection('{{ $s['no'] }}')">
            <b>{{ $s['roman'] }}.</b> <span class="t">{{ $s['title'] }}</span><span class="n tnum">{{ $s['count'] }}</span>
          </button>
        @endforeach
      </nav>

      @if($districts->isNotEmpty())
        <nav class="wr-kcard" aria-label="Туманлар">
          <div class="kt">Туманлар</div>
          @foreach($districts as $d)
            @php $dOn = $district === (string) $d['code']; @endphp
            <button type="button" class="wr-drow {{ $dOn ? 'on' : '' }}"
                    title="{{ $d['count'] }} та чора-тадбир{{ $d['head'] ? ' · ' . $d['head'] : '' }}"
                    aria-pressed="{{ $dOn ? 'true' : 'false' }}"
                    wire:click="selectDistrict('{{ $d['code'] }}')">
              <span class="t">{{ $d['name'] }}</span>
              <span class="mb" aria-hidden="true"><i style="width:{{ $d['pct'] ?? 0 }}%"></i></span>
              <span class="p tnum">{{ $d['pct'] === null ? '—' : $d['pct'] . '%' }}</span>
            </button>
          @endforeach
        </nav>
      @endif
    </aside>

    <main class="wr-main">
      <header class="wr-head">
        <h2>Сув хўжалиги йўл харитаси <span class="rg">· {{ $region->name_full }}</span></h2>
      </header>

      <div class="wr-kpis">
        <div class="wr-kpi"><b class="tnum">{{ $counts['all'] }}</b><span>жами чора-тадбир</span></div>
        <div class="wr-kpi ok"><b class="tnum">{{ $counts['done'] }}</b><span>бажарилди</span></div>
        <div class="wr-kpi wait"><b class="tnum">{{ $counts['in_progress'] }}</b><span>бажарилмоқда</span></div>
        <div class="wr-kpi bad"><b class="tnum">{{ $counts['open'] }}</b><span>бажарилмаган</span></div>
      </div>

      @if($filtered)
        <div class="wr-filterbar">
          <span>Кўрсатилмоқда: <b class="tnum">{{ $shown }}</b> / {{ $counts['all'] }}</span>
          <button type="button" wire:click="clearFilters">Фильтрни тозалаш</button>
        </div>
      @endif

      @forelse($groups as $g)
        <section class="wr-group" wire:key="wr-group-{{ $g['key'] }}">
          <h3 class="wr-gtitle">
            <span class="rn">{{ $g['roman'] }}.</span> <span class="st">{{ $g['section_title'] }}</span>
            @if($g['district'])
              <span class="sep">·</span> <span class="dn">{{ $g['district']->name_full }}</span>
              @if($g['head'])<span class="hd">{{ $g['head'] }}</span>@endif
            @endif
          </h3>
          <div class="wr-cards">
            @foreach($g['measures'] as $m)
              @php
                $docLines = $m->detailLines();
                $chip     = MeasureDisplay::statusChip($m->status);
                $pctShown = MeasureDisplay::pshow($m->pct !== null ? (float) $m->pct : null, $m->status === 'done');
                $latest   = $m->latest_period;
                $lineRows = $m->lines->map(function ($l) use ($latest) {
                    $p   = $latest ? $l->progress->firstWhere('report_period', $latest) : null;
                    $pct = $p?->pct_of_plan !== null ? (float) $p->pct_of_plan : null;
                    return [
                        'label' => $l->label, 'unit' => $l->unit, 'plan' => $l->plan_value, 'actual' => $p?->actual_value,
                        'pct' => $pct, 'tier' => MeasureDisplay::tier($pct), 'width' => MeasureDisplay::barWidth($pct), 'note' => $p?->note,
                    ];
                })->values();
                $extra    = max(0, $lineRows->count() - 4);
                $deadline = MeasureDisplay::deadlineChip($m->deadline_text, $roadmap->year, $m->status, $today);
                $notes    = $lineRows->filter(fn ($r) => $r['note'] !== null && $r['note'] !== '');
                $history  = MeasureDisplay::history($m);
                $spark    = MeasureDisplay::sparkPoints($history);
                $sparkEnd = $spark === '' ? [0, 0] : explode(',', Str::afterLast($spark, ' '));
                $moreLabel = $docLines !== [] ? 'Батафсил (' . count($docLines) . ' банд)' : 'Батафсил';
                $hasMore  = $docLines !== [] || $notes->isNotEmpty() || $spark !== '';
              @endphp
              <article class="wr-mcard st-{{ $chip['cls'] }}" wire:key="wr-m-{{ $m->id }}" x-data="{ open: false, all: false }">
                <div class="wr-ring {{ $lineRows->isEmpty() ? 'na' : '' }}" role="img"
                     aria-label="Бажарилиш {{ $pctShown === null ? 'маълумот йўқ' : $pctShown . '%' }}">
                  <svg viewBox="0 0 44 44">
                    <circle class="tr" cx="22" cy="22" r="18"/>
                    <circle class="fg" cx="22" cy="22" r="18" style="stroke-dasharray:113.1;stroke-dashoffset:{{ MeasureDisplay::ringOffset($pctShown) }}"/>
                  </svg>
                  <b class="tnum">{{ $pctShown === null ? '—' : $pctShown . '%' }}</b>
                </div>
                <div class="body">
                  <div class="head">
                    <span class="no tnum">{{ $m->seq_no }}</span>
                    <div class="ttl">{{ $m->title }}</div>
                    <span class="wr-status {{ $chip['cls'] }}"><i></i>{{ $chip['label'] }}</span>
                  </div>

                  @if($lineRows->isEmpty())
                    <div class="wr-line none"><span class="lb">Индикаторлар ҳали белгиланмаган</span></div>
                  @else
                    @foreach($lineRows as $i => $r)
                      <div class="wr-line t-{{ $r['tier'] }}" @if($i >= 4) x-show="all" x-cloak @endif>
                        <span class="lb" title="{{ $r['label'] }}">{{ $r['label'] }}</span>
                        <span class="bar" aria-hidden="true"><i style="width:{{ $r['width'] }}%"></i><span class="tick"></span></span>
                        <span class="pv tnum"><b>{{ MeasureDisplay::fmt($r['actual']) }}</b> / {{ MeasureDisplay::fmt($r['plan']) }} {{ $r['unit'] }}</span>
                        <span class="pp tnum">{{ $r['pct'] === null ? '—' : round($r['pct']) . '%' }}</span>
                      </div>
                    @endforeach
                    @if($extra > 0)
                      <button type="button" class="wr-more" aria-expanded="false" x-on:click="all = !all" :aria-expanded="all">
                        <span class="c" :class="all && 'open'">▸</span>
                        <span x-text="all ? 'Камроқ' : 'яна {{ $extra }} индикатор'">яна {{ $extra }} индикатор</span>
                      </button>
                    @endif
                  @endif

                  <div class="foot">
                    <span class="wr-tag {{ $deadline['cls'] }}">{{ $deadline['label'] }}</span>
                    @if($m->funding_text)
                      <span class="wr-chip {{ mb_stripos($m->funding_text, 'талаб этилмайди') !== false ? 'muted' : '' }}" title="{{ $m->funding_text }}">{{ $m->funding_text }}</span>
                    @endif
                    @if($m->responsible_text)
                      <span class="wr-chip" title="{{ $m->responsible_text }}">{{ mb_strimwidth($m->responsible_text, 0, 60, '…') }}</span>
                    @endif
                  </div>

                  @if($hasMore)
                    <button type="button" class="wr-more" aria-expanded="false" x-on:click="open = !open" :aria-expanded="open">
                      <span class="c" :class="open && 'open'">▸</span>
                      <span x-text="open ? 'Ёпиш' : '{{ $moreLabel }}'">{{ $moreLabel }}</span>
                    </button>
                    <div class="wr-details" x-show="open" x-cloak>
                      @foreach($docLines as $line)<p>{{ $line }}</p>@endforeach
                      @if($notes->isNotEmpty())
                        <div class="wr-notes">
                          @foreach($notes as $r)<p><b>{{ $r['label'] }}:</b> {{ $r['note'] }}</p>@endforeach
                        </div>
                      @endif
                      @if($spark !== '')
                        <div class="wr-spark">
                          <span class="sl">{{ RoadmapPeriod::label($history[0]['period']) }}</span>
                          <svg viewBox="0 0 120 28" width="120" height="28" aria-hidden="true">
                            <polyline points="{{ $spark }}"/>
                            <circle cx="{{ $sparkEnd[0] }}" cy="{{ $sparkEnd[1] }}" r="2.5"/>
                          </svg>
                          <span class="sr"><b class="tnum">{{ MeasureDisplay::fmt($history[array_key_last($history)]['pct']) }}%</b> · {{ RoadmapPeriod::label($history[array_key_last($history)]['period']) }}</span>
                        </div>
                      @endif
                    </div>
                  @endif
                </div>
              </article>
            @endforeach
          </div>
        </section>
      @empty
        <div class="wr-empty small">Мос чора-тадбир топилмади.</div>
      @endforelse
    </main>
  @endif
</div>
