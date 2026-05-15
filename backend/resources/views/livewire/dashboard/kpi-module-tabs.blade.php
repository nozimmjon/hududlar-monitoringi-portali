<div class="dashboard-module-tabs">
    @foreach($modules as $code => $module)
        @php
            $counts   = $taskCounts[$code] ?? ['done' => 0, 'total' => 0];
            $total    = (int) $counts['total'];
            $done     = (int) $counts['done'];
            $pct      = $total > 0 ? round(($done / $total) * 100, 1) : 0;
            $iconName = $icons[$code] ?? 'trend';
        @endphp
        <button class="module-tab {{ $code === $currentModule ? 'active' : '' }}"
                wire:click="selectModule('{{ $code }}')"
                type="button">
            <span class="module-tab__icon" aria-hidden="true">
                @include('partials.icon', ['name' => $iconName])
            </span>
            <span class="module-tab__body">
                <strong>{{ preg_replace('/^\d+\.\s*/u', '', $module['label']) }}</strong>
                <span class="module-tab__count">{{ $done }}/{{ $total }}</span>
            </span>
            <span class="module-tab__bar" aria-hidden="true">
                <i style="--w:{{ $pct }}%"></i>
            </span>
        </button>
    @endforeach
</div>
