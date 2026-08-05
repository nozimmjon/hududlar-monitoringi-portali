@php use App\Support\SectorDisplay; @endphp
<div class="sec-shell" wire:keydown.escape.window="closeSector">
    @php
        $allDone   = $summary['tasks'] > 0 && $summary['done'] === $summary['tasks'];
        $ringShown = SectorDisplay::pshow($summary['pct'], $allDone) ?? 0;
        $ringTier  = SectorDisplay::tier($summary['pct']);
        $circ      = 282.7; // 2π × r45
    @endphp

    <header class="sec-top" id="secTop" style="--p:{{ min(1, $summary['pct'] / 120) }}">
        <span class="sec-pline" title="Умумий ижро"></span>
        <div class="sec-tin">
            <a class="sec-back" href="{{ route('home') }}">← Бош саҳифа</a>
            <h1>Тармоқ корхоналари топшириқлари</h1>
            <span class="sp"></span>
            <span class="sec-search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Излаш…" autocomplete="off">
            </span>
        </div>
    </header>

    <div class="sec-body">
        <aside class="sec-rail">
            <div class="sec-kcard hero rise" id="secHero">
                <div class="kt">Умумий ижро</div>
                <div class="sec-ringwrap">
                    <div class="sec-ring">
                        <svg viewBox="0 0 104 104">
                            <circle class="tr" cx="52" cy="52" r="45"/>
                            <circle class="fg" id="secRingFg" cx="52" cy="52" r="45"
                                    style="stroke:var(--sec-{{ $ringTier }});stroke-dasharray:{{ $circ }};stroke-dashoffset:{{ number_format($circ * (1 - min(1, $summary['pct'] / 100)), 1, '.', '') }}"/>
                        </svg>
                        <div class="cv"><b class="tnum" id="secRingVal" data-p="{{ $ringShown }}">{{ $ringShown }}%</b><span>индикатор</span></div>
                    </div>
                    <div class="sec-hstats">
                        <div>
                            <div class="hv tnum">{{ $summary['lines_done'] }}<small>/{{ $summary['lines_total'] }}</small></div>
                            <div class="hk">Индикатор</div>
                        </div>
                        <div>
                            <div class="hv tnum">{{ $summary['done'] }}<small>/{{ $summary['tasks'] }}</small></div>
                            <div class="hk">Топшириқ</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sec-kcard rise">
                <div class="kt">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>
                    Ижро рейтинги
                </div>
                <div class="sec-pstrip" title="Барча корхоналар — тартиб бўйича">
                    @foreach($cards as $c)
                        @php $pt = SectorDisplay::tier($c['pct']); @endphp
                        <span role="button" tabindex="0" wire:key="ps-{{ $c['sector']->code }}"
                              wire:click="openSector(@js($c['sector']->code))"
                              style="background:var(--sec-{{ $pt }})"
                              title="{{ $c['sector']->cardName() }}: {{ $c['pct'] === null ? 'кутилмоқда' : SectorDisplay::pshow($c['pct'], $c['tasks_total'] > 0 && $c['done'] === $c['tasks_total']) . '%' }}"></span>
                    @endforeach
                </div>
                @if($ranked->isNotEmpty())
                    <div class="lsec up">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>
                        Энг юқори
                    </div>
                    @foreach($ranked->take(3) as $k => $r)
                        <div class="sec-lrow" role="button" tabindex="0" wire:key="top-{{ $r['sector']->code }}"
                             wire:click="openSector(@js($r['sector']->code))">
                            <span class="rk tnum">{{ str_pad($k + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="ln">{{ $r['sector']->cardName() }}</span>
                            <span class="lp tnum" style="color:var(--sec-{{ SectorDisplay::tier($r['pct']) }})">{{ SectorDisplay::pshow($r['pct'], $r['tasks_total'] > 0 && $r['done'] === $r['tasks_total']) }}%</span>
                        </div>
                    @endforeach
                    <div class="ldiv"></div>
                    <div class="lsec down">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l6 6 4-4 8 8"/><path d="M15 17h6v-6"/></svg>
                        Энг паст
                    </div>
                    @php $n = $ranked->count(); @endphp
                    @foreach($ranked->slice(max(0, $n - 3))->reverse()->values() as $k => $r)
                        <div class="sec-lrow" role="button" tabindex="0" wire:key="low-{{ $r['sector']->code }}"
                             wire:click="openSector(@js($r['sector']->code))">
                            <span class="rk tnum">{{ str_pad($n - $k, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="ln">{{ $r['sector']->cardName() }}</span>
                            <span class="lp tnum" style="color:var(--sec-{{ SectorDisplay::tier($r['pct']) }})">{{ SectorDisplay::pshow($r['pct'], $r['tasks_total'] > 0 && $r['done'] === $r['tasks_total']) }}%</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </aside>

        <main class="sec-main">
            <div class="sec-grid">
                @forelse($visible as $card)
                    @php
                        $s        = $card['sector'];
                        $tier     = SectorDisplay::tier($card['pct']);
                        $cardDone = $card['tasks_total'] > 0 && $card['done'] === $card['tasks_total'];
                        $shown    = SectorDisplay::pshow($card['pct'], $cardDone);
                        $allWait  = $card['tasks_total'] > 0 && $card['waiting'] === $card['tasks_total'];
                    @endphp
                    <button type="button" class="sec-card {{ $allWait ? 'waitc' : '' }}" data-code="{{ $s->code }}"
                            wire:key="sector-{{ $s->code }}" wire:click="openSector(@js($s->code))"
                            style="--tc:var(--sec-{{ $tier }})">
                        <div class="chd">
                            <span class="cidx tnum">{{ str_pad($card['idx'], 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="sec-logo">
                                @if($card['logo'])
                                    <img src="{{ asset($card['logo']) }}" alt="" loading="lazy">
                                @else
                                    <span class="sec-mono" aria-hidden="true">{{ mb_substr($s->cardName(), 0, 1) }}</span>
                                @endif
                            </span>
                            <span class="cname"><span class="nm {{ mb_strlen($s->cardName()) > 12 ? 'long' : '' }}">{{ $s->cardName() }}</span></span>
                        </div>
                        <div class="cmeasure">
                            <div class="cbig">
                                <div class="cpct tnum {{ $shown === null ? 'na' : '' }}">@if($shown === null)—@else{{ $shown }}<span class="u">%</span>@endif</div>
                            </div>
                            <div class="cstat">
                                <svg class="ic" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11.5 2.5 2.5L17 8.5"/><path d="M21 12v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10"/></svg>
                                <div class="v tnum">{{ $card['done'] }}<small>/{{ $card['tasks_total'] }}</small></div>
                                <div class="k">Топшириқ</div>
                            </div>
                            <div class="cstat">
                                <svg class="ic" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="6" y1="20" x2="6" y2="15"/><line x1="12" y1="20" x2="12" y2="9"/><line x1="18" y1="20" x2="18" y2="4"/></svg>
                                <div class="v tnum">{{ $card['lines_done'] }}<small>/{{ $card['lines_total'] }}</small></div>
                                <div class="k">Индикатор</div>
                            </div>
                        </div>
                        @if($allWait)
                            <div class="waitnote">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                                Ҳисобот кутилмоқда
                            </div>
                        @else
                            <div class="cstrip-zone">
                                <div class="cstrip">
                                    @foreach($card['strip'] as $t)
                                        @php
                                            $st = $t['status'] === 'done' ? 'ok' : ($t['status'] === 'in_progress' ? 'wait' : SectorDisplay::tier($t['pct']));
                                            $tp = SectorDisplay::pshow($t['pct'], $t['status'] === 'done');
                                        @endphp
                                        <span style="background:var(--sec-{{ $st }})"
                                              title="Т-{{ str_pad($t['no'], 2, '0', STR_PAD_LEFT) }}: {{ $tp === null ? 'кутилмоқда' : $tp . '%' }}"></span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="cfoot">
                            <span class="dlx">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></svg>
                                {{ $card['deadline'] }}
                            </span>
                            <span class="more">Батафсил →</span>
                        </div>
                    </button>
                @empty
                    <div class="sec-empty">Ҳеч нарса топилмади</div>
                @endforelse
            </div>
        </main>
    </div>

    <div class="sec-veil {{ $open ? 'on' : '' }}" wire:click="closeSector"></div>
    <aside class="sec-over {{ $open ? 'on' : '' }}" role="dialog" aria-modal="true"
           @if($drawer) style="border-left-color:var(--sec-{{ SectorDisplay::tier($drawer['agg']['pct']) }})" @endif>
        @if($drawer)
            @include('livewire.partials.sector-tasks', [
                'sector'   => $drawer['sector'],
                'tasks'    => $drawer['tasks'],
                'counts'   => $drawer['counts'],
                'agg'      => $drawer['agg'],
                'filter'   => $filter,
                'expanded' => $expanded,
                'inDrawer' => true,
            ])
        @endif
    </aside>
</div>
