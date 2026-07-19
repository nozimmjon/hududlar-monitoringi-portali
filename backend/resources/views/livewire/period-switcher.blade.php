<div class="topbar-period" role="group" aria-label="Давр танлаш">
    @foreach($options as $code => $label)
        <button type="button"
                class="topbar-period__btn {{ $period === $code ? 'active' : '' }}"
                wire:click="select('{{ $code }}')">{{ $label }}</button>
    @endforeach
</div>
