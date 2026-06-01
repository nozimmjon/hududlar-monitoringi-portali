@php
    $totals = $this->totals;
    $tasks = $this->tasks;
    $moduleOptions = $this->moduleOptions;
    $indicatorOptions = $this->indicatorOptions;
    $shownScope = $status === 'done' ? 'Бажарилган' : ($status === 'open' ? 'Бажарилмаган' : 'Барчаси');
@endphp

<div>
    <div class="task-filter report-filter">
        <label>Йўналиш / жадвал
            <select wire:model.live="module">
                <option value="all">Барча 7 йўналиш</option>
                @foreach($moduleOptions as $m)
                    <option value="{{ $m->code }}">{{ $m->label }}</option>
                @endforeach
            </select>
        </label>
        <label>KPI / топшириқ йўналиши
            <select wire:model.live="indicator">
                <option value="all">Барча KPI</option>
                @foreach($indicatorOptions as $i)
                    <option value="{{ $i->code }}">{{ $i->label_short }} — {{ $i->label_full }}</option>
                @endforeach
            </select>
        </label>
        <label>Ҳолат
            <select wire:model.live="status">
                <option value="open">Бажарилмаган</option>
                <option value="all">Барчаси</option>
                <option value="done">Бажарилган</option>
            </select>
        </label>
        <label>Қидириш
            <input wire:model.live.debounce.300ms="search" placeholder="Топшириқ, масъул ёки ҳудуд">
        </label>
    </div>

    <div class="tasks-layout">
        <div class="tasks-main">
            <section class="task-group">
                <div class="task-group-head">
                    <h3>{{ $shownScope }} топшириқлар</h3>
                    <span class="chip grey">{{ $tasks->count() }} та</span>
                </div>
                <div class="task-list">
                    @forelse($tasks as $task)
                        @php
                            $pct = $task->headline_pct !== null ? (float) $task->headline_pct : null;
                            $isDone = $task->status === 'done';
                            // Percent text and its colour tier come from the SAME value so they can never disagree.
                            // A not-done task never shows 100% (capped at 99); green is reserved for genuinely done tasks.
                            $pctShown = $pct === null ? null : ($isDone ? (int) round($pct) : min(99, (int) round($pct)));
                            $tier = $pct === null ? 'none' : ($isDone ? 'green' : ($pctShown >= 50 ? 'amber' : 'red'));
                            $tierVar = ['none' => '--grey', 'red' => '--task-red', 'amber' => '--task-amber', 'green' => '--task-green'][$tier];
                            $statusChip = $isDone ? 'green' : 'grey';
                            $statusLabel = $isDone ? 'Бажарилди' : 'Бажарилмаган';
                            $cadenceLabel = $task->cadence === 'monthly' ? 'Ойлик' : ($task->cadence === 'quarterly' ? 'Чорак' : '');
                            $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ' '), '0'), '.');
                            $srok = $task->deadline_text;
                            $yonalish = $task->module?->label ?? $task->section_label;
                            $scopeText = $task->districts->count() ? $task->districts->count() . ' туман/шаҳар' : 'вилоят';
                            // "Planned" mirrors Task::scopeHasPlan: a headline plan OR a plan on any sub-metric line.
                            $hasAnyPlan = $task->headline_plan !== null
                                || $task->progress->contains(fn ($p) => $p->plan_value !== null);
                        @endphp
                        <article class="task-card" data-task-id="{{ $task->id }}">
                            <span class="task-num">{{ $loop->iteration }}</span>
                            <div class="task-body">
                                <strong>{{ $task->title }}</strong>

                                <div class="task-ctx">
                                    @if($srok)<span><span class="k">Срок</span> <span class="v">{{ $srok }}</span></span>@endif
                                    @if($yonalish)<span><span class="k">Йўналиш</span> <span class="v">{{ $yonalish }}</span></span>@endif
                                </div>

                                <div class="task-strip">
                                    <div class="cell">
                                        <span class="clab">Режа</span>
                                        <span class="val">{{ $fmt($task->headline_plan) }}<small>{{ $task->headline_unit }}</small></span>
                                    </div>
                                    <div class="cell">
                                        <span class="clab">Амалда</span>
                                        <span class="val">{{ $fmt($task->headline_actual) }}<small>{{ $task->headline_unit }}</small></span>
                                    </div>
                                    <div class="cell">
                                        <span class="clab">Бажарилиш</span>
                                        <span class="val task-pct task-pct--{{ $tier }}">{{ $pctShown === null ? '—' : $pctShown . '%' }}</span>
                                    </div>
                                </div>

                                @if($hasAnyPlan)
                                    {{-- Planned (headline or sub-line) -> always show the bar. No actual/pct yet -> empty 0% grey track. --}}
                                    <div class="task-foot">
                                        <div class="progress"><i style="--w:{{ $pct === null ? 0 : max(0, min(100, $pct)) }}%;--c:var({{ $tierVar }})"></i></div>
                                        @if($task->latest_period)<span class="task-foot-cap">ҳолат: {{ $task->latest_period }}</span>@endif
                                    </div>
                                @endif

                                @php
                                    $latestLines = $task->latest_period
                                        ? $task->progress->where('report_period', $task->latest_period)
                                        : collect();
                                @endphp
                                @if($latestLines->count() > 1 || $task->districts->isNotEmpty())
                                    <details class="task-detail">
                                        <summary class="muted">Батафсил ({{ $latestLines->count() }} кўрсаткич{{ $task->districts->isNotEmpty() ? ', ' . $task->districts->count() . ' ҳудуд' : '' }})</summary>
                                        <div class="task-meta">
                                            <span>Қамров: {{ $scopeText }}</span>
                                            @if($cadenceLabel)<span>Даврийлик: {{ $cadenceLabel }}</span>@endif
                                        </div>
                                        @foreach($latestLines as $line)
                                            <div class="task-meta">
                                                <span>{{ $line->metric_label ?? '—' }}</span>
                                                <span>Режа: <b>{{ $fmt($line->plan_value) }}</b> {{ $line->unit }}</span>
                                                <span>Амалда: <b>{{ $fmt($line->actual_value) }}</b> {{ $line->unit }}</span>
                                                <span><b>{{ $line->pct_of_plan !== null ? round((float) $line->pct_of_plan) . '%' : '—' }}</b></span>
                                            </div>
                                        @endforeach
                                        @if($task->districts->isNotEmpty())
                                            <div class="task-meta">
                                                <span>Ижрочи ҳудудлар:</span>
                                                @foreach($task->districts as $d)
                                                    <span class="chip blue">{{ $d->name_full }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </details>
                                @endif
                            </div>
                            <div class="task-chips">
                                <span class="chip {{ $statusChip }}">{{ $statusLabel }}</span>
                                <span class="chip grey">{{ $task->kind === 'kpi' ? 'KPI' : 'Чора-тадбир' }}</span>
                                @if($task->indicator)
                                    <span class="chip blue">{{ $task->indicator->label_short }}</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="muted">Бу филтр бўйича топшириқ топилмади.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="tasks-side">
            <div class="task-donut-card">
                <div class="task-donut-head">Бажарилиш ҳолати</div>
                <div class="task-donut" style="--pct:{{ $totals['pct'] }}">
                    <strong>{{ $totals['pct'] }}%</strong>
                </div>
            </div>
            <div class="task-stat-stack">
                <div class="task-stat-box blue">
                    <span class="task-stat-num">{{ $totals['total'] }}</span>
                    <span class="task-stat-label">Жами</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'users'])</span>
                </div>
                <div class="task-stat-box green">
                    <span class="task-stat-num">{{ $totals['done'] }}</span>
                    <span class="task-stat-label">Бажарилди</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'check'])</span>
                </div>
                <div class="task-stat-box red">
                    <span class="task-stat-num">{{ $totals['open'] }}</span>
                    <span class="task-stat-label">Бажарилмади</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'x'])</span>
                </div>
            </div>
        </aside>
    </div>
</div>
