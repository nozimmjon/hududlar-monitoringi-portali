@php
    use App\Support\Roadmaps\EntryValue;
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
    $stateLabels = ['saved' => 'сақланган', 'carry' => 'ўтган ойдан', 'empty' => 'бўш'];
@endphp
<div class="ad-shell ad-entry" x-data="adEntry()" x-on:keydown.window="hotkey($event)"
     x-on:ad-refresh.window="tick++; $nextTick(() => reapply())"
     {{-- an import replaces the values: the buffer is dropped, never re-applied over them --}}
     x-on:ad-imported.window="onImported()">
  <header class="ad-head ad-sticky">
    <div class="ad-head-row">
      <div class="ad-titles">
        <h2 class="ad-title">Амалда киритиш <span class="rg">· {{ $region->name_full }}</span></h2>
        <p class="ad-sub">{{ RoadmapPeriod::label($period) }} · {{ count($sheet['measures']) }} та чора-тадбир · йил бошидан жами қийматлар</p>
      </div>
      <label class="ad-select">
        <span>Вилоят</span>
        <select data-current="{{ $region->code }}" x-on:change="changeRegion($event)" aria-label="Вилоят">
          @foreach($regions as $r)
            <option value="{{ $r->code }}" @selected($r->code === $region->code)>{{ $r->name_full }}</option>
          @endforeach
        </select>
      </label>
      <label class="ad-select">
        <span>Ой</span>
        <select data-current="{{ $period }}" x-on:change="changePeriod($event)" aria-label="Ҳисобот ойи">
          @foreach($periods as $p)
            <option value="{{ $p }}" @selected($p === $period)>{{ RoadmapPeriod::label($p) }}</option>
          @endforeach
        </select>
      </label>
      <div class="ad-search"><input type="search" x-model.debounce.150ms="search" placeholder="Чора-тадбир, индикатор…" aria-label="Қидирув"></div>
      {{-- an import overwrites the sheet, so it must not start on top of unsaved typing --}}
      <button type="button" class="ad-btn ghost" :disabled="editedLines > 0"
              :title="editedLines > 0 ? 'Аввал ўзгаришларни сақланг ёки бекор қилинг' : 'Тўлдирилган шаблонни юклаш'"
              wire:click="$dispatch('open-upload', { code: {{ $region->code }} })">Excel’дан юклаш</button>
    </div>
    <nav class="ad-pills" aria-label="Ҳолат бўйича фильтр">
      <button type="button" :class="filter === 'all' && 'on'" :aria-pressed="filter === 'all'" x-on:click="filter = 'all'">Барчаси <b class="tnum">{{ count($sheet['measures']) }}</b></button>
      <button type="button" :class="filter === 'unfilled' && 'on'" :aria-pressed="filter === 'unfilled'" x-on:click="filter = 'unfilled'">Тўлдирилмаган <b class="tnum" x-text="count('unfilled')">—</b></button>
      <button type="button" :class="filter === 'edited' && 'on'" :aria-pressed="filter === 'edited'" x-on:click="filter = 'edited'">Ўзгартирилган <b class="tnum" x-text="count('edited')">0</b></button>
      <button type="button" :class="filter === 'carry' && 'on'" :aria-pressed="filter === 'carry'" x-on:click="filter = 'carry'">Ўтган ойдан <b class="tnum" x-text="count('carry')">{{ $sheet['counts']['carry'] }}</b></button>
      <button type="button" :class="filter === 'saved' && 'on'" :aria-pressed="filter === 'saved'" x-on:click="filter = 'saved'">Сақланган <b class="tnum" x-text="count('saved')">{{ $sheet['counts']['saved'] }}</b></button>
    </nav>
  </header>

  @if($error !== '')
    <div class="ad-alert" role="alert" wire:key="err-{{ $version }}-{{ md5($error) }}">
      <span>{{ $error }}</span>
      <button type="button" class="ad-ico" wire:click="$set('error', '')" aria-label="Ёпиш">✕</button>
    </div>
  @endif

  @foreach($groups as $g)
    <section class="ad-sheet ad-sec" wire:key="g-{{ $g['key'] }}" x-show="showGroup($el)">
      <header class="ad-sec-head">
        <div class="t">
          <span class="rn">{{ $g['roman'] }}</span>
          <span class="st">{{ $g['section_title'] }}</span>
          @if($g['district'])
            <span class="dn">{{ $g['district'] }}</span>
            @if($g['head'])<span class="hd">{{ $g['head'] }}</span>@endif
          @endif
        </div>
        <span class="cnt tnum">{{ count($g['measures']) }} та чора-тадбир</span>
        <span class="cols"><span>Режа</span><span>Амалда</span><span>%</span></span>
      </header>

      @foreach($g['measures'] as $m)
        @php
          $deadline  = MeasureDisplay::deadlineChip($m['deadline_text'], $roadmap->year, $m['status'], $today);
          $search    = mb_strtolower(implode(' ', array_merge([$m['title'], $g['section_title'], (string) $g['district']], $m['details'], array_column($m['lines'], 'label'))));
          $panelOpen = $panelFor === $m['id'];
        @endphp
        <article class="ad-m" wire:key="m-{{ $m['id'] }}" data-measure="{{ $m['id'] }}" data-state="{{ $m['state'] }}" data-deadline="{{ $m['deadline_reached'] ? 1 : 0 }}"
                 data-search="{{ $search }}" :class="'st-' + statusClass({{ $m['id'] }})" x-show="show($el)" x-data="{ det: false }">
          <div class="ad-mrow">
            <span class="no ad-num" :class="mstate({{ $m['id'] }}) !== 'empty' && 'on'">{{ $m['seq_no'] }}</span>
            <div class="ttl">
              <div class="t">{{ $m['title'] }}</div>
              <div class="meta">
                <span class="due">муддат: {{ $deadline['label'] }}</span>
                @if($deadline['cls'] === 'over')<span class="ad-chip warn">кечиккан</span>@endif
                <span class="ad-chip" :class="mstateChip({{ $m['id'] }})" x-show="mstate({{ $m['id'] }}) !== 'empty'" x-text="mstateLabel({{ $m['id'] }})" x-cloak>{{ $stateLabels[$m['state']] }}</span>
              </div>
              @if($m['details'] !== [])
                <button type="button" class="wr-more" x-on:click="det = !det" :aria-expanded="det">
                  <span class="c" :class="det && 'open'">▸</span>
                  <span x-text="det ? 'Ёпиш' : 'Батафсил ({{ count($m['details']) }} банд)'">Батафсил ({{ count($m['details']) }} банд)</span>
                </button>
                <div class="wr-details" x-show="det" x-cloak>@foreach($m['details'] as $line)<p>{{ $line }}</p>@endforeach</div>
              @endif
            </div>
            <span class="ad-tools">
              <button type="button" class="ad-ico" title="Индикаторларни тузатиш" aria-label="Тузатиш" wire:click="openPanel({{ $m['id'] }})">✎</button>
              <button type="button" class="ad-ico" title="Ўзгаришлар тарихи" aria-label="Тарих" wire:click="openHistory({{ $m['id'] }})">🕓</button>
            </span>
          </div>

          @forelse($m['lines'] as $l)
            @php $pct = $l['plan'] !== null && $l['plan'] != 0.0 && $l['actual'] !== null ? $l['actual'] / $l['plan'] * 100 : null; @endphp
            <div class="ad-lrow" wire:key="l-{{ $l['id'] }}">
              <div class="c-label">{{ $l['label'] }}</div>
              <div class="c-unit">{{ $l['unit'] }}</div>
              <div class="c-plan ad-num">{{ MeasureDisplay::fmt($l['plan']) }}</div>
              <div class="c-actual">
                <div class="ad-cell actual {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'actual')" wire:key="ac-{{ $l['id'] }}-{{ $version }}"
                     @if($l['state'] === 'carry') title="{{ RoadmapPeriod::label($l['source_period']) }}дан кўчирилган" @endif>
                  <input type="text" inputmode="decimal" class="ad-num" value="{{ EntryValue::format($l['actual']) }}" placeholder="—"
                         data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="actual" data-plan="{{ $l['plan'] ?? '' }}" data-loaded="{{ $l['actual'] ?? '' }}" data-loaded-text="{{ EntryValue::format($l['actual']) }}" data-state="{{ $l['state'] }}"
                         x-on:input="onInput($event)" x-on:keydown="cellKey($event)" x-on:blur="onBlur($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — амалда">
                  <span class="msg" x-text="invalid[{{ $l['id'] }}] || ''"></span>
                </div>
              </div>
              <div class="c-pct ad-num"><span :class="pctClass({{ $l['id'] }})" x-text="pctText({{ $l['id'] }})">{{ $pct === null ? '—' : round($pct) . '%' }}</span></div>
              <div class="c-note">
                <div class="ad-cell note {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'note')" wire:key="nc-{{ $l['id'] }}-{{ $version }}">
                  <input type="text" maxlength="500" value="{{ $l['note'] }}" placeholder="Изоҳ…"
                         data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="note" data-loaded-text="{{ $l['note'] }}"
                         x-on:input="onInput($event)" x-on:keydown="cellKey($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — изоҳ">
                </div>
              </div>
            </div>
          @empty
            <div class="ad-lrow none">Индикаторлар белгиланмаган — ✎ орқали қўшинг</div>
          @endforelse

          @if($panelOpen)
            <div class="ad-prow" wire:key="p-{{ $m['id'] }}">@include('livewire.admin.partials.correction-panel')</div>
          @endif
        </article>
      @endforeach
    </section>
  @endforeach

  {{-- with every sheet hidden the page would be blank: say so, and offer the way back --}}
  <div class="ad-sheet ad-empty" x-show="visibleMeasures === 0" x-cloak>
    Фильтрга мос чора-тадбир йўқ ·
    <button type="button" class="ad-link" x-on:click="filter = 'all'; search = ''">Барчасини кўрсатиш</button>
  </div>

  <div class="ad-savebar" x-data="{ arm: false }">
    <span class="s">
      <b class="tnum" x-text="editedLines">0</b> та ўзгариш ·
      <span class="tnum" x-text="count('carry')">{{ $sheet['counts']['carry'] }}</span> чора-тадбир ўтган ойдан ·
      <span class="tnum" x-text="count('unfilled')">—</span> тўлдирилмаган
    </span>
    <span class="a">
      <button type="button" class="ad-btn ghost" x-show="editedLines > 0" x-cloak x-on:click="discard()">Бекор қилиш</button>
      <template x-if="!arm">
        <button type="button" class="ad-btn ghost" x-on:click="arm = true" @disabled($sheet['counts']['confirmable'] === 0)>Қолганини ўзгаришсиз тасдиқлаш</button>
      </template>
      <template x-if="arm">
        <span class="ad-arm">
          <span>{{ $sheet['counts']['confirmable'] }} та чора-тадбир {{ RoadmapPeriod::label($period) }} учун ҳозирги кўринишда сақланади — {{ $sheet['counts']['carry'] }} таси ўтган ой қийматлари билан, {{ $sheet['counts']['confirmable'] - $sheet['counts']['carry'] }} таси бўш.</span>
          <button type="button" class="ad-btn" x-on:click="arm = false; $wire.confirmRest()">Ҳа, тасдиқлаш</button>
          <button type="button" class="ad-btn ghost" x-on:click="arm = false">Йўқ</button>
        </span>
      </template>
      <button type="button" class="ad-btn primary" :disabled="editedLines === 0 || saving" x-on:click="save()">
        <span x-text="saving ? 'Сақланмоқда…' : 'Сақлаш'">Сақлаш</span>
      </button>
    </span>
  </div>

  @if($flash !== '')
    <div class="ad-toast" role="status" wire:key="toast-{{ $version }}" x-data="{ on: true }" x-show="on" x-init="setTimeout(() => on = false, 4000)">{{ $flash }}</div>
  @endif
  <div class="ad-toast warn" role="status" x-show="jsToast !== ''" x-text="jsToast" x-cloak></div>

  @if($historyFor !== null)
    @include('livewire.admin.partials.history-drawer')
  @endif

  <livewire:admin.progress-upload :period="$period" />
</div>
