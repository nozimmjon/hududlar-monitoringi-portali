{{-- Inline editor for one measure's indicator definitions. Expects $panelLines from RoadmapEntry. --}}
<div class="ad-panel" x-data="{ armed: null }">
  <div class="h">
    <b>✎ Индикаторларни тузатиш</b>
    <span class="muted">Тартиб = рўйхат тартиби · ↑/↓ билан кўчиринг</span>
  </div>
  <div class="rows">
    <div class="r head"><span>Индикатор</span><span>Ўлчов</span><span>Режа</span><span></span></div>
    @foreach($panelLines as $i => $pl)
      <div class="r" wire:key="pl-{{ $i }}">
        <textarea rows="2" wire:model="panelLines.{{ $i }}.label" aria-label="Индикатор номи"></textarea>
        <input type="text" wire:model="panelLines.{{ $i }}.unit" maxlength="48" aria-label="Ўлчов">
        <input type="text" inputmode="decimal" class="tnum" wire:model="panelLines.{{ $i }}.plan" aria-label="Режа">
        <span class="t">
          <button type="button" class="ad-ico" wire:click="panelMove({{ $i }}, -1)" x-on:click="armed = null" @disabled($i === 0) aria-label="Юқорига">↑</button>
          <button type="button" class="ad-ico" wire:click="panelMove({{ $i }}, 1)" x-on:click="armed = null" @disabled($i === count($panelLines) - 1) aria-label="Пастга">↓</button>
          <template x-if="armed !== {{ $i }}">
            <button type="button" class="ad-ico danger" x-on:click="armed = {{ $i }}" aria-label="Ўчириш">🗑</button>
          </template>
          <template x-if="armed === {{ $i }}">
            <span class="ad-arm small">
              <span>{{ $pl['months'] > 0 ? $pl['months'] . ' ойлик ҳисобот ҳам ўчирилади.' : 'Ўчирилсинми?' }}</span>
              <button type="button" class="ad-btn danger small" x-on:click="armed = null; $wire.panelRemove({{ $i }})">Ўчириш</button>
              <button type="button" class="ad-btn ghost small" x-on:click="armed = null">Йўқ</button>
            </span>
          </template>
        </span>
      </div>
    @endforeach
  </div>
  <div class="f">
    <button type="button" class="ad-btn ghost" wire:click="panelAdd">+ Индикатор қўшиш</button>
    <span class="sp"></span>
    <button type="button" class="ad-btn ghost" wire:click="panelClose">Бекор</button>
    <button type="button" class="ad-btn primary" wire:click="panelSave">Сақлаш</button>
  </div>
</div>
