<?php

namespace App\Support;

class DashboardCatalog
{
    public const PERIODS = ['q1', 'h1', 'm9', 'year'];

    public const PERIOD_LABELS = [
        'q1'   => 'I чорак',
        'h1'   => 'II чорак',
        'm9'   => 'III чорак',
        'year' => 'Йиллик',
    ];

    public const LOWER_BETTER = ['inflation', 'poverty', 'unemployment'];

    public const MACRO_GROWTH_KPIS = ['grp', 'industry', 'agriculture', 'construction', 'services'];

    public const MODULE_ICONS = [
        'macro'          => 'trend',
        'inflation'      => 'price',
        'budget'         => 'bank',
        'budget_invest'  => 'briefcase',
        'foreign_invest' => 'globe',
        'export'         => 'rocket',
        'employment'     => 'users',
    ];

    public const MODULES = [
        'macro' => [
            'label' => '1. Макроиқтисодиёт',
            'intro' => 'ЯҲМ ва асосий тармоқлар кўрсаткичлари',
            'kpis'  => ['grp', 'industry', 'agriculture', 'construction', 'services'],
            'has_front_cards' => true,
            'layout_class' => 'macro-layout',
        ],
        'inflation' => [
            'label' => '2. Инфляция',
            'intro' => 'Инфляция чегараси, озиқ-овқат баланси ва омборлар.',
            'kpis'  => ['inflation'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'budget' => [
            'label' => '3. Бюджет',
            'intro' => 'Бюджет тушумлари бўйича режа ва ижро.',
            'kpis'  => ['budget'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'budget_invest' => [
            'label' => '4. Бюджет инвестициялари',
            'intro' => 'Бюджет инвестициялари ўзлаштирилиши.',
            'kpis'  => ['budget_investment'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'foreign_invest' => [
            'label' => '5. Хорижий инвестициялар',
            'intro' => 'Хорижий инвестициялар ҳажми, режа ва ижро.',
            'kpis'  => ['investment'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'export' => [
            'label' => '6. Экспорт',
            'intro' => 'Экспорт ҳажми ва ўсиш кўрсаткичлари.',
            'kpis'  => ['export'],
            'has_front_cards' => false,
            'layout_class' => '',
        ],
        'employment' => [
            'label' => '7. Бандлик ва камбағаллик',
            'intro' => 'Ишсизлик, камбағаллик ва кичик тадбиркорлик бўйича асосий KPIлар.',
            'kpis'  => ['unemployment', 'poverty', 'jobs', 'legalization', 'mfy_clear', 'microprojects'],
            'has_front_cards' => true,
            'layout_class' => 'employment-layout',
        ],
    ];

    /**
     * Modules whose `tasks.indicator_code` is populated. For other modules
     * tasks land with indicator_code = NULL, and the scoreline must skip the
     * indicator_code predicate.
     */
    public const MODULES_WITH_INDICATOR_TASKS = ['macro', 'employment'];

    public const INFLATION_PRICE_CAPS = [
        ['name' => 'Гўшт ва гўшт маҳсулотлари', 'icon' => 'meat',         'cap' => '6–7%дан ошмаслик'],
        ['name' => 'Тухум',                      'icon' => 'egg',          'cap' => '5–6%дан ошмаслик'],
        ['name' => 'Сут ва сут маҳсулотлари',    'icon' => 'milk_bottle',  'cap' => '6–7%дан ошмаслик'],
        ['name' => 'Картошка',                   'icon' => 'potato',       'cap' => '4–5%дан ошмаслик'],
        ['name' => 'Пиёз',                       'icon' => 'onion',        'cap' => '5%дан ошмаслик'],
        ['name' => 'Сабзи',                      'icon' => 'carrot',       'cap' => '5%дан ошмаслик'],
        ['name' => 'Гуруч',                      'icon' => 'rice',         'cap' => '2025 йил даражасида'],
        ['name' => 'Ун',                         'icon' => 'flour',        'cap' => '2025 йил даражасида'],
    ];

    public const INFLATION_LIMITS = [
        ['period' => 'II чорак',   'cap' => '≤2,9%', 'note' => 'амалдаги инфляцияга нисбатан'],
        ['period' => 'Йил якуни',  'cap' => '≤6,6%', 'note' => 'йил якуни бўйича чегара'],
    ];

    public const INDUSTRY_DRIVERS = [
        'localization_h1_projects'    => 122,
        'localization_h1_value_mln'   => 3864542.138295122,
        'localization_year_projects'  => 124,
        'localization_year_value_mln' => 12445307.572824338,
        'energy_electricity_h1'       => 81.40672334972915,
        'energy_gas_h1'               => 22.847122793194615,
        'energy_electricity_year'     => 177.62126910265005,
        'energy_gas_year'             => 53.119454870439036,
    ];

    public static function modules(): array
    {
        return self::MODULES;
    }

    public static function moduleCodes(): array
    {
        return array_keys(self::MODULES);
    }

    public static function module(string $code): ?array
    {
        return self::MODULES[$code] ?? null;
    }

    public static function moduleLabel(string $code): string
    {
        return self::MODULES[$code]['label'] ?? '';
    }

    public static function moduleIntro(string $code): string
    {
        return self::MODULES[$code]['intro'] ?? '';
    }

    public static function moduleKpis(string $code): array
    {
        return self::MODULES[$code]['kpis'] ?? [];
    }

    public static function hasFrontCards(string $code): bool
    {
        return (bool) (self::MODULES[$code]['has_front_cards'] ?? false);
    }

    public static function layoutClass(string $code): string
    {
        return self::MODULES[$code]['layout_class'] ?? '';
    }

    public static function firstKpiForModule(string $code): string
    {
        return self::MODULES[$code]['kpis'][0] ?? 'grp';
    }

    public static function moduleForKpi(string $kpi): string
    {
        foreach (self::MODULES as $code => $module) {
            if (in_array($kpi, $module['kpis'], true)) {
                return $code;
            }
        }
        return 'macro';
    }

    public static function isLowerBetter(string $kpi): bool
    {
        return in_array($kpi, self::LOWER_BETTER, true);
    }

    public static function isMacroGrowthKpi(string $kpi): bool
    {
        return in_array($kpi, self::MACRO_GROWTH_KPIS, true);
    }

    public static function moduleIcon(string $code): string
    {
        return self::MODULE_ICONS[$code] ?? 'trend';
    }

    public static function periodLabel(string $period): string
    {
        return self::PERIOD_LABELS[$period] ?? $period;
    }

    public static function periodSourceKind(string $kpi, string $period, ?object $row): string
    {
        // A reported hokimyat actual wins for any period — H1/year actuals arrive
        // via the tasks workbook (TaskFactBridge) and replace the Кутилиш forecast.
        if ($row && ($row->actual_hokimyat !== null || $row->actual_statistika !== null)) {
            return 'actual';
        }
        if ($period === 'q1' && $row && $row->growth_pct !== null) {
            return 'actual';
        }
        if (in_array($kpi, ['budget', 'budget_investment', 'investment', 'export'], true)
            && $period !== 'q1'
            && $row
            && ($row->expected_value !== null || $row->pct_of_plan !== null)) {
            return 'expected';
        }
        if (in_array($kpi, ['inflation', 'unemployment', 'poverty', 'small_business_share'], true)) {
            return 'target';
        }
        return ($row && self::hasPeriodValue($row)) ? 'plan' : 'empty';
    }

    public static function hasPeriodValue(object $row): bool
    {
        return $row->plan_value !== null
            || $row->actual_hokimyat !== null
            || $row->actual_statistika !== null
            || $row->growth_pct !== null
            || $row->pct_of_plan !== null
            || $row->expected_value !== null;
    }

    /**
     * Returns ['cls' => actual|planned|empty, 'chip' => '', 'label' => '']
     */
    public static function periodState(string $kpi, string $period, ?object $row): array
    {
        $kind = self::periodSourceKind($kpi, $period, $row);
        if ($kind === 'actual') {
            return ['cls' => 'actual', 'chip' => '', 'label' => ''];
        }
        if ($period === 'q1') {
            return ['cls' => 'empty', 'chip' => 'grey', 'label' => 'I чорак белгиланмаган'];
        }
        return match ($kind) {
            'expected' => ['cls' => 'planned', 'chip' => 'grey', 'label' => 'Кутилиш'],
            'target'   => ['cls' => 'planned', 'chip' => 'grey', 'label' => 'Маълумот кутилмоқда'],
            'plan'     => ['cls' => 'planned', 'chip' => 'grey', 'label' => 'Режа'],
            default    => ['cls' => 'empty',   'chip' => 'grey', 'label' => 'Давр белгиланмаган'],
        };
    }

    public static function planLabel(string $kpi, string $period, ?object $row): string
    {
        return self::periodSourceKind($kpi, $period, $row) === 'target' ? 'Режа (мақсад)' : 'Режа';
    }

    public static function factLabel(string $kpi, string $period, ?object $row): string
    {
        return self::periodSourceKind($kpi, $period, $row) === 'expected' ? 'Кутилиш' : 'Амалда';
    }

    public static function executionLabel(string $kpi, string $period, ?object $row): string
    {
        $kind = self::periodSourceKind($kpi, $period, $row);
        if ($kind === 'target') return 'Мақсад';
        if ($kind === 'expected') return 'Кутилган ижро';
        if (! $row || $row->pct_of_plan === null) return 'Кўрсаткич';
        if ($kind === 'actual') return 'Ижро';
        return 'Кутилган ижро';
    }

    /**
     * Format growth percent the way index.html growthValue() does.
     * Values >50 are treated as ratios (105.5 → "+5,5%"), small values as deltas (5.5 → "+5,5%").
     */
    public static function growthValue($value): string
    {
        if ($value === null) return '—';
        $num = (float) $value;
        $delta = abs($num) > 50 ? $num - 100 : $num;
        $sign = $delta >= 0 ? '+' : '';
        return $sign . self::fmt($delta, 1) . '%';
    }

    /**
     * Display-side number formatter. Uses comma decimal + space thousands.
     * Rounds to $digits, then drops trailing zeros after the comma so 8,0 → 8
     * and 8,10 → 8,1. Negative sign and integer part are preserved.
     */
    public static function fmt($value, int $digits = 1): string
    {
        if ($value === null) return '—';
        $num = (float) $value;
        $rounded = round($num, $digits);
        $hasFraction = abs($rounded - (int) $rounded) > 0;
        if (! $hasFraction || $digits <= 0) {
            return number_format($rounded, 0, ',', ' ');
        }
        $str = number_format($rounded, $digits, ',', ' ');
        $str = rtrim($str, '0');
        return rtrim($str, ',');
    }

    public static function displayMlnSum($value): string
    {
        if ($value === null) return '—';
        return self::fmt(((float) $value) / 1000, 1) . ' млрд сўм';
    }

    /**
     * Mirrors index.html displayValue(): unit-aware scaling for currency, percent,
     * minimal pretty-printing for other units. Returns '—' on null/non-numeric input.
     */
    public static function displayValue($value, string $unit = '', bool $compact = true): string
    {
        if ($value === null || ! is_numeric($value)) return '—';
        $num = (float) $value;

        if (str_contains($unit, 'минг доллар')) {
            return self::fmt($num / 1000, $compact ? 1 : 2) . ' млн $';
        }
        if (str_contains($unit, 'млн доллар')) {
            if ($compact && abs($num) >= 1000) return self::fmt($num / 1000, 1) . ' млрд $';
            return self::fmt($num, $compact ? 1 : 2) . ' млн $';
        }
        if (str_contains($unit, 'млрд сўм')) {
            if ($compact && abs($num) >= 1000) return self::fmt($num / 1000, 1) . ' трлн сўм';
            return self::fmt($num, 1) . ' млрд сўм';
        }
        if (str_contains($unit, 'млн сўм')) {
            return self::fmt($num / 1000, 1) . ' млрд сўм';
        }
        if (str_contains($unit, '%')) {
            return self::fmt($num, 1) . '%';
        }
        if (str_contains($unit, 'минг нафар')) {
            return self::fmt($num, 1) . ' минг';
        }
        if ($unit === 'count') {
            return self::fmt($num, 0) . ' та';
        }
        return trim(self::fmt($num, $compact ? 1 : 2) . ' ' . $unit);
    }

    /**
     * Maps a Cyrillic food name to a Phosphor icon name (see partials/icon.blade.php).
     * Best-effort: returns a specific icon where a close Phosphor equivalent exists,
     * 'basket' as the generic fallback otherwise.
     */
    public static function foodIcon(string $name): string
    {
        $lower = mb_strtolower($name);
        $patterns = [
            '/мол\s*гўшт|қорамол/u'               => 'cow',
            '/қўй\s*гўшт|қўзи|эчки/u'             => 'sheep',
            '/гўшт/u'                             => 'meat',
            '/тухум/u'                            => 'egg',
            '/сут/u'                              => 'milk',
            '/картошка/u'                         => 'potato',
            '/пиёз/u'                             => 'onion',
            '/сабзи/u'                            => 'carrot',
            '/қалампир/u'                         => 'pepper',
            '/балиқ/u'                            => 'fish',
            '/сабзавот/u'                         => 'leaf',
            '/мева|узум|лимон|тарвуз|қовун|олма/u' => 'orange',
            '/гуруч/u'                            => 'bowl-food',
            '/нон|бўғдой/u'                       => 'bread',
            '/ун/u'                               => 'grains',
            '/мой|ёғ|маска/u'                     => 'drop',
        ];
        foreach ($patterns as $pattern => $icon) {
            if (preg_match($pattern, $lower)) return $icon;
        }
        return 'basket';
    }

    /**
     * Build the 3 industry-driver cards. When `$facts` is non-empty (grouped by
     * indicator_code, each containing rows for h1 + year), values come from DB.
     * Otherwise falls back to INDUSTRY_DRIVERS hardcoded snapshot from index.html.
     */
    public static function industryDrivers($facts = null): array
    {
        if ($facts && method_exists($facts, 'isNotEmpty') && $facts->isNotEmpty()) {
            return self::industryDriversFromFacts($facts);
        }
        $d = self::INDUSTRY_DRIVERS;
        return [
            [
                'id'       => 'localization',
                'cls'      => 'green',
                'icon'     => 'factory',
                'title'    => 'Маҳаллийлаштириш',
                'desc'     => 'Лойиҳалар сони ва қиймати',
                'h1'       => self::fmt($d['localization_h1_projects'], 0) . ' та',
                'h1Note'   => self::displayMlnSum($d['localization_h1_value_mln']),
                'year'     => self::fmt($d['localization_year_projects'], 0) . ' та',
                'yearNote' => self::displayMlnSum($d['localization_year_value_mln']),
            ],
            [
                'id'       => 'energy_electricity',
                'cls'      => 'blue',
                'icon'     => 'lightning',
                'title'    => 'Электр тежаш',
                'desc'     => 'Тежаладиган электр энергияси',
                'h1'       => self::fmt($d['energy_electricity_h1'], 1) . ' млн кВт·соат',
                'h1Note'   => '',
                'year'     => self::fmt($d['energy_electricity_year'], 1) . ' млн кВт·соат',
                'yearNote' => '',
            ],
            [
                'id'       => 'energy_gas',
                'cls'      => 'orange',
                'icon'     => 'flame',
                'title'    => 'Газ тежаш',
                'desc'     => 'Тежаладиган табиий газ',
                'h1'       => self::fmt($d['energy_gas_h1'], 1) . ' млн м³',
                'h1Note'   => '',
                'year'     => self::fmt($d['energy_gas_year'], 1) . ' млн м³',
                'yearNote' => '',
            ],
        ];
    }

    private static function industryDriversFromFacts($facts): array
    {
        $pick = function (string $code, string $period, string $field) use ($facts) {
            $rows = $facts->get($code);
            if (! $rows) return null;
            $row = $rows->firstWhere('period', $period);
            if (! $row) return null;
            return $row->{$field} ?? null;
        };

        $locH1Cnt   = $pick('localization', 'h1',   'count_extra');
        $locH1Val   = $pick('localization', 'h1',   'expected_value');
        $locYrCnt   = $pick('localization', 'year', 'count_extra');
        $locYrVal   = $pick('localization', 'year', 'expected_value');
        $elH1       = $pick('energy_electricity', 'h1',   'expected_value');
        $elYr       = $pick('energy_electricity', 'year', 'expected_value');
        $gasH1      = $pick('energy_gas',         'h1',   'expected_value');
        $gasYr      = $pick('energy_gas',         'year', 'expected_value');

        $cnt = fn ($v) => $v === null ? '—' : self::fmt((float) $v, 0) . ' та';
        $kwh = fn ($v) => $v === null ? '—' : self::fmt((float) $v, 1) . ' млн кВт·соат';
        $m3  = fn ($v) => $v === null ? '—' : self::fmt((float) $v, 1) . ' млн м³';

        return [
            [
                'id'       => 'localization',
                'cls'      => 'green',
                'icon'     => 'factory',
                'title'    => 'Маҳаллийлаштириш',
                'desc'     => 'Лойиҳалар сони ва қиймати',
                'h1'       => $cnt($locH1Cnt),
                'h1Note'   => self::displayMlnSum($locH1Val),
                'year'     => $cnt($locYrCnt),
                'yearNote' => self::displayMlnSum($locYrVal),
            ],
            [
                'id'       => 'energy_electricity',
                'cls'      => 'blue',
                'icon'     => 'lightning',
                'title'    => 'Электр тежаш',
                'desc'     => 'Тежаладиган электр энергияси',
                'h1'       => $kwh($elH1),
                'h1Note'   => '',
                'year'     => $kwh($elYr),
                'yearNote' => '',
            ],
            [
                'id'       => 'energy_gas',
                'cls'      => 'orange',
                'icon'     => 'flame',
                'title'    => 'Газ тежаш',
                'desc'     => 'Тежаладиган табиий газ',
                'h1'       => $m3($gasH1),
                'h1Note'   => '',
                'year'     => $m3($gasYr),
                'yearNote' => '',
            ],
        ];
    }
}
