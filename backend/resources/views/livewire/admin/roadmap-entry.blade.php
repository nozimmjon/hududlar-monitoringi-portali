@php
    use App\Support\Roadmaps\EntryValue;
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
    $stateLabels = ['saved' => 'сақланган', 'carry' => 'ўтган ойдан', 'empty' => 'бўш'];
@endphp
<div class="ad-shell ad-entry" x-data="adEntry()" x-on:keydown.window="hotkey($event)" x-on:ad-refresh.window="tick++; $nextTick(() => reapply())">
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
      <button type="button" class="ad-btn ghost" wire:click="$dispatch('open-upload', { code: {{ $region->code }} })">Excel’дан юклаш</button>
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

  <div class="ad-gridwrap">
    <table class="ad-grid">
      <thead>
        <tr>
          <th class="c-label">Индикатор</th><th class="c-unit">Ўлчов</th><th class="c-plan">Режа</th>
          <th class="c-actual">Амалда</th><th class="c-pct">%</th><th class="c-note">Изоҳ</th>
        </tr>
      </thead>
      @foreach($groups as $g)
        <tbody class="ad-group" wire:key="g-{{ $g['key'] }}" x-show="showGroup($el)">
          <tr><th colspan="6">
            <span class="rn">{{ $g['roman'] }}.</span> <span class="st">{{ $g['section_title'] }}</span>
            @if($g['district'])
              <span class="dn">{{ $g['district'] }}</span>
              @if($g['head'])<span class="hd">{{ $g['head'] }}</span>@endif
            @endif
          </th></tr>
        </tbody>
        @foreach($g['measures'] as $m)
          @php
            $chip      = MeasureDisplay::statusChip($m['status']);
            $pctShown  = MeasureDisplay::pshow($m['pct'], $m['status'] === 'done');
            $deadline  = MeasureDisplay::deadlineChip($m['deadline_text'], $roadmap->year, $m['status'], $today);
            $search    = mb_strtolower(implode(' ', array_merge([$m['title'], $g['section_title'], (string) $g['district']], $m['details'], array_column($m['lines'], 'label'))));
            $panelOpen = $panelFor === $m['id'];
          @endphp
          <tbody class="ad-m" wire:key="m-{{ $m['id'] }}" data-measure="{{ $m['id'] }}" data-state="{{ $m['state'] }}" data-deadline="{{ $m['deadline_reached'] ? 1 : 0 }}"
                 data-search="{{ $search }}" :class="'st-' + statusClass({{ $m['id'] }})" x-show="show($el)" x-data="{ det: false }">
            <tr class="ad-mrow">
              <td colspan="6">
                <div class="ad-mhead">
                  <div class="wr-ring {{ $m['lines'] === [] ? 'na' : '' }}" role="img" aria-label="Бажарилиш">
                    <svg viewBox="0 0 44 44">
                      <circle class="tr" cx="22" cy="22" r="18"/>
                      <circle class="fg" cx="22" cy="22" r="18" :style="ringStyle({{ $m['id'] }})" style="stroke-dasharray:113.1;stroke-dashoffset:{{ MeasureDisplay::ringOffset($pctShown) }}"/>
                    </svg>
                    <b class="tnum" x-text="ringText({{ $m['id'] }})">{{ $pctShown === null ? '—' : $pctShown . '%' }}</b>
                  </div>
                  <span class="no tnum">{{ $m['seq_no'] }}</span>
                  <div class="ttl">
                    <div class="t">{{ $m['title'] }}</div>
                    @if($m['details'] !== [])
                      <button type="button" class="wr-more" x-on:click="det = !det" :aria-expanded="det">
                        <span class="c" :class="det && 'open'">▸</span>
                        <span x-text="det ? 'Ёпиш' : 'Батафсил ({{ count($m['details']) }} банд)'">Батафсил ({{ count($m['details']) }} банд)</span>
                      </button>
                      <div class="wr-details" x-show="det" x-cloak>@foreach($m['details'] as $line)<p>{{ $line }}</p>@endforeach</div>
                    @endif
                  </div>
                  <span class="wr-status" :class="statusClass({{ $m['id'] }})"
                        @if(! $m['status_is_current'] && $m['latest_period']) title="Базадаги ҳолат: {{ RoadmapPeriod::label($m['latest_period']) }} ҳисоботи бўйича" @endif><i></i><span x-text="statusLabel({{ $m['id'] }})">{{ $chip['label'] }}</span></span>
                  <span class="wr-tag {{ $deadline['cls'] }}">{{ $deadline['label'] }}</span>
                  <span class="ad-state" :class="mstate({{ $m['id'] }})" x-text="mstateLabel({{ $m['id'] }})">{{ $stateLabels[$m['state']] }}</span>
                  <span class="ad-tools">
                    <button type="button" class="ad-ico" title="Индикаторларни тузатиш" aria-label="Тузатиш" wire:click="openPanel({{ $m['id'] }})">✎</button>
                    <button type="button" class="ad-ico" title="Ўзгаришлар тарихи" aria-label="Тарих" wire:click="openHistory({{ $m['id'] }})">🕓</button>
                  </span>
                </div>
              </td>
            </tr>
            @forelse($m['lines'] as $l)
              @php $pct = $l['plan'] !== null && $l['plan'] != 0.0 && $l['actual'] !== null ? $l['actual'] / $l['plan'] * 100 : null; @endphp
              <tr class="ad-lrow" wire:key="l-{{ $l['id'] }}">
                <td class="c-label">{{ $l['label'] }}</td>
                <td class="c-unit">{{ $l['unit'] }}</td>
                <td class="c-plan tnum">{{ MeasureDisplay::fmt($l['plan']) }}</td>
                <td class="c-actual">
                  <div class="ad-cell actual {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'actual')" wire:key="ac-{{ $l['id'] }}-{{ $version }}"
                       @if($l['state'] === 'carry') title="{{ RoadmapPeriod::label($l['source_period']) }}дан кўчирилган" @endif>
                    <input type="text" inputmode="decimal" class="tnum" value="{{ EntryValue::format($l['actual']) }}" placeholder="—"
                           data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="actual" data-plan="{{ $l['plan'] ?? '' }}" data-loaded="{{ $l['actual'] ?? '' }}" data-loaded-text="{{ EntryValue::format($l['actual']) }}" data-state="{{ $l['state'] }}"
                           x-on:input="onInput($event)" x-on:keydown="cellKey($event)" x-on:blur="onBlur($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — амалда">
                    <span class="msg" x-text="invalid[{{ $l['id'] }}] || ''"></span>
                  </div>
                </td>
                <td class="c-pct tnum"><span :class="pctClass({{ $l['id'] }})" x-text="pctText({{ $l['id'] }})">{{ $pct === null ? '—' : round($pct) . '%' }}</span></td>
                <td class="c-note">
                  <div class="ad-cell note {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'note')" wire:key="nc-{{ $l['id'] }}-{{ $version }}">
                    <input type="text" maxlength="500" value="{{ $l['note'] }}" placeholder="Изоҳ…"
                           data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="note" data-loaded-text="{{ $l['note'] }}"
                           x-on:input="onInput($event)" x-on:keydown="cellKey($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — изоҳ">
                  </div>
                </td>
              </tr>
            @empty
              <tr class="ad-lrow none"><td colspan="6">Индикаторлар белгиланмаган — ✎ орқали қўшинг</td></tr>
            @endforelse
            @if($panelOpen)
              <tr class="ad-prow" wire:key="p-{{ $m['id'] }}"><td colspan="6">@include('livewire.admin.partials.correction-panel')</td></tr>
            @endif
          </tbody>
        @endforeach
      @endforeach
    </table>
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
          <span>{{ $sheet['counts']['confirmable'] }} та чора-тадбир {{ RoadmapPeriod::label($period) }} учун ўтган ой қийматлари билан сақланади.</span>
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
