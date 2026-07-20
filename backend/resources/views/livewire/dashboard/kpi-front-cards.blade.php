<div class="front-kpis module-kpis {{ $layoutClass }}">
    @foreach($codes as $code)
        @php
            $ind = $indicators->get($code);
            if (! $ind) continue;
            $fact = $facts->get($code);
            $active = $code === $selectedKpi ? 'active' : '';
            $parent = ($code === 'grp' && $isMacro) ? 'parent' : '';
            $growth = $fact && $fact->growth_pct !== null
                ? \App\Support\DashboardCatalog::growthValue($fact->growth_pct)
                : null;
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
                @if($growth !== null)
                    <strong class="front-kpi-value">{{ $growth }}</strong>
                    <span class="front-kpi-note">{{ $growthNote }}</span>
                @else
                    <p>{{ $ind->label_full }}</p>
                @endif
            </div>
        </button>
    @endforeach
</div>
