<?php

namespace App\Support;

class TaskStatus
{
    /** Binary done/open from a percent-of-plan value. */
    public static function statusFor(?float $pct): string
    {
        return $pct !== null && $pct >= 100.0 ? 'done' : 'open';
    }
}
