<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * One-time cleanup: import:promote historically left staging rows behind after
     * copying them into the production tables, so staging held a full duplicate of
     * every promoted run. The command now purges staging on promote; this removes
     * the leftovers from runs promoted before that change.
     */
    public function up(): void
    {
        $stagingTables = [
            'import_staging_indicator_facts',
            'import_staging_food_balance',
            'import_staging_warehouses',
        ];

        foreach ($stagingTables as $table) {
            DB::statement("
                DELETE FROM {$table} s
                USING import_runs r
                WHERE s.import_run_id = r.id
                  AND r.status = 'promoted'
            ");
        }
    }

    public function down(): void
    {
        // Data cleanup is not reversible; the source workbooks can be re-imported.
    }
};
