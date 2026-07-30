<div class="sectors-wrap">
    <div class="dp-crumb"><a href="{{ route('home') }}"><span class="arr">←</span> Бош саҳифа</a></div>

    <div class="dp-hero">
        <div>
            <h1>Тармоқ корхоналари топшириқлари</h1>
        </div>
        <div class="dp-hero-facts">
            <div class="dp-fact"><div class="v">{{ $summary['sectors'] }}</div><div class="k">корхона</div></div>
            <div class="dp-fact">
                <div class="v">{{ $summary['tasks'] }}</div>
                <div class="k">топшириқдан {{ $summary['done'] }} таси бажарилди</div>
            </div>
            <div class="dp-fact">
                <div class="v"><span class="warn">{{ $summary['open'] }}</span>/{{ $summary['waiting'] }}</div>
                <div class="k">бажарилмаган / кутилмоқда</div>
            </div>
        </div>
    </div>

    <div class="dp-sect">
        <h2>Корхоналар</h2>
    </div>

    <div class="sector-grid">
        @foreach($cards as $card)
            @php
                $s = $card['sector'];
                $pct = $card['pct'];
                // Same 99-cap rule as the task cards: only a fully-done sector shows 100%.
                $pctShown = $pct === null ? null : ($pct >= 100 ? 100 : min(99, (int) round($pct)));
                $tierVar = $pct === null ? '--grey'
                    : ($pct >= 100 ? '--task-green' : ($pct >= 50 ? '--task-amber' : '--task-red'));
                $isActive = $selected && $selected->code === $s->code;
            @endphp
            <div class="sector-card {{ $isActive ? 'active' : '' }} {{ $pct === null ? 'nodata' : '' }}"
                 role="button" tabindex="0" wire:key="sector-{{ $s->code }}"
                 wire:click="selectSector(@js($s->code))"
                 x-on:keydown.enter="$wire.selectSector(@js($s->code))"
                 x-on:keydown.space.prevent="$wire.selectSector(@js($s->code))">
                <div class="sector-card-head">
                    @if($card['logo'])
                        <img class="sector-card-logo" src="{{ asset($card['logo']) }}" alt="" loading="lazy">
                    @else
                        <span class="sector-card-mono" aria-hidden="true">{{ mb_substr($s->cardName(), 0, 1) }}</span>
                    @endif
                </div>
                <div class="sector-card-name {{ mb_strlen($s->cardName()) > 20 ? 'long' : '' }}">{{ $s->cardName() }}</div>
                <div class="sector-card-sub">{{ $card['tasks_total'] }} топшириқ · {{ $card['lines_total'] }} индикатор</div>
                <div class="progress"><i style="--w:{{ $pct === null ? 0 : max(0, min(100, $pct)) }}%;--c:var({{ $tierVar }})"></i></div>
                @if($pct !== null)
                    <div class="sector-card-foot">
                        <span class="sector-card-pct">{{ $pctShown }}%</span>
                        <span class="sector-card-counts">
                            <b class="ok">{{ $card['done'] }}</b> ·
                            <b class="bad">{{ $card['open'] }}</b> ·
                            <b class="wait">{{ $card['waiting'] }}</b>
                        </span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if($selected)
        <div class="dp-sect" id="sector-detail">
            <h2>{{ $selected->org_full }} — {{ $selectedTasks->count() }} топшириқ</h2>
            @if($selected->signer_text)
                <span class="dp-sect-hint">{{ $selected->signer_text }} имзолаган кафолат хати</span>
            @endif
        </div>

        <div class="dp-tasks">
            @foreach($selectedTasks as $task)
                @php
                    $isMulti = (int) $task->lines_total > 1;
                    $pct = $task->status === 'in_progress' ? null
                        : ($isMulti
                            ? ($task->lines_total > 0 ? $task->lines_done / $task->lines_total * 100 : null)
                            : ($task->headline_pct !== null ? (float) $task->headline_pct : null));
                    $isDone = $task->status === 'done';
                    $pctShown = $pct === null ? null : ($isDone ? (int) round($pct) : min(99, (int) round($pct)));
                    $tierVar = $pct === null ? '--grey'
                        : ($isDone ? '--task-green' : ($pctShown >= 50 ? '--task-amber' : '--task-red'));
                    $chip = $isDone ? ['green', 'Бажарилди']
                        : ($task->status === 'in_progress' ? ['violet', 'Бажарилмоқда'] : ['amber', 'Бажарилмаган']);
                    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
                    $unitLabel = \App\Support\DashboardCatalog::unitLabel($task->headline_unit);
                    $lines = $task->progress
                        ->where('report_period', $task->latest_period)
                        ->sortBy('line_no')
                        ->values();
                    $headLine = $lines->first();
                @endphp
                <div class="dp-task {{ $isMulti ? 'expandable' : '' }} {{ ($expanded[$task->id] ?? false) ? 'open' : '' }}"
                     wire:key="stask-{{ $task->id }}"
                     @if($isMulti)
                         wire:click="toggleTask(@js($task->id))" role="button" tabindex="0"
                         x-on:keydown.enter="$wire.toggleTask(@js($task->id))"
                         x-on:keydown.space.prevent="$wire.toggleTask(@js($task->id))"
                     @endif>
                    <div class="dp-task-top">
                        <div class="dp-task-title">{{ $task->task_no }}. {{ $task->title }}</div>
                        <span class="chip {{ $chip[0] }}">{{ $chip[1] }}</span>
                    </div>
                    <div class="task-strip">
                        @if($isMulti)
                            <div class="cell"><span class="clab">Индикаторлар</span><span class="val">{{ $task->lines_total }}<small>та</small></span></div>
                            <div class="cell"><span class="clab">Бажарилди</span><span class="val">{{ $task->lines_done }}<small>та</small></span></div>
                        @else
                            <div class="cell"><span class="clab">Режа</span><span class="val">{{ $fmt($task->headline_plan) }}<small>{{ $unitLabel }}</small></span></div>
                            <div class="cell"><span class="clab">Амалда</span><span class="val">{{ $fmt($task->headline_actual) }}<small>{{ $task->headline_actual !== null ? $unitLabel : '' }}</small></span></div>
                        @endif
                        <div class="cell"><span class="clab">Бажарилиш</span><span class="val">{{ $pctShown === null ? '—' : $pctShown . '%' }}</span></div>
                    </div>
                    <div class="task-foot">
                        <div class="progress"><i style="--w:{{ $pct === null ? 0 : max(0, min(100, $pct)) }}%;--c:var({{ $tierVar }})"></i></div>
                        @if($task->latest_period)<span class="task-foot-cap">ҳолат: {{ $task->latest_period }}</span>@endif
                    </div>
                    @if($isMulti && ($expanded[$task->id] ?? false))
                        <div class="stp-lines" onclick="event.stopPropagation()">
                            @foreach($lines as $line)
                                @php
                                    $lineUnit = \App\Support\DashboardCatalog::unitLabel($line->unit);
                                    $linePct = $line->pct_of_plan !== null ? (int) round((float) $line->pct_of_plan) : null;
                                    $lineTier = $linePct === null ? '--grey'
                                        : ($linePct >= 100 ? '--task-green' : ($linePct >= 50 ? '--task-amber' : '--task-red'));
                                @endphp
                                <div class="stp-line {{ $line->actual_value === null ? 'dim' : '' }}" wire:key="stpl-{{ $line->id }}">
                                    <span class="stp-line-label">{{ $line->metric_label }}</span>
                                    <span class="stp-line-vals">
                                        <b>{{ $fmt($line->actual_value) }}</b> / {{ $fmt($line->plan_value) }} <small>{{ $lineUnit }}</small>
                                        <span class="stp-line-pct" style="color:var({{ $lineTier }})">{{ $linePct === null ? '—' : $linePct . '%' }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="dp-task-meta">
                        Муддат: <b>{{ $headLine?->deadline_text ?? '—' }}</b>
                        @if($task->status === 'in_progress') · маълумот кутилмоқда @endif
                        @if($isMulti)<span class="stask-toggle">{{ ($expanded[$task->id] ?? false) ? '▴ ёпиш' : '▾ индикаторлар' }}</span>@endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
