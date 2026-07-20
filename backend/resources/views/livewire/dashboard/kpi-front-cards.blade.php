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
            // Plan for the selected period, so the card carries promise + fact.
            // A growth-led card compares like with like: the promised RATE, not the
            // money plan. Other cards keep their own unit via displayValue()
            // (scaling, «count» -> «та») as everywhere else on the dashboard.
            $plan = null;
            if ($growth !== null) {
                $plan = $fact->plan_growth_pct !== null
                    ? \App\Support\DashboardCatalog::growthValue($fact->plan_growth_pct)
                    : null;
            } elseif ($fact && $fact->plan_value !== null) {
                $plan = \App\Support\DashboardCatalog::displayValue($fact->plan_value, $fact->unit ?? '');
            }
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
                @if($plan !== null)
                    <span class="front-kpi-plan"><i>Режа</i> {{ $plan }}</span>
                @endif
            </div>
        </button>
    @endforeach
</div>
