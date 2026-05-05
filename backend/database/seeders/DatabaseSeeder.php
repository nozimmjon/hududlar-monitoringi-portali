<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ReportingYearSeeder::class,
            ModuleSeeder::class,
            RegionSeeder::class,
            DistrictSeeder::class,
            IndicatorSeeder::class,
        ]);
    }
}
