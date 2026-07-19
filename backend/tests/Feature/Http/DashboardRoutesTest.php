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
    $this->get('/profile?districtCode=1703203')->assertStatus(200);
});

test('execution route returns 200', function () {
    $this->seed();
    $this->get('/execution')->assertStatus(200);
});

test('root renders the entry map landing page', function () {
    $this->seed();
    $this->get('/')->assertOk();
});

test('dashboard with explicit module and kpi returns 200', function () {
    $this->seed();
    $this->get('/dashboard?module=inflation&kpi=inflation')->assertStatus(200);
});

test('dashboard inflation panel renders price caps', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=inflation&kpi=inflation');
    $response->assertStatus(200);
    $response->assertSee('Инфляция чегаралари', false);
    $response->assertSee('Тухум', false);
});

test('dashboard macro module renders the period row', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=macro');
    $response->assertStatus(200);
    $response->assertSee('macro-period-row', false);
});

test('dashboard employment module renders front cards layout', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=employment&kpi=poverty');
    $response->assertStatus(200);
    $response->assertSee('module-kpis employment-layout', false);
});

test('dashboard renders scoreline strip', function () {
    $this->seed();
    $response = $this->get('/dashboard');
    $response->assertStatus(200);
    $response->assertSee('scoreline execution-strip', false);
});

test('dashboard module tabs render all 7 modules', function () {
    $this->seed();
    $response = $this->get('/dashboard');
    $response->assertStatus(200);
    $response->assertSee('Макроиқтисодиёт', false);
    $response->assertSee('Инфляция', false);
    $response->assertSee('Бюджет', false);
    $response->assertSee('Хорижий инвестициялар', false);
    $response->assertSee('Экспорт', false);
    $response->assertSee('Бандлик', false);
});

test('dashboard macro module wraps content in a module card', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=macro');
    $response->assertStatus(200);
    $response->assertSee('class="module-card"', false);
});

test('dashboard non-macro module is wrapped in a module card', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=budget');
    $response->assertStatus(200);
    $response->assertSee('class="module-card"', false);
});

test('non-macro module renders the scoreline strip', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=budget');
    $response->assertStatus(200);
    $response->assertSee('scoreline execution-strip', false);
});
