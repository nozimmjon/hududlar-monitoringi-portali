<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

function authTestUser(): User
{
    return User::factory()->create(['name' => 'Админ', 'email' => 'admin@example.test', 'password' => 'secret-123']);
}

test('the login page renders for guests and sends a logged-in user to the panel', function () {
    $this->get('/login')->assertOk()->assertSee('Бошқарув маркази')->assertSee('Электрон почта')->assertSee('Парол');
    $this->actingAs(authTestUser())->get('/login')->assertRedirect('/admin/roadmaps');
});

test('valid credentials log in and land on the admin overview; the e-mail is case-insensitive', function () {
    authTestUser();

    $this->post('/login', ['email' => 'Admin@Example.test', 'password' => 'secret-123'])->assertRedirect('/admin/roadmaps');
    $this->assertAuthenticated();
});

test('wrong credentials stay on the form with the message', function () {
    authTestUser();

    $this->from('/login')->post('/login', ['email' => 'admin@example.test', 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Почта ёки парол нотўғри.']);
    $this->assertGuest();
});

test('the sixth attempt within a minute is throttled even with the right password', function () {
    authTestUser();
    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['email' => 'admin@example.test', 'password' => 'nope']);
    }

    $this->from('/login')->post('/login', ['email' => 'admin@example.test', 'password' => 'secret-123'])
        ->assertRedirect('/login')
        ->assertInvalid(['email' => 'Жуда кўп уриниш']);
    $this->assertGuest();
});

test('logout ends the session', function () {
    $this->actingAs(authTestUser())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

test('admin routes redirect guests to the login page', function () {
    $this->get('/admin/roadmaps')->assertRedirect('/login');
    $this->get('/admin/roadmaps/1733')->assertRedirect('/login');
});

test('the sidebar shows Админ and Чиқиш only to a logged-in user', function () {
    $this->seed();

    $this->get('/roadmaps')->assertOk()->assertDontSee('Чиқиш')->assertDontSee('href="' . route('admin.roadmaps') . '"', false);
    $this->actingAs(authTestUser())->get('/roadmaps')->assertOk()->assertSee('Чиқиш')->assertSee('href="' . route('admin.roadmaps') . '"', false);
});

test('admin:create creates the user, then resets its password; bad input is refused', function () {
    $this->artisan('admin:create', ['email' => 'Boss@Example.test', '--name' => 'Бош', '--password' => 'first-pass-1'])
        ->expectsOutputToContain('Админ яратилди')->assertExitCode(0);
    $user = User::where('email', 'boss@example.test')->firstOrFail();
    expect($user->name)->toBe('Бош');
    expect(Hash::check('first-pass-1', $user->password))->toBeTrue();

    $this->artisan('admin:create', ['email' => 'boss@example.test', '--password' => 'second-pass-2'])
        ->expectsOutputToContain('Парол янгиланди')->assertExitCode(0);
    expect(Hash::check('second-pass-2', $user->fresh()->password))->toBeTrue();
    expect(User::count())->toBe(1);

    $this->artisan('admin:create', ['email' => 'not-an-email', '--password' => 'whatever-12'])->assertExitCode(1);
    $this->artisan('admin:create', ['email' => 'x@example.test', '--password' => 'short'])->assertExitCode(1);
    expect(User::count())->toBe(1);
});

test('admin:create rotates the remember-me token when it resets a password', function () {
    $user = User::factory()->create(['email' => 'boss@example.test', 'remember_token' => 'old-token']);

    $this->artisan('admin:create', ['email' => 'boss@example.test', '--password' => 'second-pass-2'])->assertExitCode(0);

    expect($user->fresh()->remember_token)->not->toBe('old-token');
});

test('a logged-in user gets the admin pages themselves', function () {
    $this->seed();
    AdminFixture::roadmap();   // the entry page 404s for a region without a road map
    $this->actingAs(authTestUser());

    $this->get('/admin/roadmaps')->assertOk();
    $this->get('/admin/roadmaps/1733')->assertOk();
});

test('the intended admin URL survives the login round trip', function () {
    authTestUser();

    $this->get('/admin/roadmaps/1733')->assertRedirect('/login');

    $this->post('/login', ['email' => 'admin@example.test', 'password' => 'secret-123'])
        ->assertRedirect('/admin/roadmaps/1733');
});

test('the throttle is scoped per e-mail; a different address is unaffected', function () {
    authTestUser();
    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['email' => 'admin@example.test', 'password' => 'nope']);
    }

    $this->from('/login')->post('/login', ['email' => 'other@example.test', 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Почта ёки парол нотўғри.']);
    $this->assertGuest();
});

test('the login page cannot be framed at all; the public pages keep the partner allow-list', function () {
    $this->get('/login')->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");

    $this->seed();
    $this->get('/roadmaps')->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://du.egov.uz");
});
