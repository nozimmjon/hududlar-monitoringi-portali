<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\Task;
use App\Support\CountryMapGeometry;
use App\Support\CurrentRegion;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    /** Entry page: country map with per-region task execution status. */
    public function index()
    {
        // Region-level display names: short for the pills, full for tooltips.
        $regions = Region::orderBy('code')->get()->keyBy('code');

        // One pass over all planned tasks; group counts per region in PHP.
        $tasks = Task::query()->hasPlan()->get(['region_code', 'status']);
        $byRegion = $tasks->groupBy('region_code');

        $stats = [];
        foreach ($byRegion as $code => $rows) {
            $region = $regions->get($code);
            if ($region === null) {
                continue;
            }
            $total = $rows->count();
            $done  = $rows->where('status', 'done')->count();
            $stats[] = [
                'code'  => (int) $code,
                'short' => self::shortLabel($region),
                'full'  => $region->name_full,
                'total' => $total,
                'done'  => $done,
                'open'  => $total - $done,
            ];
        }
        usort($stats, fn ($a, $b) => $a['code'] <=> $b['code']);

        $republic = [
            'total' => array_sum(array_column($stats, 'total')),
            'done'  => array_sum(array_column($stats, 'done')),
            'open'  => array_sum(array_column($stats, 'open')),
        ];

        return view('pages.home', [
            'geometry' => CountryMapGeometry::regions(),
            'stats'    => $stats,
            'republic' => $republic,
        ]);
    }

    /** Region click: remember the region in the session, open its dashboard. */
    public function enter(int $code): RedirectResponse
    {
        CurrentRegion::set($code);

        return redirect()->route('dashboard');
    }

    /** Disambiguate the two Tashkents; otherwise the seeded short name. */
    private static function shortLabel(Region $region): string
    {
        return match ((int) $region->code) {
            1726    => 'Тошкент ш.',
            1727    => 'Тошкент вил.',
            default => $region->name_short ?? $region->name_full,
        };
    }
}
