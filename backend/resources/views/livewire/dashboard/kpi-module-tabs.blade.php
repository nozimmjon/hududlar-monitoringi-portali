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
                <span class="module-tab__count">
                    <span class="module-tab__stat"><i>Амалда бажарилган:</i> <b>{{ $done }} та</b></span>
                    <span class="module-tab__stat"><i>Жами топшириқлар:</i> <b>{{ $total }} та</b></span>
                </span>
            </span>
        </button>
    @endforeach
</div>
