<div class="sectors-wrap">
    <div class="dp-crumb"><a href="{{ route('home') }}"><span class="arr">←</span> Бош саҳифа</a></div>

    <div class="dp-hero">
        <div>
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
    </div>

    <div class="sector-grid">
        @foreach($cards as $card)
            @php
                $s = $card['sector'];
                $pct = $card['pct'];
                // Same 99-cap rule as the task cards: only a fully-done sector shows 100%.
                $pctShown = $pct === null ? null : ($pct >= 100 ? 100 : min(99, (int) round($pct)));
                $tierVar = $pct === null ? '--grey'
                    : ($pct >= 100 ? '--task-green' : ($pct >= 50 ? '--task-amber' : '--task-red'));
            @endphp
            <a class="sector-card {{ $pct === null ? 'nodata' : '' }}"
               href="{{ route('sectors.detail', $s->code) }}" wire:key="sector-{{ $s->code }}">
                <div class="sector-card-head">
                    @if($card['logo'])
                        <img class="sector-card-logo" src="{{ asset($card['logo']) }}" alt="" loading="lazy">
                    @else
                        <span class="sector-card-mono" aria-hidden="true">{{ mb_substr($s->cardName(), 0, 1) }}</span>
                    @endif
                </div>
                <div class="sector-card-name {{ mb_strlen($s->cardName()) > 20 ? 'long' : '' }}">{{ $s->cardName() }}</div>
                <div class="sector-card-sub">{{ $card['tasks_total'] }} топшириқ · {{ $card['lines_total'] }} индикатор</div>
                <div class="progress"><i style="--w:{{ $pct === null ? 0 : max(0, min(100, $pct)) }}%;--c:var({{ $tierVar }})"></i></div>
                @if($pct !== null)
                    <div class="sector-card-foot">
                        <span class="sector-card-pct">{{ $pctShown }}%</span>
                        <span class="sector-card-counts">
                            <b class="ok">{{ $card['done'] }}</b> ·
                            <b class="bad">{{ $card['open'] }}</b> ·
                            <b class="wait">{{ $card['waiting'] }}</b>
                        </span>
                    </div>
                @endif
            </a>
        @endforeach
    </div>
</div>
