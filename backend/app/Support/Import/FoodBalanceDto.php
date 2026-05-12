<?php

namespace App\Support\Import;

final readonly class FoodBalanceDto
{
    public function __construct(
        public int $regionCode,
        public int $year,
        public string $product,
        public int $productSortOrder,
        public ?float $resourceTotal,
        public ?float $yearStartStock,
        public ?float $production,
        public ?float $importVolume,
        public ?float $useTotal,
        public ?float $useHousehold,
        public ?float $useProcessing,
        public ?float $useOther,
        public ?float $perCapitaNorm,
        public ?float $perCapitaBalance,
        public ?float $localSupplyRatio,
        public ?float $yearEndStock,
        public string $sourceLabel,
    ) {}

    public function toStagingRow(int $importRunId): array
    {
        $now = now();
        return [
            'import_run_id'      => $importRunId,
            'region_code'        => $this->regionCode,
            'year'               => $this->year,
            'product'            => $this->product,
            'product_sort_order' => $this->productSortOrder,
            'resource_total'     => $this->resourceTotal,
            'year_start_stock'   => $this->yearStartStock,
            'production'         => $this->production,
            'import_volume'      => $this->importVolume,
            'use_total'          => $this->useTotal,
            'use_household'      => $this->useHousehold,
            'use_processing'     => $this->useProcessing,
            'use_other'          => $this->useOther,
            'per_capita_norm'    => $this->perCapitaNorm,
            'per_capita_balance' => $this->perCapitaBalance,
            'local_supply_ratio' => $this->localSupplyRatio,
            'year_end_stock'     => $this->yearEndStock,
            'source_label'       => $this->sourceLabel,
            'staging_status'     => 'pending',
            'created_at'         => $now,
            'updated_at'         => $now,
        ];
    }
}
