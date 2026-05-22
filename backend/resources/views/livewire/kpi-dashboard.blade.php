<div>
    <livewire:dashboard.kpi-module-tabs :module="$module" :key="'tabs-'.$module" />

    <div class="module-card">
        <div class="module-heading">
            <div>
                <h2>{{ $moduleLabel }}</h2>
                <p>{{ $moduleIntro }}</p>
            </div>
        </div>

        @if($hasFrontCards)
            <livewire:dashboard.kpi-front-cards :module="$module" :kpi="$kpi" :key="'front-'.$module.'-'.$kpi" />
        @else
            <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :key="'work-'.$module.'-'.$kpi" />
        @endif
    </div>

    @if($hasFrontCards)
        <div class="module-card module-panel-card">
            <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :key="'work-'.$module.'-'.$kpi" />
        </div>
    @endif

    @if($module === 'macro' && $kpi === 'industry')
        <livewire:dashboard.industry-driver-panel :key="'industry-drivers'" />
    @endif

    <livewire:dashboard.kpi-scoreline :module="$module" :kpi="$kpi" :key="'score-'.$module.'-'.$kpi" />
</div>
