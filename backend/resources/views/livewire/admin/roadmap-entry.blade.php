@php
    use App\Support\Roadmaps\EntryValue;
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
    $stateLabels = ['saved' => 'сақланган', 'carry' => 'ўтган ойдан', 'empty' => 'бўш'];
    $measureCount = count($sheet['measures']);
    $lineCount    = array_sum(array_map(fn ($m) => count($m['lines']), $sheet['measures']));
@endphp
<div class="ad-shell ad-entry" x-data="adEntry()" x-on:keydown.window="hotkey($event)"
     x-on:ad-refresh.window="tick++; $nextTick(() => reapply())"
     {{-- an import replaces the values: the buffer is dropped, never re-applied over them --}}
     x-on:ad-imported.window="onImported()">
  <header class="ad-head">
    <div class="ad-head-row">
      <div class="ad-titles">
        <h2 class="ad-title">{{ $region->name_full }}</h2>
        <p class="ad-sub">{{ RoadmapPeriod::label($period) }} · {{ $measureCount }} та чора-тадбир · {{ $lineCount }} та индикатор · <span class="tnum" x-text="count('saved')">{{ $sheet['counts']['saved'] }}</span> тўлдирилди@if($overdue > 0) · <span class="over">{{ $overdue }} кечиккан</span>@endif</p>
      </div>
      <div class="ad-controls">
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
        {{-- an import overwrites the sheet, so it must not start on top of unsaved typing --}}
        <button type="button" class="ad-btn ghost" :disabled="editedLines > 0"
                :title="editedLines > 0 ? 'Аввал ўзгаришларни сақланг ёки бекор қилинг' : 'Тўлдирилган шаблонни юклаш'"
                wire:click="$dispatch('open-upload', { code: {{ $region->code }} })">Excel’дан юклаш</button>
      </div>
    </div>
    <div class="ad-toolrow">
      <nav class="ad-pills" aria-label="Ҳолат бўйича фильтр">
        <button type="button" :class="filter === 'all' && 'on'" :aria-pressed="filter === 'all'" x-on:click="filter = 'all'">Барчаси <b class="tnum">{{ $measureCount }}</b></button>
        <button type="button" :class="filter === 'unfilled' && 'on'" :aria-pressed="filter === 'unfilled'" x-on:click="filter = 'unfilled'">Тўлдирилмаган <b class="tnum" x-text="count('unfilled')">—</b></button>
        <button type="button" :class="filter === 'edited' && 'on'" :aria-pressed="filter === 'edited'" x-on:click="filter = 'edited'">Ўзгартирилган <b class="tnum" x-text="count('edited')">0</b></button>
        <button type="button" :class="filter === 'carry' && 'on'" :aria-pressed="filter === 'carry'" x-on:click="filter = 'carry'">Ўтган ойдан <b class="tnum" x-text="count('carry')">{{ $sheet['counts']['carry'] }}</b></button>
        <button type="button" :class="filter === 'saved' && 'on'" :aria-pressed="filter === 'saved'" x-on:click="filter = 'saved'">Сақланган <b class="tnum" x-text="count('saved')">{{ $sheet['counts']['saved'] }}</b></button>
      </nav>
      <div class="ad-search"><input type="search" x-model.debounce.150ms="search" placeholder="Чора-тадбир ёки индикатор бўйича қидириш…" aria-label="Қидирув"></div>
    </div>
  </header>

  @if($error !== '')
    <div class="ad-alert" role="alert" wire:key="err-{{ $version }}-{{ md5($error) }}">
      <span>{{ $error }}</span>
      <button type="button" class="ad-ico" wire:click="$set('error', '')" aria-label="Ёпиш">✕</button>
    </div>
  @endif

  {{-- One worksheet. Every row — the column header, a single-line measure, a line of a multi-line
       measure — lays out on the same grid tracks, so the eye reads straight down each column. --}}
  <div class="ad-grid">
    <div class="ad-cols" aria-hidden="true">
      <span class="c-no">№</span>
      <span class="c-label">Чора-тадбир · индикатор</span>
      <span class="c-unit">Ўлчов</span>
      <span class="c-plan">Режа</span>
      <span class="c-actual">Амалда</span>
      <span class="c-pct">%</span>
      <span class="c-note">Изоҳ</span>
      <span class="c-tools"></span>
    </div>

    @foreach($groups as $g)
      <section class="ad-sec" wire:key="g-{{ $g['key'] }}" x-show="showGroup($el)">
        <header class="ad-sec-head">
          <span class="rn">{{ $g['roman'] }}</span>
          <span class="st">{{ $g['section_title'] }}</span>
          @if($g['district'])
            <span class="dn">{{ $g['district'] }}</span>
            @if($g['head'])<span class="hd">{{ $g['head'] }}</span>@endif
          @endif
          <span class="cnt tnum">{{ count($g['measures']) }}</span>
        </header>

        @foreach($g['measures'] as $m)
          @php
            $deadline  = MeasureDisplay::deadlineChip($m['deadline_text'], $roadmap->year, $m['status'], $today);
            // the year-end default («… декабрь») is on 9 measures in 10 and says nothing; only an
            // overdue deadline or an unusual one is worth a word next to the title
            $dueNote   = $deadline['cls'] === 'over' ? 'over' : (mb_stripos((string) $m['deadline_text'], 'декабр') === false ? 'due' : null);
            $search    = mb_strtolower(implode(' ', array_merge([$m['title'], $g['section_title'], (string) $g['district']], $m['details'], array_column($m['lines'], 'label'))));
            $panelOpen = $panelFor === $m['id'];
            $single    = count($m['lines']) === 1;
          @endphp
          <article class="ad-m" wire:key="m-{{ $m['id'] }}" data-measure="{{ $m['id'] }}" data-state="{{ $m['state'] }}" data-deadline="{{ $m['deadline_reached'] ? 1 : 0 }}"
                   data-search="{{ $search }}" x-show="show($el)" x-data="{ det: false }">

            @if($single)
              @php $l = $m['lines'][0]; @endphp
              <div class="ad-row one" wire:key="l-{{ $l['id'] }}" title="{{ $l['label'] }}">
                <span class="c-no tnum">{{ $m['seq_no'] }}</span>
                @include('livewire.admin.partials.entry-title', ['m' => $m, 'deadline' => $deadline, 'dueNote' => $dueNote, 'stateLabels' => $stateLabels])
                @include('livewire.admin.partials.entry-cells', ['m' => $m, 'l' => $l, 'panelOpen' => $panelOpen, 'version' => $version])
                <span class="c-tools">
                  <button type="button" class="ad-ico" title="Индикаторларни тузатиш" aria-label="Тузатиш" wire:click="openPanel({{ $m['id'] }})">✎</button>
                  <button type="button" class="ad-ico" title="Ўзгаришлар тарихи" aria-label="Тарих" wire:click="openHistory({{ $m['id'] }})">🕓</button>
                </span>
              </div>
            @else
              <div class="ad-row head">
                <span class="c-no tnum">{{ $m['seq_no'] }}</span>
                @include('livewire.admin.partials.entry-title', ['m' => $m, 'deadline' => $deadline, 'dueNote' => $dueNote, 'stateLabels' => $stateLabels])
                <span class="c-tools">
                  <button type="button" class="ad-ico" title="Индикаторларни тузатиш" aria-label="Тузатиш" wire:click="openPanel({{ $m['id'] }})">✎</button>
                  <button type="button" class="ad-ico" title="Ўзгаришлар тарихи" aria-label="Тарих" wire:click="openHistory({{ $m['id'] }})">🕓</button>
                </span>
              </div>
              @forelse($m['lines'] as $l)
                <div class="ad-row line" wire:key="l-{{ $l['id'] }}">
                  <span class="c-no"></span>
                  <div class="c-label sub">{{ $l['label'] }}</div>
                  @include('livewire.admin.partials.entry-cells', ['m' => $m, 'l' => $l, 'panelOpen' => $panelOpen, 'version' => $version])
                  <span class="c-tools"></span>
                </div>
              @empty
                <div class="ad-row none">Индикаторлар белгиланмаган — ✎ орқали қўшинг</div>
              @endforelse
            @endif

            @if($panelOpen)
              <div class="ad-prow" wire:key="p-{{ $m['id'] }}">@include('livewire.admin.partials.correction-panel')</div>
            @endif
          </article>
        @endforeach
      </section>
    @endforeach

    {{-- with every section hidden the sheet would be blank: say so, and offer the way back --}}
    <div class="ad-empty" x-show="visibleMeasures === 0" x-cloak>
      Фильтрга мос чора-тадбир йўқ ·
      <button type="button" class="ad-link" x-on:click="filter = 'all'; search = ''">Барчасини кўрсатиш</button>
    </div>
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
