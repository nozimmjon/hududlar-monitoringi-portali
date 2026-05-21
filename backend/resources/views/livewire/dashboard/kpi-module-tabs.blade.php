<div class="dashboard-module-tabs">
    @foreach($modules as $code => $module)
        @php
            $counts = $taskCounts[$code] ?? ['done' => 0, 'total' => 0];
            $total  = (int) $counts['total'];
            $done   = (int) $counts['done'];
        @endphp
        <button class="module-tab {{ $code === $currentModule ? 'active' : '' }}"
                wire:click="selectModule('{{ $code }}')"
                type="button">
            <span class="module-tab__body">
                <strong>{{ preg_replace('/^\d+\.\s*/u', '', $module['label']) }}</strong>
                <span class="module-tab__count">({{ $done }}/{{ $total }})</span>
            </span>
        </button>
    @endforeach
</div>
