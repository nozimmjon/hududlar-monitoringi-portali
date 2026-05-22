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
            <div class="task-stat-row">
                <button class="task-stat-box blue {{ $status === 'all' ? 'is-active' : '' }}" type="button" wire:click="selectStatus('all')">
                    <span class="task-stat-num">{{ $totals['total'] }}</span>
                    <span class="task-stat-label">Жами</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'users'])</span>
                </button>
                <button class="task-stat-box green {{ $status === 'done' ? 'is-active' : '' }}" type="button" wire:click="selectStatus('done')">
                    <span class="task-stat-num">{{ $totals['done'] }}</span>
                    <span class="task-stat-label">Бажарилди</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'check'])</span>
                </button>
                <button class="task-stat-box red {{ $status === 'open' ? 'is-active' : '' }}" type="button" wire:click="selectStatus('open')">
                    <span class="task-stat-num">{{ $totals['open'] }}</span>
                    <span class="task-stat-label">Бажарилмади</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'x'])</span>
                </button>
            </div>

            <section class="task-group">
                <div class="task-group-head">
                    <h3>{{ $shownScope }} топшириқлар</h3>
                    <span class="chip grey">{{ $tasks->count() }} та</span>
                </div>
                <div class="task-list">
                    @forelse($tasks as $task)
                        <article class="task-card" data-task-id="{{ $task->id }}">
                            <span class="task-num">{{ $loop->iteration }}</span>
                            <div class="task-body">
                                <strong>{{ $task->title }}</strong>
                                <div class="task-meta">
                                    <span>{{ $task->deadline_text }}</span>
                                    <span>{{ $task->districts->count() ? $task->districts->count() . ' туман/шаҳар' : 'вилоят' }}</span>
                                    <span>{{ $task->module?->label ?? $task->section_label }}</span>
                                </div>
                            </div>
                            <div class="task-chips">
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
                <div class="task-donut" style="--pct:{{ $totals['pct'] }}">
                    <strong>{{ $totals['pct'] }}%</strong>
                    <span>бажарилиш</span>
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
