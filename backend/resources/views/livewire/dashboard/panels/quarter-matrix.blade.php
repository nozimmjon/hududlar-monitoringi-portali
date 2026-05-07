@php
    use App\Support\DashboardCatalog;
    $periods = DashboardCatalog::PERIODS;
    $periodLabels = DashboardCatalog::PERIOD_LABELS;
@endphp

<div class="quarter-matrix">
    @foreach($periods as $period)
        @php
            $row = $rows->get($period);
            $unit = ($row->unit ?? null) ?: ($indicator->default_unit ?? '');
            $stateInfo = DashboardCatalog::periodState($kpi, $period, $row);
            $sourceKind = DashboardCatalog::periodSourceKind($kpi, $period, $row);

            $growthText = $row && $row->growth_pct !== null
                ? DashboardCatalog::growthValue($row->growth_pct)
                : '—';
            $planFmt = DashboardCatalog::displayValue($row->plan_value ?? null, $unit);
            $factRaw = $sourceKind === 'expected'
                ? ($row?->expected_value ?? $row?->actual_hokimyat ?? $row?->actual_statkom)
                : ($row?->actual_hokimyat ?? $row?->actual_statkom ?? $row?->expected_value);
            $factFmt = DashboardCatalog::displayValue($factRaw, $unit);
            $execFmt = $row && $row->pct_of_plan !== null
                ? DashboardCatalog::fmt((float) $row->pct_of_plan, 1) . '%'
                : '—';

            $hero = $row && $row->growth_pct !== null
                ? $growthText
                : ($row && $row->pct_of_plan !== null
                    ? $execFmt
                    : ($row && $row->plan_value !== null
                        ? $planFmt
                        : '—'));

            $measureLabel = $row && $row->growth_pct !== null
                ? 'Ўсиш'
                : DashboardCatalog::executionLabel($kpi, $period, $row);

            $statusText = $stateInfo['label'] !== ''
                ? $stateInfo['label']
                : ($stateInfo['cls'] === 'actual' ? 'Амалда бор' : '—');
            $chipClass = $stateInfo['chip'] !== '' ? $stateInfo['chip'] : ($stateInfo['cls'] === 'actual' ? 'green' : 'grey');

            $hidePlanRow = $sourceKind === 'target' || ($kpi === 'investment' && $sourceKind === 'expected');
        @endphp
        <div class="quarter-row {{ $stateInfo['cls'] }}">
            <div class="q-head">
                <span class="q-period">{{ $periodLabels[$period] }}</span>
            </div>
            <div class="q-hero">
                <span class="q-hero-value">{{ $hero }}</span>
                <span class="q-hero-label">{{ $measureLabel }}</span>
            </div>
            <dl class="q-aux">
                @if(! $hidePlanRow)
                    <div class="q-aux-row"><span>{{ DashboardCatalog::planLabel($kpi, $period, $row) }}</span><b class="num">{{ $planFmt }}</b></div>
                @endif
                <div class="q-aux-row"><span>{{ DashboardCatalog::factLabel($kpi, $period, $row) }}</span><b class="num">{{ $factFmt }}</b></div>
                <div class="q-aux-row status"><span>Ҳолат</span><b><span class="chip {{ $chipClass }}">{{ $statusText }}</span></b></div>
            </dl>
        </div>
    @endforeach
</div>
