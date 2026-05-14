@php
    $kindLabels = ['measure' => 'Чора-тадбир', 'guarantee' => 'Кафолат', 'kpi' => 'KPI', 'monitoring' => 'Мониторинг'];
    $taskChip = $taskCounts['total'] > 0 && $taskCounts['unfinished'] > 0 ? 'red'
              : ($taskCounts['total'] > 0 ? 'green' : 'grey');
@endphp

<article class="panel">
    <div class="panel-head">
        <div>
            <h3>{{ $indicator?->label_short ?? 'KPI' }} топшириқлари</h3>
            <p>Фақат танланган KPI бўйича қисқа рўйхат.</p>
        </div>
        <span class="chip {{ $taskChip }}">{{ $taskCounts['unfinished'] }}/{{ $taskCounts['total'] }}</span>
    </div>
    <div class="panel-body">
        <div class="profile-task-list">
            @forelse($tasks as $task)
                <article class="task-card">
                    <header>
                        <strong>№ {{ $task->task_number }}</strong>
                        <span class="chip grey">{{ $kindLabels[$task->kind] ?? $task->kind }}</span>
                    </header>
                    <p>{{ \Illuminate\Support\Str::limit($task->title, 200) }}</p>
                    <footer>
                        <span>{{ $task->executor_text }}</span>
                        <span>{{ $task->deadline_text }}</span>
                    </footer>
                </article>
            @empty
                <p class="muted">Бу KPI бўйича топшириқ топилмади.</p>
            @endforelse
        </div>
        <div class="action-row">
            <a class="mini-button" href="{{ route('tasks') }}?district={{ $district->code }}&kpi={{ $kpi }}&status=open">Барча топшириқлар</a>
        </div>
    </div>
</article>
