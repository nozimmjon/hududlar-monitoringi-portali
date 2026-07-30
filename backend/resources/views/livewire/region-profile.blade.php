<div>
    @if(! $district)
        @include('livewire.profile.empty', ['districtCode' => $districtCode])
    @else
        <div class="dp-crumb"><a href="{{ route('districts') }}"><span class="arr">←</span> Туманлар кесими</a></div>

        <div class="dp-hero">
            <div>
                <h1>{{ $district->name_full }}</h1>
            </div>
            <div class="dp-hero-facts">
                <div class="dp-fact"><div class="v">{{ count($kpiCards) }}</div><div class="k">кўрсаткич</div></div>
                <div class="dp-fact"><div class="v">{{ $taskCounts['total'] }}</div><div class="k">топшириқ</div></div>
                <div class="dp-fact">
                    <div class="v"><span class="warn">{{ $taskCounts['open'] }}</span>/{{ $taskCounts['in_progress'] }}</div>
                    <div class="k">бажарилмаган / жараёнда</div>
                </div>
            </div>
        </div>

        <div class="dp-sect">
            <h2>Асосий кўрсаткичлар</h2>
            <div class="topbar-period" role="group" aria-label="Давр">
                @foreach($periodOptions as $code => $label)
                    <button class="topbar-period__btn {{ $code === $period ? 'active' : '' }}"
                            wire:click="selectPeriod('{{ $code }}')" type="button">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="dp-kpis">
            @forelse($kpiCards as $card)
                <div class="front-kpi">
                    <div class="kpi-icon">@include('partials.icon', ['name' => $card['icon']])</div>
                    <div class="front-kpi-copy">
                        <h3>{{ $card['label'] }}</h3>
                        <strong class="front-kpi-value {{ $card['color'] }}">{{ $card['value'] }}</strong>
                        <span class="front-kpi-note"><span class="chip {{ $card['chip'] }}">{{ $card['tag'] }}</span> {{ $card['note'] }}</span>
                    </div>
                </div>
            @empty
                <p class="muted">Бу давр учун кўрсаткич маълумоти йўқ.</p>
            @endforelse
        </div>

        <div class="dp-sect">
            <h2>Топшириқлар — {{ $tasks->count() }} та</h2>
        </div>

        <div class="dp-tasks">
            @forelse($tasks as $task)
                @php
                    $isMulti = (int) $task->lines_total > 1;
                    $pct = $task->status === 'in_progress' ? null
                        : ($isMulti
                            ? $task->lines_done / $task->lines_total * 100
                            : ($task->headline_pct !== null ? (float) $task->headline_pct : null));
                    $isDone = $task->status === 'done';
                    $pctShown = $pct === null ? null : ($isDone ? (int) round($pct) : min(99, (int) round($pct)));
                    $tierVar = $pct === null ? '--grey'
                        : ($isDone ? '--task-green' : ($pctShown >= 50 ? '--task-amber' : '--task-red'));
                    $chip = $isDone ? ['green', 'Бажарилди']
                        : ($task->status === 'in_progress' ? ['violet', 'Бажарилмоқда'] : ['amber', 'Бажарилмаган']);
                    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
                    $unitLabel = \App\Support\DashboardCatalog::unitLabel($task->headline_unit);
                @endphp
                <div class="dp-task" wire:key="task-{{ $task->id }}">
                    <div class="dp-task-top">
                        <div class="dp-task-title">{{ $task->title }}</div>
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
                    <div class="dp-task-meta">
                        Муддат: <b>{{ $task->deadline_text }}</b>
                        @if($task->status === 'in_progress') · маълумот кутилмоқда @endif
                    </div>
                </div>
            @empty
                <p class="muted">Бу муддат бўйича топшириқ топилмади.</p>
            @endforelse
        </div>
    @endif
</div>
