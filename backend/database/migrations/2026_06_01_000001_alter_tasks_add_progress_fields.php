<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('cadence', 16)->nullable()->after('period_code');          // monthly | quarterly
            $table->text('data_source')->nullable()->after('cadence');                // col I
            $table->text('report_schedule_text')->nullable()->after('data_source');   // col J (raw)
            $table->string('integration_status', 64)->nullable()->after('report_schedule_text'); // col L
            $table->text('mechanism_text')->nullable()->after('integration_status');  // col K
            // Denormalized latest-period headline snapshot (recomputed on every import)
            $table->string('latest_period', 16)->nullable()->after('mechanism_text');
            $table->string('headline_unit', 48)->nullable()->after('latest_period');
            $table->decimal('headline_plan', 20, 6)->nullable()->after('headline_unit');
            $table->decimal('headline_actual', 20, 6)->nullable()->after('headline_plan');
            $table->decimal('headline_pct', 10, 4)->nullable()->after('headline_actual');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'cadence', 'data_source', 'report_schedule_text', 'integration_status',
                'mechanism_text', 'latest_period', 'headline_unit', 'headline_plan',
                'headline_actual', 'headline_pct',
            ]);
        });
    }
};
