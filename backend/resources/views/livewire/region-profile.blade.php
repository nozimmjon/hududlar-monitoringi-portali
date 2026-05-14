<div>
    @if(! $districtCode || ! $district)
        @include('livewire.profile.empty', ['districtCode' => $districtCode])
    @else
        @include('livewire.profile.filter',    ['district' => $district, 'kpi' => $kpi, 'availableKpis' => $availableKpis])
        @include('livewire.profile.hero',      ['district' => $district, 'indicator' => $selectedIndicator, 'fact' => $selectedFact, 'status' => $status, 'tableConfig' => $tableConfig, 'taskCounts' => $taskCounts, 'districtTargetsCount' => $districtTargetsCount, 'kpi' => $kpi, 'facts' => $facts])
        @include('livewire.profile.kpis-grid', ['district' => $district, 'availableKpis' => $availableKpis, 'kpi' => $kpi, 'facts' => $facts])
        @include('livewire.profile.bottom',    ['district' => $district, 'kpi' => $kpi, 'tasks' => $tasks, 'taskCounts' => $taskCounts, 'indicator' => $selectedIndicator])
    @endif
</div>
