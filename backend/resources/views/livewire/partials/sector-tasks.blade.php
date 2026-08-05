@php
    use App\Support\DashboardCatalog;
    use App\Support\SectorDisplay;

    $aggDone  = $counts['all'] > 0 && $counts['done'] === $counts['all'];
    $aggShown = SectorDisplay::pshow($agg['pct'], $aggDone);
@endphp
<div class="sec-ohead">
    <div class="otop">
        <span class="sec-logo big">
            @if($sector->logoPath())
                <img src="{{ asset($sector->logoPath()) }}" alt="">
            @else
                <span class="sec-mono" aria-hidden="true">{{ mb_substr($sector->cardName(), 0, 1) }}</span>
            @endif
        </span>
        <div class="oid">
            <h2>{{ $sector->cardName() }}</h2>
            <div class="org">{{ $sector->org_full }}</div>
            @if($sector->signer_text)
                <div class="sg">Кафолат хати: {{ $sector->signer_text }}</div>
            @endif
        </div>
        @if($inDrawer)
            <button type="button" class="x" wire:click="closeSector" aria-label="Ёпиш">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        @endif
    </div>
    <div class="sec-ostats">
        <div class="sec-ostat"><div class="v tnum">{{ $aggShown === null ? '—' : $aggShown . '%' }}</div><div class="k">Умумий ижро</div></div>
        <div class="sec-ostat ok"><div class="v tnum">{{ $counts['done'] }}</div><div class="k">Бажарилди</div></div>
        <div class="sec-ostat bad"><div class="v tnum">{{ $counts['open'] }}</div><div class="k">Бажарилмаган</div></div>
        <div class="sec-ostat"><div class="v tnum">{{ $agg['lines_done'] }}/{{ $agg['lines_total'] }}</div><div class="k">Индикатор</div></div>
    </div>
</div>
<div class="sec-obody">
    <div class="sec-otabs" role="tablist">
        @foreach(['all' => 'Барчаси', 'done' => 'Бажарилди', 'open' => 'Бажарилмаган', 'in_progress' => 'Кутилмоқда'] as $key => $label)
            <button type="button" role="tab" class="{{ $filter === $key ? 'on' : '' }}"
                    aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                    wire:click="setFilter(@js($key))">
                {{ $label }}<span class="n tnum">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>
    <div class="sec-otasks">
        @forelse($tasks as $task)
            @php
                $isMulti  = (int) $task->lines_total > 1;
                $isDone   = $task->status === 'done';
                $tpct     = SectorDisplay::taskPct($task);
                $tshown   = SectorDisplay::pshow($tpct, $isDone);
                $ttier    = $task->status === 'in_progress' ? 'wait' : SectorDisplay::tier($tpct);
                $chip     = match ($task->status) {
                    'done'        => ['ok', 'Бажарилди'],
                    'in_progress' => ['wait', 'Кутилмоқда'],
                    default       => ['bad', 'Бажарилмаган'],
                };
                $lines    = $task->progress->where('report_period', $task->latest_period)->sortBy('line_no')->values();
                $headLine = $lines->first();
                $unit     = DashboardCatalog::unitLabel($task->headline_unit);
                $isOpen   = $expanded[$task->id] ?? false;
                // Bar scale is 120% like the prototype; the tick at 83.33% marks 100% of plan.
                $barW     = $tpct === null ? 0 : min(100, $tpct / 120 * 100);
            @endphp
            <article class="sec-task" wire:key="sec-task-{{ $task->id }}">
                <div class="thead">
                    <span class="tno tnum">№{{ $task->task_no }}</span>
                    <div class="ttl">{{ $task->title }}</div>
                    <span class="sec-chip {{ $chip[0] }}"><i></i>{{ $chip[1] }}</span>
                </div>
                <div class="tfacts">
                    @if($isMulti)
                        <span>Индикаторлар: <b class="tnum">{{ $task->lines_done }}/{{ $task->lines_total }}</b></span>
                    @else
                        <span>Режа: <b class="tnum">{{ SectorDisplay::fmt($task->headline_plan) }}</b> {{ $task->headline_plan !== null ? $unit : '' }}</span>
                        <span>Факт: <b class="tnum">{{ SectorDisplay::fmt($task->headline_actual) }}</b></span>
                    @endif
                    <span class="tb"><i style="width:{{ $barW }}%;background:var(--sec-{{ $ttier }})"></i><span class="tick"></span></span>
                    <span><b class="tnum">{{ $tshown === null ? '—' : $tshown . '%' }}</b></span>
                    @if($headLine?->deadline_text)
                        <span class="dl">муддат: {{ $headLine->deadline_text }}</span>
                    @endif
                </div>
                @if($isMulti)
                    <button type="button" class="sec-ltog {{ $isOpen ? 'open' : '' }}" wire:click="toggleTask(@js($task->id))">
                        <span class="c">▸</span> Индикаторлар ({{ $task->lines_total }})
                    </button>
                    @if($isOpen)
                        <div class="sec-lines">
                            @foreach($lines as $line)
                                @php
                                    $lpct      = $line->pct_of_plan !== null ? (float) $line->pct_of_plan : null;
                                    $lineShown = SectorDisplay::pshow($lpct, $lpct !== null && $lpct >= 100);
                                    $lineUnit  = DashboardCatalog::unitLabel($line->unit);
                                @endphp
                                <div class="sec-line {{ $line->actual_value === null ? 'dim' : '' }}" wire:key="sec-line-{{ $line->id }}">
                                    <span class="lb">{{ $line->metric_label }}@if($lineUnit) <span class="u">· {{ $lineUnit }}</span>@endif</span>
                                    <span class="lv tnum"><b>{{ SectorDisplay::fmt($line->actual_value) }}</b> / {{ SectorDisplay::fmt($line->plan_value) }}</span>
                                    <span class="lp tnum {{ $lineShown === null ? 'na' : '' }}">{{ $lineShown === null ? '—' : $lineShown . '%' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </article>
        @empty
            <div class="sec-empty">Бу ҳолатда топшириқ йўқ</div>
        @endforelse
    </div>
</div>
