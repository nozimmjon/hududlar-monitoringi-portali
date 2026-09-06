<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\StarterController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StarterController::class, 'index'])->name('home');
Route::get('/regions', [HomeController::class, 'index'])->name('regions');
Route::get('/region/{code}', [HomeController::class, 'enter'])
    ->whereNumber('code')->name('region.enter');
Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
Route::view('/districts', 'pages.districts')->name('districts');
Route::view('/tasks', 'pages.tasks')->name('tasks');
Route::view('/profile', 'pages.profile')->name('profile');
Route::view('/execution', 'pages.execution')->name('execution');
Route::view('/sectors', 'pages.sectors')->name('sectors');
Route::get('/sectors/{code}', fn (string $code) => view('pages.sector-detail', [
    'sector' => \App\Models\Sector::where('code', $code)->firstOrFail(),
]))->where('code', '[a-z0-9_]+')->name('sectors.detail');
// Direct entry with no region chosen yet: make the first loaded road-map region the
// active one (only some regions are imported so far). An explicitly chosen region is kept.
Route::get('/roadmaps', function () {
    if (! session()->has('region_code')) {
        $code = \App\Models\Roadmap::firstLoadedRegionCode(
            \App\Livewire\RoadmapsPage::DOMAIN,
            \App\Livewire\RoadmapsPage::YEAR,
        );
        if ($code !== null) {
            \App\Support\CurrentRegion::set($code);
        }
    }

    return view('pages.roadmaps');
})->name('roadmaps');
