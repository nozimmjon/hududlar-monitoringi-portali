<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportingYearSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('reporting_years')->upsert([
            [
                'year' => 2026,
                'is_active' => true,
                'started_at' => '2026-01-01',
                'ended_at' => '2026-12-31',
                'notes' => 'Pilot reporting year — Andijan + 13 regions',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['year'], ['is_active', 'started_at', 'ended_at', 'notes', 'updated_at']);
    }
}
