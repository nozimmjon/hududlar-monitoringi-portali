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

      <nav class="wr-kcard" aria-label="Бўлимлар">
        <div class="kt">Бўлимлар</div>
        @php $allOn = $section === 'all' && $district === 'all'; @endphp
        <button type="button" class="{{ $allOn ? 'on' : '' }}" aria-pressed="{{ $allOn ? 'true' : 'false' }}"
                wire:click="selectSection('all')">Барчаси<span class="n tnum">{{ $kpi['total'] }}</span></button>
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
            <button type="button" class="{{ $dOn ? 'on' : '' }}"@if($d['head']) title="{{ $d['head'] }}"@endif
                    aria-pressed="{{ $dOn ? 'true' : 'false' }}"
                    wire:click="selectDistrict('{{ $d['code'] }}')">
              <span class="t">{{ $d['name'] }}</span><span class="n tnum">{{ $d['count'] }}</span>
            </button>
          @endforeach
        </nav>
      @endif
    </aside>

    <main class="wr-main">
      <header class="wr-head">
        <h2>Сув хўжалиги йўл харитаси</h2>
        <p class="sub">{{ $roadmap->title_text }}</p>
      </header>

      <div class="wr-kpis">
        <div class="wr-kpi"><b class="tnum">{{ $kpi['total'] }}</b><span>жами чора-тадбир</span></div>
        <div class="wr-kpi"><b class="tnum">{{ $kpi['region_level'] }}</b><span>вилоят даражаси</span></div>
        <div class="wr-kpi"><b class="tnum">{{ $kpi['district_level'] }}</b><span>туман лойиҳалари</span></div>
        <div class="wr-kpi"><b class="tnum">{{ $kpi['districts'] }}</b><span>туман</span></div>
      </div>

      @if($filtered)
        <div class="wr-filterbar">
          <span>Кўрсатилмоқда: <b class="tnum">{{ $shown }}</b> / {{ $kpi['total'] }}</span>
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
          @foreach($g['measures'] as $m)
            @php $lines = $m->detailLines(); @endphp
            <article class="wr-card" wire:key="wr-m-{{ $m->id }}" x-data="{ open: false }">
              <span class="no tnum">{{ $m->seq_no }}</span>
              <div class="body">
                <div class="ttl">{{ $m->title }}</div>
                @if($lines !== [])
                  <button type="button" class="wr-more" aria-expanded="false"
                          x-on:click="open = !open" :aria-expanded="open">
                    <span class="c" :class="open && 'open'">▸</span>
                    <span x-text="open ? 'Ёпиш' : 'Батафсил ({{ count($lines) }} банд)'">Батафсил ({{ count($lines) }} банд)</span>
                  </button>
                  <div class="wr-details" x-show="open" x-cloak>
                    @foreach($lines as $line)<p>{{ $line }}</p>@endforeach
                  </div>
                @endif
                <div class="chips">
                  @if($m->funding_text)<span class="wr-chip" title="{{ $m->funding_text }}">{{ $m->funding_text }}</span>@endif
                </div>
              </div>
              <div class="col"><b>Масъуллар</b>{{ $m->responsible_text ?? '—' }}</div>
              <div class="col"><b>Муддат</b>{{ $m->deadline_text ?? '—' }}</div>
            </article>
          @endforeach
        </section>
      @empty
        <div class="wr-empty small">Мос чора-тадбир топилмади.</div>
      @endforelse
    </main>
  @endif
</div>
