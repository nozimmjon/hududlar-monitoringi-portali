@php
    $statusLabel = ['green' => 'Яхши', 'amber' => 'Ўртача', 'red' => 'Эътибор', 'grey' => 'Маълумот йўқ'][$status] ?? 'Маълумот йўқ';
    $taskChip = $taskCounts['total'] > 0 && $taskCounts['unfinished'] > 0 ? 'red'
              : ($taskCounts['total'] > 0 ? 'green' : 'grey');
    $primaryValue = match (true) {
        $fact?->pct_of_plan !== null => number_format((float) $fact->pct_of_plan, 1, ',', ' ') . '%',
        $fact?->growth_pct !== null  => number_format((float) $fact->growth_pct, 1, ',', ' ') . '%',
        $fact?->plan_value !== null  => number_format((float) $fact->plan_value, 1, ',', ' '),
        default                      => '—',
    };
    $unit = $indicator?->default_unit ?? '';
    $moduleLabel = $indicator?->module_code ? \App\Support\DashboardCatalog::MODULES[$indicator->module_code]['label'] ?? '' : '';
@endphp

<div class="profile-grid">
    <article class="profile-focus">
        <div class="profile-hero">
            <div>
                <div class="eyebrow">{{ preg_replace('/^\d+\.\s*/u', '', $moduleLabel) }}</div>
                <h3>{{ $district->name_full }}: {{ $indicator?->label_short ?? $kpi ?? '' }}</h3>
                <p>Танланган KPI бўйича туман ҳолати: режа, амалдаги натижа ва очиқ топшириқлар.</p>
                <div class="action-row">
                    <span class="chip blue">Туман профили</span>
                    <span class="chip {{ $status }}">{{ $statusLabel }}</span>
                    <span class="chip {{ $taskChip }}">{{ $taskCounts['unfinished'] }}/{{ $taskCounts['total'] }} T-топшириқ</span>
                    <span class="chip grey">{{ $districtTargetsCount }} D-мақсад</span>
                </div>
            </div>
            <div class="profile-main-value">
                <strong>{{ $primaryValue }}</strong>
                <span>{{ $unit }}</span>
            </div>
        </div>
        <div class="profile-metrics">
            @foreach($tableConfig['columns'] ?? [] as $col)
                @php
                    $cellFact = isset($col['metric']) && $col['metric'] !== null
                        ? ($facts->get($col['metric']['kpi']) ?? null)
                        : $fact;
                    $val  = \App\Support\DistrictMetricResolver::value($cellFact, $col['metric']['kind'] ?? 'value');
                    $note = \App\Support\DistrictMetricResolver::note($cellFact, $col['note'] ?? null);
                @endphp
                <div class="profile-metric">
                    <span>{{ $col['label'] }}</span>
                    <strong>{{ $val }}</strong>
                    <small>{{ $note }}</small>
                </div>
            @endforeach
        </div>
    </article>

    <article class="panel">
        <div class="panel-head">
            <div>
                <h3>Қисқа ҳолат</h3>
                <p>Шу туман бўйича тезкор қарор учун керакли маълумот.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="profile-side-stat"><span>Масъул</span><strong>ҳокимлик</strong></div>
            <div class="profile-side-stat"><span>Жорий маълумот</span><strong>{{ $primaryValue }}</strong></div>
            <div class="profile-side-stat"><span>Бажарилмаган T-топшириқ</span><strong>{{ $taskCounts['unfinished'] }}/{{ $taskCounts['total'] }}</strong></div>
            <div class="profile-side-stat"><span>Туман мақсадлари</span><strong>{{ $districtTargetsCount }}</strong></div>
            <div class="profile-actions" style="margin-top:12px">
                <a class="mini-button" href="{{ route('districts') }}?kpi={{ $kpi }}">Туманлар жадвали</a>
                <button class="mini-button" type="button" disabled title="Тез орада">Ижро журнали</button>
            </div>
        </div>
    </article>
</div>
