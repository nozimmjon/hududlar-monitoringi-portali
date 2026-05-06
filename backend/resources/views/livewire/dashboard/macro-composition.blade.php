<details class="macro-composition-panel macro-composition-dropdown" aria-label="ЯҲМ таркиби">
    <summary class="macro-composition-head">
        <div>
            <strong>ЯҲМ таркибий мақсадлари</strong>
            <small>Саноат, қишлоқ хўжалиги, қурилиш ва хизматлар ўсиш суръати</small>
        </div>
        <span class="macro-dropdown-meta">
            <span>{{ count($components) }} та мақсад</span>
            <span class="macro-dropdown-caret" aria-hidden="true">⌄</span>
        </span>
    </summary>
    <div class="macro-composition-body">
        <div class="composition-grid">
            @foreach($components as $code)
                @php
                    $ind = $indicators->get($code);
                    $fact = $facts->get($code);
                    if (! $ind) continue;
                    $growth = $fact && $fact->growth_pct !== null ? number_format((float) $fact->growth_pct, 1) . '%' : '—';
                @endphp
                <button class="component-card"
                        wire:click="selectKpi('{{ $code }}')"
                        type="button">
                    <span class="product-body">
                        <span class="product-name">{{ $ind->label_short }}</span>
                        <strong class="product-value">{{ $growth }}</strong>
                        <small class="product-note">йиллик ўсиш суръати</small>
                    </span>
                </button>
            @endforeach
        </div>
    </div>
</details>
