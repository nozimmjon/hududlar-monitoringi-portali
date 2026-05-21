<div class="region-switcher">
    <label for="region-select">
        <svg viewBox="0 0 256 256" width="13" height="13" fill="currentColor" aria-hidden="true">
            <path d="M128,64a40,40,0,1,0,40,40A40,40,0,0,0,128,64Zm0,64a24,24,0,1,1,24-24A24,24,0,0,1,128,128Zm0-112a88.1,88.1,0,0,0-88,88c0,31.4,14.51,64.68,42,96.25a254.19,254.19,0,0,0,41.45,38.3,8,8,0,0,0,9.18,0A254.19,254.19,0,0,0,174,200.25c27.45-31.57,42-64.85,42-96.25A88.1,88.1,0,0,0,128,16Zm0,206c-16.53-13-72-60.75-72-118a72,72,0,0,1,144,0C200,161.23,144.53,209,128,222Z"/>
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
