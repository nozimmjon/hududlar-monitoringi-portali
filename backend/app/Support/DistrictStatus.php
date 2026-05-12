<?php

namespace App\Support;

class DistrictStatus
{
    public const GREEN = 'green';
    public const AMBER = 'amber';
    public const RED   = 'red';
    public const GREY  = 'grey';

    /**
     * Direction-aware status thresholds.
     *
     * Higher-is-better: >=95 green, >=80 amber, else red.
     * Lower-is-better:  <=100 green, <=110 amber, else red.
     *
     * Falls back to growth_pct if pct_of_plan is null. Both null -> grey.
     */
    public static function statusFor(?float $pctOfPlan, ?float $growth, bool $lowerIsBetter): string
    {
        $value = $pctOfPlan ?? $growth;
        if ($value === null) {
            return self::GREY;
        }

        if ($lowerIsBetter) {
            if ($value <= 100) return self::GREEN;
            if ($value <= 110) return self::AMBER;
            return self::RED;
        }

        if ($value >= 95) return self::GREEN;
        if ($value >= 80) return self::AMBER;
        return self::RED;
    }
}
