@php
    use App\Support\DashboardCatalog;
    $periods = DashboardCatalog::PERIODS;
    $periodLabels = DashboardCatalog::PERIOD_LABELS;
@endphp

<div class="quarter-matrix">
    @foreach($periods as $period)
        @php
            $row = $rows->get($period);
            $unit = $row->unit ?? $indicator->default_unit ?? '';
            $plan = $row && $row->plan_value !== null ? number_format((float) $row->plan_value, 1) . ' ' . $unit : '—';
            $fact = $row && $row->actual_hokimyat !== null ? number_format((float) $row->actual_hokimyat, 1) . ' ' . $unit : '—';
            $exec = $row && $row->pct_of_plan !== null ? number_format((float) $row->pct_of_plan, 1) . '%' : '—';
            $extra = $row && $row->count_extra !== null ? (int) $row->count_extra : null;
            $extra2 = $row && $row->count_extra_2 !== null ? (int) $row->count_extra_2 : null;

            $hero = $row && $row->pct_of_plan !== null ? number_format((float) $row->pct_of_plan, 1) . '%' : $plan;
            $hasFact = $row && $row->actual_hokimyat !== null;
            $stateClass = $hasFact ? 'actual' : 'planned';
        @endphp
        <div class="quarter-row {{ $stateClass }}">
            <div class="q-head">
                <span class="q-period">{{ $periodLabels[$period] }}</span>
            </div>
            <div class="q-hero">
                <span class="q-hero-value">{{ $hero }}</span>
                <span class="q-hero-label">Ижро</span>
            </div>
            <dl class="q-aux">
                <div class="q-aux-row"><span>Режа</span><b class="num">{{ $plan }}</b></div>
                <div class="q-aux-row"><span>Амалда</span><b class="num">{{ $fact }}</b></div>
                @if($extra !== null)
                    <div class="q-aux-row"><span>{{ $indicator->count_extra_label ?? 'Объектлар' }}</span><b class="num">{{ $extra }}</b></div>
                @endif
                @if($extra2 !== null)
                    <div class="q-aux-row"><span>{{ $indicator->count_extra_2_label ?? 'Ишга туширилди' }}</span><b class="num">{{ $extra2 }}</b></div>
                @endif
            </dl>
        </div>
    @endforeach
</div>
