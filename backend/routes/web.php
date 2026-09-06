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
Route::view('/roadmaps', 'pages.roadmaps')->name('roadmaps');
