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
                <article class="task-card">
                    <p>{{ $task->title }}</p>
                    <footer>
                        <span>{{ $task->deadline_text }}</span>
                    </footer>
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
