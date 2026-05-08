@php
    $totals = $this->totals;
    $tasks = $this->tasks;
    $moduleOptions = $this->moduleOptions;
    $indicatorOptions = $this->indicatorOptions;
    $districtOptions = $this->districtOptions;
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

    <details class="task-advanced-filters" @if($period !== 'all' || $district !== 'all') open @endif>
        <summary>Қўшимча фильтрлар</summary>
        <div class="task-advanced-grid">
            <label>Муддат
                <select wire:model.live="period">
                    <option value="all">Барча муддатлар</option>
                    <option value="h1">II чорак / I ярим йиллик</option>
                    <option value="year">Йил якуни / давомида</option>
                </select>
            </label>
            <label>Туман/шаҳар
                <select wire:model.live="district">
                    <option value="all">Барча ҳудудлар</option>
                    @foreach($districtOptions as $d)
                        <option value="{{ $d->id }}">{{ $d->name_full }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </details>

    <div class="task-summary-strip execution-overview">
        <div class="task-summary-copy">
            <span>Ижро ҳолати</span>
            <strong>{{ $totals['total'] }} та топшириқ</strong>
            <small>{{ $shownScope }} топшириқлар кўрсатилмоқда.</small>
        </div>
        <div class="exec-status-grid">
            <button class="exec-status-pill {{ $status === 'all' ? 'active' : '' }}" type="button" wire:click="selectStatus('all')">
                <span>Жами</span>
                <strong>{{ $totals['total'] }}</strong>
            </button>
            <button class="exec-status-pill green {{ $status === 'done' ? 'active' : '' }}" type="button" wire:click="selectStatus('done')">
                <span>Бажарилди</span>
                <strong>{{ $totals['done'] }}</strong>
            </button>
            <button class="exec-status-pill red {{ $status === 'open' ? 'active' : '' }}" type="button" wire:click="selectStatus('open')">
                <span>Бажарилмади</span>
                <strong>{{ $totals['open'] }}</strong>
            </button>
            <button class="exec-status-pill blue" type="button" disabled>
                <span>Ҳисобот киритилган</span>
                <strong>0</strong>
            </button>
        </div>
        <div class="exec-progress-box">
            <div class="exec-donut" style="--pct:{{ $totals['pct'] }}"><strong>{{ $totals['pct'] }}%</strong></div>
            <small>бажарилиш</small>
        </div>
        <div class="score-actions">
            <a class="score-action primary" href="{{ route('dashboard') }}">KPI экрани</a>
            <a class="score-action" href="{{ route('execution') }}">Ижро журнали</a>
        </div>
    </div>

    <div class="task-workspace">
        <div class="task-groups">
            <section class="task-group">
                <div class="task-group-head">
                    <h3>{{ $shownScope }} топшириқлар</h3>
                    <span class="chip grey">{{ $tasks->count() }} та</span>
                </div>
                <div class="task-list">
                    @forelse($tasks as $task)
                        <article class="task-card compact" data-task-id="{{ $task->id }}">
                            <header>
                                <span class="task-code">{{ $task->task_number }}</span>
                                <strong>{{ $task->title }}</strong>
                                <div class="task-meta">
                                    <span>{{ $task->deadline_text }}</span>
                                    <span>{{ $task->districts->count() ? $task->districts->count() . ' туман/шаҳар' : 'вилоят' }}</span>
                                    <span>{{ $task->module?->label ?? $task->section_label }}</span>
                                </div>
                            </header>
                            <div class="task-actions">
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
        <aside class="task-focus">
            <div class="eyebrow">Топшириқлар</div>
            <h3>KPI → топшириқ → ҳисобот</h3>
            <p>Бу экран KPI карточкасида кўринган ижро ҳолатини номма-ном топшириқларга очиб беради.</p>
            <div class="task-side-stack">
                <div class="task-side-row">
                    <div><strong>Танланган йўналиш</strong><span>{{ $module === 'all' ? 'Барча 7 йўналиш' : ($moduleOptions->firstWhere('code', $module)?->label ?? $module) }}</span></div>
                    <span class="chip blue">{{ $totals['total'] }} та</span>
                </div>
                <div class="task-side-row">
                    <div><strong>Танланган KPI</strong><span>{{ $indicator === 'all' ? 'Барча KPI' : ($indicatorOptions->firstWhere('code', $indicator)?->label_full ?? $indicator) }}</span></div>
                    <span class="chip blue">{{ $indicator === 'all' ? 'ҳаммаси' : $indicator }}</span>
                </div>
                <div class="task-side-row">
                    <div><strong>Ҳисобот киритилган</strong><span>Киритилган ҳисоботлар ижро журналида текширилади.</span></div>
                    <span class="chip grey">0/{{ $totals['total'] }}</span>
                </div>
            </div>
        </aside>
    </div>
</div>
