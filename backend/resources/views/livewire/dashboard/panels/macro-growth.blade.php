@php
    use App\Support\DashboardCatalog;
    $periods = DashboardCatalog::PERIODS;
    $periodLabels = DashboardCatalog::PERIOD_LABELS;
    $components = DashboardCatalog::MACRO_GROWTH_KPIS;
@endphp

<div class="quarter-matrix macro-growth">
    @foreach($periods as $period)
        @php
            $row = $rows->get($period);
            $unit = $row->unit ?? $indicator->default_unit ?? '';
            $growth = $row && $row->growth_pct !== null ? number_format((float) $row->growth_pct, 1) . '%' : '—';
            $hasFact = $row && $row->growth_pct !== null;
            $stateClass = $hasFact ? 'actual' : 'planned';
            $statusText = $hasFact ? 'Амалда бор' : 'Режа';
            $chipClass = $hasFact ? 'green' : 'grey';
        @endphp
        <div class="quarter-row {{ $stateClass }}">
            <div class="q-head">
                <span class="q-period">{{ $periodLabels[$period] }}</span>
            </div>
            <div class="q-hero">
                <span class="q-hero-value">{{ $growth }}</span>
                <span class="q-hero-label">Ўсиш</span>
            </div>
            <dl class="q-aux">
                <div class="q-aux-row status"><span>Ҳолат</span><b><span class="chip {{ $chipClass }}">{{ $statusText }}</span></b></div>
            </dl>
        </div>
    @endforeach
</div>
