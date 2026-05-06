<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoteImportRunCommand extends Command
{
    protected $signature = 'import:promote {run_id : The ID of the ImportRun to promote}';
    protected $description = 'Promote staged rows from an import run into the production fact tables.';

    public function handle(): int
    {
        $runId = (int) $this->argument('run_id');

        $run = ImportRun::find($runId);
        if (! $run) {
            $this->error("ImportRun #{$runId} not found.");
            return 1;
        }

        if ($run->status !== ImportRunStatus::AwaitingReview) {
            $this->error("Run #{$runId} is '{$run->status->value}', not 'awaiting_review'. Aborting.");
            return 1;
        }

        $run->update(['status' => ImportRunStatus::Promoting]);

        $factCount = $foodCount = $warehouseCount = 0;

        DB::transaction(function () use ($runId, &$factCount, &$foodCount, &$warehouseCount) {
            $factCount      = $this->promoteIndicatorFacts($runId);
            $foodCount      = $this->promoteFoodBalance($runId);
            $warehouseCount = $this->promoteWarehouses($runId);
        });

        $totalPromoted = $factCount + $foodCount + $warehouseCount;

        $run->update([
            'status'        => ImportRunStatus::Promoted,
            'promoted_at'   => now(),
            'rows_promoted' => $totalPromoted,
        ]);

        $this->info("Promoted run #{$runId}: {$factCount} indicator facts, {$foodCount} food balance rows, {$warehouseCount} warehouse rows.");

        return 0;
    }

    private function promoteIndicatorFacts(int $runId): int
    {
        $cols = 'region_code, district_code, year, indicator_code, period,
                 plan_value, expected_value, actual_hokimyat, actual_statkom,
                 growth_pct, pct_of_plan, count_extra, count_extra_2,
                 is_sentinel, sentinel_label, unit, source_label,
                 hokimyat_reported_at, statkom_published_at';

        $updateSet = 'plan_value = EXCLUDED.plan_value,
                      expected_value = EXCLUDED.expected_value,
                      actual_hokimyat = EXCLUDED.actual_hokimyat,
                      actual_statkom = EXCLUDED.actual_statkom,
                      growth_pct = EXCLUDED.growth_pct,
                      pct_of_plan = EXCLUDED.pct_of_plan,
                      count_extra = EXCLUDED.count_extra,
                      count_extra_2 = EXCLUDED.count_extra_2,
                      is_sentinel = EXCLUDED.is_sentinel,
                      sentinel_label = EXCLUDED.sentinel_label,
                      unit = EXCLUDED.unit,
                      source_label = EXCLUDED.source_label,
                      hokimyat_reported_at = EXCLUDED.hokimyat_reported_at,
                      statkom_published_at = EXCLUDED.statkom_published_at,
                      updated_at = now()';

        // District rows — partial index uq_indicator_facts_district
        DB::statement("
            INSERT INTO indicator_facts ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_indicator_facts
            WHERE import_run_id = ? AND district_code IS NOT NULL
            ON CONFLICT (region_code, district_code, year, indicator_code, period)
            WHERE district_code IS NOT NULL
            DO UPDATE SET {$updateSet}
        ", [$runId]);

        // Rollup rows — partial index uq_indicator_facts_rollup
        DB::statement("
            INSERT INTO indicator_facts ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_indicator_facts
            WHERE import_run_id = ? AND district_code IS NULL
            ON CONFLICT (region_code, year, indicator_code, period)
            WHERE district_code IS NULL
            DO UPDATE SET {$updateSet}
        ", [$runId]);

        return DB::table('import_staging_indicator_facts')
            ->where('import_run_id', $runId)
            ->count();
    }

    private function promoteFoodBalance(int $runId): int
    {
        $cols = 'region_code, year, product, product_sort_order,
                 resource_total, year_start_stock, production, import_volume,
                 use_total, use_household, use_processing, use_other,
                 per_capita_norm, per_capita_balance, local_supply_ratio, year_end_stock,
                 source_label';

        DB::statement("
            INSERT INTO food_balance ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_food_balance
            WHERE import_run_id = ?
            ON CONFLICT (region_code, year, product)
            DO UPDATE SET
                product_sort_order = EXCLUDED.product_sort_order,
                resource_total     = EXCLUDED.resource_total,
                year_start_stock   = EXCLUDED.year_start_stock,
                production         = EXCLUDED.production,
                import_volume      = EXCLUDED.import_volume,
                use_total          = EXCLUDED.use_total,
                use_household      = EXCLUDED.use_household,
                use_processing     = EXCLUDED.use_processing,
                use_other          = EXCLUDED.use_other,
                per_capita_norm    = EXCLUDED.per_capita_norm,
                per_capita_balance = EXCLUDED.per_capita_balance,
                local_supply_ratio = EXCLUDED.local_supply_ratio,
                year_end_stock     = EXCLUDED.year_end_stock,
                source_label       = EXCLUDED.source_label,
                updated_at         = now()
        ", [$runId]);

        return DB::table('import_staging_food_balance')
            ->where('import_run_id', $runId)
            ->count();
    }

    private function promoteWarehouses(int $runId): int
    {
        $cols = 'region_code, district_code, year,
                 reserve_warehouses, reserve_capacity_t,
                 cold_storage_count, cold_storage_capacity_t,
                 new_small_cold_count, new_small_cold_capacity_t, new_small_cold_mfys,
                 new_large_cold_count, new_large_cold_capacity_t,
                 source_label';

        $updateSet = 'reserve_warehouses        = EXCLUDED.reserve_warehouses,
                      reserve_capacity_t        = EXCLUDED.reserve_capacity_t,
                      cold_storage_count        = EXCLUDED.cold_storage_count,
                      cold_storage_capacity_t   = EXCLUDED.cold_storage_capacity_t,
                      new_small_cold_count      = EXCLUDED.new_small_cold_count,
                      new_small_cold_capacity_t = EXCLUDED.new_small_cold_capacity_t,
                      new_small_cold_mfys       = EXCLUDED.new_small_cold_mfys,
                      new_large_cold_count      = EXCLUDED.new_large_cold_count,
                      new_large_cold_capacity_t = EXCLUDED.new_large_cold_capacity_t,
                      source_label              = EXCLUDED.source_label,
                      updated_at                = now()';

        // District rows — partial index uq_warehouses_district
        DB::statement("
            INSERT INTO warehouses ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_warehouses
            WHERE import_run_id = ? AND district_code IS NOT NULL
            ON CONFLICT (region_code, district_code, year)
            WHERE district_code IS NOT NULL
            DO UPDATE SET {$updateSet}
        ", [$runId]);

        // Rollup rows — partial index uq_warehouses_rollup
        DB::statement("
            INSERT INTO warehouses ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_warehouses
            WHERE import_run_id = ? AND district_code IS NULL
            ON CONFLICT (region_code, year)
            WHERE district_code IS NULL
            DO UPDATE SET {$updateSet}
        ", [$runId]);

        return DB::table('import_staging_warehouses')
            ->where('import_run_id', $runId)
            ->count();
    }
}
