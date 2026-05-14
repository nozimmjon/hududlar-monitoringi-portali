<?php

namespace App\Support;

use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CurrentRegion
{
    public const DEFAULT_CODE = 1703;

    public static function code(): int
    {
        return (int) Session::get('region_code', self::DEFAULT_CODE);
    }

    public static function current(): Region
    {
        return Region::where('code', self::code())->firstOrFail();
    }

    public static function set(int $code): void
    {
        if (! Region::where('code', $code)->exists()) {
            return;
        }
        Session::put('region_code', $code);
    }

    public static function regions(): Collection
    {
        return Region::orderBy('sort_order')->get();
    }
}
