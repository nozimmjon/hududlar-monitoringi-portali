<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\Task;
use App\Support\SectorDisplay;
use Illuminate\Support\Facades\Cache;

class StarterController extends Controller
{
    /** Front door: module chooser with live aggregates per module. */
    public function index()
    {
        return view('pages.start', [
            'regions' => $this->regionsAggregate(),
            'sectors' => $this->sectorsAggregate(),
        ]);
    }

    /**
     * @return array{regions:int,total:int,done:int,pct:?int}|null null = aggregate
     *         unavailable; the card renders without numbers instead of 500ing.
     */
    private function regionsAggregate(): ?array
    {
        try {
            return Cache::remember('starter.regions', 600, function (): array {
                $total = Task::hasPlan()->count();
                // Strict reading: only status='done'. Deliberately diverges from the
                // map page's lenient done+in_progress pills (HomeController::regionStats)
                // — matches the tasks board and the «бажарилди» label.
                $done  = Task::hasPlan()->where('status', 'done')->count();

                return [
                    'regions' => Region::count(),
                    'total'   => $total,
                    'done'    => $done,
                    'pct'     => $total > 0 ? (int) round($done / $total * 100) : null,
                ];
            });
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{sectors:int,tasks:int,lines:int,reported:bool,pct:?int}|null
     */
    private function sectorsAggregate(): ?array
    {
        try {
            return Cache::remember('starter.sectors', 600, function (): array {
                $lines     = (int) SectorTask::sum('lines_total');
                $linesDone = (int) SectorTask::sum('lines_done');
                $reported  = SectorTask::where('status', '!=', 'in_progress')->exists();
                $allDone   = $reported && SectorTask::where('status', '!=', 'done')->doesntExist();

                return [
                    'sectors'  => Sector::count(),
                    'tasks'    => SectorTask::count(),
                    'lines'    => $lines,
                    'reported' => $reported,
                    'pct'      => $reported && $lines > 0
                        ? SectorDisplay::pshow($linesDone / $lines * 100, $allDone)
                        : null,
                ];
            });
        } catch (\Throwable) {
            return null;
        }
    }
}
