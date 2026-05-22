<div>
    <livewire:dashboard.kpi-module-tabs :module="$module" :key="'tabs-'.$module" />

    <div class="{{ $module === 'macro' ? 'module-card' : 'module-flow' }}">
        <div class="module-heading">
            <div>
                <h2>{{ $moduleLabel }}</h2>
                <p>{{ $moduleIntro }}</p>
            </div>
        </div>

        @if($hasFrontCards)
            <livewire:dashboard.kpi-front-cards :module="$module" :kpi="$kpi" :key="'front-'.$module.'-'.$kpi" />
        @endif

        @if($module !== 'macro')
            <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :key="'work-'.$module.'-'.$kpi" />
        @endif
    </div>

    @if($module === 'macro')
        <div class="module-card module-panel-card">
            <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :key="'work-'.$module.'-'.$kpi" />
        </div>
    @endif

    <livewire:dashboard.kpi-scoreline :module="$module" :kpi="$kpi" :key="'score-'.$module.'-'.$kpi" />
</div>
