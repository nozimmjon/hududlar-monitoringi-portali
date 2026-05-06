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

    public const MODULES = [
        'macro' => [
            'label' => '1. Макроиқтисодиёт',
            'intro' => 'ЯҲМ ва асосий таркибий кўрсаткичлар',
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

    public static function periodLabel(string $period): string
    {
        return self::PERIOD_LABELS[$period] ?? $period;
    }
}
