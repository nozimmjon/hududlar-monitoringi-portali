@php
    use App\Support\DashboardCatalog;

    // The monitoring tasks are the source for the half-year/annual horizons: they
    // carry the period plan and, once reported, the actual ўзлаштириш. A period
    // reads Амалда only with a reported figure; otherwise it shows the promise
    // (Режа) — a forecast is never presented as achievement.
    $targets = $periodTargets ?? [];
    $items = [];
    foreach ([
        ['label' => 'I чорак',   'period' => 'q1',   'note' => 'амалдаги ўзлаштириш'],
        ['label' => 'II чорак',  'period' => 'h1',   'note' => 'ярим йиллик ўзлаштириш'],
        ['label' => 'III чорак', 'period' => 'm9',   'note' => '9 ой учун алоҳида маълумот йўқ', 'missing' => true],
        ['label' => 'Йиллик',    'period' => 'year', 'note' => 'йил якуни бўйича режа'],
    ] as $item) {
        $row    = $rows->get($item['period']);
        $target = $targets[$item['period']] ?? null;

        if ($item['missing'] ?? false) {
            $kind = 'missing';
        } elseif ($target !== null) {
            $kind = $target['actual'] !== null ? 'actual' : ($target['plan'] !== null ? 'plan' : 'empty');
        } else {
            $kind = DashboardCatalog::periodSourceKind('budget_investment', $item['period'], $row);
        }

        $items[] = $item + [
            'kind'  => match ($kind) {
                'actual'           => 'actual',
                'missing', 'empty' => 'missing',
                default            => 'expected',
            },
            'chip'  => $kind === 'actual' ? 'blue' : 'grey',
            'state' => match ($kind) {
                'actual'           => 'Амалда',
                'expected'         => 'Кутилиш',
                'plan'             => 'Режа',
                'missing', 'empty' => 'Маълумот йўқ',
                default            => 'Режа',
            },
            'target' => $target,
        ];
    }

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
                    $target = $item['target'] ?? null;
                    // A reported actual leads the card; a period still ahead of us
                    // shows its promise instead of a forecast dressed up as a fact.
                    $headline = $target !== null
                        ? ($target['actual'] ?? $target['plan'])
                        : ($row->actual_hokimyat ?? null);
                    $exec = $row->pct_of_plan ?? null;
                    $value = ($isMissing || $headline === null)
                        ? '—'
                        : DashboardCatalog::displayMlnSum($headline);
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
                    @if($item['kind'] === 'actual' && ($target['plan'] ?? null) !== null)
                        {{-- Promise beside the fact. Plan-only cards already show the
                             plan as their headline, so repeating it would read as two
                             rival figures. --}}
                        <div class="budget-period-meta">
                            <span>давр режаси</span>
                            <b>{{ DashboardCatalog::displayMlnSum($target['plan']) }}</b>
                        </div>
                    @endif
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
