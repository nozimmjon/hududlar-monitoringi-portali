@php
    use App\Support\DashboardCatalog;
    use App\Support\SectorDisplay;

    $aggDone   = $counts['all'] > 0 && $counts['done'] === $counts['all'];
    $aggShown  = SectorDisplay::pshow($agg['pct'], $aggDone);
    $ringTier  = SectorDisplay::tier($agg['pct']);
    $circ      = 282.7; // 2π × r45
    $ringPct   = $agg['pct'] ?? 0;
@endphp
<div class="sec-shell sec-detail">
    <header class="sec-top on">
        <span class="sec-pline" style="--p:{{ min(1, $ringPct / 120) }}"></span>
        <div class="sec-tin">
            <a class="sec-back" href="{{ route('sectors') }}">← Барча корхоналар</a>
            <h1>{{ $sector->cardName() }} — топшириқлар</h1>
            <span class="sp"></span>
        </div>
    </header>

    <div class="sec-body">
        <aside class="sec-rail">
            <div class="sec-kcard sec-idcard">
                <span class="sec-logo big">
                    @if($sector->logoPath())
                        <img src="{{ asset($sector->logoPath()) }}" alt="">
                    @else
                        <span class="sec-mono" aria-hidden="true">{{ mb_substr($sector->cardName(), 0, 1) }}</span>
                    @endif
                </span>
                <h2>{{ $sector->cardName() }}</h2>
                <div class="org">{{ $sector->org_full }}</div>
            </div>

            <div class="sec-kcard hero">
                <div class="kt">Умумий ижро</div>
                <div class="sec-ringwrap">
                    <div class="sec-ring">
                        <svg viewBox="0 0 104 104">
                            <circle class="tr" cx="52" cy="52" r="45"/>
                            <circle class="fg" cx="52" cy="52" r="45"
                                    style="stroke:var(--sec-{{ $ringTier }});stroke-dasharray:{{ $circ }};stroke-dashoffset:{{ number_format($circ * (1 - min(1, $ringPct / 100)), 1, '.', '') }}"/>
                        </svg>
                        <div class="cv"><b class="tnum">{{ $aggShown === null ? '—' : $aggShown . '%' }}</b><span>индикатор</span></div>
                    </div>
                    <div class="sec-hstats">
                        <div>
                            <div class="hv tnum">{{ $agg['lines_done'] }}<small>/{{ $agg['lines_total'] }}</small></div>
                            <div class="hk">Индикатор</div>
                        </div>
                        <div>
                            <div class="hv tnum">{{ $counts['done'] }}<small>/{{ $counts['all'] }}</small></div>
                            <div class="hk">Топшириқ</div>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="sec-kcard sec-fbtns" aria-label="Ҳолат бўйича фильтр">
                @foreach(['all' => 'Барчаси', 'done' => 'Бажарилди', 'open' => 'Бажарилмаган', 'in_progress' => 'Кутилмоқда'] as $key => $label)
                    <button type="button" class="f-{{ $key }} {{ $filter === $key ? 'on' : '' }}"
                            aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                            wire:click="setFilter(@js($key))">
                        <i></i>{{ $label }}<span class="n tnum">{{ $counts[$key] }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        <main class="sec-main">
            <div class="sec-dtasks">
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
        </main>
    </div>
</div>
