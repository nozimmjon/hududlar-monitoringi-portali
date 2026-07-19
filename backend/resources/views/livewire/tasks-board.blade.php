@php
    $totals = $this->totals;
    $tasks = $this->tasks;
    $moduleOptions = $this->moduleOptions;
    $shownScope = $status === 'done' ? 'Бажарилган' : ($status === 'open' ? 'Бажарилмаган' : 'Барча');
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
        <label>Ҳолат
            <select wire:model.live="status">
                <option value="all">Барчаси</option>
                <option value="open">Бажарилмаган</option>
                <option value="done">Бажарилган</option>
            </select>
        </label>
        <label>Муддат
            <select wire:model.live="deadline">
                <option value="all">Барча муддатлар</option>
                @foreach($this->deadlineOptions as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
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
                                    @if($srok)<span><span class="k">Муддат</span> <span class="v">{{ $srok }}</span></span>@endif
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
                                    // Breakdown shows sub-metrics only; the headline (line_no 0) is on the card face.
                                    $subLines = $latestLines->where('line_no', '>', 0);
                                    // Per-line tier by raw pct (red <50, amber 50–99, green ≥100). Unlike the
                                    // card face, sub-lines have no done-gating or 99% cap — a sub-metric can
                                    // genuinely be ≥100% even when the parent task isn't "done".
                                    $tlTier = fn ($p) => $p === null ? 'none'
                                        : ((float) $p >= 100 ? 'green' : ((float) $p >= 50 ? 'amber' : 'red'));
                                @endphp
                                @if($subLines->isNotEmpty() || $task->districts->isNotEmpty())
                                    <details class="task-detail">
                                        <summary>
                                            <span class="chev" aria-hidden="true"></span>
                                            <span class="lab">Батафсил</span>
                                            <span class="ct">
                                                @if($subLines->isNotEmpty())<span class="pill">{{ $subLines->count() }} кўрсаткич</span>@endif
                                                @if($task->districts->isNotEmpty())<span class="pill">{{ $task->districts->count() }} ҳудуд</span>@endif
                                            </span>
                                        </summary>
                                        <div class="task-detail-body">
                                            <div class="task-detail-cap">Қамров: <b>{{ $scopeText }}</b>@if($cadenceLabel) · Даврийлик: <b>{{ $cadenceLabel }}</b>@endif</div>
                                            @if($subLines->isNotEmpty())
                                                <div class="task-detail-lines">
                                                    @foreach($subLines as $line)
                                                        @php $lt = $tlTier($line->pct_of_plan); @endphp
                                                        <div class="tl-row">
                                                            <div class="tl-main">
                                                                <div class="tl-name">{{ $line->metric_label ?? '—' }}</div>
                                                                <div class="tl-sub">Режа <b>{{ $fmt($line->plan_value) }}</b> {{ $line->unit }} · Амалда <b>{{ $fmt($line->actual_value) }}</b> {{ $line->unit }}</div>
                                                            </div>
                                                            <span class="tl-pill tl-pill--{{ $lt }}">{{ $line->pct_of_plan !== null ? round((float) $line->pct_of_plan) . '%' : '—' }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($task->districts->isNotEmpty())
                                                <div class="task-detail-dist">
                                                    <span class="clab">Ижрочи ҳудудлар</span>
                                                    <div class="task-detail-chips">
                                                        @foreach($task->districts as $d)
                                                            <span class="chip blue">{{ $d->name_full }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
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
