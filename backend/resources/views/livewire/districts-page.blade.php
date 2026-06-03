@php
    $taskCountByDistrict   = $this->taskCountByDistrict;
    $targetCountByDistrict = $this->targetCountByDistrict;
    $moduleKpiStats        = $this->moduleKpiStats;

    $statusLabel = [
        'green' => 'Яхши', 'amber' => 'Ўртача', 'red' => 'Эътибор', 'grey' => 'Маълумот йўқ',
    ];

    $taskDone      = fn (array $t): int => max(0, $t['total'] - $t['unfinished']);
    $taskChipClass = function (array $t): string {
        if ($t['total'] > 0 && $t['unfinished'] > 0) return 'red';
        if ($t['total'] > 0) return 'green';
        return 'grey';
    };
    $targetChipClass = fn (int $n): string => $n > 0 ? 'blue' : 'grey';

    $fmt = function ($v, int $decimals = 1): string {
        if ($v === null || $v === '') return '—';
        return number_format((float) $v, $decimals, ',', ' ');
    };

    // KPI value text: execution -> "NN,N%"; growth -> "+/−NN,N%"
    $statText = function (?float $v, string $kind) use ($fmt): string {
        if ($v === null) return '—';
        if ($kind === 'growth') return ($v >= 0 ? '+' : '−') . $fmt(abs($v), 1) . '%';
        return $fmt($v, 1) . '%';
    };
    $statUp = fn (?float $v, string $kind): bool => $v !== null && ($kind === 'execution' ? $v >= 100 : $v >= 0);

    $districts       = $this->districts;
    $rollup          = $this->rollup;
    $rankedDistricts = $this->rankedDistricts;
    $selectedRow     = $this->selectedDistrict;
    $moduleOptions   = $this->moduleOptions;
    $kpiOptions      = $this->kpiOptions;
    $indicator       = $this->indicator;

    $selectedCode        = $selectedRow ? $selectedRow['district']->code : '';
    $selectedDistrict    = $selectedRow ? $selectedRow['district'] : null;
    $selectedFact        = $selectedRow ? $selectedRow['fact'] : null;
    $selectedStatus      = $selectedRow ? $selectedRow['status'] : 'grey';
    $selectedTasks       = $selectedRow ? ($taskCountByDistrict[$selectedCode] ?? ['unfinished' => 0, 'total' => 0]) : ['unfinished' => 0, 'total' => 0];
    $selectedTargetCount = $selectedRow ? ($targetCountByDistrict[$selectedCode] ?? 0) : 0;

    $kpiShort = $indicator?->label_short ?? $kpi;
    $kpiFull  = $indicator?->label_full  ?? $kpi;

    $regionName = \App\Support\CurrentRegion::current()->name_full;
    $heroVal    = $rollup?->pct_of_plan ?? $rollup?->growth_pct;
    $heroVal    = $heroVal !== null ? (float) $heroVal : null;
    $heroKind   = $rollup?->pct_of_plan !== null ? 'execution' : 'growth';
@endphp

<div>
    <header class="districts-header">
        <div class="districts-header-top">
            <div class="module-seg">
                @foreach($moduleOptions as $m)
                    <button class="module-seg-btn {{ $m->code === $module ? 'on' : '' }}"
                            wire:click="selectModule('{{ $m->code }}')" type="button">
                        {{ preg_replace('/^\d+\.\s*/u', '', $m->label) }}
                    </button>
                @endforeach
            </div>
            <div class="districts-tools">
                <label class="districts-control districts-control--search">
                    <span>Қидириш</span>
                    <input wire:model.live.debounce.300ms="search" placeholder="Туман қидириш">
                </label>
                <label class="districts-control">
                    <span>Саралаш</span>
                    <select wire:model.live="sort">
                        <option value="attention">Эътибор талаб</option>
                        <option value="execution">Юқоридан</option>
                        <option value="plan">Режа каттадан</option>
                        <option value="name">Алифбо бўйича</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="districts-hero">
            <span class="districts-hero-icon" aria-hidden="true">@include('partials.icon', ['name' => $indicator?->icon ?? 'trend'])</span>
            <div class="districts-hero-title">
                <h2>{{ $kpiFull }}</h2>
                <span>{{ $regionName }} · туманлар кесими</span>
            </div>
            <div class="districts-hero-value">
                <strong class="{{ $heroKind === 'growth' && $heroVal !== null ? ($statUp($heroVal, $heroKind) ? 'up' : 'down') : '' }}">{{ $statText($heroVal, $heroKind) }}</strong>
                <small>вилоят бўйича</small>
            </div>
            <span class="chip blue districts-hero-period">{{ $period }}</span>
        </div>

        @if($kpiOptions->count() > 1)
            <div class="kpi-stats">
                @foreach($kpiOptions as $i)
                    @php
                        $st = $moduleKpiStats[$i->code] ?? null;
                        $sv = $st['value'] ?? null;
                        $sk = $st['kind'] ?? 'growth';
                    @endphp
                    <button class="kpi-stat-card {{ $i->code === $kpi ? 'on' : '' }}"
                            wire:click="selectKpi('{{ $i->code }}')" type="button"
                            title="{{ $i->label_full }}">
                        <span class="kpi-stat-icon" aria-hidden="true">@include('partials.icon', ['name' => $i->icon ?? 'trend'])</span>
                        <span class="kpi-stat-body">
                            <small>{{ $i->label_short }}</small>
                            <strong>{{ $statText($sv, $sk) }}</strong>
                        </span>
                        @if($sv !== null)
                            <span class="kpi-stat-trend {{ $statUp($sv, $sk) ? 'up' : 'down' }}" aria-hidden="true">{{ $statUp($sv, $sk) ? '▲' : '▼' }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    </header>

    <div class="districts-grid">
        <section class="districts-map">
            <header class="districts-map-head">
                <div>
                    <strong>Ҳудудлар харитаси</strong>
                    <span>Ҳар туман ранги вилоятдаги ўрнига нисбатан. Туман устига босинг.</span>
                </div>
            </header>
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
                               x-on:keydown.enter="$wire.selectDistrict('{{ $cellCode }}')"
                               x-on:keydown.space.prevent="$wire.selectDistrict('{{ $cellCode }}')"
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
                                $scaleEntry = $cellCode !== null ? ($colorScale[$cellCode] ?? null) : null;
                                $cellValue = $scaleEntry['value'] ?? null;
                                $isCity = str_ends_with($cell['name'], ' ш.');
                                $cellSel = $cellCode !== null && (string) $cellCode === (string) $selectedCode;
                            @endphp
                            @if($isCity)
                                <circle class="map-dot {{ $cellSel ? 'selected' : '' }}"
                                        cx="{{ $cell['cx'] }}" cy="{{ $cell['cy'] }}" r="3"/>
                            @elseif($cellValue !== null)
                                <text class="map-value" x="{{ $cell['cx'] }}" y="{{ $cell['cy'] + 4 }}"
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

        <section class="districts-ranklist">
            <header class="ranklist-head">
                <strong>Туманлар рейтинги</strong>
                <span>{{ count($rankedDistricts) }} та</span>
            </header>
            <ol class="ranklist-rows">
                @foreach($rankedDistricts as $idx => $row)
                    @php
                        $rd = $row['district']; $code = $rd->code; $rf = $row['fact']; $rs = $row['status'];
                        $rPct = $rf?->pct_of_plan !== null ? (float) $rf->pct_of_plan : null;
                        $rGrowth = $rf?->growth_pct !== null ? (float) $rf->growth_pct : null;
                        $primary = $rPct !== null ? $fmt($rPct, 1) . '%' : ($rGrowth !== null ? $fmt($rGrowth, 1) . '%' : '—');
                        $barW = $rPct !== null ? max(0, min(100, $rPct)) : 0;
                    @endphp
                    <li class="rank-row {{ $rs }} {{ $code === $selectedCode ? 'selected' : '' }}"
                        wire:click="selectDistrict('{{ $code }}')"
                        tabindex="0"
                        x-on:keydown.enter="$wire.selectDistrict('{{ $code }}')"
                        x-on:keydown.space.prevent="$wire.selectDistrict('{{ $code }}')">
                        <span class="rank-rk">{{ $idx + 1 }}</span>
                        <span class="rank-dot" aria-hidden="true"></span>
                        <span class="rank-nm">{{ $rd->name_full }}</span>
                        <span class="rank-vbar">
                            <span class="rank-bar"><i style="width:{{ number_format($barW, 1, '.', '') }}%"></i></span>
                            <span class="rank-vv">{{ $primary }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <div class="district-peek-backdrop {{ $selectedDistrict ? 'open' : '' }}" wire:click="clearDistrict"></div>
    <aside class="district-peek {{ $selectedDistrict ? 'open' : '' }}" aria-hidden="{{ $selectedDistrict ? 'false' : 'true' }}">
        @if($selectedDistrict)
            <button class="district-peek-x" wire:click="clearDistrict" type="button" aria-label="Ёпиш">×</button>
            <div class="district-peek-head">
                <span class="district-peek-eyebrow">Танланган ҳудуд</span>
                <h2>{{ $selectedDistrict->name_full }}</h2>
                <span class="chip {{ $selectedStatus }}">{{ $statusLabel[$selectedStatus] ?? '—' }}</span>
            </div>
            <div class="district-peek-value">
                <strong>{{ $selectedFact?->pct_of_plan !== null ? $fmt($selectedFact->pct_of_plan, 1) . '%' : ($selectedFact?->growth_pct !== null ? $fmt($selectedFact->growth_pct, 1) . '%' : '—') }}</strong>
                <span>Ижро бажарилиши · {{ $kpiShort }}</span>
            </div>
            <div class="district-peek-pf">
                <div><small>Режа</small><strong>{{ $selectedFact?->plan_value !== null ? $fmt($selectedFact->plan_value, 1) : '—' }}</strong></div>
                <div><small>Факт</small><strong>{{ ($selectedFact?->actual_hokimyat ?? $selectedFact?->actual_statkom) !== null ? $fmt($selectedFact->actual_hokimyat ?? $selectedFact->actual_statkom, 1) : '—' }}</strong></div>
            </div>
            <div class="district-peek-chips">
                <span class="chip {{ $taskChipClass($selectedTasks) }}">Топшириқлар {{ $taskDone($selectedTasks) }}/{{ $selectedTasks['total'] }}</span>
                <span class="chip {{ $targetChipClass($selectedTargetCount) }}">Кафолат мажбурияти {{ $selectedTargetCount }}</span>
            </div>
            <div class="district-peek-actions">
                <a class="mini-button primary" href="{{ route('profile') }}?districtCode={{ $selectedCode }}">Профил</a>
                <a class="mini-button" href="{{ route('execution') }}?indicator={{ $kpi }}&district={{ $selectedCode }}&period={{ $period }}">Журнал</a>
            </div>
        @endif
    </aside>
</div>
