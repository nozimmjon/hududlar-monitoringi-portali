<?php

namespace Tests\Feature\Schema;

use App\Models\FoodBalance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoodBalanceTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','year','product','product_sort_order',
                 'resource_total','year_start_stock','production','import_volume',
                 'use_total','use_household','use_processing','use_other',
                 'per_capita_norm','per_capita_balance','local_supply_ratio','year_end_stock',
                 'source_label'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('food_balance', $c), "missing column $c");
        }
    }

    public function test_unique_region_year_product(): void
    {
        $this->seed();
        FoodBalance::create([
            'region_code' => 1703, 'year' => 2026, 'product' => 'Ун',
            'production' => 368.3, 'source_label' => 'test',
        ]);
        $this->expectException(QueryException::class);
        FoodBalance::create([
            'region_code' => 1703, 'year' => 2026, 'product' => 'Ун',
            'production' => 999.0, 'source_label' => 'dup',
        ]);
    }
}
