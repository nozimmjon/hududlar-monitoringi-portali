<div>
    {{-- Module tabs --}}
    <div class="dashboard-module-tabs">
        @foreach($moduleLabels as $mod => $label)
            <button class="module-tab {{ $module === $mod ? 'active' : '' }}"
                    wire:click="$set('module', '{{ $mod }}')"
                    type="button">
                <span class="module-dot" aria-hidden="true"></span>
                <strong>{{ preg_replace('/^\d+\.\s*/', '', $label) }}</strong>
            </button>
        @endforeach
    </div>

    {{-- Module heading --}}
    <div class="module-heading" style="margin: 16px 0 12px;">
        <div>
            <h2>{{ $moduleLabels[$module] ?? '' }}</h2>
        </div>
    </div>

    {{-- Period switcher --}}
    <div class="segmented" style="margin-bottom: 20px;">
        @foreach($periodLabels as $p => $label)
            <button class="seg-btn {{ $period === $p ? 'active' : '' }}"
                    wire:click="$set('period', '{{ $p }}')"
                    type="button">{{ $label }}</button>
        @endforeach
    </div>

    {{-- KPI tiles --}}
    @php $codes = $moduleMap[$module] ?? []; @endphp

    @if($facts->isEmpty())
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Маълумот топилмади. Аввал <code>import:region andijan 2026</code> ва <code>import:promote</code> буйруқларини ишга туширинг.</p>
        </div>
    @else
        <div class="front-kpis module-kpis">
            @foreach($codes as $code)
                @php
                    $fact = $facts->get($code);
                    $ind  = $indicators->get($code);
                    if (! $ind) continue;

                    if ($fact && $fact->growth_pct !== null) {
                        $main = number_format((float) $fact->growth_pct, 1) . '%';
                    } elseif ($fact && $fact->plan_value !== null) {
                        $main = number_format((float) $fact->plan_value, 1) . ' ' . $fact->unit;
                    } else {
                        $main = '—';
                    }

                    $hasData = $fact !== null;
                @endphp
                <div role="button" tabindex="0" class="front-kpi {{ ! $hasData ? 'muted' : '' }}">
                    <div class="front-kpi-copy">
                        <h3>{{ $ind->label_short }}</h3>
                        <p>{{ $ind->label_full }}</p>
                        <span class="front-kpi-meta">
                            <i class="front-kpi-dot" aria-hidden="true"></i>
                            {{ $main }}
                        </span>
                        @if($fact && $fact->plan_value !== null && $fact->growth_pct !== null)
                            <small style="display:block; margin-top:4px; color:var(--muted);">
                                Режа: {{ number_format((float) $fact->plan_value, 1) }} {{ $fact->unit }}
                            </small>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
