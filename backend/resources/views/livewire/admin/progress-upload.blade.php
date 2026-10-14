@php use App\Support\Roadmaps\RoadmapPeriod; @endphp
<div class="ad-upload-host">
  @if($open)
    <div class="ad-scrim" wire:click="close" aria-hidden="true"></div>
    {{-- rendered only while open, so the window-level Escape handler lives and dies with the panel --}}
    <aside class="ad-upload" role="dialog" aria-modal="true" aria-label="Excel’дан юклаш"
           x-on:keydown.escape.window="$wire.close()"
           x-init="$nextTick(() => $el.querySelector('select, input, button')?.focus())">
      <div class="h">
        <div>
          <b>Excel’дан юклаш</b>
          <span class="muted">{{ $region?->name_full ?? '' }}</span>
        </div>
        <button type="button" class="ad-ico" wire:click="close" aria-label="Ёпиш">✕</button>
      </div>

      @if($done !== '')
        <div class="ad-done">✓ {{ $done }}</div>
      @else
        {{-- a div, not a label: the picker below is the label, and labels do not nest --}}
        <div class="f">
          <span>Тўлдирилган шаблон (.xlsx)</span>
          {{-- the native control writes «Choose File / No file chosen» in English; the input stays, only hidden --}}
          <label class="ad-file" x-data="{ name: '' }">
            <input type="file" accept=".xlsx" wire:model="file"
                   x-on:change="name = $event.target.files[0]?.name ?? ''" aria-label="Тўлдирилган шаблон (.xlsx)">
            <span class="ad-file-btn">Файл танлаш</span>
            <span class="ad-file-name" x-text="name || 'Файл танланмаган'">Файл танланмаган</span>
          </label>
          @error('file')<em>{{ $message }}</em>@enderror
          <div wire:loading wire:target="file" class="muted">Файл юкланмоқда…</div>
        </div>
        <label class="f">
          <span>Ҳисобот ойи</span>
          <select wire:model="uploadPeriod">
            @foreach($periods as $p)<option value="{{ $p }}">{{ RoadmapPeriod::label($p) }}</option>@endforeach
          </select>
          @error('uploadPeriod')<em>{{ $message }}</em>@enderror
        </label>
        <div class="acts">
          <button type="button" class="ad-btn ghost" wire:click="check" wire:loading.attr="disabled">Текшириш</button>
          @if($report && $report['ok'])
            <button type="button" class="ad-btn primary" wire:click="apply" wire:loading.attr="disabled">Қўллаш</button>
          @endif
          <span wire:loading wire:target="check,apply" class="muted">Ишланмоқда…</span>
        </div>
      @endif

      @if($report)
        <div class="rep">
          @if($report['error'] !== null)
            <p class="err">{{ $report['error'] }}</p>
          @else
            <p class="ok">{{ $report['file'] }} · {{ RoadmapPeriod::label($report['period']) }} · {{ $report['blocks'] }} та чора-тадбир{{ $report['written'] ? ' · ёзилди' : ' · текширув, ҳали ёзилмади' }}</p>
            <div class="tbl">
              <table>
                <thead><tr><th>Вилоят</th><th>Файлда/жами</th><th>Қаторлар</th><th>Ўчирилди</th><th>Амалда</th><th>Бажарилди</th><th>Бажарилмоқда</th><th>Бажарилмаган</th><th>Киритилмаган</th></tr></thead>
                <tbody>
                  @foreach($report['rows'] as $row)
                    <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @foreach($report['warnings'] as $w)
              <p class="warn">{{ $w }}</p>
            @endforeach
          @endif
        </div>
      @endif
    </aside>
  @endif
</div>
