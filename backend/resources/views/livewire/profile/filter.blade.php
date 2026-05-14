@php
    $districts = \App\Models\District::where('region_code', 1703)->orderBy('sort_order')->get(['code', 'name_full']);
    $moduleCode = $availableKpis->firstWhere('code', $kpi)?->module_code ?? 'macro';
@endphp

<div class="profile-filter">
    <label>Туман/шаҳар танлаш
        <select wire:model.live="districtCode">
            @foreach($districts as $d)
                <option value="{{ $d->code }}" @selected((string) $d->code === $districtCode)>{{ $d->name_full }}</option>
            @endforeach
        </select>
    </label>
    <label>KPI / маълумот тури
        <select wire:model.live="kpi">
            @foreach($availableKpis as $i)
                <option value="{{ $i->code }}" @selected($i->code === $kpi)>{{ $i->label_short }} — {{ $i->label_full }}</option>
            @endforeach
        </select>
    </label>
    <div class="action-row" style="margin-top:0">
        <a class="mini-button" href="{{ route('districts') }}?kpi={{ $kpi }}">Туманлар кесимига қайтиш</a>
        <button class="mini-button" type="button" disabled title="Тез орада">Ҳисобот киритиш</button>
        <a class="mini-button primary" href="{{ route('dashboard') }}?module={{ $moduleCode }}&kpi={{ $kpi }}">KPI экрани</a>
    </div>
</div>
