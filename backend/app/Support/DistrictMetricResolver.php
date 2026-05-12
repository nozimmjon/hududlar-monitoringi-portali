<?php

namespace App\Support;

use App\Models\IndicatorFact;

class DistrictMetricResolver
{
    public static function value(?IndicatorFact $row, string $kind): string
    {
        if ($row === null) return '—';

        $raw = match ($kind) {
            'growth'    => $row->growth_pct,
            'execution' => $row->pct_of_plan,
            'plan'      => $row->plan_value,
            'fact'      => $row->actual_hokimyat ?? $row->actual_statkom,
            default     => null,
        };

        if ($raw === null) return '—';

        return match ($kind) {
            'growth', 'execution' => self::pct($raw),
            'plan', 'fact'        => self::number($raw, $row->unit ?? ''),
            default               => '—',
        };
    }

    public static function note(?IndicatorFact $row, ?string $kind): string
    {
        if ($row === null || $kind === null) return '';
        $unit = $row->unit ?? '';
        return match ($kind) {
            'fact'   => 'факт ' . self::number($row->actual_hokimyat ?? $row->actual_statkom, $unit),
            'plan'   => 'режа ' . self::number($row->plan_value, $unit),
            'volume' => 'ҳажм ' . self::number($row->plan_value, $unit),
            default  => '',
        };
    }

    public static function status(?IndicatorFact $row, bool $lowerIsBetter): string
    {
        return DistrictStatus::statusFor(
            $row?->pct_of_plan !== null ? (float) $row->pct_of_plan : null,
            $row?->growth_pct !== null ? (float) $row->growth_pct : null,
            $lowerIsBetter,
        );
    }

    private static function pct($v): string
    {
        $f = (float) $v;
        $sign = $f >= 0 ? '+' : '';
        return $sign . number_format($f, 1, ',', ' ') . '%';
    }

    private static function number($v, string $unit): string
    {
        if ($v === null) return '—';
        $s = number_format((float) $v, 1, ',', ' ');
        return $unit === '' ? $s : "{$s} {$unit}";
    }
}
