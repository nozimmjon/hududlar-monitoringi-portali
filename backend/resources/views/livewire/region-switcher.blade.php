<div class="region-switcher">
    <label for="region-select">Вилоят</label>
    <select id="region-select" wire:change="select($event.target.value)">
        @foreach($regions as $r)
            <option value="{{ $r->code }}" @selected($r->code === $regionCode)>{{ $r->name_full }}</option>
        @endforeach
    </select>
</div>
