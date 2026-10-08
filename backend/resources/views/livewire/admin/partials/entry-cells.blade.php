{{-- The five data cells of one indicator line: unit, plan, the «Амалда» input, its percentage, the
     note. The data-* attributes and the wire:keys are the contract public/js/ad-entry.js reads —
     keep them exactly. Expects $m, $l, $panelOpen, $version. --}}
@php
    use App\Support\Roadmaps\EntryValue;
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
    $pct = $l['plan'] !== null && $l['plan'] != 0.0 && $l['actual'] !== null ? $l['actual'] / $l['plan'] * 100 : null;
@endphp
<div class="c-unit">{{ $l['unit'] }}</div>
<div class="c-plan ad-num">{{ MeasureDisplay::fmt($l['plan']) }}</div>
<div class="c-actual">
  <div class="ad-cell actual {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'actual')" wire:key="ac-{{ $l['id'] }}-{{ $version }}"
       @if($l['state'] === 'carry') title="{{ RoadmapPeriod::label($l['source_period']) }}дан кўчирилган" @endif>
    <input type="text" inputmode="decimal" class="ad-num" value="{{ EntryValue::format($l['actual']) }}" placeholder="—"
           data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="actual" data-plan="{{ $l['plan'] ?? '' }}" data-loaded="{{ $l['actual'] ?? '' }}" data-loaded-text="{{ EntryValue::format($l['actual']) }}" data-state="{{ $l['state'] }}"
           x-on:input="onInput($event)" x-on:keydown="cellKey($event)" x-on:blur="onBlur($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — амалда, режа {{ MeasureDisplay::fmt($l['plan']) }} {{ $l['unit'] }}">
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
