<div class="front-kpis module-kpis {{ $layoutClass }}">
    @foreach($codes as $code)
        @php
            $ind = $indicators->get($code);
            if (! $ind) continue;
            $fact = $facts->get($code);
            $active = $code === $selectedKpi ? 'active' : '';
            $parent = ($code === 'grp' && $isMacro) ? 'parent' : '';
            $meta = $code === $selectedKpi ? 'Танланган KPI' : 'Кўрсаткични очиш';
        @endphp
        <button class="front-kpi {{ $active }} {{ $parent }}"
                wire:click="selectKpi('{{ $code }}')"
                type="button"
                aria-label="{{ $ind->label_full }}">
            <div class="kpi-icon">
                @include('partials.icon', ['name' => $ind->icon ?? 'trend'])
            </div>
            <div class="front-kpi-copy">
                <h3>{{ $ind->label_short }}</h3>
                <p>{{ $ind->label_full }}</p>
                <span class="front-kpi-meta">
                    <i class="front-kpi-dot" aria-hidden="true"></i>{{ $meta }}
                </span>
            </div>
        </button>
    @endforeach
</div>
