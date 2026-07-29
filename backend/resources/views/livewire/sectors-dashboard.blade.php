<div class="sectors-wrap">
    <div class="dp-crumb"><a href="{{ route('home') }}">← Бош саҳифа</a></div>

    <div class="dp-hero">
        <div>
            <div class="dp-hero-eyebrow">Кафолат хатлари · 2026 йил 2-ярим йиллик</div>
            <h1>Тармоқ корхоналари топшириқлари</h1>
        </div>
        <div class="dp-hero-facts">
            <div class="dp-fact"><div class="v">{{ $summary['sectors'] }}</div><div class="k">корхона</div></div>
            <div class="dp-fact">
                <div class="v">{{ $summary['tasks'] }}</div>
                <div class="k">топшириқдан {{ $summary['done'] }} таси бажарилди</div>
            </div>
            <div class="dp-fact">
                <div class="v"><span class="warn">{{ $summary['open'] }}</span>/{{ $summary['waiting'] }}</div>
                <div class="k">бажарилмаган / кутилмоқда</div>
            </div>
        </div>
    </div>

    <div class="dp-sect">
        <h2>Корхоналар</h2>
        <span class="dp-sect-hint">Корхонани босинг — топшириқлари пастда очилади</span>
    </div>

    <div class="sector-grid">
        @foreach($cards as $card)
            @php
                $s = $card['sector'];
                $pct = $card['pct'];
                $pctShown = $pct === null ? null : (int) round($pct);
                $tierVar = $pct === null ? '--grey'
                    : ($pct >= 100 ? '--task-green' : ($pct >= 50 ? '--task-amber' : '--task-red'));
                $isActive = $selected && $selected->code === $s->code;
            @endphp
            <div class="sector-card {{ $isActive ? 'active' : '' }} {{ $pct === null ? 'nodata' : '' }}"
                 role="button" tabindex="0" wire:key="sector-{{ $s->code }}"
                 wire:click="selectSector('{{ $s->code }}')">
                <div class="sector-card-name">{{ $s->name_short }}</div>
                <div class="sector-card-sub">{{ $card['tasks_total'] }} топшириқ · {{ $card['lines_total'] }} индикатор</div>
                <div class="progress"><i style="--w:{{ $pct === null ? 0 : max(0, min(100, $pct)) }}%;--c:var({{ $tierVar }})"></i></div>
                <div class="sector-card-foot">
                    @if($pct === null)
                        <span class="sector-card-wait">Маълумот кутилмоқда</span>
                    @else
                        <span class="sector-card-pct">{{ $pctShown }}%</span>
                        <span class="sector-card-counts">
                            <b class="ok">{{ $card['done'] }}</b> ·
                            <b class="bad">{{ $card['open'] }}</b> ·
                            <b class="wait">{{ $card['waiting'] }}</b>
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Drilldown (Task 2) --}}
</div>
