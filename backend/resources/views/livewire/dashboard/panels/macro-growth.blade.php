@php
    use App\Support\DashboardCatalog;

    $macroPeriods = [
        ['label' => 'I чорак',   'period' => 'q1',   'state' => 'Амалда', 'cls' => 'actual'],
        ['label' => 'II чорак',  'period' => 'h1',   'state' => 'Режа',   'cls' => 'plan'],
        ['label' => 'III чорак', 'period' => 'm9',   'state' => 'Режа',   'cls' => 'plan'],
        ['label' => 'Йиллик',    'period' => 'year', 'state' => 'Мақсад', 'cls' => 'target'],
    ];
@endphp

<section class="macro-growth-panel" aria-label="{{ $indicator->label_full ?? '' }} ўсиш мониторинги">
    <div class="macro-period-row" aria-label="{{ $indicator->label_full ?? '' }} давр кесими">
        @foreach($macroPeriods as $item)
            @php
                $row = $rows->get($item['period']);
                $growthText = $row && $row->growth_pct !== null
                    ? DashboardCatalog::growthValue($row->growth_pct)
                    : '—';
                // A reported actual (e.g. H1 via TaskFactBridge) overrides the static Режа state.
                $isActual = DashboardCatalog::periodSourceKind($indicator->code ?? '', $item['period'], $row) === 'actual';
                $state = $isActual ? 'Амалда' : $item['state'];
                $cls   = $isActual ? 'actual' : $item['cls'];
            @endphp
            <div class="macro-period-cell {{ $cls }}">
                <span class="macro-period-cell__label">{{ $item['label'] }}</span>
                <strong class="macro-period-cell__value">{{ $growthText }}</strong>
                <span class="macro-period-cell__state">({{ $state }})</span>
            </div>
        @endforeach
    </div>
</section>
