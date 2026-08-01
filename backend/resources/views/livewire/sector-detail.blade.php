<div class="sdp-wrap">
    <div class="dp-crumb"><a href="{{ route('sectors') }}"><span class="arr">←</span> Барча корхоналар</a></div>

    @php
        $pct = $agg['pct'];
        $pctShown = $pct === null ? null : ($pct >= 100 ? 100 : min(99, (int) round($pct)));
        $ringColor = $pct === null ? 'var(--grey)'
            : ($pct >= 100 ? 'var(--task-green)' : ($pct >= 50 ? 'var(--task-amber)' : 'var(--task-red)'));
    @endphp

    <header class="sdp-hero">
        <div class="sdp-id">
            @if($sector->logoPath())
                <div class="sdp-logo"><img src="{{ asset($sector->logoPath()) }}" alt=""></div>
            @endif
            <div class="sdp-id-text">
                <h1>{{ $sector->org_full }}</h1>
                @if($sector->signer_text)
                    <div class="sdp-signer">{{ $sector->signer_text }} имзолаган кафолат хати</div>
                @endif
            </div>
        </div>
        <div class="sdp-agg">
            <div class="sdp-ring" style="--p:{{ $pct === null ? 0 : max(0, min(100, $pct)) }};--c:{{ $ringColor }}">
                <span class="sdp-ring-val">{{ $pctShown === null ? '—' : $pctShown . '%' }}</span>
            </div>
            <div class="sdp-agg-facts">
                <div><b>{{ $counts['all'] }}</b> топшириқ · <b>{{ $agg['lines_total'] }}</b> индикатор</div>
                <div><b class="ok">{{ $counts['done'] }}</b> бажарилди · <b class="bad">{{ $counts['open'] }}</b> бажарилмаган</div>
                <div><b class="wait">{{ $counts['in_progress'] }}</b> кутилмоқда</div>
            </div>
        </div>
    </header>

    <div class="sdp-tabs" role="tablist">
        @foreach([
            'all'         => 'Барчаси',
            'done'        => 'Бажарилди',
            'open'        => 'Бажарилмаган',
            'in_progress' => 'Кутилмоқда',
        ] as $key => $label)
            <button type="button" role="tab" class="sdp-tab {{ $filter === $key ? 'active' : '' }}"
                    aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                    wire:click="setFilter(@js($key))">
                {{ $label }} <span class="n">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    <div class="sdp-tasks">
        @forelse($tasks as $task)
            @php
                $isMulti = (int) $task->lines_total > 1;
                $tpct = $task->status === 'in_progress' ? null
                    : ($isMulti
                        ? ($task->lines_total > 0 ? $task->lines_done / $task->lines_total * 100 : null)
                        : ($task->headline_pct !== null ? (float) $task->headline_pct : null));
                $isDone = $task->status === 'done';
                $tpctShown = $tpct === null ? null : ($isDone ? (int) round($tpct) : min(99, (int) round($tpct)));
                $tier = $tpct === null ? '--grey'
                    : ($isDone ? '--task-green' : ($tpctShown >= 50 ? '--task-amber' : '--task-red'));
                $chip = $isDone ? ['green', 'Бажарилди']
                    : ($task->status === 'in_progress' ? ['violet', 'Бажарилмоқда'] : ['amber', 'Бажарилмаган']);
                $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
                $unitLabel = \App\Support\DashboardCatalog::unitLabel($task->headline_unit);
                $lines = $task->progress
                    ->where('report_period', $task->latest_period)
                    ->sortBy('line_no')
                    ->values();
                $headLine = $lines->first();
                $isOpen = $expanded[$task->id] ?? false;
            @endphp
            <article class="sdp-task" wire:key="sdt-{{ $task->id }}">
                <div class="sdp-task-head">
                    <span class="sdp-task-no">{{ $task->task_no }}</span>
                    <h2 class="sdp-task-title">{{ $task->title }}</h2>
                    <span class="chip {{ $chip[0] }}">{{ $chip[1] }}</span>
                </div>
                <div class="sdp-task-grid">
                    <div class="sdp-cells">
                        @if($isMulti)
                            <div class="cell"><span class="clab">Индикаторлар</span><span class="val">{{ $task->lines_total }}<small>та</small></span></div>
                            <div class="cell"><span class="clab">Бажарилди</span><span class="val">{{ $task->lines_done }}<small>та</small></span></div>
                        @else
                            <div class="cell"><span class="clab">Режа</span><span class="val">{{ $fmt($task->headline_plan) }}<small>{{ $unitLabel }}</small></span></div>
                            <div class="cell"><span class="clab">Амалда</span><span class="val">{{ $fmt($task->headline_actual) }}<small>{{ $task->headline_actual !== null ? $unitLabel : '' }}</small></span></div>
                        @endif
                        <div class="cell"><span class="clab">Бажарилиш</span><span class="val">{{ $tpctShown === null ? '—' : $tpctShown . '%' }}</span></div>
                        <div class="cell wide">
                            <span class="clab">Муддат</span>
                            <span class="val val-sm">{{ $headLine?->deadline_text ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="sdp-bar"><i style="--w:{{ $tpct === null ? 0 : max(0, min(100, $tpct)) }}%;--c:var({{ $tier }})"></i></div>
                </div>
                @if($isMulti)
                    <button type="button" class="sdp-lines-toggle" wire:click="toggleTask(@js($task->id))">
                        {{ $isOpen ? '▴ Индикаторларни ёпиш' : '▾ Индикаторлар (' . $task->lines_total . ')' }}
                    </button>
                    @if($isOpen)
                        <div class="sdp-lines">
                            @foreach($lines as $line)
                                @php
                                    $lineUnit = \App\Support\DashboardCatalog::unitLabel($line->unit);
                                    $linePct = $line->pct_of_plan !== null ? (int) round((float) $line->pct_of_plan) : null;
                                    $lineTier = $linePct === null ? '--grey'
                                        : ($linePct >= 100 ? '--task-green' : ($linePct >= 50 ? '--task-amber' : '--task-red'));
                                @endphp
                                <div class="sdp-line {{ $line->actual_value === null ? 'dim' : '' }}" wire:key="sdl-{{ $line->id }}">
                                    <span class="sdp-line-label">{{ $line->metric_label }}</span>
                                    <span class="sdp-line-vals">
                                        <b>{{ $fmt($line->actual_value) }}</b> / {{ $fmt($line->plan_value) }} <small>{{ $lineUnit }}</small>
                                        <span class="sdp-line-pct" style="color:var({{ $lineTier }})">{{ $linePct === null ? '—' : $linePct . '%' }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </article>
        @empty
            <div class="sdp-empty">Бу ҳолатда топшириқ йўқ.</div>
        @endforelse
    </div>
</div>
