<div class="dashboard-module-tabs">
    @foreach($modules as $code => $module)
        <button class="module-tab {{ $code === $currentModule ? 'active' : '' }}"
                wire:click="selectModule('{{ $code }}')"
                type="button">
            <span class="module-dot" aria-hidden="true"></span>
            <strong>{{ preg_replace('/^\d+\.\s*/u', '', $module['label']) }}</strong>
        </button>
    @endforeach
</div>
