@php
    use App\Support\DashboardCatalog;

    // Half-year promise vs reported fact for the page's own KPI. Yearly figures are
    // deliberately untouched — the year is still running, so only H1 carries a fact.
    $planValue   = $row->plan_value ?? null;
    $factValue   = $row->actual_hokimyat ?? $row->actual_statkom ?? null;
    $lowerBetter = $lowerBetter ?? false;
    $onTarget    = ($planValue !== null && $factValue !== null)
        ? ($lowerBetter ? (float) $factValue <= (float) $planValue : (float) $factValue >= (float) $planValue)
        : null;
@endphp

@if($planValue !== null || $factValue !== null)
    <div class="lagging h1-plan-fact">
        <div class="lagging-title"><strong>II чорак (I ярим йиллик)</strong></div>
        <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div class="driver-card">
                <span>Режа</span>
                <strong>{{ DashboardCatalog::displayValue($planValue, $unit ?? '') }}</strong>
                <small>ярим йиллик мажбурият</small>
            </div>
            <div class="driver-card">
                <span>Амалда</span>
                <strong @class(['is-ok' => $onTarget === true, 'is-over' => $onTarget === false])>
                    {{ DashboardCatalog::displayValue($factValue, $unit ?? '') }}
                </strong>
                <small>
                    @if($factValue === null)
                        маълумот кутилмоқда
                    @elseif($onTarget === true)
                        режа даражасида
                    @elseif($onTarget === false)
                        режадан четлашган
                    @else
                        ҳисобот қиймати
                    @endif
                </small>
            </div>
        </div>
    </div>
@endif
