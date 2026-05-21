@php
    use App\Support\DistrictMetricResolver;

    $tableConfig         = $this->tableConfig;
    $factMatrix          = $this->factMatrix;
    $taskCountByDistrict = $this->taskCountByDistrict;
    $targetCountByDistrict = $this->targetCountByDistrict;

    $statusLabel = [
        'green' => 'Яхши', 'amber' => 'Ўртача', 'red' => 'Эътибор', 'grey' => 'Маълумот йўқ',
    ];

    $taskChipClass = function (array $tasks): string {
        if ($tasks['total'] > 0 && $tasks['unfinished'] > 0) return 'red';
        if ($tasks['total'] > 0) return 'green';
        return 'grey';
    };
    $targetChipClass = fn (int $n): string => $n > 0 ? 'blue' : 'grey';

    $resolveCell = function ($col, string $code) use ($factMatrix) {
        if ($col['metric'] === null) {
            return ['value' => '—', 'note' => ''];
        }
        $fact = $factMatrix[$col['metric']['kpi']][$code][$col['metric']['period']] ?? null;
        return [
            'value' => DistrictMetricResolver::value($fact, $col['metric']['kind']),
            'note'  => DistrictMetricResolver::note($fact, $col['note'] ?? null),
        ];
    };

    $districts        = $this->districts;
    $facts            = $this->facts;
    $rollup           = $this->rollup;
    $statusByDistrict = $this->statusByDistrict;
    $rankedDistricts  = $this->rankedDistricts;
    $selectedRow      = $this->selectedDistrict;
    $moduleOptions    = $this->moduleOptions;
    $kpiOptions       = $this->kpiOptions;
    $coverage         = $this->coverage;
    $targetCount      = $this->targetCount;
    $taskCount        = $this->taskCount;
    $indicator        = $this->indicator;

    $selectedCode = $selectedRow ? $selectedRow['district']->code : '';
    $selectedDistrict = $selectedRow ? $selectedRow['district'] : null;
    $selectedFact = $selectedRow ? $selectedRow['fact'] : null;
    $selectedStatus = $selectedRow ? $selectedRow['status'] : 'grey';

    $selectedTasks  = $selectedRow ? ($taskCountByDistrict[$selectedCode] ?? ['unfinished' => 0, 'total' => 0]) : ['unfinished' => 0, 'total' => 0];
    $selectedTargetCount = $selectedRow ? ($targetCountByDistrict[$selectedCode] ?? 0) : 0;

    $fmt = function ($v, int $decimals = 1): string {
        if ($v === null || $v === '') return '—';
        return number_format((float) $v, $decimals, ',', ' ');
    };

    $unit = $indicator?->default_unit ?? '';
    $kpiShort = $indicator?->label_short ?? $kpi;
    $kpiFull  = $indicator?->label_full  ?? $kpi;

    $moduleLabel = $moduleOptions->firstWhere('code', $module)?->label ?? 'Туманлар';
    $moduleDesc = 'KPI бўйича туман/шаҳарлар кесими ва ҳолат харитаси.';

    $isCity = fn (string $name) => str_ends_with($name, ' шаҳри');
@endphp

<div>
    <header class="districts-head">
        <div class="dashboard-module-tabs district-module-tabs">
            @foreach($moduleOptions as $m)
                <button class="module-tab {{ $m->code === $module ? 'active' : '' }}"
                        wire:click="selectModule('{{ $m->code }}')"
                        type="button">
                    <span class="module-dot" aria-hidden="true"></span>
                    <strong>{{ preg_replace('/^\d+\.\s*/u', '', $m->label) }}</strong>
                </button>
            @endforeach
        </div>

        <div class="module-heading">
            <div>
                <h2>{{ $moduleLabel }}</h2>
                <p>{{ $moduleDesc }}</p>
            </div>
        </div>

        @if($kpiOptions->count() > 1)
            <div class="district-kpi-selector">
                @foreach($kpiOptions as $i)
                    @php $iCfg = \App\Support\DistrictTableConfig::for($i->code); @endphp
                    <button class="district-kpi-option {{ $i->code === $kpi ? 'active' : '' }}"
                            wire:click="selectKpi('{{ $i->code }}')"
                            type="button"
                            aria-label="{{ $i->label_full }}">
                        <span class="kpi-mini-icon" aria-hidden="true">
                            @include('partials.icon', ['name' => $i->icon ?? 'trend'])
                        </span>
                        <span>
                            <strong>{{ $i->label_short }}</strong>
                            <small>{{ $iCfg['source'] }}</small>
                        </span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="district-data-layers">
            <div class="district-data-layer">
                <span>D-маълумот</span>
                <strong>{{ $coverage['count'] }} ҳудуд</strong>
                <small>{{ $kpiFull }} бўйича маълумоти бор туман/шаҳарлар.</small>
            </div>
            <div class="district-data-layer">
                <span>D-мақсад</span>
                <strong>{{ $targetCount }} та</strong>
                <small>Кафолат хатидан ажратилган туман/шаҳар мажбурияти.</small>
            </div>
            <div class="district-data-layer">
                <span>T-топшириқ</span>
                <strong>{{ $taskCount }} та</strong>
                <small>Шу KPI билан боғлиқ амалий топшириқлар сони.</small>
            </div>
        </div>

        <div class="districts-head-actions">
            <label class="districts-control">
                <span>Саралаш</span>
                <select wire:model.live="sort">
                    <option value="attention">Эътибор талаб</option>
                    <option value="execution">Юқоридан</option>
                    <option value="plan">Режа каттадан</option>
                    <option value="name">Алифбо бўйича</option>
                </select>
            </label>
            <label class="districts-control districts-control--search">
                <span>Қидириш</span>
                <input wire:model.live.debounce.300ms="search" placeholder="Туман қидириш">
            </label>
        </div>
    </header>

    <div class="districts-grid">
        <section class="districts-map">
            <header class="districts-map-head">
                <div>
                    <strong>{{ $kpiShort }} — {{ $kpiFull }}</strong>
                    <span>Ҳар бир туман ранги вилоятдаги ўрнига нисбатан.</span>
                </div>
            </header>
            @php
                $regionName = \App\Support\CurrentRegion::current()->name_full;
                $rollupValue = $rollup?->pct_of_plan !== null
                    ? $fmt($rollup->pct_of_plan, 1) . '%'
                    : ($rollup?->growth_pct !== null ? $fmt($rollup->growth_pct, 1) . '%' : '—');
            @endphp
            <div class="districts-rollup-banner">
                <div>
                    <span class="rollup-label">{{ $regionName }} · {{ $kpiShort }}</span>
                    <strong class="rollup-value">{{ $rollupValue }}</strong>
                </div>
                <span class="chip blue">{{ $period }}</span>
            </div>
            <div class="districts-map-canvas" x-data="{hovered:null,x:0,y:0}">
                <svg viewBox="{{ $mapGeometry['viewBox'] }}" class="andijan-map" role="img" aria-label="Ҳудудлар харитаси">
                    <g>
                        @foreach($mapGeometry['cells'] as $cell)
                            @php
                                $cellCode = $cell['code'] ?? null;
                                $cellDistrict = $cellCode !== null ? $districts->get($cellCode) : null;
                                $scaleEntry = $cellCode !== null ? ($colorScale[$cellCode] ?? null) : null;
                                $cellColor = $scaleEntry['color'] ?? \App\Support\MapColorScale::NO_DATA;
                                $cellValue = $scaleEntry['value'] ?? null;
                                $valueText = $cellValue !== null ? $fmt($cellValue, 1) . '%' : '—';
                                $cellSelected = $cellCode !== null && (string) $cellCode === (string) $selectedCode ? 'selected' : '';
                                $cellName = $cellDistrict?->name_full ?? $cell['name'];
                            @endphp
                            <g class="map-cell {{ $cellSelected }}"
                               wire:click="selectDistrict('{{ $cellCode }}')"
                               x-on:mouseenter="hovered={name:@js($cellName), value:@js($valueText), color:@js($cellColor)}"
                               x-on:mouseleave="hovered=null"
                               x-on:mousemove="x=$event.offsetX; y=$event.offsetY"
                               tabindex="0">
                                <title>{{ $cellName }} · {{ $valueText }}</title>
                                <path class="map-fill" d="{{ $cell['path'] }}" fill="{{ $cellColor }}"/>
                            </g>
                        @endforeach
                    </g>
                    <g class="map-labels">
                        @foreach($mapGeometry['cells'] as $cell)
                            @php
                                $cellCode = $cell['code'] ?? null;
                                $cellDistrict = $cellCode !== null ? $districts->get($cellCode) : null;
                                $scaleEntry = $cellCode !== null ? ($colorScale[$cellCode] ?? null) : null;
                                $cellValue = $scaleEntry['value'] ?? null;
                                $cellCity = str_ends_with($cell['name'], ' ш.') ? 'is-city' : '';
                                $shortLabel = $cellDistrict?->name_short ?? $cell['name'];
                            @endphp
                            <text class="map-label {{ $cellCity }}"
                                  x="{{ $cell['cx'] }}" y="{{ $cell['cy'] - 4 }}"
                                  text-anchor="middle">{{ $shortLabel }}</text>
                            @if($cellValue !== null)
                                <text class="map-value" x="{{ $cell['cx'] }}" y="{{ $cell['cy'] + 10 }}"
                                      text-anchor="middle">{{ $fmt($cellValue, 1) }}%</text>
                            @endif
                        @endforeach
                    </g>
                </svg>
                <div class="map-tooltip" x-show="hovered" x-cloak
                     :style="`left:${x + 14}px; top:${y + 14}px; --c:${hovered?.color}`">
                    <strong x-text="hovered?.name"></strong>
                    <span x-text="hovered?.value"></span>
                </div>
            </div>
            <div class="districts-map-legend">
                @php
                    $rangeMin = $colorRange['min'] ?? null;
                    $rangeMax = $colorRange['max'] ?? null;
                @endphp
                <span class="legend-bound">{{ $rangeMin !== null ? $fmt($rangeMin, 1) . '%' : '—' }}</span>
                <span class="legend-bar {{ ($colorRange['lowerIsBetter'] ?? false) ? 'reverse' : '' }}"></span>
                <span class="legend-bound">{{ $rangeMax !== null ? $fmt($rangeMax, 1) . '%' : '—' }}</span>
            </div>
        </section>

        <aside class="districts-side">
            <section class="district-summary-card {{ $selectedDistrict ? '' : 'empty' }}">
                <header class="district-summary-head">
                    <div>
                        <span>Танланган ҳудуд</span>
                        <h3>{{ $selectedDistrict?->name_full ?? 'Туман танланмаган' }}</h3>
                    </div>
                    @if($selectedDistrict)
                        <span class="chip {{ $selectedStatus }}">{{ $statusLabel[$selectedStatus] ?? '—' }}</span>
                    @endif
                </header>
                @if($selectedDistrict)
                    <div class="district-summary-value">
                        <div>
                            <strong>{{ $selectedFact?->pct_of_plan !== null ? $fmt($selectedFact->pct_of_plan, 1) . '%' : '—' }}</strong>
                            <span>Ижро бажарилиши · {{ $kpiShort }}</span>
                        </div>
                        <div class="district-count-split">
                            <span class="chip {{ $taskChipClass($selectedTasks) }}">T: {{ $selectedTasks['unfinished'] }}/{{ $selectedTasks['total'] }}</span>
                            <span class="chip {{ $targetChipClass($selectedTargetCount) }}">D: {{ $selectedTargetCount }}</span>
                        </div>
                    </div>
                    <div class="district-summary-metrics">
                        @foreach(array_slice($tableConfig['columns'], 0, 4) as $col)
                            @php $cell = $resolveCell($col, $selectedCode); @endphp
                            <div class="district-summary-metric">
                                <span>{{ $col['label'] }}</span>
                                <strong>{{ $cell['value'] }}</strong>
                                <small>{{ $cell['note'] }}</small>
                            </div>
                        @endforeach
                    </div>
                    <div class="district-summary-actions">
                        <a class="mini-button primary" href="{{ route('profile') }}?districtCode={{ $selectedCode }}">Туман профили</a>
                        <a class="mini-button" href="{{ route('tasks') }}?indicator={{ $kpi }}&district={{ $selectedCode }}">Топшириқлар</a>
                    </div>
                @else
                    <p class="muted">Харита ёки рейтингдан туман/шаҳарни танланг.</p>
                @endif
            </section>

            <section class="districts-leaderboard">
                <header class="districts-lb-head">
                    <strong>Туманлар</strong>
                    <span>{{ count($rankedDistricts) }} та туманлар · {{ $kpiShort }}</span>
                </header>
                <ol class="districts-lb-list">
                    @foreach($rankedDistricts as $idx => $row)
                        @php
                            $rd = $row['district'];
                            $rf = $row['fact'];
                            $rs = $row['status'];
                            $rPct = $rf?->pct_of_plan !== null ? (float) $rf->pct_of_plan : null;
                            $barW = $rPct !== null ? max(0, min(100, $rPct)) : 0;
                            $primary = $rf?->pct_of_plan !== null
                                ? $fmt($rf->pct_of_plan, 1) . '%'
                                : ($rf?->growth_pct !== null ? $fmt($rf->growth_pct, 1) . '%' : '—');
                        @endphp
                        <li class="lb-row {{ $rs }} {{ $rd->code === $selectedCode ? 'selected' : '' }}"
                            wire:click="selectDistrict('{{ $rd->code }}')"
                            tabindex="0">
                            <span class="lb-rank">{{ $idx + 1 }}</span>
                            <span class="lb-name">{{ $rd->name_full }}</span>
                            <span class="lb-value">{{ $primary }}</span>
                            <span class="lb-bar"><i style="width:{{ number_format($barW, 1, '.', '') }}%"></i></span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>

    <section class="panel district-detail-table">
        <div class="panel-head">
            <div>
                <h3>Батафсил жадвал</h3>
                <p>{{ $tableConfig['title'] }}. {{ $tableConfig['description'] }}</p>
            </div>
            <span class="chip grey">{{ $tableConfig['source'] }}</span>
        </div>
        <div class="district-table-wrap">
            <table class="district-table">
                <thead>
                    <tr>
                        <th>Туман/шаҳар</th>
                        @foreach($tableConfig['columns'] as $col)
                            <th>{{ $col['label'] }}</th>
                        @endforeach
                        <th class="num">T-топшириқ</th>
                        <th class="num">D-мақсад</th>
                        <th>Амал</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rankedDistricts as $row)
                        @php
                            $rd = $row['district'];
                            $code = $rd->code;
                            $rs = $row['status'];
                            $tasks = $taskCountByDistrict[$code] ?? ['unfinished' => 0, 'total' => 0];
                            $targets = $targetCountByDistrict[$code] ?? 0;
                        @endphp
                        <tr class="clickable {{ $code === $selectedCode ? 'active-row' : '' }}"
                            wire:click="selectDistrict('{{ $code }}')">
                            <td class="row-title"><strong>{{ $rd->name_full }}</strong><span>ҳокимлик</span></td>
                            @foreach($tableConfig['columns'] as $col)
                                @php $cell = $resolveCell($col, $code); @endphp
                                <td><strong>{{ $cell['value'] }}</strong><small>{{ $cell['note'] }}</small></td>
                            @endforeach
                            <td class="num"><span class="chip {{ $taskChipClass($tasks) }}">{{ $tasks['unfinished'] }}/{{ $tasks['total'] }}</span></td>
                            <td class="num"><span class="chip {{ $targetChipClass($targets) }}">{{ $targets }}</span></td>
                            <td>
                                <div class="action-row compact">
                                    <a class="mini-button profile" href="{{ route('profile') }}?districtCode={{ $code }}">Профил</a>
                                    <a class="mini-button" href="{{ route('execution') }}?indicator={{ $kpi }}&district={{ $code }}&period={{ $period }}">Журнал</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
