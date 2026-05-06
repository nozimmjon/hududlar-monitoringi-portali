<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
Route::view('/districts', 'pages.districts')->name('districts');
Route::view('/tasks', 'pages.tasks')->name('tasks');
Route::view('/profile', 'pages.profile')->name('profile');
Route::view('/execution', 'pages.execution')->name('execution');
