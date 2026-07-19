<div class="kpi-monitor-grid single">
    @php
        // Driver-head text folded into the card head for panels that used to carry
        // their own header (poverty-head). Keeps a single head per KPI page.
        $driverHead = match ($kpi) {
            'unemployment' => ['Ишсизликни пасайтириш драйверлари', 'Ишсизлик KPI мақсадини бажариш учун бандлик бўйича асосий ўлчанадиган ишлар.'],
            'poverty'      => ['Камбағалликни камайтириш драйверлари', 'Камбағаллик KPIсига олиб борувчи асосий чора-тадбирлар.'],
            default        => null,
        };
    @endphp
    <article class="kpi-monitor-card {{ \App\Support\DashboardCatalog::isMacroGrowthKpi($kpi) ? 'macro-layout-card' : '' }}">
        <div class="kpi-monitor-head">
            <div class="small-icon">
                @include('partials.icon', ['name' => $indicator->icon ?? 'trend'])
            </div>
            <div>
                <h3>{{ $driverHead[0] ?? $indicator->label_short ?? $kpi }}</h3>
                <p>{{ $driverHead[1] ?? $indicator->label_full ?? '' }}</p>
            </div>
            @if(! in_array($kpi, ['grp', 'budget', 'investment', 'export', 'unemployment'], true))
                <a class="mini-button primary kpi-head-district"
                   href="{{ route('districts') }}?indicatorCode={{ $kpi }}">Туманлар кесими</a>
            @endif
            <div class="head-watermark" aria-hidden="true">
                @include('partials.icon', ['name' => $indicator->icon ?? 'trend'])
            </div>
        </div>

        @include('livewire.dashboard.panels.' . $panel, get_defined_vars())
    </article>
</div>
