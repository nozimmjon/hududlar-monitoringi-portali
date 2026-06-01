@php
    $taskChip = $taskCounts['total'] > 0 && $taskCounts['unfinished'] > 0 ? 'red'
              : ($taskCounts['total'] > 0 ? 'green' : 'grey');
@endphp

<article class="panel">
    <div class="panel-head">
        <div>
            <h3>Туман топшириқлари</h3>
            <p>{{ $district->name_full }} бўйича барча KPIлар.</p>
        </div>
        <span class="chip {{ $taskChip }}">{{ $taskCounts['unfinished'] }}/{{ $taskCounts['total'] }}</span>
    </div>
    <div class="panel-body">
        <div class="profile-task-list">
            @forelse($tasks as $task)
                @php
                    $pct = $task->headline_pct !== null ? (float) $task->headline_pct : null;
                    $tStatusChip = $task->status === 'done' ? 'green' : 'grey';
                    $tStatusLabel = $task->status === 'done' ? 'Бажарилди' : 'Бажарилмаган';
                    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ' '), '0'), '.');
                @endphp
                <article class="profile-task">
                    <p class="profile-task-title">{{ $task->title }}</p>
                    <div class="task-meta">
                        <span>Режа: <b>{{ $fmt($task->headline_plan) }}</b> {{ $task->headline_unit }}</span>
                        <span>Амалда: <b>{{ $fmt($task->headline_actual) }}</b> {{ $task->headline_unit }}</span>
                        <span class="chip {{ $tStatusChip }}">{{ $tStatusLabel }}{{ $pct !== null ? ' · ' . round($pct) . '%' : '' }}</span>
                    </div>
                    @if($task->deadline_text)
                        <span class="profile-task-deadline">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            {{ $task->deadline_text }}
                        </span>
                    @endif
                </article>
            @empty
                <p class="muted">Бу туман бўйича топшириқ топилмади.</p>
            @endforelse
        </div>
        <div class="action-row">
            <a class="mini-button" href="{{ route('tasks') }}?district={{ $district->code }}&status=open">Барча топшириқлар</a>
        </div>
    </div>
</article>
