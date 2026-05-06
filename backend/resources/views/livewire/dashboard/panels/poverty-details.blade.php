@php
    $stats = [
        ['code' => 'jobs',          'icon' => 'users',   'label' => 'Доимий ишга жойлаштириш',         'unit' => 'минг'],
        ['code' => 'legalization',  'icon' => 'globe',   'label' => 'Норасмий бандларни легаллаштириш', 'unit' => 'минг'],
        ['code' => 'mfy_clear',     'icon' => 'bank',    'label' => 'Камбағалликдан холи МФЙлар',       'unit' => 'та'],
        ['code' => 'microprojects', 'icon' => 'rocket',  'label' => 'Микролойиҳалар',                   'unit' => 'та'],
    ];
@endphp

<div class="drivers poverty-section">
    <div class="lagging">
        <header class="poverty-head">
            <div>
                <strong>Камбағалликни камайтириш драйверлари</strong>
                <p>Камбағаллик KPIсига олиб борувчи асосий чора-тадбирлар.</p>
            </div>
            <a class="mini-button primary"
               href="{{ route('districts') }}?indicatorCode=poverty">Туманлар кесими →</a>
        </header>
        <div class="poverty-stats">
            @foreach($stats as $s)
                @php
                    $facts = $employmentFacts->get($s['code'], collect());
                    $h1Fact = $facts->firstWhere('period', 'h1');
                    $yearFact = $facts->firstWhere('period', 'year');
                    $h1Val = $h1Fact?->actual_hokimyat ?? $h1Fact?->plan_value;
                    $yearVal = $yearFact?->plan_value ?? $yearFact?->actual_hokimyat;
                    $digits = in_array($s['unit'], ['та'], true) ? 0 : 1;
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
                        <strong class="poverty-stat-value">{{ $yearVal !== null ? number_format((float) $yearVal, $digits) : '—' }}<em>{{ $s['unit'] }}</em></strong>
                        <div class="poverty-stat-meta">
                            <span>II чорак <b>{{ $h1Val !== null ? number_format((float) $h1Val, $digits) : '—' }}</b></span>
                            <span class="poverty-stat-divider">·</span>
                            <span>Йиллик мақсад</span>
                        </div>
                        <div class="poverty-progress" role="progressbar" aria-valuenow="{{ (int) $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <i style="width:{{ number_format($pct, 1) }}%"></i>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    @if($clearDistricts->isNotEmpty())
        <div class="composition">
            <div class="lagging-title"><strong>Камбағалликдан холи туманлар</strong></div>
            <div class="composition-grid">
                @foreach($clearDistricts as $d)
                    <a class="component-card product-card"
                       href="{{ route('profile') }}?districtCode={{ $d->code }}">
                        <span class="product-body">
                            <span class="product-name">{{ $d->name_short ?? $d->code }}</span>
                            <small class="product-note">холи туман</small>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <p class="finance-source">Манба: 6-жадвал ва кафолат хати.</p>
</div>
