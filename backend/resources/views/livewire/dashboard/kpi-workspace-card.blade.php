<div class="kpi-monitor-grid single">
    <article class="kpi-monitor-card {{ \App\Support\DashboardCatalog::isMacroGrowthKpi($kpi) ? 'macro-layout-card' : '' }}">
        <div class="kpi-monitor-head">
            <div class="small-icon">
                @include('partials.icon', ['name' => $indicator->icon ?? 'trend'])
            </div>
            <div>
                <h3>{{ $indicator->label_short ?? $kpi }}</h3>
                <p>{{ $indicator->label_full ?? '' }}</p>
            </div>
            @if(! in_array($kpi, ['grp', 'budget', 'investment', 'export'], true))
                <a class="mini-button primary kpi-head-district"
                   href="{{ route('districts') }}?indicatorCode={{ $kpi }}">Туманлар кесими</a>
            @endif
            <div class="head-watermark" aria-hidden="true">
                @include('partials.icon', ['name' => $indicator->icon ?? 'trend'])
            </div>
        </div>

        @include('livewire.dashboard.panels.' . $panel, get_defined_vars())

        @if(in_array($kpi, ['budget', 'budget_investment', 'investment'], true))
            <p class="finance-source">Манба: 4-жадвал ва кафолат хати.</p>
        @endif
    </article>
</div>
