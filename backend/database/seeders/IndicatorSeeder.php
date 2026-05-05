<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndicatorSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $allPeriods = json_encode(['q1','h1','m9','year']);
        $yearOnly   = json_encode(['year']);
        $h1Year     = json_encode(['h1','year']);

        $rows = [
            // code, label_full, label_short, sector, module, scope, unit, lower, periods, growth, pct_plan, sentinel, ce, ce2, icon, sort
            ['grp',                  'ЯҲМ',                                            'ЯҲМ',                          'Макро иқтисодиёт',     'macro',          'region',   'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'trend',     10],
            ['industry',             'Саноат маҳсулотлари',                             'Саноат',                      'Макро иқтисодиёт',     'macro',          'both',     'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'factory',   20],
            ['agriculture',          'Қишлоқ хўжалиги маҳсулотлари',                    'Қишлоқ хўжалиги',             'Макро иқтисодиёт',     'macro',          'both',     'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'trend',     30],
            ['construction',         'Қурилиш ишлари',                                  'Қурилиш',                     'Макро иқтисодиёт',     'macro',          'region',   'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'bank',      40],
            ['services',             'Бозор хизматлари',                                'Хизматлар',                   'Макро иқтисодиёт',     'macro',          'both',     'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'globe',     50],
            ['inflation',            'Инфляция ва асосий озиқ-овқат нархлари',           'Инфляция',                    'Инфляция',             'inflation',      'region',   '%',           true,  $allPeriods, false, false, false, null,                           null,           'price',     60],
            ['budget',               'Бюджет тушумлари',                                'Бюджет',                      'Бюджет',               'budget',         'both',     'млрд сўм',    false, $allPeriods, false, true,  false, null,                           null,           'bank',      70],
            ['budget_investment',    'Бюджет инвестициялари ўзлаштирилиши',             'Бюджет инвест',               'Бюджет инвестициялари','budget_invest',  'both',     'млн сўм',     false, $allPeriods, false, true,  false, 'Объектлар сони',                'Ишга туширилаётган объектлар', 'bank',      80],
            ['investment',           'Хорижий инвестициялар',                          'Инвестиция',                  'Хорижий инвестиция',   'foreign_invest', 'both',     'млн доллар',  false, $allPeriods, false, true,  false, 'Лойиҳалар сони',                'Иш ўринлари', 'rocket',    90],
            ['export',               'Экспорт ҳажми',                                  'Экспорт',                     'Экспорт',              'export',         'both',     'минг доллар', false, $allPeriods, true,  false, false, 'Экспортчи корхоналар сони',     null,           'globe',    100],
            ['unemployment',         'Ишсизлик даражаси',                              'Ишсизлик',                    'Бандлик ва камбағаллик','employment',     'both',     '%',           true,  $h1Year,     false, false, false, null,                           null,           'users',    110],
            ['poverty',              'Камбағаллик даражаси',                           'Камбағаллик',                 'Бандлик ва камбағаллик','employment',     'both',     '%',           true,  $h1Year,     false, false, true,  null,                           null,           'users',    120],
            ['small_business_share', 'Кичик тадбиркорликнинг ЯҲМдаги улуши',           'Кичик бизнес улуши',          'Бандлик ва камбағаллик','macro',          'region',   '%',           false, $yearOnly,   false, false, false, null,                           null,           'briefcase', 130],
            ['localization',         'Маҳаллийлаштириш дастури',                       'Маҳаллийлаштириш',            'Саноат',               'macro',          'district', 'млн сўм',     false, $h1Year,     false, false, false, 'Лойиҳалар сони',                null,           'factory',  140],
            ['energy_electricity',   'Электр энергиясини тежаш',                       'Электр тежаш',                'Саноат',               'macro',          'district', 'млн кВт·с',   false, $h1Year,     false, false, false, null,                           null,           'trend',    150],
            ['energy_gas',           'Табиий газни тежаш',                             'Газ тежаш',                   'Саноат',               'macro',          'district', 'млн м³',      false, $h1Year,     false, false, false, null,                           null,           'trend',    160],
            ['jobs',                 'Доимий ишга жойлаштириш',                        'Ишга жойлаштириш',            'Бандлик ва камбағаллик','employment',     'district', 'минг нафар',  false, $h1Year,     false, false, false, null,                           null,           'users',    170],
            ['legalization',         'Норасмий бандларни легаллаштириш',               'Легаллаштириш',               'Бандлик ва камбағаллик','employment',     'district', 'минг нафар',  false, $h1Year,     false, false, false, null,                           null,           'users',    180],
            ['mfy_clear',            'Камбағаллик ва ишсизликдан холи МФЙлар',         'Камбағалликдан холи МФЙлар',  'Бандлик ва камбағаллик','employment',     'district', 'count',       false, $h1Year,     false, false, false, null,                           null,           'users',    190],
            ['microprojects',        'Микролойиҳалар',                                 'Микролойиҳа',                 'Бандлик ва камбағаллик','employment',     'district', 'count',       false, $h1Year,     false, false, false, null,                           null,           'users',    200],
        ];

        $records = [];
        foreach ($rows as $r) {
            $records[] = [
                'code'                => $r[0],
                'label_full'          => $r[1],
                'label_short'         => $r[2],
                'sector'              => $r[3],
                'module_code'         => $r[4],
                'scope'               => $r[5],
                'default_unit'        => $r[6],
                'lower_is_better'     => $r[7],
                'supported_periods'   => $r[8],
                'has_growth_pct'      => $r[9],
                'has_pct_of_plan'     => $r[10],
                'has_sentinel'        => $r[11],
                'count_extra_label'   => $r[12],
                'count_extra_2_label' => $r[13],
                'icon'                => $r[14],
                'sort_order'          => $r[15],
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        DB::table('indicators')->upsert(
            $records,
            ['code'],
            ['label_full','label_short','sector','module_code','scope','default_unit',
             'lower_is_better','supported_periods','has_growth_pct','has_pct_of_plan',
             'has_sentinel','count_extra_label','count_extra_2_label','icon','sort_order','updated_at']
        );

        $this->command->info('Seeded ' . count($records) . ' indicators.');
    }
}
