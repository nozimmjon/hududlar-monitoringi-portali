@php
    use App\Support\AndijanMapGeometry;
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

    $selectedTasks  = $selectedRow ? ($taskCountByDistrict[$selectedCode] ?? ['unfinished' => 0, 'total' => 0]) : ['unfinished' => 0, 'total' => 0];
    $selectedTargetCount = $selectedRow ? ($targetCountByDistrict[$selectedCode] ?? 0) : 0;

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
                    <button class="district-kpi-option {{ $i->code === $kpi ? 'active' : '' }}"
                            wire:click="selectKpi('{{ $i->code }}')"
                            type="button">
                        <strong>{{ $i->label_short }}</strong>
                        <span>{{ $i->label_full }}</span>
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
            <div class="district-layer-note">
                <span>Мантиқ</span>
                <strong>Ижро % = факт / режа × 100. Ўсиш % = ўтган йил билан таққослаш.</strong>
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
                    <span>Ранглар танланган KPI ҳолатини кўрсатади.</span>
                </div>
            </header>
            <div class="districts-map-canvas">
                <svg viewBox="{{ AndijanMapGeometry::VIEWBOX }}" class="andijan-map" role="img" aria-label="Андижон вилоятининг ҳудудлар харитаси">
                    <defs>
                        <linearGradient id="mapGradGreen" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#d6ecdb"/>
                            <stop offset="100%" stop-color="#8fc69f"/>
                        </linearGradient>
                        <linearGradient id="mapGradAmber" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#fbe9b6"/>
                            <stop offset="100%" stop-color="#e3b766"/>
                        </linearGradient>
                        <linearGradient id="mapGradRed" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#f5cfcf"/>
                            <stop offset="100%" stop-color="#d68585"/>
                        </linearGradient>
                        <linearGradient id="mapGradGrey" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#e8e6dd"/>
                            <stop offset="100%" stop-color="#bcb9ac"/>
                        </linearGradient>
                        <filter id="mapCellShadow" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#1b4d5a" flood-opacity="0.18"/>
                        </filter>
                    </defs>
                    <g>
                        @foreach(AndijanMapGeometry::CELLS as $cell)
                            @php
                                $cellDistrict = $districts->firstWhere('name_full', $cell['name']);
                                $cellCode = $cellDistrict?->code ?? '';
                                $cellStatus = $cellCode !== '' ? ($statusByDistrict[$cellCode] ?? 'grey') : 'grey';
                                $cellFact = $cellCode !== '' ? $facts->get($cellCode) : null;
                                $cellSelected = $cellCode === $selectedCode ? 'selected' : '';
                                $cellCity = $isCity($cell['name']) ? 'is-city' : '';
                                $cellValue = $cellFact?->pct_of_plan !== null
                                    ? $fmt($cellFact->pct_of_plan, 1) . '%'
                                    : ($cellFact?->growth_pct !== null ? $fmt($cellFact->growth_pct, 1) . '%' : '—');
                            @endphp
                            <g class="map-cell {{ $cellStatus }} {{ $cellSelected }} {{ $cellCity }}"
                               wire:click="selectDistrict('{{ $cellCode }}')"
                               tabindex="0">
                                <title>{{ $cell['name'] }} · {{ $cellValue }}</title>
                                <path class="map-fill" d="{{ $cell['path'] }}"/>
                            </g>
                        @endforeach
                    </g>
                    <g class="map-labels">
                        @foreach(AndijanMapGeometry::CELLS as $cell)
                            @php
                                $cellDistrict = $districts->firstWhere('name_full', $cell['name']);
                                $cellCode = $cellDistrict?->code ?? '';
                                $cellSelected = $cellCode === $selectedCode ? 'selected' : '';
                                $cellCity = $isCity($cell['name']) ? 'is-city' : '';
                            @endphp
                            <text class="map-label {{ $cellCity }} {{ $cellSelected }}"
                                  x="{{ $cell['cx'] }}" y="{{ $cell['cy'] + 1 }}"
                                  text-anchor="middle" dominant-baseline="central">{{ $cell['short'] }}</text>
                        @endforeach
                    </g>
                </svg>
            </div>
            <div class="districts-map-legend">
                <span class="legend-chip green">Яхши</span>
                <span class="legend-chip amber">Ўртача</span>
                <span class="legend-chip red">Эътибор</span>
                <span class="legend-chip grey">Маълумот йўқ</span>
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
                        <a class="mini-button" href="{{ route('execution') }}?indicator={{ $kpi }}&district={{ $selectedCode }}&period={{ $period }}">Ижро журнали</a>
                        <a class="mini-button" href="{{ route('tasks') }}?indicator={{ $kpi }}&district={{ $selectedCode }}">Топшириқлар</a>
                    </div>
                    <span class="chip grey">ҳисобот йўқ</span>
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
                        <th>Ҳисобот / таъсир</th>
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
                            <td><span class="chip grey">ҳисобот йўқ</span><small>амалдаги натижа киритилмаган</small></td>
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
