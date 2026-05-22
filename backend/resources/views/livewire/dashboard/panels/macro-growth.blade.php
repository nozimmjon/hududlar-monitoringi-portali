@php
    use App\Support\DashboardCatalog;

    $macroPeriods = [
        ['label' => 'I чорак',   'period' => 'q1',   'state' => 'Амалда', 'cls' => 'actual'],
        ['label' => 'II чорак',  'period' => 'h1',   'state' => 'Режа',   'cls' => 'plan'],
        ['label' => 'III чорак', 'period' => 'm9',   'state' => 'Режа',   'cls' => 'plan'],
        ['label' => 'Йиллик',    'period' => 'year', 'state' => 'Мақсад', 'cls' => 'target'],
    ];

    $showIndustryDrivers = $kpi === 'industry';
    $industryDrivers = $showIndustryDrivers
        ? DashboardCatalog::industryDrivers($industryDriverFacts ?? null)
        : [];
@endphp

<section class="macro-growth-panel" aria-label="{{ $indicator->label_full ?? '' }} ўсиш мониторинги">
    <div class="macro-period-row" aria-label="{{ $indicator->label_full ?? '' }} давр кесими">
        @foreach($macroPeriods as $item)
            @php
                $row = $rows->get($item['period']);
                $growthText = $row && $row->growth_pct !== null
                    ? DashboardCatalog::growthValue($row->growth_pct)
                    : '—';
            @endphp
            <div class="macro-period-cell {{ $item['cls'] }}">
                <span class="macro-period-cell__label">{{ $item['label'] }}</span>
                <strong class="macro-period-cell__value">{{ $growthText }}</strong>
                <span class="macro-period-cell__state">({{ $item['state'] }})</span>
            </div>
        @endforeach
    </div>
    @if($showIndustryDrivers)
        <aside class="industry-driver-panel" aria-label="Саноат драйверлари">
            <div class="industry-driver-head">
                <strong>Саноат драйверлари</strong>
                <span class="info-dot" title="Саноатга боғланган туманлар кесимидаги драйверлар">i</span>
            </div>
            <div class="industry-driver-list">
                @foreach($industryDrivers as $item)
                    <a class="industry-driver-card {{ $item['cls'] }}"
                       href="{{ route('districts') }}?indicatorCode={{ $item['id'] }}&period=h1">
                        <span class="driver-icon {{ $item['cls'] }}">
                            @include('partials.icon', ['name' => $item['icon']])
                        </span>
                        <span class="industry-driver-body">
                            <span class="industry-driver-title">
                                <strong>{{ $item['title'] }}</strong>
                                <span>{{ $item['desc'] }}</span>
                            </span>
                            <span class="industry-driver-metrics">
                                <span class="industry-driver-metric">
                                    <span>I ярим йиллик</span>
                                    <strong>{{ $item['h1'] }}</strong>
                                    @if($item['h1Note'] !== '')
                                        <small>{{ $item['h1Note'] }}</small>
                                    @endif
                                </span>
                                <span class="industry-driver-divider" aria-hidden="true"></span>
                                <span class="industry-driver-metric">
                                    <span>Йиллик кутилиш</span>
                                    <strong>{{ $item['year'] }}</strong>
                                    @if($item['yearNote'] !== '')
                                        <small>{{ $item['yearNote'] }}</small>
                                    @endif
                                </span>
                            </span>
                        </span>
                        <span class="industry-driver-arrow" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
        </aside>
    @endif
</section>
