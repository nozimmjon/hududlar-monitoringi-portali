<div class="region-switcher">
    <label for="region-select">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 22s-7-4.5-7-12a7 7 0 1 1 14 0c0 7.5-7 12-7 12Z"/>
            <circle cx="12" cy="10" r="2.5"/>
        </svg>
        Вилоят
    </label>
    <div class="region-select-wrap">
        <select id="region-select" wire:change="select($event.target.value)">
            @foreach($regions as $r)
                <option value="{{ $r->code }}" @selected($r->code === $regionCode)>{{ $r->name_full }}</option>
            @endforeach
        </select>
        <svg class="region-chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </div>
</div>
