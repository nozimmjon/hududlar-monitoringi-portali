@php
    use App\Support\DashboardCatalog;

    $items = [
        ['label' => 'I чорак',  'period' => 'q1',   'kind' => 'actual',   'chip' => 'blue', 'state' => 'Амалда',        'note' => 'амалдаги ўзлаштириш'],
        ['label' => 'II чорак', 'period' => 'h1',   'kind' => 'expected', 'chip' => 'grey', 'state' => 'Кутилиш',        'note' => 'тезкор кутилиш'],
        ['label' => 'III чорак','period' => 'm9',   'kind' => 'missing',  'chip' => 'grey', 'state' => 'Маълумот йўқ',  'note' => '9 ой учун алоҳида маълумот йўқ'],
        ['label' => 'Йиллик',   'period' => 'year', 'kind' => 'expected', 'chip' => 'grey', 'state' => 'Кутилиш',        'note' => 'йил якуни бўйича кутилиш'],
    ];

    $referenceRow = $rows->get('year') ?? $rows->get('h1') ?? $rows->get('q1');
    $limit   = $referenceRow->plan_value ?? null;
    $objects = $referenceRow->count_extra ?? null;
    $unit    = $referenceRow->unit ?? 'млн сўм';

    $yearRow      = $rows->get('year');
    $yearFact     = $yearRow->actual_hokimyat ?? null;
    $yearExec     = $yearRow->pct_of_plan ?? null;

    $points = [];
    $coords = [24, 96, 168, 240];
    foreach ($items as $idx => $item) {
        $row = $rows->get($item['period']);
        $exec = $row->pct_of_plan ?? null;
        if ($exec === null || $item['kind'] === 'missing') continue;
        $clamped = max(0, min((float) $exec, 110));
        $y = 100 - ($clamped / 110) * 78;
        $points[] = [
            'idx'   => $idx,
            'label' => $item['label'],
            'value' => (float) $exec,
            'x'     => $coords[$idx],
            'y'     => $y,
        ];
    }
    $path = '';
    foreach ($points as $i => $p) {
        $path .= ($i ? 'L' : 'M') . ' ' . number_format($p['x'], 1, '.', '') . ' ' . number_format($p['y'], 1, '.', '') . ' ';
    }
    $path = trim($path);
@endphp

<section class="budget-invest-panel" aria-label="Бюджет инвестициялари ўзлаштирилиши">
    <div class="budget-invest-summary">
        <div>
            <span>Йиллик лимит</span>
            <strong>{{ DashboardCatalog::displayMlnSum($limit) }}</strong>
            <small>2026 йил прогноз лимити</small>
        </div>
        <div>
            <span>Объектлар</span>
            <strong>{{ $objects !== null ? DashboardCatalog::fmt($objects, 0) . ' та' : '—' }}</strong>
            <small>жами объектлар</small>
        </div>
        <div>
            <span>Йиллик кутилиш</span>
            <strong>{{ DashboardCatalog::displayMlnSum($yearFact) }}</strong>
            <small>йил якуни бўйича тезкор кутилиш</small>
        </div>
        <div>
            <span>Йиллик ижро</span>
            <strong>{{ $yearExec !== null ? DashboardCatalog::fmt($yearExec, 1) . '%' : '—' }}</strong>
            <small>йиллик лимитга нисбатан</small>
        </div>
    </div>
    <div class="budget-invest-body">
        <div class="budget-periods-grid">
            @foreach($items as $item)
                @php
                    $row = $rows->get($item['period']);
                    $isMissing = $item['kind'] === 'missing';
                    $fact = $row->actual_hokimyat ?? null;
                    $exec = $row->pct_of_plan ?? null;
                    $value = $isMissing
                        ? '—'
                        : ($fact !== null ? DashboardCatalog::displayMlnSum($fact) : '—');
                    $pctText = ($isMissing || $exec === null) ? '—' : DashboardCatalog::fmt($exec, 1) . '%';
                    $progress = ($isMissing || $exec === null) ? 0 : max(0, min((float) $exec, 108));
                    $accent = $isMissing ? 'var(--grey)' : 'var(--blue)';
                @endphp
                <div class="budget-period-card {{ $item['kind'] }}">
                    <div class="budget-period-top">
                        <b>{{ $item['label'] }}</b>
                        <span class="chip {{ $item['chip'] }}">{{ $item['state'] }}</span>
                    </div>
                    <strong>{{ $value }}</strong>
                    <div class="budget-period-meta">
                        <span>йиллик лимитга нисбатан</span>
                        <b>{{ $pctText }}</b>
                    </div>
                    <div class="budget-progress" aria-hidden="true">
                        <i style="--w:{{ number_format($progress, 1, '.', '') }}%;--c:{{ $accent }}"></i>
                    </div>
                    <div class="budget-period-note">{{ $item['note'] }}</div>
                </div>
            @endforeach
        </div>
        <div class="budget-dynamics-card">
            <div class="budget-dynamics-head">
                <strong>Ўзлаштириш динамикаси</strong>
                <span>йиллик лимитга нисбатан</span>
            </div>
            <svg class="budget-dynamics-svg" viewBox="0 0 264 116" role="img" aria-label="Бюджет инвестициялари ўзлаштириш динамикаси">
                <line x1="16" y1="100" x2="250" y2="100" stroke="#dce6f0" stroke-width="1"/>
                <line x1="16" y1="61"  x2="250" y2="61"  stroke="#edf2f7" stroke-width="1"/>
                <line x1="16" y1="22"  x2="250" y2="22"  stroke="#edf2f7" stroke-width="1"/>
                @if($path !== '')
                    <path d="{{ $path }}" fill="none" stroke="var(--blue)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                @endif
                @foreach($points as $p)
                    <circle cx="{{ $p['x'] }}" cy="{{ number_format($p['y'], 1, '.', '') }}" r="4.5" fill="#fff" stroke="var(--blue)" stroke-width="3">
                        <title>{{ $p['label'] }}: {{ DashboardCatalog::fmt($p['value'], 1) }}%</title>
                    </circle>
                @endforeach
            </svg>
            <div class="budget-dynamics-list">
                @foreach($items as $item)
                    @php
                        $row = $rows->get($item['period']);
                        $exec = $row->pct_of_plan ?? null;
                        $hasValue = $exec !== null && $item['kind'] !== 'missing';
                        $pctText = $hasValue ? DashboardCatalog::fmt($exec, 1) . '%' : '—';
                        $width = $hasValue ? max(0, min((float) $exec, 100)) : 0;
                        $color = $item['kind'] === 'missing' ? 'var(--grey)' : 'var(--blue)';
                    @endphp
                    <div>
                        <span>{{ $item['label'] }}</span>
                        <i style="--w:{{ number_format($width, 1, '.', '') }}%;--c:{{ $color }}"></i>
                        <b>{{ $pctText }}</b>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
