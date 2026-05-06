<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard route returns 200', function () {
    $this->seed();
    $this->get('/dashboard')->assertStatus(200);
});

test('districts route returns 200', function () {
    $this->seed();
    $this->get('/districts')->assertStatus(200);
});

test('tasks route returns 200', function () {
    $this->seed();
    $this->get('/tasks')->assertStatus(200);
});

test('profile route returns 200 with no district', function () {
    $this->seed();
    $this->get('/profile')->assertStatus(200);
});

test('profile route returns 200 with valid districtCode', function () {
    $this->seed();
    $this->get('/profile?districtCode=andijon_tumani')->assertStatus(200);
});

test('execution route returns 200', function () {
    $this->seed();
    $this->get('/execution')->assertStatus(200);
});

test('root redirect hits dashboard', function () {
    $this->seed();
    $this->get('/')->assertRedirect('/dashboard');
});
