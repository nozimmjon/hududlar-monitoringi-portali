<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['code' => 'macro',          'label' => 'Макро иқтисодиёт',          'sort_order' => 10],
            ['code' => 'inflation',      'label' => 'Инфляция ва озиқ-овқат баланси', 'sort_order' => 20],
            ['code' => 'budget',         'label' => 'Бюджет тушумлари',          'sort_order' => 30],
            ['code' => 'budget_invest',  'label' => 'Бюджет инвестициялари',     'sort_order' => 40],
            ['code' => 'foreign_invest', 'label' => 'Хорижий инвестициялар',     'sort_order' => 50],
            ['code' => 'export',         'label' => 'Экспорт',                   'sort_order' => 60],
            ['code' => 'employment',     'label' => 'Бандлик ва камбағаллик',    'sort_order' => 70],
        ];
        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        DB::table('modules')->upsert($rows, ['code'], ['label', 'sort_order', 'updated_at']);
    }
}
