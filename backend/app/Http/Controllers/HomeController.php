<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\Task;
use App\Support\CountryMapGeometry;
use App\Support\CurrentRegion;
use App\Support\TaskPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    /** Entry page: country map with per-region task execution status. */
    public function index()
    {
        // Region-level display names: short for the pills, full for tooltips.
        $regions = Region::orderBy('code')->get()->keyBy('code');

        // One pass over all planned tasks; the deadline filter splits them into
        // "everything" vs "due in the first half-year" (bucket h1 = ярим йиллик
        // deadlines incl. Jan–Jun month deadlines).
        $tasks = Task::query()->hasPlan()->get(['region_code', 'status', 'period_code', 'deadline_text']);
        $h1Tasks = $tasks->filter(
            fn ($t) => TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text) === 'h1'
        );

        return view('pages.home', [
            'geometry' => CountryMapGeometry::regions(),
            'stats'    => [
                'all' => self::regionStats($tasks, $regions),
                'h1'  => self::regionStats($h1Tasks, $regions),
            ],
        ]);
    }

    /** @return list<array{code:int,short:string,full:string,total:int,done:int,open:int}> */
    private static function regionStats(Collection $tasks, Collection $regions): array
    {
        $stats = [];
        foreach ($tasks->groupBy('region_code') as $code => $rows) {
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

        return $stats;
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
