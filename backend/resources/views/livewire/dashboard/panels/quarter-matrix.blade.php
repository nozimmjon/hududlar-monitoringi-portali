@php
    use App\Support\DashboardCatalog;
    $periods = DashboardCatalog::PERIODS;
    $periodLabels = DashboardCatalog::PERIOD_LABELS;
    $hasGrowth = $indicator->has_growth_pct ?? false;
@endphp

<div class="quarter-matrix">
    @foreach($periods as $period)
        @php
            $row = $rows->get($period);
            $unit = $row->unit ?? $indicator->default_unit ?? '';
            $growth   = $row && $row->growth_pct !== null ? number_format((float) $row->growth_pct, 1) . '%' : null;
            $plan     = $row && $row->plan_value !== null ? number_format((float) $row->plan_value, 1) . ' ' . $unit : '—';
            $fact     = $row && $row->actual_hokimyat !== null ? number_format((float) $row->actual_hokimyat, 1) . ' ' . $unit : '—';
            $exec     = $row && $row->pct_of_plan !== null ? number_format((float) $row->pct_of_plan, 1) . '%' : null;

            $hero = $growth ?? $exec ?? ($row && $row->plan_value !== null ? number_format((float) $row->plan_value, 1) . ' ' . $unit : '—');

            $hasFact = $row && ($row->actual_hokimyat !== null || $row->actual_statistika !== null || $row->growth_pct !== null);
            $stateClass = $hasFact ? 'actual' : 'planned';
            $statusText = $hasFact ? 'Амалда бор' : '—';
            $chipClass = $hasFact ? 'green' : 'grey';
            $measureLabel = $growth ? 'Ўсиш' : ($exec ? 'Ижро' : 'Режа');
        @endphp
        <div class="quarter-row {{ $stateClass }}">
            <div class="q-head">
                <span class="q-period">{{ $periodLabels[$period] }}</span>
            </div>
            <div class="q-hero">
                <span class="q-hero-value">{{ $hero }}</span>
                <span class="q-hero-label">{{ $measureLabel }}</span>
            </div>
            <dl class="q-aux">
                <div class="q-aux-row"><span>Режа</span><b class="num">{{ $plan }}</b></div>
                <div class="q-aux-row"><span>Амалда</span><b class="num">{{ $fact }}</b></div>
                <div class="q-aux-row status"><span>Ҳолат</span><b><span class="chip {{ $chipClass }}">{{ $statusText }}</span></b></div>
            </dl>
        </div>
    @endforeach
</div>
