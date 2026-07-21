@php
    $stats = [
        ['code' => 'jobs',         'icon' => 'briefcase', 'label' => 'Доимий ишга жойлаштириш',         'unit' => 'минг'],
        ['code' => 'legalization', 'icon' => 'users',     'label' => 'Норасмий бандларни легаллаштириш', 'unit' => 'минг'],
    ];
@endphp

<div class="drivers poverty-section employment-driver-section">
    @include('livewire.dashboard.panels.partials.h1-plan-fact', [
        'row'         => $rows->get('h1'),
        'unit'        => '%',
        'lowerBetter' => true,
    ])
    <div class="lagging">
        <div class="poverty-stats">
            @foreach($stats as $s)
                @php
                    $facts = $employmentFacts->get($s['code'], collect());
                    $h1Fact = $facts->firstWhere('period', 'h1');
                    $yearFact = $facts->firstWhere('period', 'year');
                    // H1 promise and fact stay separate — a plan shown where a fact
                    // is expected reads as achievement that never happened.
                    $h1Plan = $h1Fact?->plan_value;
                    $h1Fact_ = $h1Fact?->actual_hokimyat;
                    $h1Val = $h1Fact_ ?? $h1Plan;
                    $yearVal = $yearFact?->plan_value ?? $yearFact?->actual_hokimyat;
                    $pct = ($h1Val !== null && $yearVal !== null && (float) $yearVal != 0)
                        ? max(0, min(100, ((float) $h1Val / (float) $yearVal) * 100))
                        : 0;
                @endphp
                <article class="poverty-stat">
                    <div class="poverty-stat-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => $s['icon']])
                    </div>
                    <div class="poverty-stat-body">
                        <span class="poverty-stat-label">{{ $s['label'] }}</span>
                        <strong class="poverty-stat-value">{{ \App\Support\DashboardCatalog::fmt($yearVal, 1) }}<em>{{ $s['unit'] }}</em></strong>
                        <div class="poverty-stat-meta">
                            <span>II чорак режа <b>{{ \App\Support\DashboardCatalog::fmt($h1Plan, 1) }}</b></span>
                            <span class="poverty-stat-divider">·</span>
                            <span>амалда <b>{{ $h1Fact_ !== null ? \App\Support\DashboardCatalog::fmt($h1Fact_, 1) : '—' }}</b></span>
                        </div>
                        <div class="poverty-progress" role="progressbar" aria-valuenow="{{ (int) $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <i style="width:{{ number_format($pct, 1, '.', '') }}%"></i>
                        </div>
                        <small class="poverty-progress-label">II чорак йиллик мақсаднинг {{ (int) $pct }}%</small>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</div>
