<?php

namespace App\Support;

class DistrictTableConfig
{
    public static function for(string $kpi): array
    {
        $configs = self::configs();
        return $configs[$kpi] ?? $configs['export'];
    }

    private static function configs(): array
    {
        $growthCols = fn (string $id) => [
            self::metricCol('I чорак амалда',         $id, 'q1',   'growth', 'fact'),
            self::metricCol('I ярим йиллик прогноз',  $id, 'h1',   'growth', 'plan'),
            self::metricCol('9 ойлик прогноз',        $id, 'm9',   'growth', 'plan'),
            self::metricCol('Йиллик прогноз',         $id, 'year', 'growth', 'plan'),
        ];

        return [
            'grp' => [
                'title'          => 'ЯҲМ таркиби: туманлар кесими',
                'description'    => 'ЯҲМ туман кесимида берилмаган; солиштириш саноат, қишлоқ хўжалиги ва хизматлар ўсиши орқали берилади.',
                'source'         => '1.1-1.5-жадваллар: 1.2, 1.4, 1.5',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('Саноат ўсиши', 'industry', 'h1', 'growth', 'volume'),
                    self::metricCol('ҚХ ўсиши', 'agriculture', 'h1', 'growth', 'volume'),
                    self::metricCol('Хизматлар ўсиши', 'services', 'h1', 'growth', 'volume'),
                    self::fieldCol('Изоҳ'),
                ],
            ],
            'industry' => [
                'title'          => 'Саноат: туманлар кесими',
                'description'    => 'Туманлар бўйича саноат маҳсулотлари ҳажми ва ўсиш суръати.',
                'source'         => '1.2-жадвал',
                'primary_period' => 'h1',
                'columns'        => $growthCols('industry'),
            ],
            'agriculture' => [
                'title'          => 'Қишлоқ хўжалиги: туманлар кесими',
                'description'    => 'Туманлар бўйича қишлоқ хўжалиги маҳсулотлари ҳажми ва ўсиш суръати.',
                'source'         => '1.4-жадвал',
                'primary_period' => 'h1',
                'columns'        => $growthCols('agriculture'),
            ],
            'services' => [
                'title'          => 'Хизматлар: туманлар кесими',
                'description'    => 'Туманлар бўйича бозор хизматлари ҳажми ва ўсиш суръати.',
                'source'         => '1.5-жадвал',
                'primary_period' => 'h1',
                'columns'        => $growthCols('services'),
            ],
            'localization' => [
                'title'          => 'Маҳаллийлаштириш дастури: туманлар кесими',
                'description'    => 'Бу кўрсаткичда I чорак/9 ойлик йўқ; Excelда I ярим йиллик ва йиллик режа берилган.',
                'source'         => '1.3-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 лойиҳа', 'localization', 'h1', 'plan'),
                    self::fieldCol('H1 қиймат'),
                    self::metricCol('Йиллик лойиҳа', 'localization', 'year', 'plan'),
                    self::fieldCol('Йиллик қиймат'),
                ],
            ],
            'energy_electricity' => [
                'title'          => 'Электр энергиясини тежаш: туманлар кесими',
                'description'    => 'Энергия самарадорлиги бўйича I ярим йиллик ва йиллик мақсадли ҳажмлар.',
                'source'         => '1.3-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 тежаш', 'energy_electricity', 'h1', 'plan'),
                    self::metricCol('Йиллик тежаш', 'energy_electricity', 'year', 'plan'),
                ],
            ],
            'energy_gas' => [
                'title'          => 'Табиий газни тежаш: туманлар кесими',
                'description'    => 'Газ тежаш бўйича I ярим йиллик ва йиллик мақсадли ҳажмлар.',
                'source'         => '1.3-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 тежаш', 'energy_gas', 'h1', 'plan'),
                    self::metricCol('Йиллик тежаш', 'energy_gas', 'year', 'plan'),
                ],
            ],
            'inflation' => [
                'title'          => 'Озиқ-овқат захира инфратузилмаси: туманлар кесими',
                'description'    => 'Туманлар бўйича инфляция фоизи берилмаган; шу ерда нарх барқарорлигига хизмат қилувчи омборлар кўрсатилади.',
                'source'         => '2.2-жадвал',
                'primary_period' => 'year',
                'columns'        => [
                    self::fieldCol('Захира омбори'),
                    self::fieldCol('Совутгичли омбор'),
                    self::fieldCol('Янги омбор режаси'),
                    self::metricCol('Жами сиғим', 'inflation', 'year', 'plan'),
                ],
            ],
            'budget' => [
                'title'          => 'Бюджет тушумлари: туманлар кесими',
                'description'    => 'II чорак, I ярим йиллик ва йиллик прогноз/кутилиш алоҳида кўрсатилади.',
                'source'         => '3-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('II чорак ижро', 'budget', 'q2', 'execution', 'fact'),
                    self::metricCol('I ярим йиллик ижро', 'budget', 'h1', 'execution', 'fact'),
                    self::metricCol('Йиллик кутилиш', 'budget', 'year', 'execution', 'fact'),
                ],
            ],
            'budget_investment' => [
                'title'          => 'Бюджет инвестициялари: туманлар кесими',
                'description'    => 'Объектлар, лимит ва ўзлаштириш динамикаси алоҳида кўрсатилади.',
                'source'         => '4.1-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::fieldCol('Объектлар'),
                    self::metricCol('I чорак ўзлаштириш', 'budget_investment', 'q1', 'execution', 'fact'),
                    self::metricCol('H1 ўзлаштириш', 'budget_investment', 'h1', 'execution', 'fact'),
                    self::metricCol('Йиллик ўзлаштириш', 'budget_investment', 'year', 'execution', 'fact'),
                ],
            ],
            'investment' => [
                'title'          => 'Хорижий инвестициялар: туманлар кесими',
                'description'    => 'I чорак факт/режа, I ярим йиллик кутилиш ва йиллик прогноз кесимида.',
                'source'         => '4.2-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('I чорак ижро', 'investment', 'q1', 'execution', 'fact'),
                    self::metricCol('H1 ижро', 'investment', 'h1', 'execution', 'fact'),
                    self::metricCol('Йиллик ижро', 'investment', 'year', 'execution', 'fact'),
                    self::fieldCol('H1 лойиҳа / иш ўрни'),
                ],
            ],
            'export' => [
                'title'          => 'Экспорт: туманлар кесими',
                'description'    => 'Экспорт ҳажми, ўсиш суръати ва экспортчи корхоналар сони.',
                'source'         => '5.1-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('I чорак амалда', 'export', 'q1', 'growth', 'fact'),
                    self::metricCol('H1 кутилиш', 'export', 'h1', 'growth', 'fact'),
                    self::metricCol('Йиллик кутилиш', 'export', 'year', 'growth', 'fact'),
                    self::fieldCol('Экспортчилар'),
                ],
            ],
            'unemployment' => [
                'title'          => 'Ишсизлик даражаси: туманлар кесими',
                'description'    => '6-жадвалда I ярим йиллик ва йиллик мақсадли даражалар берилган.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 мақсад', 'unemployment', 'h1', 'plan'),
                    self::metricCol('Йиллик мақсад', 'unemployment', 'year', 'plan'),
                    self::metricCol('Ишга жойлаштириш H1', 'jobs', 'h1', 'plan'),
                    self::metricCol('Легаллаштириш H1', 'legalization', 'h1', 'plan'),
                ],
            ],
            'poverty' => [
                'title'          => 'Камбағаллик даражаси: туманлар кесими',
                'description'    => 'Камбағаллик даражаси ва унга боғланган драйверлар бир жадвалда.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 камбағаллик', 'poverty', 'h1', 'plan'),
                    self::metricCol('Йиллик камбағаллик', 'poverty', 'year', 'plan'),
                    self::metricCol('Камбағалликдан холи МФЙлар H1', 'mfy_clear', 'h1', 'plan'),
                    self::metricCol('Микролойиҳа H1', 'microprojects', 'h1', 'plan'),
                ],
            ],
            'jobs' => [
                'title'          => 'Доимий ишга жойлаштириш: туманлар кесими',
                'description'    => 'Ишга жойлаштириш мақсадлари I ярим йиллик ва йиллик кесимда.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 ишга жойлаштириш', 'jobs', 'h1', 'plan'),
                    self::metricCol('Йиллик ишга жойлаштириш', 'jobs', 'year', 'plan'),
                    self::metricCol('H1 легаллаштириш', 'legalization', 'h1', 'plan'),
                    self::metricCol('H1 микролойиҳа', 'microprojects', 'h1', 'plan'),
                ],
            ],
            'legalization' => [
                'title'          => 'Норасмий бандларни легаллаштириш: туманлар кесими',
                'description'    => 'Легаллаштириш мақсадлари I ярим йиллик ва йиллик кесимда.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 мақсад', 'legalization', 'h1', 'plan'),
                    self::metricCol('Йиллик мақсад', 'legalization', 'year', 'plan'),
                    self::metricCol('Ишга жойлаштириш H1', 'jobs', 'h1', 'plan'),
                ],
            ],
            'mfy_clear' => [
                'title'          => 'Холи МФЙлар: туманлар кесими',
                'description'    => 'Камбағаллик ва ишсизликдан холи ҳудудга айлантириладиган МФЙлар.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 МФЙ', 'mfy_clear', 'h1', 'plan'),
                    self::metricCol('Йиллик МФЙ', 'mfy_clear', 'year', 'plan'),
                    self::metricCol('Камбағаллик H1', 'poverty', 'h1', 'plan'),
                ],
            ],
            'microprojects' => [
                'title'          => 'Микролойиҳалар: туманлар кесими',
                'description'    => 'Камбағалликни қисқартиришга боғланган микролойиҳалар.',
                'source'         => '6-жадвал',
                'primary_period' => 'h1',
                'columns'        => [
                    self::metricCol('H1 микролойиҳа', 'microprojects', 'h1', 'plan'),
                    self::metricCol('Йиллик микролойиҳа', 'microprojects', 'year', 'plan'),
                    self::metricCol('Ишга жойлаштириш H1', 'jobs', 'h1', 'plan'),
                ],
            ],
        ];
    }

    private static function metricCol(string $label, string $kpi, string $period, string $kind, ?string $note = null): array
    {
        return [
            'label'  => $label,
            'metric' => ['kpi' => $kpi, 'period' => $period, 'kind' => $kind],
            'note'   => $note,
        ];
    }

    private static function fieldCol(string $label): array
    {
        return [
            'label'  => $label,
            'metric' => null,
            'note'   => null,
        ];
    }
}
