<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
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

test('the public portal never shows the panel: no admin link, no logout, logged in or not', function () {
    $this->seed();

    // the panel has its own shell (layouts/admin.blade.php); the portal sidebar stays public
    $user = authTestUser();
    foreach ([null, $user] as $as) {
        ($as ? $this->actingAs($as) : $this)->get('/roadmaps')->assertOk()
            ->assertDontSee('Чиқиш')
            ->assertDontSee('href="' . route('admin.roadmaps') . '"', false)
            ->assertDontSee(route('logout'), false);
    }

    // and the panel's own shell carries them, without the portal's nav
    $this->actingAs($user)->get('/admin/roadmaps')->assertOk()
        ->assertSee('Админ панели')
        ->assertSee('Чиқиш')
        ->assertDontSee('Топшириқлар')
        ->assertDontSee('class="sidebar"', false);
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

test('admin:create rotates the remember-me token and drops the live sessions when it resets a password', function () {
    config(['session.driver' => 'database']);           // the suite runs on the array driver; the purge is guarded on this
    $user  = User::factory()->create(['email' => 'boss@example.test', 'remember_token' => 'old-token']);
    $other = User::factory()->create(['email' => 'else@example.test']);
    foreach ([['live-session', $user->id], ['other-session', $other->id]] as [$id, $userId]) {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $userId, 'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit', 'payload' => base64_encode(serialize([])), 'last_activity' => time(),
        ]);
    }

    $this->artisan('admin:create', ['email' => 'boss@example.test', '--password' => 'second-pass-2'])->assertExitCode(0);

    expect($user->fresh()->remember_token)->not->toBe('old-token');
    expect(DB::table('sessions')->pluck('id')->all())->toBe(['other-session']);   // only this user's session is gone
});

test('an expired session sends the visitor back to the login form with the reason', function () {
    // ValidateCsrfToken skips itself while the suite runs (Application::runningUnitTests()), so a
    // token-less POST would never 419 here; the 419 is raised straight from a throwaway route
    // instead — what is under test is the handler's answer to it, not the middleware that raises it.
    Route::middleware('web')->get('/__stale-form', fn () => throw new TokenMismatchException('CSRF token mismatch.'));

    $this->get('/__stale-form')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Сессия муддати тугади — қайта киринг.']);

    $this->getJson('/__stale-form')->assertStatus(419);          // a JSON caller keeps the status
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
