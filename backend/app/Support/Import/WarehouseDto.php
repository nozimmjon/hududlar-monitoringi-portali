<?php

namespace App\Support\Import;

final readonly class WarehouseDto
{
    public function __construct(
        public string $regionCode,
        public ?string $districtCode,        // NULL = region rollup
        public int $year,
        public ?int $reserveWarehouses,
        public ?int $reserveCapacityT,
        public ?int $coldStorageCount,
        public ?int $coldStorageCapacityT,
        public ?int $newSmallColdCount,
        public ?int $newSmallColdCapacityT,
        public ?int $newSmallColdMfys,
        public ?int $newLargeColdCount,
        public ?int $newLargeColdCapacityT,
        public string $sourceLabel,
    ) {}

    public function toStagingRow(int $importRunId): array
    {
        $now = now();
        return [
            'import_run_id'              => $importRunId,
            'region_code'                => $this->regionCode,
            'district_code'              => $this->districtCode,
            'year'                       => $this->year,
            'reserve_warehouses'         => $this->reserveWarehouses,
            'reserve_capacity_t'         => $this->reserveCapacityT,
            'cold_storage_count'         => $this->coldStorageCount,
            'cold_storage_capacity_t'    => $this->coldStorageCapacityT,
            'new_small_cold_count'       => $this->newSmallColdCount,
            'new_small_cold_capacity_t'  => $this->newSmallColdCapacityT,
            'new_small_cold_mfys'        => $this->newSmallColdMfys,
            'new_large_cold_count'       => $this->newLargeColdCount,
            'new_large_cold_capacity_t'  => $this->newLargeColdCapacityT,
            'source_label'               => $this->sourceLabel,
            'staging_status'             => 'pending',
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ];
    }
}
