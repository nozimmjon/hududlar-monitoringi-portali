<div>
    <livewire:dashboard.kpi-module-tabs :module="$module" :period="$period" :key="'tabs-'.$module.'-'.$period" />

    <div class="module-card">
        @if($hasFrontCards)
            <livewire:dashboard.kpi-front-cards :module="$module" :kpi="$kpi" :period="$period" :key="'front-'.$module.'-'.$kpi.'-'.$period" />
        @else
            <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :period="$period" :key="'work-'.$module.'-'.$kpi.'-'.$period" />
        @endif
    </div>

    @if($hasFrontCards)
        <div class="module-card module-panel-card">
            <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :period="$period" :key="'work-'.$module.'-'.$kpi.'-'.$period" />
        </div>
    @endif

    @if($module === 'macro' && $kpi === 'industry')
        <livewire:dashboard.industry-driver-panel :key="'industry-drivers'" />
    @endif

    <livewire:dashboard.kpi-scoreline :module="$module" :kpi="$kpi" :period="$period" :key="'score-'.$module.'-'.$kpi.'-'.$period" />
</div>
