@php
    use App\Support\DashboardCatalog;

    $macroPeriods = [
        ['label' => 'I чорак',  'period' => 'q1',   'state' => 'Амалда', 'cls' => 'actual'],
        ['label' => 'II чорак', 'period' => 'h1',   'state' => 'Режа',   'cls' => 'planned'],
        ['label' => 'III чорак', 'period' => 'm9',  'state' => 'Режа',   'cls' => 'planned'],
        ['label' => 'Йиллик',   'period' => 'year', 'state' => 'Режа',   'cls' => 'planned'],
    ];

    $yearRow = $rows->get('year');
    $yearGrowth = $yearRow && $yearRow->growth_pct !== null
        ? DashboardCatalog::growthValue($yearRow->growth_pct)
        : '—';

    $values = [];
    foreach ($macroPeriods as $item) {
        $r = $rows->get($item['period']);
        $values[] = $r ? (float) ($r->growth_pct ?? 0) : 0;
    }
    $maxDelta = max(1, ...array_map(fn ($v) => abs($v - 100), $values));
@endphp

<section class="macro-growth-panel solo" aria-label="{{ $indicator->label_full ?? '' }} ўсиш мониторинги">
    <div class="macro-main-panel">
        <div class="macro-section-title">
            <strong>{{ $indicator->label_short ?? '' }} ўсиши</strong>
            <span>(солиштирма нархларда)</span>
        </div>
        <div class="macro-hero-card">
            <div class="macro-hero-copy">
                <span>Йиллик ўсиш (мақсад)</span>
                <strong>{{ $yearGrowth }}</strong>
                <small>2026 йил</small>
            </div>
        </div>
        <div class="macro-period-grid">
            @foreach($macroPeriods as $item)
                @php
                    $row = $rows->get($item['period']);
                    $growthText = $row && $row->growth_pct !== null
                        ? DashboardCatalog::growthValue($row->growth_pct)
                        : '—';
                    $rawGrowth = $row && $row->growth_pct !== null ? (float) $row->growth_pct : null;
                    $delta = $rawGrowth !== null ? abs($rawGrowth - 100) : 0;
                    $width = max(8, min(100, ($delta / $maxDelta) * 100));
                    $chipClass = $item['cls'] === 'actual' ? 'blue' : 'grey';
                @endphp
                <div class="macro-period-card {{ $item['cls'] }}">
                    <div class="macro-period-head">
                        <b>{{ $item['label'] }}</b>
                        <span class="chip {{ $chipClass }}">{{ $item['state'] }}</span>
                    </div>
                    <strong>{{ $growthText }}</strong>
                    <small>ўсиш суръати</small>
                    <i class="macro-mini-bar" aria-hidden="true"><i style="--w:{{ number_format($width, 1) }}%"></i></i>
                </div>
            @endforeach
        </div>
    </div>
</section>
