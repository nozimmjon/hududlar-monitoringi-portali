<?php

namespace Tests\Feature\Schema;

use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarehousesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','district_code','year',
                 'reserve_warehouses','reserve_capacity_t',
                 'cold_storage_count','cold_storage_capacity_t',
                 'new_small_cold_count','new_small_cold_capacity_t','new_small_cold_mfys',
                 'new_large_cold_count','new_large_cold_capacity_t','source_label'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('warehouses', $c), "missing column $c");
        }
    }

    public function test_district_rollup_via_null_district_code(): void
    {
        $this->seed();
        $row = Warehouse::create([
            'region_code' => 1703, 'district_code' => null, 'year' => 2026,
            'reserve_warehouses' => 89, 'cold_storage_count' => 320,
            'source_label' => 'test',
        ]);
        $this->assertNotNull($row->id);
    }

    public function test_partial_unique_blocks_rollup_duplicates(): void
    {
        $this->seed();
        Warehouse::create([
            'region_code' => 1703, 'district_code' => null, 'year' => 2026,
            'reserve_warehouses' => 89, 'source_label' => 'test',
        ]);

        // Wrap the failing INSERT in a savepoint so the outer RefreshDatabase
        // transaction stays alive after Postgres aborts the inner transaction.
        try {
            DB::transaction(function () {
                Warehouse::create([
                    'region_code' => 1703, 'district_code' => null, 'year' => 2026,
                    'reserve_warehouses' => 99, 'source_label' => 'dup',
                ]);
            });
            $this->fail('expected QueryException for duplicate region-rollup row');
        } catch (QueryException $e) {
            // expected
        }

        $this->assertSame(1,
            Warehouse::where('region_code', 1703)
                ->whereNull('district_code')
                ->where('year', 2026)
                ->count()
        );
    }

    public function test_partial_unique_blocks_district_duplicates(): void
    {
        $this->seed();
        Warehouse::create([
            'region_code' => 1703, 'district_code' => 1703401, 'year' => 2026,
            'reserve_warehouses' => 3, 'source_label' => 'test',
        ]);

        $this->expectException(QueryException::class);
        Warehouse::create([
            'region_code' => 1703, 'district_code' => 1703401, 'year' => 2026,
            'reserve_warehouses' => 9, 'source_label' => 'dup',
        ]);
    }
}
