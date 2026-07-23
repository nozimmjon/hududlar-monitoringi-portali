@php
    $taskCountByDistrict   = $this->taskCountByDistrict;
    $targetCountByDistrict = $this->targetCountByDistrict;
    $statusLabel = [
        'green' => 'Яхши', 'amber' => 'Ўртача', 'red' => 'Эътибор', 'grey' => 'Маълумот йўқ',
        'plan' => 'Режалаштирилган',
    ];
    // 'plan' status has no chip colour of its own; reuse the blue chip.
    $chipClass = fn (string $s): string => $s === 'plan' ? 'blue' : $s;

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
    // Plan-only KPIs (employment): the region rollup has no execution to show, so
    // the hero displays the вилоят plan instead of a dash.
    $heroVal    = $rollup?->pct_of_plan ?? $rollup?->growth_pct;
    $heroVal    = $heroVal !== null ? (float) $heroVal : null;
    $heroKind   = $rollup?->pct_of_plan !== null ? 'execution' : 'growth';
    $heroPlan   = $planMode && $rollup?->plan_value !== null
        ? $fmt($rollup->plan_value, 1) . (($indicator?->default_unit ?? '') === '%' || (bool) ($indicator?->lower_is_better) ? '%' : '')
        : null;
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
        </div>

        <div class="districts-hero">
            <span class="districts-hero-icon" aria-hidden="true">@include('partials.icon', ['name' => $indicator?->icon ?? 'trend'])</span>
            <div class="districts-hero-title">
                <h2>{{ $kpiFull }}</h2>
                <span>{{ $regionName }} · туманлар кесими</span>
            </div>
            <div class="districts-hero-value">
                @if($heroPlan !== null)
                    <strong>{{ $heroPlan }}</strong>
                    <small>вилоят режаси</small>
                @else
                    <strong class="{{ $heroKind === 'growth' && $heroVal !== null ? ($statUp($heroVal, $heroKind) ? 'up' : 'down') : '' }}">{{ $statText($heroVal, $heroKind) }}</strong>
                    <small>вилоят бўйича</small>
                @endif
            </div>
        </div>

        @if($kpiOptions->count() > 1)
            <div class="kpi-switch">
                @foreach($kpiOptions as $i)
                    <button class="kpi-switch-btn {{ $i->code === $kpi ? 'on' : '' }}"
                            wire:click="selectKpi('{{ $i->code }}')" type="button"
                            title="{{ $i->label_full }}">{{ $i->label_short }}</button>
                @endforeach
            </div>
        @endif
    </header>

    <section class="districts-mapstage">
        <header class="mapstage-head">
            <div>
                <strong>Ҳудудлар харитаси</strong>
            </div>
        </header>
        <div class="mapstage-canvas" x-data="{hovered:null,x:0,y:0}">
            <svg viewBox="{{ $mapLayout['viewBox'] }}" class="region-map" role="img" aria-label="Ҳудудлар харитаси">
                <g transform="{{ $mapLayout['mapTransform'] }}">
                    @foreach($mapGeometry['cells'] as $cell)
                        @php
                            $cellCode = $cell['code'] ?? null;
                            $cellDistrict = $cellCode !== null ? $districts->get($cellCode) : null;
                            $cellColor = $cellCode !== null ? ($mapColors[$cellCode] ?? 'nd') : 'nd';
                            $cellSel = $cellCode !== null && (string) $cellCode === (string) $selectedCode;
                            $cellName = $cellDistrict?->name_full ?? $cell['name'];
                        @endphp
                        <g class="map-cell {{ $cellColor }} {{ $cellSel ? 'selected' : '' }}"
                           @if($cellCode !== null)
                               wire:click="selectDistrict('{{ $cellCode }}')"
                               x-on:keydown.enter="$wire.selectDistrict('{{ $cellCode }}')"
                               x-on:keydown.space.prevent="$wire.selectDistrict('{{ $cellCode }}')"
                               x-on:mouseenter="hovered=@js($cellName)"
                               x-on:mouseleave="hovered=null"
                               x-on:mousemove="x=$event.offsetX; y=$event.offsetY"
                               tabindex="0"
                           @endif>
                            <title>{{ $cellName }}</title>
                            <path class="map-fill" d="{{ $cell['path'] }}"/>
                        </g>
                    @endforeach
                </g>
                <g class="map-leaders">
                    @foreach($mapLayout['pills'] as $pill)
                        <path class="map-leader" d="{{ $pill['leader'] }}"/>
                        <circle class="map-anchor" cx="{{ $pill['dotX'] }}" cy="{{ $pill['dotY'] }}" r="2.2"/>
                    @endforeach
                </g>
                <g class="map-pills">
                    @foreach($mapLayout['pills'] as $pill)
                        @php $psel = (string) $pill['code'] === (string) $selectedCode; @endphp
                        <g class="map-pill {{ $pill['color'] }} {{ $psel ? 'selected' : '' }}"
                           wire:click="selectDistrict('{{ $pill['code'] }}')"
                           x-on:keydown.enter="$wire.selectDistrict('{{ $pill['code'] }}')"
                           x-on:keydown.space.prevent="$wire.selectDistrict('{{ $pill['code'] }}')"
                           tabindex="0">
                            <rect class="pill-bg" x="{{ $pill['x'] }}" y="{{ $pill['y'] - $pill['h'] / 2 }}"
                                  width="{{ $pill['w'] }}" height="{{ $pill['h'] }}" rx="9"/>
                            <text class="pill-name" x="{{ $pill['x'] + 9 }}" y="{{ $pill['y'] + 4 }}">{{ $pill['name'] }}</text>
                            <text class="pill-value" x="{{ $pill['x'] + $pill['w'] - 9 }}" y="{{ $pill['y'] + 4 }}"
                                  text-anchor="end">{{ $pill['value'] }}</text>
                        </g>
                    @endforeach
                </g>
            </svg>
            <div class="map-tooltip" x-show="hovered" x-cloak
                 :style="`left:${x + 14}px; top:${y + 14}px`">
                <strong x-text="hovered"></strong>
            </div>
        </div>
    </section>

    <div class="district-peek-backdrop {{ $selectedDistrict ? 'open' : '' }}" wire:click="clearDistrict"></div>
    <aside class="district-peek {{ $selectedDistrict ? 'open' : '' }}" aria-hidden="{{ $selectedDistrict ? 'false' : 'true' }}">
        @if($selectedDistrict)
            <button class="district-peek-x" wire:click="clearDistrict" type="button" aria-label="Ёпиш">×</button>
            <div class="district-peek-head">
                <span class="district-peek-eyebrow">Танланган ҳудуд</span>
                <h2>{{ $selectedDistrict->name_full }}</h2>
                <span class="chip {{ $chipClass($selectedStatus) }}">{{ $statusLabel[$selectedStatus] ?? '—' }}</span>
            </div>
            <div class="district-peek-value">
                @if($planMode)
                    <strong>{{ $selectedFact?->plan_value !== null ? $fmt($selectedFact->plan_value, 1) : '—' }}</strong>
                    <span>Туман режаси · {{ $kpiShort }}</span>
                @else
                    <strong>{{ $selectedFact?->pct_of_plan !== null ? $fmt($selectedFact->pct_of_plan, 1) . '%' : ($selectedFact?->growth_pct !== null ? $fmt($selectedFact->growth_pct, 1) . '%' : '—') }}</strong>
                    <span>Ижро бажарилиши · {{ $kpiShort }}</span>
                @endif
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
            </div>
        @endif
    </aside>
</div>
