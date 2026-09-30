# Road-map Admin Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An authenticated panel (`/admin/roadmaps`) where the team types, corrects and monitors the monthly «Амалда» values of the water road maps, and can upload the filled xlsx template — all inside the portal shell, with every change logged.

**Architecture:** Laravel session auth on the existing `users` table (one admin, created by `admin:create`). Three services do the work — `ProgressSheet` resolves a region-month sheet (saved / carried-over / empty per line), `ProgressEntry` writes saves, confirmations and line corrections through the existing `MeasureLineSync` + `MeasureRecomputer`, and `ProgressImport` is the `import:roadmap-progress` command refactored into a service the upload panel reuses. Three Livewire components render the overview, the entry grid and the upload panel; the grid's typing, live % and keyboard flow are Alpine (`public/js/ad-entry.js`), reading values from the DOM so Livewire re-renders never fight the edit buffer. New `ad-` block in `public/css/portal.css`, no build step.

**Tech Stack:** Laravel 12 (12.69), Livewire 4.4 (class components in `app/Livewire`, Alpine bundled), PostgreSQL, Pest 3, PhpSpreadsheet (already used by the importer), hand-maintained `portal.css`. PHP 8.2 — no typed class constants, no `#[\Override]`.

**Spec:** `docs/superpowers/specs/2026-09-30-roadmaps-admin-panel-design.md`. Deviations decided here (record them in the spec's as-built note in Task 13): upload limit is Livewire's default **12 MB** (the largest real file is 150 KB; 20 MB would need a published config); the CLI import now also writes an `import` log entry (user null → shown as «терминал»); the overview computes its counts with `ProgressSheet::build()` per region (13 × 3 queries) rather than one aggregate query; native `confirm()` dialogs are never used — every destructive button is a two-click «arm then confirm» control (browser automation and screen readers both handle it better).

**Conventions that apply to every task:**
- Work on branch `roadmap-admin` (created in Task 1, step 1) from `main`. Commit after every task with Conventional Commits and the trailer `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`. Never commit anything under `data/`.
- Run tests from `backend/`: `php artisan test --filter=<Name>`. The shared test DB is `hududlar_monitoringi_test`; never run two test processes at once.
- Feature tests: `uses(RefreshDatabase::class);` at the top, `$this->seed();` explicitly (regions/districts come from `SoatoSeeder`). Pest test functions are global — helper function names must be unique across the whole `tests/` tree; this plan uses the `Tests\Helpers\AdminFixture` class instead of free functions.
- UI text is Cyrillic Uzbek. Code comments and commit messages in English.
- No `npm`. Vite/Tailwind stay unused.
- The dev `.env` has `APP_ENV=production` → migrations need `php artisan migrate --force`.

---

## File structure

| Path | Responsibility |
| --- | --- |
| `backend/app/Console/Commands/AdminCreate.php` | `admin:create` — create the admin / reset its password |
| `backend/app/Http/Controllers/Auth/LoginController.php` | show / store / destroy the session |
| `backend/resources/views/layouts/auth.blade.php`, `resources/views/auth/login.blade.php` | login page without the sidebar |
| `backend/database/migrations/2026_09_30_000001_create_roadmap_change_log_table.php`, `app/Models/RoadmapChangeLog.php` | the change log |
| `backend/app/Support/Roadmaps/EntryValue.php` | number parsing/formatting for typed cells |
| `backend/app/Services/Roadmaps/ProgressSheet.php` | read side: region-month sheet, states, period options |
| `backend/app/Services/Roadmaps/ProgressEntry.php` | write side: save, confirmRest, correct, history |
| `backend/app/Services/Roadmaps/ProgressImport.php`, `ImportReport.php` | the importer as a service + its report |
| `backend/app/Console/Commands/ImportRoadmapProgress.php` | becomes a printer around `ProgressImport` |
| `backend/app/Livewire/Admin/RoadmapOverview.php` + `resources/views/livewire/admin/roadmap-overview.blade.php` | `/admin/roadmaps` |
| `backend/app/Livewire/Admin/RoadmapEntry.php` + `resources/views/livewire/admin/roadmap-entry.blade.php` + `partials/correction-panel.blade.php` + `partials/history-drawer.blade.php` | `/admin/roadmaps/{code}` |
| `backend/app/Livewire/Admin/ProgressUpload.php` + `resources/views/livewire/admin/progress-upload.blade.php` | upload slide-over used by both pages |
| `backend/public/js/ad-entry.js` | Alpine component of the grid (edit buffer, live %, keyboard, filters, save) |
| `backend/resources/views/pages/admin/roadmaps.blade.php`, `pages/admin/roadmap-entry.blade.php` | page shells mounting the components |
| `backend/public/css/portal.css` | appended `auth-`/`side-auth` and `ad-` blocks |
| `backend/tests/Helpers/AdminFixture.php` | the Хоразм fixture road map, user, template helpers |
| `backend/tests/Feature/Admin/*.php`, `tests/Unit/Roadmaps/EntryValueTest.php` | tests |
| `backend/docs/roadmap-monitoring.md`, `CLAUDE.md`, the spec | docs |

---

### Task 1: Login, logout, `admin:create`, guarded admin routes, sidebar links

**Files:**
- Create: `backend/app/Console/Commands/AdminCreate.php`
- Create: `backend/app/Http/Controllers/Auth/LoginController.php`
- Create: `backend/resources/views/layouts/auth.blade.php`, `backend/resources/views/auth/login.blade.php`
- Create: `backend/resources/views/pages/admin/roadmaps.blade.php`, `backend/resources/views/pages/admin/roadmap-entry.blade.php` (placeholders, replaced in Tasks 8 and 9)
- Modify: `backend/routes/web.php`, `backend/bootstrap/app.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/resources/views/layouts/app.blade.php`, `backend/public/css/portal.css`
- Test: `backend/tests/Feature/Admin/AuthTest.php`

- [ ] **Step 1: Branch**

```bash
cd C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali
git checkout main && git checkout -b roadmap-admin
```

- [ ] **Step 2: Write the failing tests**

`backend/tests/Feature/Admin/AuthTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `cd backend && php artisan test --filter=AuthTest`
Expected: FAIL — `/login` returns 404, `admin:create` is not defined.

- [ ] **Step 4: The command**

`backend/app/Console/Commands/AdminCreate.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** The portal has one admin; this creates it, or resets its password when the e-mail already exists. */
class AdminCreate extends Command
{
    protected $signature = 'admin:create
        {email : Login e-mail}
        {--name=Администратор : Display name (only used when creating)}
        {--password= : Password; prompted (twice, hidden) when omitted}';

    protected $description = 'Create the admin user, or reset the password of an existing one.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("«{$email}» электрон почта эмас.");

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        if ($password === '') {
            $password = (string) $this->secret('Парол');
            if ($password !== (string) $this->secret('Паролни такрорланг')) {
                $this->error('Пароллар мос эмас.');

                return self::FAILURE;
            }
        }
        if (mb_strlen($password) < 8) {
            $this->error('Парол камида 8 белги бўлсин.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->forceFill(['password' => $password])->save();        // the model's 'hashed' cast hashes it
            $this->info("Парол янгиланди: {$email}");
        } else {
            User::create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => $password]);
            $this->info("Админ яратилди: {$email}");
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: The controller**

`backend/app/Http/Controllers/Auth/LoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(
            ['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']],
            ['required' => ':attribute киритилмаган.', 'email' => 'Электрон почта нотўғри ёзилган.'],
            ['email' => 'Электрон почта', 'password' => 'Парол'],
        );

        $ok = Auth::attempt(
            ['email' => mb_strtolower($credentials['email']), 'password' => $credentials['password']],
            remember: true,
        );
        if (! $ok) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Почта ёки парол нотўғри.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.roadmaps'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
```

- [ ] **Step 6: Routes, redirects, throttle**

Append to `backend/routes/web.php` (add `use App\Http\Controllers\Auth\LoginController;` to the imports):

```php
// ---- admin panel (road-map monitoring entry) -------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::view('/roadmaps', 'pages.admin.roadmaps')->name('roadmaps');
    Route::get('/roadmaps/{code}', fn (string $code) => view('pages.admin.roadmap-entry', ['code' => (int) $code]))
        ->whereNumber('code')->name('roadmaps.entry');
});
```

In `backend/bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware): void {` add before the `trustProxies` call:

```php
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/admin/roadmaps');
```

`backend/app/Providers/AppServiceProvider.php` — replace the whole file:

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Five login attempts a minute per e-mail + IP; the sixth gets the same form back with a message.
        RateLimiter::for('login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('email')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);

                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => "Жуда кўп уриниш — {$seconds} сониядан кейин қайта уриниб кўринг."]);
            });
        });
    }
}
```

- [ ] **Step 7: Login views and the two placeholder admin pages**

`backend/resources/views/layouts/auth.blade.php`:

```blade
<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Кириш · Бошқарув маркази</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css?v={{ filemtime(public_path('css/portal.css')) }}">
</head>
<body class="auth-body">
  @yield('content')
</body>
</html>
```

`backend/resources/views/auth/login.blade.php`:

```blade
@extends('layouts.auth')

@section('content')
<main class="auth-wrap">
  <form method="post" action="{{ route('login.store') }}" class="auth-card" novalidate>
    @csrf
    <div class="auth-brand"><img src="/logo.svg" alt="CERR"></div>
    <h1>Бошқарув маркази</h1>
    <p class="sub">Йўл хариталари мониторинги · кириш</p>
    @error('email')
      <div class="auth-error" role="alert">{{ $message }}</div>
    @enderror
    @error('password')
      <div class="auth-error" role="alert">{{ $message }}</div>
    @enderror
    <label for="email">Электрон почта</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    <label for="password">Парол</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Кириш</button>
  </form>
</main>
@endsection
```

`backend/resources/views/pages/admin/roadmaps.blade.php` (placeholder until Task 8):

```blade
@extends('layouts.app')

@section('content')
  <div class="ad-shell"><h2 class="ad-title">Йўл хариталари · мониторинг</h2></div>
@endsection
```

`backend/resources/views/pages/admin/roadmap-entry.blade.php` (placeholder until Task 9):

```blade
@extends('layouts.app')

@section('content')
  <div class="ad-shell"><h2 class="ad-title">Амалда киритиш · {{ $code }}</h2></div>
@endsection
```

- [ ] **Step 8: Sidebar links**

In `backend/resources/views/layouts/app.blade.php`, directly after the closing `</a>` of the «Топшириқлар» link (before the commented-out Туманлар block), insert:

```blade
    @auth
      <a class="nav-btn {{ Route::is('admin.*') ? 'active' : '' }}"
         href="{{ route('admin.roadmaps') }}" title="Админ">
        <svg viewBox="0 0 256 256" fill="currentColor">
          <path d="M227.31,73.37,182.63,28.68a16,16,0,0,0-22.63,0L36.69,152A15.86,15.86,0,0,0,32,163.31V208a16,16,0,0,0,16,16H92.69A15.86,15.86,0,0,0,104,219.31L227.31,96a16,16,0,0,0,0-22.63ZM92.69,208H48V163.31l88-88L180.69,120ZM192,108.68,147.31,64l24-24L216,84.68Z"/>
        </svg>
        <span>Админ</span>
      </a>
    @endauth
```

Directly after `<livewire:region-switcher />` insert:

```blade
    @auth
      <form method="post" action="{{ route('logout') }}" class="side-auth">
        @csrf
        <span class="who" title="{{ auth()->user()->email }}">{{ auth()->user()->name }}</span>
        <button type="submit">Чиқиш</button>
      </form>
    @endauth
```

- [ ] **Step 9: CSS**

Append to `backend/public/css/portal.css`:

```css

/* ===================== admin panel (2026-09-30): login page + sidebar auth ===================== */
.auth-body{min-height:100vh;margin:0;background:linear-gradient(160deg,#0a1128 0%,#12305e 55%,#2b61af 100%);font-family:Inter,system-ui,sans-serif;color:var(--ink)}
.auth-wrap{min-height:100vh;display:grid;place-items:center;padding:24px}
.auth-card{width:min(100%,380px);background:var(--paper);border-radius:20px;padding:28px 28px 26px;box-shadow:0 30px 70px rgba(5,20,45,.45);display:flex;flex-direction:column}
.auth-brand{align-self:center;background:#0a1128;border-radius:14px;padding:12px 18px;margin-bottom:16px}
.auth-brand img{height:30px;display:block}
.auth-card h1{font-size:19px;font-weight:800;letter-spacing:-.01em;text-align:center;margin:0}
.auth-card .sub{text-align:center;font-size:12.5px;color:var(--muted);margin:4px 0 18px}
.auth-card label{font-size:11.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin:10px 0 5px}
.auth-card input{border:1px solid var(--line-strong);border-radius:10px;padding:10px 12px;font:inherit;font-size:14px;color:var(--ink);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s}
.auth-card input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(43,97,175,.16)}
.auth-card button{margin-top:18px;border:0;border-radius:999px;padding:11px 16px;font:inherit;font-size:14px;font-weight:800;color:#fff;background:var(--accent-grad);box-shadow:0 10px 24px rgba(23,105,224,.32);cursor:pointer}
.auth-card button:hover{filter:brightness(1.06)}
.auth-error{background:#fdecea;color:var(--task-red);border-radius:10px;padding:9px 12px;font-size:12.5px;font-weight:600;margin-bottom:4px}
.side-auth{display:flex;align-items:center;gap:8px;padding:10px 6px 0;margin-top:8px;border-top:1px solid rgba(255,255,255,.12);font-size:12px;color:rgba(255,255,255,.7)}
.side-auth .who{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}
.side-auth button{border:0;border-radius:999px;padding:5px 11px;font:inherit;font-size:11.5px;font-weight:700;color:#fff;background:rgba(255,255,255,.12);cursor:pointer}
.side-auth button:hover{background:rgba(255,255,255,.22)}
```

- [ ] **Step 10: Run the tests**

Run: `cd backend && php artisan test --filter=AuthTest`
Expected: PASS (8 tests). Also run `php artisan test --filter=RoadmapsPageTest` — the layout change must not break the public pages.

- [ ] **Step 11: Commit**

```bash
git add backend/app/Console/Commands/AdminCreate.php backend/app/Http/Controllers/Auth backend/app/Providers/AppServiceProvider.php backend/bootstrap/app.php backend/routes/web.php backend/resources/views/layouts backend/resources/views/auth backend/resources/views/pages/admin backend/public/css/portal.css backend/tests/Feature/Admin/AuthTest.php
git commit -m "feat(admin): session login, admin:create, guarded /admin routes, sidebar links

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: Change-log table, model, and the shared test fixture

**Files:**
- Create: `backend/database/migrations/2026_09_30_000001_create_roadmap_change_log_table.php`
- Create: `backend/app/Models/RoadmapChangeLog.php`
- Create: `backend/tests/Helpers/AdminFixture.php`
- Test: `backend/tests/Feature/Admin/RoadmapChangeLogTest.php`

- [ ] **Step 1: The fixture helper**

`backend/tests/Helpers/AdminFixture.php`:

```php
<?php

namespace Tests\Helpers;

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Models\User;
use App\Services\Roadmaps\MeasureLineSync;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The Хоразм road map the admin-panel tests work on (xlsx layout, lines predefined):
 *   I/1  «Куловот» канали            · L1 Бажарилиш даражаси % 100       · deadline 2026 йил декабрь
 *   I/2  484,5 млн м3 сув             · L1 Тежалган сув млн м³ 484,5       · deadline 2026 йил июнь (reached from June on)
 *   II · Боғот/1  бетонлаштириш       · L1 Хўжаликлараро каналлар км 7,8 · L2 Ички каналлар км 33
 *   II · Гурлан/1 насос агрегатлари   · L1 Насос агрегатлари та 24
 * roadmap:template puts them on rows 4, 5, 8–9 and 11 of the «Хоразм вилояти» sheet.
 * Callers seed first ($this->seed()).
 */
final class AdminFixture
{
    public static function roadmap(): Roadmap
    {
        $file = RoadmapXlsxBuilder::make([
            ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
            ['measure', ['B' => 1, 'C' => '«Куловот» каналини реконструкция қилиш.', 'D' => 'Бажарилиш даражаси', 'E' => '%', 'F' => 100,
                'I' => '2026 йил декабрь', 'J' => 'СХВ (Ў.Шералиев)']],
            ['measure', ['B' => 2, 'C' => '484,5 млн м3 сувни иқтисод қилиш.', 'D' => 'Тежалган сув', 'E' => 'млн м3', 'F' => '484,5',
                'I' => '2026 йил июнь', 'J' => 'ИТҲБ (Э.Нурметов)']],
            ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
            ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
            ['measure', ['B' => 1, 'C' => "Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.",
                'D' => 'Хўжаликлараро каналлар', 'E' => 'км', 'F' => '7,8', 'I' => '2026 йил декабрь', 'J' => 'ИТҲБ (Э.Нурметов)']],
            ['line', ['D' => 'Ички каналлар', 'E' => 'км', 'F' => 33]],
            ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
            ['measure', ['B' => 1, 'C' => '24 та насос агрегатларини таъмирлаш.', 'D' => 'Насос агрегатлари', 'E' => 'та', 'F' => 24,
                'I' => '2026 йил декабрь', 'J' => 'ИТҲБ']],
        ], null, 'Хоразм вилояти');

        try {
            if (Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $file]) !== 0) {
                throw new \RuntimeException('AdminFixture import failed: ' . Artisan::output());
            }
        } finally {
            @unlink($file);
        }
        Artisan::output();   // drain

        return Roadmap::where('region_code', 1733)->firstOrFail();
    }

    public static function user(string $email = 'admin@example.test'): User
    {
        return User::factory()->create(['name' => 'Админ', 'email' => $email, 'password' => 'secret-123']);
    }

    public static function measure(int $sectionNo, int $seqNo, ?int $districtCode = null): RoadmapMeasure
    {
        $q = RoadmapMeasure::where('section_no', $sectionNo)->where('seq_no', $seqNo);
        $q = $districtCode === null
            ? $q->whereNull('district_id')
            : $q->whereHas('district', fn ($d) => $d->where('code', $districtCode));

        return $q->firstOrFail();
    }

    public static function line(RoadmapMeasure $measure, int $lineNo): RoadmapMeasureLine
    {
        return $measure->lines()->where('line_no', $lineNo)->firstOrFail();
    }

    /**
     * Report a period the way the importer does: every line of the measure gets a row;
     * $actuals / $notes are keyed by line_no (missing = null).
     */
    public static function report(RoadmapMeasure $measure, string $period, array $actuals, array $notes = []): void
    {
        $measure->load('lines.progress');
        $lines = [];
        foreach ($measure->lines as $l) {
            $lines[] = [
                'label'  => $l->label,
                'unit'   => $l->unit,
                'plan'   => $l->plan_value === null ? null : (float) $l->plan_value,
                'actual' => $actuals[$l->line_no] ?? null,
                'note'   => $notes[$l->line_no] ?? null,
            ];
        }
        MeasureLineSync::sync($measure, $lines, $period, $measure->roadmap->year);
        $measure->unsetRelation('lines');
    }

    /**
     * A filled roadmap:template file for Хоразм: $cells are coordinate => value on the
     * «Хоразм вилояти» sheet (G4/G5/G8/G9/G11 are the «Амалда» cells). Caller unlinks it.
     */
    public static function template(string $period = '2026-09', array $cells = []): string
    {
        $out = tempnam(sys_get_temp_dir(), 'rmadm_') . '.xlsx';
        Artisan::call('roadmap:template', ['--region' => 1733, '--period' => $period, '--out' => $out]);
        Artisan::output();
        if ($cells !== []) {
            $book  = IOFactory::load($out);
            $sheet = $book->getSheetByName('Хоразм вилояти');
            foreach ($cells as $coord => $value) {
                $sheet->setCellValue($coord, $value);
            }
            IOFactory::createWriter($book, 'Xlsx')->save($out);
            $book->disconnectWorksheets();
        }

        return $out;
    }
}
```

- [ ] **Step 2: Write the failing test**

`backend/tests/Feature/Admin/RoadmapChangeLogTest.php`:

```php
<?php

use App\Models\RoadmapChangeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

test('a change-log entry records who changed what; it survives its line and goes with its measure', function () {
    $this->seed();
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $line    = AdminFixture::line($bogot, 2);

    $entry = RoadmapChangeLog::record([
        'user_id' => $user->id, 'roadmap_id' => $roadmap->id, 'roadmap_measure_id' => $bogot->id,
        'roadmap_measure_line_id' => $line->id, 'report_period' => '2026-09', 'action' => 'actual',
        'old_value' => null, 'new_value' => '20',
    ]);

    expect($entry->created_at)->not->toBeNull();
    expect($entry->updated_at)->toBeNull();
    expect($entry->user->name)->toBe('Админ');
    expect($entry->line->label)->toBe('Ички каналлар');
    expect($entry->measure->is($bogot))->toBeTrue();
    expect(RoadmapChangeLog::ACTION_LABELS['actual'])->toBe('Амалда');

    $line->delete();
    expect($entry->fresh()->roadmap_measure_line_id)->toBeNull();     // nullOnDelete

    $bogot->delete();
    expect(RoadmapChangeLog::count())->toBe(0);                          // cascadeOnDelete
});
```

- [ ] **Step 3: Run it to verify it fails**

Run: `cd backend && php artisan test --filter=RoadmapChangeLogTest`
Expected: FAIL — class `App\Models\RoadmapChangeLog` not found.

- [ ] **Step 4: Migration and model**

`backend/database/migrations/2026_09_30_000001_create_roadmap_change_log_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_change_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();           // null = terminal import
            $table->foreignId('roadmap_id')->constrained('roadmaps')->cascadeOnDelete();
            $table->foreignId('roadmap_measure_id')->nullable()->constrained('roadmap_measures')->cascadeOnDelete();   // null for an import entry
            $table->foreignId('roadmap_measure_line_id')->nullable()->constrained('roadmap_measure_lines')->nullOnDelete();   // the entry outlives a removed line
            $table->string('report_period', 16)->nullable();
            $table->string('action', 16);          // actual · note · label · unit · plan · line_added · line_removed · confirm · import
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at');
            $table->index(['roadmap_measure_id', 'created_at'], 'ix_roadmap_change_log_measure');
            $table->index(['roadmap_id', 'created_at'], 'ix_roadmap_change_log_roadmap');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_change_log');
    }
};
```

`backend/app/Models/RoadmapChangeLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per change made through the admin panel (or an import): who, when, what, old → new. */
class RoadmapChangeLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LABELS = [
        'actual'       => 'Амалда',
        'note'         => 'Изоҳ',
        'label'        => 'Индикатор номи',
        'unit'         => 'Ўлчов',
        'plan'         => 'Режа',
        'line_added'   => 'Индикатор қўшилди',
        'line_removed' => 'Индикатор ўчирилди',
        'confirm'      => 'Ўзгаришсиз тасдиқланди',
        'import'       => 'Excel’дан юкланди',
    ];

    protected $table = 'roadmap_change_log';

    protected $fillable = [
        'user_id', 'roadmap_id', 'roadmap_measure_id', 'roadmap_measure_line_id',
        'report_period', 'action', 'old_value', 'new_value',
    ];

    protected $casts = [
        'user_id'                 => 'integer',
        'roadmap_id'              => 'integer',
        'roadmap_measure_id'      => 'integer',
        'roadmap_measure_line_id' => 'integer',
        'created_at'              => 'datetime',
    ];

    /** @param array<string, mixed> $attributes */
    public static function record(array $attributes): self
    {
        if (! isset(self::ACTION_LABELS[$attributes['action'] ?? ''])) {
            throw new \InvalidArgumentException('Unknown change-log action «' . ($attributes['action'] ?? '') . '».');
        }

        return self::create($attributes);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(Roadmap::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(RoadmapMeasure::class, 'roadmap_measure_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(RoadmapMeasureLine::class, 'roadmap_measure_line_id');
    }
}
```

- [ ] **Step 5: Run the test**

Run: `cd backend && php artisan test --filter=RoadmapChangeLogTest`
Expected: PASS. Then migrate the dev DB: `php artisan migrate --force`.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_09_30_000001_create_roadmap_change_log_table.php backend/app/Models/RoadmapChangeLog.php backend/tests/Helpers/AdminFixture.php backend/tests/Feature/Admin/RoadmapChangeLogTest.php
git commit -m "feat(admin): roadmap_change_log table and model; shared admin test fixture

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: `EntryValue` — parsing and formatting typed numbers

**Files:**
- Create: `backend/app/Support/Roadmaps/EntryValue.php`
- Test: `backend/tests/Unit/Roadmaps/EntryValueTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Support\Roadmaps\EntryValue;

test('parse accepts the spellings people type and refuses the rest', function () {
    expect(EntryValue::parse(null))->toBeNull();
    expect(EntryValue::parse(''))->toBeNull();
    expect(EntryValue::parse('   '))->toBeNull();
    expect(EntryValue::parse('12,5'))->toBe(12.5);
    expect(EntryValue::parse('12.5'))->toBe(12.5);
    expect(EntryValue::parse('1 240'))->toBe(1240.0);
    expect(EntryValue::parse("1\u{00A0}240,5"))->toBe(1240.5);          // NBSP
    expect(EntryValue::parse("1\u{202F}240"))->toBe(1240.0);            // narrow no-break space
    expect(EntryValue::parse(' 0 '))->toBe(0.0);
    expect(EntryValue::parse('1,240'))->toBe(1.24);                      // a typed comma is a decimal comma
});

test('parse names the problem', function () {
    expect(fn () => EntryValue::parse('кўп'))->toThrow(InvalidArgumentException::class, 'рақам эмас');
    expect(fn () => EntryValue::parse('1.2.3'))->toThrow(InvalidArgumentException::class, 'рақам эмас');
    expect(fn () => EntryValue::parse('12%'))->toThrow(InvalidArgumentException::class, 'рақам эмас');
    expect(fn () => EntryValue::parse('-1'))->toThrow(InvalidArgumentException::class, 'манфий бўлмасин');
    expect(fn () => EntryValue::parse('1000000000001'))->toThrow(InvalidArgumentException::class, 'жуда катта');
});

test('format writes the value the way the inputs show it', function () {
    expect(EntryValue::format(null))->toBe('');
    expect(EntryValue::format(''))->toBe('');
    expect(EntryValue::format(7.8))->toBe('7,8');
    expect(EntryValue::format('7.800000'))->toBe('7,8');                 // the decimal cast hands back strings
    expect(EntryValue::format(1240))->toBe('1240');
    expect(EntryValue::format(0.0))->toBe('0');
    expect(EntryValue::format(60.606061))->toBe('60,606061');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd backend && php artisan test --filter=EntryValueTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

`backend/app/Support/Roadmaps/EntryValue.php`:

```php
<?php

namespace App\Support\Roadmaps;

use InvalidArgumentException;

/**
 * Numbers typed into the admin grid. Unlike the xlsx readers (where «1,240» is
 * ambiguous and refused), a comma typed by hand is a decimal comma. Mirrored in
 * public/js/ad-entry.js (parse/fmt) — keep the two in step.
 */
final class EntryValue
{
    public const MAX = 1e12;

    /** Ordinary, no-break, narrow no-break and thin spaces — all of them turn up in pasted numbers. */
    private const SPACES = '/[ \x{00A0}\x{202F}\x{2009}]/u';

    /** @throws InvalidArgumentException with the short message the cell shows */
    public static function parse(?string $raw): ?float
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = str_replace(',', '.', (string) preg_replace(self::SPACES, '', $s));
        if (preg_match('/^-?\d+(\.\d+)?$/', $s) !== 1) {
            throw new InvalidArgumentException('рақам эмас');
        }
        $v = (float) $s;
        if ($v < 0) {
            throw new InvalidArgumentException('манфий бўлмасин');
        }
        if ($v > self::MAX) {
            throw new InvalidArgumentException('жуда катта');
        }

        return $v;
    }

    /** Up to 6 decimals, trailing zeros dropped, decimal comma, no thousands separator; '' for null. */
    public static function format(null|float|int|string $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $s = rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');

        return str_replace('.', ',', $s === '-0' ? '0' : $s);
    }
}
```

- [ ] **Step 4: Run the test**

Run: `cd backend && php artisan test --filter=EntryValueTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/Roadmaps/EntryValue.php backend/tests/Unit/Roadmaps/EntryValueTest.php
git commit -m "feat(admin): EntryValue — parsing and formatting of typed indicator values

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4: `ProgressSheet` — the region-month sheet

**Files:**
- Create: `backend/app/Services/Roadmaps/ProgressSheet.php`
- Test: `backend/tests/Feature/Admin/ProgressSheetTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Services\Roadmaps\ProgressSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());
afterEach(fn () => Carbon::setTestNow());

test('a line is saved for the period, carried from the latest earlier value, or empty; measure states and counts follow', function () {
    $roadmap = AdminFixture::roadmap();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-07', [1 => 3.0, 2 => 10.0], [1 => 'июль изоҳи']);
    AdminFixture::report($bogot, '2026-08', [1 => 5.0]);                 // line 2 not reported in August (null row)

    $sheet = ProgressSheet::build($roadmap, '2026-09');
    $byId  = collect($sheet['measures'])->keyBy('id');
    $m     = $byId[$bogot->id];
    expect($m['state'])->toBe('carry');
    expect($m['lines'][0])->toMatchArray(['state' => 'carry', 'actual' => 5.0, 'note' => null, 'source_period' => '2026-08', 'saved_actual' => null, 'saved_note' => null]);
    expect($m['lines'][1])->toMatchArray(['state' => 'carry', 'actual' => 10.0, 'source_period' => '2026-07']);   // August's null row is skipped
    expect($m['lines'][0])->toMatchArray(['id' => AdminFixture::line($bogot, 1)->id, 'line_no' => 1, 'label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => 7.8]);
    expect($m)->toMatchArray(['seq_no' => 1, 'district_name' => 'Боғот тумани', 'district_code' => 1733204, 'district_head' => 'туман ҳокими Ж.Назаров', 'section_no' => 2, 'status' => 'in_progress', 'deadline_reached' => false]);
    expect($m['details'])->toBe(['1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.']);

    // a saved row for the period wins even when its actual is null
    $m8 = collect(ProgressSheet::build($roadmap, '2026-08')['measures'])->keyBy('id')[$bogot->id];
    expect($m8['state'])->toBe('saved');
    expect($m8['lines'][0])->toMatchArray(['state' => 'saved', 'actual' => 5.0, 'saved_actual' => 5.0, 'source_period' => null]);
    expect($m8['lines'][1])->toMatchArray(['state' => 'saved', 'actual' => null, 'saved_actual' => null]);

    // untouched measures are empty; the June deadline of I/2 is reached in September
    expect($byId[AdminFixture::measure(1, 1)->id]['state'])->toBe('empty');
    expect($byId[AdminFixture::measure(1, 2)->id]['deadline_reached'])->toBeTrue();
    expect($sheet['counts'])->toBe(['saved' => 0, 'carry' => 1, 'empty' => 3, 'confirmable' => 4]);
    expect(array_column($sheet['measures'], 'seq_no'))->toBe([1, 2, 1, 1]);     // document order
});

test('a quarter carries as its last month; periods run from January to the current month, newest first', function () {
    $roadmap = AdminFixture::roadmap();
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    AdminFixture::report($gurlan, '2026-Q2', [1 => 6.0]);

    $july = collect(ProgressSheet::build($roadmap, '2026-07')['measures'])->keyBy('id')[$gurlan->id]['lines'][0];
    expect($july)->toMatchArray(['state' => 'carry', 'actual' => 6.0, 'source_period' => '2026-Q2']);
    $june = collect(ProgressSheet::build($roadmap, '2026-06')['measures'])->keyBy('id')[$gurlan->id]['lines'][0];
    expect($june['state'])->toBe('empty');                                // the quarter closes June — not "before" June

    Carbon::setTestNow('2026-09-15 10:00:00');
    expect(ProgressSheet::currentPeriod())->toBe('2026-09');
    expect(ProgressSheet::periodOptions(2026))->toBe(['2026-09', '2026-08', '2026-07', '2026-06', '2026-05', '2026-04', '2026-03', '2026-02', '2026-01']);
    Carbon::setTestNow('2025-12-01 10:00:00');                            // before the road-map year: at least January
    expect(ProgressSheet::periodOptions(2026))->toBe(['2026-01']);
    Carbon::setTestNow('2026-09-30 20:30:00');                            // 01:30 on 1 October in Tashkent
    expect(ProgressSheet::currentPeriod())->toBe('2026-10');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `cd backend && php artisan test --filter=ProgressSheetTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

`backend/app/Services/Roadmaps/ProgressSheet.php`:

```php
<?php

namespace App\Services\Roadmaps;

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Support\Roadmaps\RoadmapDeadline;
use App\Support\Roadmaps\RoadmapPeriod;

/**
 * Read side of the admin entry grid: one region + one month → every measure with every
 * line resolved to what the cell shows and where it came from.
 *
 * Per line, for period P:
 *   1. a progress row for P exists            → 'saved'  (its actual/note; a null actual is an empty saved cell, not a carry)
 *   2. else the latest earlier row with a value → 'carry'  (actual/note from it, source_period = its period)
 *   3. else                                     → 'empty'
 * Measure state: 'saved' when any line is saved (saves are whole-measure), else 'carry'
 * when any line carries, else 'empty'.
 */
final class ProgressSheet
{
    public const TZ = 'Asia/Tashkent';

    /** «YYYY-MM» of the current month where the people typing live. */
    public static function currentPeriod(): string
    {
        return now(self::TZ)->format('Y-m');
    }

    /**
     * Months the panel offers: from January of the road-map year up to the current
     * month (at least January), newest first.
     *
     * @return list<string>
     */
    public static function periodOptions(int $year, ?string $current = null): array
    {
        $first = RoadmapPeriod::monthIndex(RoadmapPeriod::fromYearMonth($year, 1));
        $last  = max($first, RoadmapPeriod::monthIndex($current ?? self::currentPeriod()));

        $out = [];
        for ($idx = $last; $idx >= $first; $idx--) {
            $y = intdiv($idx - 1, 12);
            $out[] = RoadmapPeriod::fromYearMonth($y, $idx - $y * 12);
        }

        return $out;
    }

    /**
     * @return array{measures: list<array<string, mixed>>, counts: array{saved: int, carry: int, empty: int, confirmable: int}}
     */
    public static function build(Roadmap $roadmap, string $period): array
    {
        $pIdx     = RoadmapPeriod::monthIndex($period);
        $measures = $roadmap->measures()->with(['district', 'lines.progress'])->orderBy('source_row')->get();

        $out    = [];
        $counts = ['saved' => 0, 'carry' => 0, 'empty' => 0, 'confirmable' => 0];
        foreach ($measures as $m) {
            $lines    = [];
            $anySaved = false;
            $anyCarry = false;
            foreach ($m->lines as $l) {
                $r = self::resolveLine($l, $period, $pIdx);
                $lines[]   = $r;
                $anySaved  = $anySaved || $r['state'] === 'saved';
                $anyCarry  = $anyCarry || $r['state'] === 'carry';
            }
            $state = $anySaved ? 'saved' : ($anyCarry ? 'carry' : 'empty');
            $counts[$state]++;
            if ($state !== 'saved' && $lines !== []) {
                $counts['confirmable']++;
            }
            $out[] = self::measureRow($m, $roadmap->year, $period, $state, $lines);
        }

        return ['measures' => $out, 'counts' => $counts];
    }

    /**
     * @return array{id: int, line_no: int, label: string, unit: ?string, plan: ?float, actual: ?float, note: ?string,
     *               state: string, source_period: ?string, saved_actual: ?float, saved_note: ?string}
     */
    public static function resolveLine(RoadmapMeasureLine $line, string $period, ?int $pIdx = null): array
    {
        $pIdx ??= RoadmapPeriod::monthIndex($period);
        $saved  = $line->progress->firstWhere('report_period', $period);
        $base   = [
            'id'           => $line->id,
            'line_no'      => $line->line_no,
            'label'        => $line->label,
            'unit'         => $line->unit,
            'plan'         => $line->plan_value === null ? null : (float) $line->plan_value,
            'saved_actual' => $saved?->actual_value === null ? null : (float) $saved->actual_value,
            'saved_note'   => $saved?->note,
        ];

        if ($saved !== null) {
            return $base + ['actual' => $base['saved_actual'], 'note' => $saved->note, 'state' => 'saved', 'source_period' => null];
        }

        $best    = null;
        $bestIdx = -1;
        foreach ($line->progress as $p) {
            if ($p->actual_value === null) {
                continue;
            }
            $idx = RoadmapPeriod::monthIndex($p->report_period);
            if ($idx >= $pIdx) {
                continue;
            }
            if ($idx > $bestIdx || ($idx === $bestIdx && strcmp($p->report_period, (string) $best?->report_period) > 0)) {
                $best    = $p;
                $bestIdx = $idx;
            }
        }
        if ($best !== null) {
            return $base + ['actual' => (float) $best->actual_value, 'note' => $best->note, 'state' => 'carry', 'source_period' => $best->report_period];
        }

        return $base + ['actual' => null, 'note' => null, 'state' => 'empty', 'source_period' => null];
    }

    /** @param list<array<string, mixed>> $lines */
    private static function measureRow(RoadmapMeasure $m, int $year, string $period, string $state, array $lines): array
    {
        return [
            'id'               => $m->id,
            'section_no'       => (int) $m->section_no,
            'section_title'    => $m->section_title,
            'district_id'      => $m->district_id,
            'district_name'    => $m->district?->name_full,
            'district_code'    => $m->district?->code,
            'district_head'    => $m->district_head_text,
            'seq_no'           => (int) $m->seq_no,
            'title'            => $m->title,
            'details'          => $m->detailLines(),
            'deadline_text'    => $m->deadline_text,
            'responsible_text' => $m->responsible_text,
            'status'           => $m->status,
            'pct'              => $m->pct === null ? null : (float) $m->pct,
            'latest_period'    => $m->latest_period,
            'deadline_reached' => RoadmapDeadline::reached($period, $m->deadline_text, $year),
            'state'            => $state,
            'lines'            => $lines,
        ];
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `cd backend && php artisan test --filter=ProgressSheetTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Roadmaps/ProgressSheet.php backend/tests/Feature/Admin/ProgressSheetTest.php
git commit -m "feat(admin): ProgressSheet — region-month sheet with saved/carried/empty resolution

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5: `ProgressEntry::save()` and `confirmRest()`

**Files:**
- Create: `backend/app/Services/Roadmaps/ProgressEntry.php`
- Test: `backend/tests/Feature/Admin/ProgressEntrySaveTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use App\Services\Roadmaps\ProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());
afterEach(fn () => Carbon::setTestNow());

test('save writes the whole touched measure — edited and carried lines — recomputes it and logs only the changes', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 3.0, 2 => 10.0], [2 => 'давом этмоқда']);
    $l1 = AdminFixture::line($bogot, 1);
    $l2 = AdminFixture::line($bogot, 2);

    $r = ProgressEntry::save($roadmap, '2026-09', [$l1->id => ['actual' => '7,8']], $user);
    expect($r)->toBe(['measures' => 1, 'changes' => 1]);

    $p1 = $l1->progress()->where('report_period', '2026-09')->firstOrFail();
    $p2 = $l2->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $p1->actual_value)->toBe(7.8);
    expect((float) $p2->actual_value)->toBe(10.0);                       // carried from August
    expect($p2->note)->toBe('давом этмоқда');                             // notes carry too
    expect($p1->period_type)->toBe('month');
    expect($p1->reported_at->toDateString())->toBe(now()->toDateString());
    $bogot->refresh();
    expect($bogot->latest_period)->toBe('2026-09');
    expect($bogot->lines_done)->toBe(1);
    expect((float) $bogot->pct)->toBeNumericallyClose(65.15, 0.01);      // (100 + 30.3) / 2
    expect($bogot->status)->toBe('in_progress');                         // December deadline, September report
    expect(RoadmapLineProgress::where('report_period', '2026-09')->count())->toBe(2);   // the other measures were not touched

    $log = RoadmapChangeLog::all();
    expect($log)->toHaveCount(1);
    expect($log[0])->toMatchArray([
        'user_id' => $user->id, 'roadmap_id' => $roadmap->id, 'roadmap_measure_id' => $bogot->id,
        'roadmap_measure_line_id' => $l1->id, 'line_label' => 'Хўжаликлараро каналлар', 'report_period' => '2026-09', 'action' => 'actual',
        'old_value' => null, 'new_value' => '7,8',
    ]);
});

test('a second save logs the old stored value; clearing a cell logs null; an edit that changes nothing logs nothing', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    $line    = AdminFixture::line($gurlan, 1);
    AdminFixture::report($gurlan, '2026-09', [1 => 12.0], [1 => 'ярми']);

    ProgressEntry::save($roadmap, '2026-09', [$line->id => ['actual' => '24', 'note' => 'ярми']], $user);   // note unchanged
    ProgressEntry::save($roadmap, '2026-09', [$line->id => ['note' => '']], $user);                          // note cleared
    $r = ProgressEntry::save($roadmap, '2026-09', [$line->id => ['actual' => '24,0']], $user);               // nothing changes
    expect($r)->toBe(['measures' => 1, 'changes' => 0]);

    $rows = RoadmapChangeLog::orderBy('id')->get();
    expect($rows->pluck('action')->all())->toBe(['actual', 'note']);
    expect($rows[0])->toMatchArray(['old_value' => '12', 'new_value' => '24']);
    expect($rows[1])->toMatchArray(['old_value' => 'ярми', 'new_value' => null]);
    $p = $line->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $p->actual_value)->toBe(24.0);
    expect($p->note)->toBeNull();
    expect($gurlan->fresh()->status)->toBe('done');
});

test('a bad number, a negative, a foreign line or a bad period refuses the whole save before anything is written', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $l1 = AdminFixture::line($bogot, 1);
    $l2 = AdminFixture::line($bogot, 2);

    expect(fn () => ProgressEntry::save($roadmap, '2026-09', [$l1->id => ['actual' => '5'], $l2->id => ['actual' => 'кўп']], $user))
        ->toThrow(InvalidArgumentException::class, '«Ички каналлар»: рақам эмас');
    expect(fn () => ProgressEntry::save($roadmap, '2026-09', [$l1->id => ['actual' => '-1']], $user))
        ->toThrow(InvalidArgumentException::class, 'манфий');
    expect(fn () => ProgressEntry::save($roadmap, '2026-09', [999999 => ['actual' => '5']], $user))
        ->toThrow(InvalidArgumentException::class, '#999999');
    expect(fn () => ProgressEntry::save($roadmap, '2026-13', [$l1->id => ['actual' => '5']], $user))
        ->toThrow(InvalidArgumentException::class, '2026-13');
    expect(ProgressEntry::save($roadmap, '2026-09', [], $user))->toBe(['measures' => 0, 'changes' => 0]);
    expect(RoadmapLineProgress::count())->toBe(0);
    expect(RoadmapChangeLog::count())->toBe(0);
});

test('confirmRest writes carried values for every unsaved measure with lines and logs one confirm per measure', function () {
    Carbon::setTestNow('2026-09-15 10:00:00');
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    $m2      = AdminFixture::measure(1, 2);
    AdminFixture::report($bogot, '2026-08', [1 => 7.8, 2 => 33.0]);
    AdminFixture::report($m2, '2026-08', [1 => 100.0]);
    ProgressEntry::save($roadmap, '2026-09', [AdminFixture::line($gurlan, 1)->id => ['actual' => '24']], $user);   // Гурлан already saved

    expect(ProgressEntry::confirmRest($roadmap, '2026-09', $user))->toBe(3);          // Боғот, I/1 (empty), I/2 — not Гурлан
    expect(RoadmapLineProgress::where('report_period', '2026-09')->count())->toBe(5);
    expect($bogot->fresh()->status)->toBe('done');
    expect($bogot->fresh()->latest_period)->toBe('2026-09');
    expect($m2->fresh()->status)->toBe('open');                                       // June deadline passed, 100 of 484,5
    expect(AdminFixture::measure(1, 1)->latest_period)->toBe('2026-09');              // the empty line was written as null
    expect(AdminFixture::measure(1, 1)->status)->toBe('in_progress');
    $confirms = RoadmapChangeLog::where('action', 'confirm')->get();
    expect($confirms)->toHaveCount(3);
    expect($confirms->firstWhere('roadmap_measure_id', $bogot->id)->new_value)->toBe('2 та қатор');
    expect(ProgressEntry::confirmRest($roadmap, '2026-09', $user))->toBe(0);          // nothing left
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `cd backend && php artisan test --filter=ProgressEntrySaveTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the write side (save + confirmRest; `correct`/`history` come in Task 6)**

`backend/app/Services/Roadmaps/ProgressEntry.php`:

```php
<?php

namespace App\Services\Roadmaps;

use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Models\User;
use App\Support\Roadmaps\EntryValue;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Write side of the admin panel. Every write goes through MeasureLineSync::sync() —
 * the same path the importer uses — so the page can never disagree with the importer,
 * and every write logs to roadmap_change_log.
 */
final class ProgressEntry
{
    /**
     * Writes every measure that owns an edited line, as a whole: edited lines get the
     * typed value, its other lines their saved-or-carried value. Untouched measures are
     * left alone.
     *
     * @param  array<int|string, array{actual?: ?string, note?: ?string}> $edits keyed by line id; a missing key = unchanged
     * @return array{measures: int, changes: int}
     * @throws InvalidArgumentException naming the offending line — nothing is written
     */
    public static function save(Roadmap $roadmap, string $period, array $edits, User $user): array
    {
        self::assertMonth($period);
        if ($edits === []) {
            return ['measures' => 0, 'changes' => 0];
        }

        $lineIds = array_map('intval', array_keys($edits));
        $owned   = RoadmapMeasureLine::whereIn('id', $lineIds)
            ->whereHas('measure', fn ($q) => $q->where('roadmap_id', $roadmap->id))
            ->get()->keyBy('id');
        foreach ($lineIds as $id) {
            if (! $owned->has($id)) {
                throw new InvalidArgumentException("Индикатор #{$id} бу йўл харитага тегишли эмас.");
            }
        }

        // Parse everything first: a bad number aborts before anything is written.
        $parsed = [];
        foreach ($edits as $id => $edit) {
            $edit  = (array) $edit;
            $entry = [];
            if (array_key_exists('actual', $edit)) {
                try {
                    $entry['actual'] = EntryValue::parse($edit['actual'] === null ? null : (string) $edit['actual']);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException("«{$owned[(int) $id]->label}»: {$e->getMessage()}");
                }
            }
            if (array_key_exists('note', $edit)) {
                $entry['note'] = self::note($edit['note']);
            }
            $parsed[(int) $id] = $entry;
        }

        $measures = RoadmapMeasure::whereIn('id', $owned->pluck('roadmap_measure_id')->unique())
            ->with('lines.progress')->orderBy('source_row')->get();

        $changes = 0;
        DB::transaction(function () use ($measures, $parsed, $period, $roadmap, $user, &$changes): void {
            foreach ($measures as $measure) {
                $rows = [];
                foreach ($measure->lines as $line) {
                    $r      = ProgressSheet::resolveLine($line, $period);
                    $edit   = $parsed[$line->id] ?? [];
                    $actual = array_key_exists('actual', $edit) ? $edit['actual'] : $r['actual'];
                    $note   = array_key_exists('note', $edit) ? $edit['note'] : $r['note'];
                    $rows[] = ['label' => $line->label, 'unit' => $line->unit, 'plan' => $r['plan'], 'actual' => $actual, 'note' => $note];

                    if (array_key_exists('actual', $edit) && MeasureLineSync::differs($r['saved_actual'], $actual)) {
                        self::log($user, $roadmap, $measure, $line, $period, 'actual', self::num($r['saved_actual']), self::num($actual));
                        $changes++;
                    }
                    if (array_key_exists('note', $edit) && (string) $r['saved_note'] !== (string) $note) {
                        self::log($user, $roadmap, $measure, $line, $period, 'note', $r['saved_note'], $note);
                        $changes++;
                    }
                }
                MeasureLineSync::sync($measure, $rows, $period, $roadmap->year);
            }
        });

        return ['measures' => $measures->count(), 'changes' => $changes];
    }

    /**
     * Every measure that has lines and is not yet saved for the period gets its
     * carried values written as the period's report (lines without any value stay
     * null). One `confirm` entry per measure. Returns how many were written.
     */
    public static function confirmRest(Roadmap $roadmap, string $period, User $user): int
    {
        self::assertMonth($period);
        $sheet = ProgressSheet::build($roadmap, $period);
        $ids   = [];
        foreach ($sheet['measures'] as $m) {
            if ($m['state'] !== 'saved' && $m['lines'] !== []) {
                $ids[] = $m['id'];
            }
        }
        if ($ids === []) {
            return 0;
        }

        $measures = RoadmapMeasure::whereIn('id', $ids)->with('lines.progress')->orderBy('source_row')->get();
        DB::transaction(function () use ($measures, $period, $roadmap, $user): void {
            foreach ($measures as $measure) {
                $rows = [];
                foreach ($measure->lines as $line) {
                    $r      = ProgressSheet::resolveLine($line, $period);
                    $rows[] = ['label' => $line->label, 'unit' => $line->unit, 'plan' => $r['plan'], 'actual' => $r['actual'], 'note' => $r['note']];
                }
                MeasureLineSync::sync($measure, $rows, $period, $roadmap->year);
                self::log($user, $roadmap, $measure, null, $period, 'confirm', null, count($rows) . ' та қатор');
            }
        });

        return $measures->count();
    }

    private static function assertMonth(string $period): void
    {
        if (! RoadmapPeriod::isValid($period) || RoadmapPeriod::type($period) !== 'month') {
            throw new InvalidArgumentException("Нотўғри давр «{$period}» — YYYY-MM кутилади.");
        }
    }

    private static function note(mixed $raw): ?string
    {
        $s = trim((string) $raw);

        return $s === '' ? null : mb_substr($s, 0, 500);
    }

    /** '7,8' for the log, null stays null ('0' must survive — never use `?:` here). */
    private static function num(null|float|int|string $v): ?string
    {
        return $v === null ? null : EntryValue::format($v);
    }

    private static function log(User $user, Roadmap $roadmap, RoadmapMeasure $measure, ?RoadmapMeasureLine $line, ?string $period, string $action, ?string $old, ?string $new): void
    {
        RoadmapChangeLog::record([
            'user_id'                 => $user->id,
            'roadmap_id'              => $roadmap->id,
            'roadmap_measure_id'      => $measure->id,
            'roadmap_measure_line_id' => $line?->id,
            'line_label'              => $line?->label,      // snapshot: history stays readable after the line is removed
            'report_period'           => $period,
            'action'                  => $action,
            'old_value'               => $old,
            'new_value'               => $new,
        ]);
    }
}
```

Also add to `backend/app/Models/RoadmapChangeLog.php`, right after `public const UPDATED_AT = null;`:

```php
    /** created_at is timestamp(3); without this Eloquent writes whole seconds and the precision is wasted. */
    protected $dateFormat = 'Y-m-d H:i:s.v';
```

(Reads are safe: when Postgres returns a value without a fraction, Eloquent falls back to `Date::parse()`.) Extend the first test's log assertion with `'line_label' => 'Хўжаликлараро каналлар'`.

- [ ] **Step 4: Run the tests**

Run: `cd backend && php artisan test --filter=ProgressEntrySaveTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Roadmaps/ProgressEntry.php backend/tests/Feature/Admin/ProgressEntrySaveTest.php
git commit -m "feat(admin): ProgressEntry::save and confirmRest write whole measures through MeasureLineSync and log changes

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6: `ProgressEntry::correct()` and `history()`

**Files:**
- Modify: `backend/app/Services/Roadmaps/ProgressEntry.php`
- Test: `backend/tests/Feature/Admin/ProgressEntryCorrectTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasureLine;
use App\Services\Roadmaps\ProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('correct updates, reorders, adds and removes lines, renumbers, rewrites reported percentages and logs everything', function () {
    AdminFixture::roadmap();
    $user  = AdminFixture::user();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 3.9, 2 => 11.0]);
    $l1 = AdminFixture::line($bogot, 1);
    $l2 = AdminFixture::line($bogot, 2);

    ProgressEntry::correct($bogot, [
        ['id' => $l2->id, 'label' => 'Ички каналлар', 'unit' => 'км', 'plan' => '22'],                      // moved first, plan 33 → 22
        ['id' => null, 'label' => ' Гидропостлар ', 'unit' => 'та', 'plan' => '4'],                          // new (label trimmed)
        ['id' => (string) $l1->id, 'label' => 'Хўжаликлараро каналлар (бетон)', 'unit' => 'км', 'plan' => '7,8'],   // relabelled, moved last
    ], $user);

    $bogot->refresh();
    expect($bogot->lines->map(fn ($l) => [$l->line_no, $l->label, $l->unit, (float) $l->plan_value])->all())->toBe([
        [1, 'Ички каналлар', 'км', 22.0],
        [2, 'Гидропостлар', 'та', 4.0],
        [3, 'Хўжаликлараро каналлар (бетон)', 'км', 7.8],
    ]);
    expect($bogot->lines_total)->toBe(3);
    expect((float) $l2->progress()->where('report_period', '2026-08')->value('pct_of_plan'))->toBeNumericallyClose(50.0, 0.001);   // 11 / 22
    expect((float) $bogot->pct)->toBeNumericallyClose(33.33, 0.01);       // (50 + 0 + 50) / 3 — the new line has no row
    expect(RoadmapChangeLog::orderBy('id')->pluck('action')->all())->toBe(['plan', 'line_added', 'label']);
    expect(RoadmapChangeLog::where('action', 'plan')->firstOrFail())->toMatchArray(['old_value' => '33', 'new_value' => '22', 'roadmap_measure_line_id' => $l2->id, 'user_id' => $user->id]);
    expect(RoadmapChangeLog::where('action', 'line_added')->firstOrFail()->new_value)->toBe('Гидропостлар · та · 4');

    // removing the relabelled line takes its August row with it and says so
    ProgressEntry::correct($bogot, [
        ['id' => $l2->id, 'label' => 'Ички каналлар', 'unit' => 'км', 'plan' => '22'],
        ['id' => AdminFixture::line($bogot, 2)->id, 'label' => 'Гидропостлар', 'unit' => '', 'plan' => ''],   // unit and plan cleared
    ], $user);

    expect(RoadmapMeasureLine::find($l1->id))->toBeNull();
    expect(RoadmapLineProgress::where('roadmap_measure_line_id', $l1->id)->count())->toBe(0);
    $removed = RoadmapChangeLog::where('action', 'line_removed')->firstOrFail();
    expect($removed->old_value)->toBe('Хўжаликлараро каналлар (бетон) · км · 7,8 · 1 ой');
    expect($removed->roadmap_measure_line_id)->toBeNull();
    expect($removed->line_label)->toBe('Хўжаликлараро каналлар (бетон)');
    expect(RoadmapChangeLog::where('action', 'plan')->first()->line_label)->toBe('Ички каналлар');
    $bogot->refresh();
    expect($bogot->lines_total)->toBe(1);                                  // an unplanned line does not count
    expect($bogot->lines->pluck('line_no')->all())->toBe([1, 2]);
    expect(AdminFixture::line($bogot, 2)->unit)->toBeNull();
    expect(AdminFixture::line($bogot, 2)->plan_value)->toBeNull();
    expect(RoadmapChangeLog::orderBy('id')->pluck('action')->all())->toBe(['plan', 'line_added', 'label', 'line_removed', 'unit', 'plan']);
});

test('correct refuses a blank label, a bad plan, a long unit, a foreign line, a duplicate and an empty list, writing nothing', function () {
    AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $l1      = AdminFixture::line($bogot, 1);
    $l2      = AdminFixture::line($bogot, 2);
    $foreign = AdminFixture::line(AdminFixture::measure(2, 1, 1733208), 1);
    $with    = fn (array $second) => [['id' => $l1->id, 'label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => '7,8'], $second];

    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $l2->id, 'label' => '  ', 'unit' => 'км', 'plan' => '33']), $user))
        ->toThrow(InvalidArgumentException::class, '2-қатор: индикатор номи бўш');
    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $l2->id, 'label' => 'Ички', 'unit' => 'км', 'plan' => 'кўп']), $user))
        ->toThrow(InvalidArgumentException::class, '2-қатор: режа — рақам эмас');
    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $l2->id, 'label' => 'Ички', 'unit' => str_repeat('а', 49), 'plan' => '1']), $user))
        ->toThrow(InvalidArgumentException::class, '2-қатор: ўлчов');
    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $foreign->id, 'label' => 'Насос', 'unit' => 'та', 'plan' => '1']), $user))
        ->toThrow(InvalidArgumentException::class, 'тегишли эмас');
    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $l1->id, 'label' => 'Такрор', 'unit' => 'км', 'plan' => '1']), $user))
        ->toThrow(InvalidArgumentException::class, 'икки марта');
    expect(fn () => ProgressEntry::correct($bogot, [], $user))
        ->toThrow(InvalidArgumentException::class, 'Камида битта');

    expect($bogot->fresh()->lines->pluck('label')->all())->toBe(['Хўжаликлараро каналлар', 'Ички каналлар']);
    expect(RoadmapChangeLog::count())->toBe(0);
});

test('history lists a measure\'s changes newest first with the user', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    $line    = AdminFixture::line($gurlan, 1);

    ProgressEntry::save($roadmap, '2026-08', [$line->id => ['actual' => '10']], $user);
    ProgressEntry::save($roadmap, '2026-09', [$line->id => ['actual' => '20', 'note' => 'икки']], $user);
    ProgressEntry::correct($gurlan, [['id' => $line->id, 'label' => 'Насос агрегатлари', 'unit' => 'дона', 'plan' => '24']], $user);

    $h = ProgressEntry::history($gurlan);
    expect($h->pluck('action')->all())->toBe(['unit', 'note', 'actual', 'actual']);
    expect($h->first()->user->name)->toBe('Админ');
    expect($h->first()->line->label)->toBe('Насос агрегатлари');
    expect(ProgressEntry::history($gurlan, 2))->toHaveCount(2);
    expect(ProgressEntry::history(AdminFixture::measure(1, 1)))->toHaveCount(0);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `cd backend && php artisan test --filter=ProgressEntryCorrectTest`
Expected: FAIL — `correct()` / `history()` undefined.

- [ ] **Step 3: Add `correct()` and `history()` to `ProgressEntry`**

Add this import at the top of `backend/app/Services/Roadmaps/ProgressEntry.php`:

```php
use Illuminate\Support\Collection;
```

Add the constant and the methods (before `assertMonth`):

```php
    /** Lines are renumbered through this offset first so the unique (measure, line_no) index never collides mid-way. */
    private const RENUMBER_OFFSET = 10000;

    /**
     * Replace the measure's indicator definitions with $lines (complete, in order):
     * existing lines are updated (label/unit/plan), new ones (id null) created, lines
     * missing from the list removed (their progress rows go with them), then line_no is
     * renumbered 1..n. A changed plan rewrites the line's pct_of_plan for every
     * reported period. Every change is logged; the measure is recomputed at the end.
     *
     * @param list<array{id?: int|string|null, label?: ?string, unit?: ?string, plan?: ?string}> $lines
     * @throws InvalidArgumentException naming the row («2-қатор: …») — nothing is written
     */
    public static function correct(RoadmapMeasure $measure, array $lines, User $user): void
    {
        $measure->load(['lines.progress', 'roadmap']);
        $existing = $measure->lines->keyBy('id');

        if ($lines === []) {
            throw new InvalidArgumentException('Камида битта индикатор қолсин.');
        }
        $clean = [];
        $seen  = [];
        foreach (array_values($lines) as $i => $l) {
            $no    = $i + 1;
            $label = trim((string) ($l['label'] ?? ''));
            if ($label === '') {
                throw new InvalidArgumentException("{$no}-қатор: индикатор номи бўш.");
            }
            if (mb_strlen($label) > RoadmapMeasureLine::LABEL_MAX) {
                throw new InvalidArgumentException("{$no}-қатор: индикатор номи жуда узун (" . mb_strlen($label) . ' белги).');
            }
            $unit = trim((string) ($l['unit'] ?? ''));
            if (mb_strlen($unit) > 48) {
                throw new InvalidArgumentException("{$no}-қатор: ўлчов 48 белгидан ошмасин.");
            }
            try {
                $plan = EntryValue::parse(isset($l['plan']) ? (string) $l['plan'] : null);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException("{$no}-қатор: режа — {$e->getMessage()}.");
            }
            $id = isset($l['id']) && $l['id'] !== '' ? (int) $l['id'] : null;
            if ($id !== null && ! $existing->has($id)) {
                throw new InvalidArgumentException("{$no}-қатор: индикатор #{$id} бу чора-тадбирга тегишли эмас.");
            }
            if ($id !== null && isset($seen[$id])) {
                throw new InvalidArgumentException("{$no}-қатор: индикатор #{$id} рўйхатда икки марта.");
            }
            if ($id !== null) {
                $seen[$id] = true;
            }
            $clean[] = ['id' => $id, 'label' => $label, 'unit' => $unit === '' ? null : $unit, 'plan' => $plan];
        }

        $roadmap = $measure->roadmap;
        DB::transaction(function () use ($measure, $roadmap, $existing, $clean, $seen, $user): void {
            foreach ($existing as $line) {
                if (isset($seen[$line->id])) {
                    continue;
                }
                $months = $line->progress->whereNotNull('actual_value')->count();
                self::log($user, $roadmap, $measure, $line, null, 'line_removed',
                    self::describe($line->label, $line->unit, $line->plan_value) . " · {$months} ой", null);
                $line->delete();                                   // progress rows cascade
            }

            $models = [];
            foreach ($clean as $i => $l) {
                $no = $i + 1;
                if ($l['id'] === null) {
                    $line = new RoadmapMeasureLine([
                        'roadmap_measure_id' => $measure->id, 'line_no' => self::RENUMBER_OFFSET + $no,
                        'label' => $l['label'], 'unit' => $l['unit'], 'plan_value' => $l['plan'],
                    ]);
                    $line->save();
                    self::log($user, $roadmap, $measure, $line, null, 'line_added', null, self::describe($l['label'], $l['unit'], $l['plan']));
                } else {
                    $line = $existing[$l['id']];
                    if ($line->label !== $l['label']) {
                        self::log($user, $roadmap, $measure, $line, null, 'label', $line->label, $l['label']);
                    }
                    if ((string) $line->unit !== (string) $l['unit']) {
                        self::log($user, $roadmap, $measure, $line, null, 'unit', $line->unit, $l['unit']);
                    }
                    $planChanged = MeasureLineSync::differs($line->plan_value, $l['plan']);
                    if ($planChanged) {
                        self::log($user, $roadmap, $measure, $line, null, 'plan', self::num($line->plan_value), self::num($l['plan']));
                    }
                    $line->fill(['line_no' => self::RENUMBER_OFFSET + $no, 'label' => $l['label'], 'unit' => $l['unit'], 'plan_value' => $l['plan']])->save();
                    if ($planChanged) {
                        foreach ($line->progress as $p) {
                            $pct = MeasureRecomputer::pctOfPlan($l['plan'], $p->actual_value);
                            if (MeasureLineSync::differs($p->pct_of_plan, $pct)) {
                                $p->fill(['pct_of_plan' => $pct])->save();
                            }
                        }
                    }
                }
                $models[$no] = $line;
            }
            foreach ($models as $no => $line) {
                $line->fill(['line_no' => $no])->save();
            }

            $measure->unsetRelation('lines');
            (new MeasureRecomputer())->recompute($measure, $roadmap->year);
        });
    }

    /**
     * The measure's latest changes, newest first, with user and line loaded.
     *
     * @return Collection<int, RoadmapChangeLog>
     */
    public static function history(RoadmapMeasure $measure, int $limit = 30): Collection
    {
        return RoadmapChangeLog::where('roadmap_measure_id', $measure->id)
            ->with(['user', 'line'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit($limit)->get();
    }

    /** «label · unit · plan» for the line_added / line_removed entries. */
    private static function describe(string $label, ?string $unit, null|float|int|string $plan): string
    {
        return $label . ' · ' . ($unit ?? '—') . ' · ' . ($plan === null ? '—' : EntryValue::format($plan));
    }
```

The `history` test expects `['unit', 'note', 'actual', 'actual']`: the `correct` call logs only `unit`; the second save logs `actual` then `note`; newest-first with the `id` tiebreak (entries within one second share `created_at`) gives unit, note, actual (September), actual (August).

- [ ] **Step 4: Run the tests**

Run: `cd backend && php artisan test --filter="ProgressEntryCorrectTest|ProgressEntrySaveTest"`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Roadmaps/ProgressEntry.php backend/tests/Feature/Admin/ProgressEntryCorrectTest.php
git commit -m "feat(admin): ProgressEntry::correct rewrites indicator definitions with logging; history()

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 7: `ProgressImport` service — the importer the upload panel and the command share

**Files:**
- Create: `backend/app/Services/Roadmaps/ImportReport.php`, `backend/app/Services/Roadmaps/ProgressImport.php`
- Modify: `backend/app/Console/Commands/ImportRoadmapProgress.php`
- Test: `backend/tests/Feature/Admin/ProgressImportServiceTest.php` (+ the existing `ImportRoadmapProgressTest` must stay green)

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use App\Services\Roadmaps\ImportReport;
use App\Services\Roadmaps\ProgressImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('run() with write=false reports and rolls back; with write=true it writes and logs an import entry naming the user and file', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $file    = AdminFixture::template('2026-09', ['G8' => 7.8, 'H8' => 'бетон тайёр']);

    try {
        $dry = (new ProgressImport())->run($file, '2026-09', 2026, 'water', false, $user, 'Хоразм.xlsx');
        expect($dry)->toBeInstanceOf(ImportReport::class);
        expect($dry->ok())->toBeTrue();
        expect($dry->written)->toBeFalse();
        expect($dry->period)->toBe('2026-09');
        expect($dry->file)->toBe('Хоразм.xlsx');
        expect($dry->blocks)->toBe(4);
        expect($dry->regions[0])->toMatchArray(['region' => 'Хоразм вилояти', 'in_file' => 4, 'total' => 4, 'lines' => 5, 'reported' => 1, 'missing' => 0, 'done' => 0, 'in_progress' => 4, 'open' => 0]);
        expect($dry->rows()[0])->toBe(['Хоразм вилояти', '4/4', 5, 0, 1, 0, 4, 0]);
        expect($dry->warnings)->toBe([]);
        expect(RoadmapLineProgress::count())->toBe(0);
        expect(RoadmapChangeLog::count())->toBe(0);

        $wet = (new ProgressImport())->run($file, '2026-09', 2026, 'water', true, $user, 'Хоразм.xlsx');
        expect($wet->ok())->toBeTrue();
        expect($wet->written)->toBeTrue();
        expect(RoadmapLineProgress::count())->toBe(5);
        expect(AdminFixture::line(AdminFixture::measure(2, 1, 1733204), 1)->progress()->where('report_period', '2026-09')->value('note'))->toBe('бетон тайёр');
        $log = RoadmapChangeLog::where('action', 'import')->firstOrFail();
        expect($log)->toMatchArray(['user_id' => $user->id, 'roadmap_id' => $roadmap->id, 'roadmap_measure_id' => null, 'report_period' => '2026-09']);
        expect($log->new_value)->toBe('Хоразм.xlsx · 4 та чора-тадбир · 1 та амалда');
        expect($wet->toArray()['regions'][0]['region'])->toBe('Хоразм вилояти');
        expect($wet->toArray()['ok'])->toBeTrue();
    } finally {
        @unlink($file);
    }
});

test('run() reports a broken file as an error without writing, and refuses a period that disagrees with the sheet', function () {
    AdminFixture::roadmap();
    $bad = AdminFixture::template('2026-09', ['G8' => 'кўп']);
    try {
        $r = (new ProgressImport())->run($bad, null, 2026, 'water', true, null, 'bad.xlsx');
        expect($r->ok())->toBeFalse();
        expect($r->error)->toContain('G8');
        expect($r->written)->toBeFalse();
        expect(RoadmapLineProgress::count())->toBe(0);
        expect(RoadmapChangeLog::count())->toBe(0);

        $r2 = (new ProgressImport())->run($bad, '2026-10', 2026, 'water', false);
        expect($r2->error)->toContain('does not match');
        expect((new ProgressImport())->run('C:/nowhere/none.xlsx', null, 2026, 'water', false)->error)->toContain('Файл топилмади');
        expect((new ProgressImport())->run($bad, null, 2026, 'gas', false)->error)->toContain('--domain');
    } finally {
        @unlink($bad);
    }
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd backend && php artisan test --filter=ProgressImportServiceTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: The report value object**

`backend/app/Services/Roadmaps/ImportReport.php`:

```php
<?php

namespace App\Services\Roadmaps;

/** What one ProgressImport::run() produced — printed by the command, rendered by the upload panel. */
final class ImportReport
{
    /**
     * @param list<array{region: string, in_file: int, total: int, lines: int, removed: int, reported: int, relabeled: int,
     *                   cleared: int, repct: int, blank_advance: int, done: int, in_progress: int, open: int, missing: int}> $regions
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly ?string $period,
        public readonly string $file,
        public readonly int $blocks,
        public readonly array $regions,
        public readonly array $warnings,
        public readonly ?string $error,
        public readonly bool $written,
    ) {
    }

    public static function failed(string $file, string $error): self
    {
        return new self(null, $file, 0, [], [], $error, false);
    }

    public function ok(): bool
    {
        return $this->error === null;
    }

    /** The command's table rows: Вилоят · Файлда/жами · Қаторлар · Ўчирилди · Амалда · done · in_progress · open. @return list<array> */
    public function rows(): array
    {
        return array_map(fn (array $s) => [
            $s['region'], "{$s['in_file']}/{$s['total']}", $s['lines'], $s['removed'], $s['reported'], $s['done'], $s['in_progress'], $s['open'],
        ], $this->regions);
    }

    /** Plain array for a Livewire property. @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(), 'period' => $this->period, 'file' => $this->file, 'blocks' => $this->blocks,
            'regions' => $this->regions, 'rows' => $this->rows(), 'warnings' => $this->warnings, 'error' => $this->error, 'written' => $this->written,
        ];
    }
}
```

- [ ] **Step 4: The service**

`backend/app/Services/Roadmaps/ProgressImport.php`:

```php
<?php

namespace App\Services\Roadmaps;

use App\Console\Commands\ImportRoadmap;
use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Models\User;
use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * import:roadmap-progress as a service: reads a filled template, resolves every key,
 * writes definitions + the period's actuals through MeasureLineSync, and reports.
 * write = false does everything inside a transaction and rolls it back (the dry run).
 * A successful write logs one `import` entry per region (user null = terminal).
 */
final class ProgressImport
{
    public function run(string $file, ?string $periodOption, int $year, string $domain, bool $write, ?User $user = null, ?string $displayName = null): ImportReport
    {
        $name = $displayName ?? basename($file);
        if (! is_file($file)) {
            return ImportReport::failed($name, "Файл топилмади: {$file}");
        }
        if ($year < 2000 || $year > 2100) {
            return ImportReport::failed($name, "--year must be a four-digit year, got «{$year}».");
        }
        if (! in_array($domain, ImportRoadmap::DOMAINS, true)) {
            return ImportReport::failed($name, '--domain must be one of: ' . implode(', ', ImportRoadmap::DOMAINS) . " — got «{$domain}».");
        }

        $book = null;
        try {
            $book   = IOFactory::load($file);
            $parsed = (new RoadmapProgressReader())->read($book);
        } catch (RuntimeException $e) {
            return ImportReport::failed($name, $e->getMessage());
        } finally {
            $book?->disconnectWorksheets();          // a road-map workbook is small, but nothing below needs it
            unset($book);
        }

        $option = strtoupper(trim((string) $periodOption));
        $period = $option !== '' ? $option : (string) $parsed['period'];
        if (! RoadmapPeriod::isValid($period)) {
            return ImportReport::failed($name, 'Provide --period as YYYY-MM or YYYY-Qn, or a sheet title carrying «Ҳисобот даври: YYYY-MM».');
        }
        if ($option !== '' && $parsed['period'] !== null && $parsed['period'] !== $period) {
            return ImportReport::failed($name, "--period {$period} does not match the file's own period {$parsed['period']}.");
        }
        if ($parsed['blocks'] === []) {
            return ImportReport::failed($name, 'No indicator rows found (no keys in column A).');
        }

        // Resolve every key before writing anything.
        $byRegion = [];
        foreach ($parsed['blocks'] as $key => $block) {
            $byRegion[RoadmapKey::parse($key)['region']][$key] = $block;
        }
        $work = [];
        foreach ($byRegion as $regionCode => $blocks) {
            $roadmap = Roadmap::where('domain', $domain)->where('region_code', $regionCode)->where('year', $year)
                ->with(['region', 'measures.district', 'measures.lines.progress'])->first();
            if (! $roadmap) {
                return ImportReport::failed($name, "Region {$regionCode} has no road map for {$domain}/{$year} (keys like " . array_key_first($blocks) . ') — run import:roadmap first.');
            }
            $measures = [];
            foreach ($roadmap->measures as $m) {
                $measures[RoadmapKey::make($regionCode, $m->section_no, $m->district?->code, $m->seq_no)] = $m;
            }
            $items = [];
            foreach ($blocks as $key => $block) {
                $m = $measures[$key] ?? null;
                if (! $m) {
                    return ImportReport::failed($name, "{$block['sheet']}!A{$block['row']}: unknown key {$key} — the road map has no such measure.");
                }
                if ($block['lines'] === [] && $m->lines->isNotEmpty()) {
                    return ImportReport::failed($name, "{$block['sheet']}!A{$block['row']}: measure {$key} has {$m->lines->count()} stored lines but no rows in the file — truncated file?");
                }
                $items[] = [$m, $block];
            }
            $work[] = ['roadmap' => $roadmap, 'items' => $items];
        }

        $regions = [];
        DB::beginTransaction();
        try {
            foreach ($work as $entry) {
                $s = ['region' => $entry['roadmap']->region->name_full, 'in_file' => 0, 'total' => $entry['roadmap']->measures->count(),
                    'lines' => 0, 'removed' => 0, 'reported' => 0, 'relabeled' => 0, 'cleared' => 0, 'repct' => 0, 'blank_advance' => 0,
                    'done' => 0, 'in_progress' => 0, 'open' => 0, 'missing' => 0];
                foreach ($entry['items'] as [$measure, $block]) {
                    $s['in_file']++;
                    $r = MeasureLineSync::sync($measure, $block['lines'], $period, $entry['roadmap']->year);
                    foreach (['lines', 'removed', 'reported', 'relabeled', 'cleared', 'repct'] as $k) {
                        $s[$k] += $r[$k];
                    }
                    $s[$r['status']]++;
                    $s['blank_advance'] += $r['blank_advance'] ? 1 : 0;
                }
                $s['missing'] = $s['total'] - $s['in_file'];
                if ($write) {
                    RoadmapChangeLog::record([
                        'user_id' => $user?->id, 'roadmap_id' => $entry['roadmap']->id, 'report_period' => $period, 'action' => 'import',
                        'new_value' => "{$name} · {$s['in_file']} та чора-тадбир · {$s['reported']} та амалда",
                    ]);
                }
                $regions[] = $s;
            }
            $write ? DB::commit() : DB::rollBack();
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return ImportReport::failed($name, $e->getMessage() . ' — nothing written.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return new ImportReport($period, $name, count($parsed['blocks']), $regions, self::warnings($regions, $period), null, $write);
    }

    /** The operator warnings, one sentence each (identical to what the command printed before the refactor). @return list<string> */
    public static function warnings(array $regions, string $period): array
    {
        $out = [];
        foreach ($regions as $n) {
            if ($n['removed'] > 0) {
                $out[] = "{$n['region']}: {$n['removed']} line(s) removed — no longer in the file (their history went with them).";
            }
            if ($n['relabeled'] > 0) {
                $out[] = "{$n['region']}: {$n['relabeled']} line(s) with reported history changed their label — a row inserted mid-block shifts the numbering; check that the history still belongs to the right indicator.";
            }
            if ($n['cleared'] > 0) {
                $out[] = "{$n['region']}: {$n['cleared']} previously reported «Амалда» value(s) cleared by this file.";
            }
            if ($n['repct'] > 0) {
                $out[] = "{$n['region']}: {$n['repct']} reported percentage(s) recomputed after a plan change.";
            }
            if ($n['blank_advance'] > 0) {
                $out[] = "{$n['region']}: {$n['blank_advance']} measure(s) advanced to {$period} with no «Амалда» values — an unfilled template imported for a new period? Their status fell back to Бажарилмоқда until the filled file is imported.";
            }
            if ($n['missing'] > 0) {
                $out[] = "{$n['missing']} of {$n['total']} measure(s) of {$n['region']} are not in the file — left untouched.";
            }
        }

        return $out;
    }
}
```

- [ ] **Step 5: The command becomes a printer**

Replace the body of `handle()` in `backend/app/Console/Commands/ImportRoadmapProgress.php` (keep the signature/description; drop the now-unused imports `DB`, `IOFactory`, `Roadmap`, `RoadmapKey`, `RoadmapPeriod`, `RoadmapProgressReader`, `MeasureLineSync`, `RuntimeException`; add `use App\Services\Roadmaps\ProgressImport;`):

```php
    public function handle(): int
    {
        $file = (string) $this->option('file');
        if ($file === '' || ! is_file($file)) {
            $this->error("Файл топилмади: {$file}");

            return self::FAILURE;
        }

        $domain = (string) $this->option('domain');
        $year   = (int) $this->option('year');
        if ($year < 2000 || $year > 2100) {
            $this->error("--year must be a four-digit year, got «{$this->option('year')}».");

            return self::FAILURE;
        }
        if (! in_array($domain, ImportRoadmap::DOMAINS, true)) {
            $this->error('--domain must be one of: ' . implode(', ', ImportRoadmap::DOMAINS) . " — got «{$domain}».");

            return self::FAILURE;
        }

        $report = (new ProgressImport())->run($file, $this->option('period'), $year, $domain, ! $this->option('dry-run'));
        if (! $report->ok()) {
            $this->error((string) $report->error);

            return self::FAILURE;
        }

        $this->table(['Вилоят', 'Файлда/жами', 'Қаторлар', 'Ўчирилди', 'Амалда', 'done', 'in_progress', 'open'], $report->rows());
        foreach ($report->warnings as $warning) {
            $this->warn($warning);
        }
        $this->info("Period {$report->period}: {$report->blocks} measure(s) processed from " . basename($file) . '.');
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }
```

Run `php -l app/Console/Commands/ImportRoadmapProgress.php` and make sure no unused `use` lines remain.

- [ ] **Step 6: Run the tests**

Run: `cd backend && php artisan test --filter="ProgressImportServiceTest|ImportRoadmapProgressTest|RoadmapTemplateTest"`
Expected: PASS — the 8 existing command tests unchanged, 2 new.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Services/Roadmaps/ImportReport.php backend/app/Services/Roadmaps/ProgressImport.php backend/app/Console/Commands/ImportRoadmapProgress.php backend/tests/Feature/Admin/ProgressImportServiceTest.php
git commit -m "refactor(roadmaps): import:roadmap-progress body moves into ProgressImport (service + report) for the admin upload

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 8: Overview page `/admin/roadmaps`

**Files:**
- Create: `backend/app/Livewire/Admin/RoadmapOverview.php`, `backend/resources/views/livewire/admin/roadmap-overview.blade.php`
- Modify: `backend/resources/views/pages/admin/roadmaps.blade.php`, `backend/public/css/portal.css`
- Test: `backend/tests/Feature/Admin/RoadmapOverviewTest.php`

The «Excel’дан юклаш» buttons dispatch `open-upload`; the panel that listens arrives in Task 11 — until then the buttons do nothing, which is fine.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Livewire\Admin\RoadmapOverview;
use App\Services\Roadmaps\ProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());
afterEach(fn () => Carbon::setTestNow());

test('the overview shows one card per region with the month\'s saved / carried / empty counts, the status split and the last change', function () {
    Carbon::setTestNow('2026-09-15 10:00:00');
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    AdminFixture::report(AdminFixture::measure(2, 1, 1733204), '2026-08', [1 => 7.8, 2 => 33.0]);
    ProgressEntry::save($roadmap, '2026-09', [AdminFixture::line(AdminFixture::measure(2, 1, 1733208), 1)->id => ['actual' => '24']], $user);
    $this->actingAs($user);

    Livewire::test(RoadmapOverview::class)
        ->assertSet('period', '2026-09')
        ->assertSee('Йўл хариталари · мониторинг')
        ->assertSee('2026 йил сентябрь')
        ->assertSee('Хоразм вилояти')
        ->assertSeeHtml('data-region="1733" data-saved="1" data-carry="1" data-empty="2"')
        ->assertSee('4 та чора-тадбир · 5 та индикатор')
        ->assertSeeHtml('Бажарилди <b class="tnum">1</b>')
        ->assertSee('Админ · 15.09.2026 15:00')                       // Tashkent time
        ->assertSee('йўл харитаси йўқ')                                // the regions without a road map
        ->assertSee('Тошкент шаҳри')
        ->call('selectPeriod', '2026-08')
        ->assertSet('period', '2026-08')
        ->assertSeeHtml('data-region="1733" data-saved="1" data-carry="0" data-empty="3"')
        ->call('selectPeriod', 'nonsense')
        ->assertSet('period', '2026-09');

    $this->get('/admin/roadmaps')->assertOk()->assertSee('Хоразм вилояти')->assertSee('Чиқиш');
    $this->get('/admin/roadmaps?davr=2026-07')->assertOk()->assertSee('2026 йил июль');
});

test('a guest gets 403 from the component', function () {
    Livewire::test(RoadmapOverview::class)->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `cd backend && php artisan test --filter=RoadmapOverviewTest`
Expected: FAIL — component class not found.

- [ ] **Step 3: The component**

`backend/app/Livewire/Admin/RoadmapOverview.php`:

```php
<?php

namespace App\Livewire\Admin;

use App\Livewire\RoadmapsPage;
use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Services\Roadmaps\ProgressSheet;
use App\Support\Roadmaps\RoadmapPeriod;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** /admin/roadmaps — one card per region: how much of the chosen month is entered, status split, last change. */
class RoadmapOverview extends Component
{
    #[Url(as: 'davr', except: '')]
    public string $period = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->period = self::month($this->period);
    }

    /** A valid month, else the current one — the panel offers months only. */
    public static function month(string $period): string
    {
        return RoadmapPeriod::isValid($period) && RoadmapPeriod::type($period) === 'month' ? $period : ProgressSheet::currentPeriod();
    }

    public function selectPeriod(string $period): void
    {
        $this->period = self::month($period);
    }

    #[On('progress-imported')]
    public function refresh(): void
    {
        // re-render only
    }

    public function render()
    {
        abort_unless(auth()->check(), 403);

        $roadmaps = Roadmap::where('domain', RoadmapsPage::DOMAIN)->where('year', RoadmapsPage::YEAR)->get()->keyBy('region_code');
        $cards    = [];
        $totals   = ['saved' => 0, 'carry' => 0, 'empty' => 0, 'measures' => 0, 'lines' => 0];
        foreach (Region::orderBy('sort_order')->get() as $region) {
            $roadmap = $roadmaps->get($region->code);
            if (! $roadmap) {
                $cards[] = ['region' => $region, 'roadmap' => null];
                continue;
            }
            $sheet    = ProgressSheet::build($roadmap, $this->period);
            $statuses = ['done' => 0, 'in_progress' => 0, 'open' => 0];
            $lines    = 0;
            foreach ($sheet['measures'] as $m) {
                $statuses[$m['status']]++;
                $lines += count($m['lines']);
            }
            $cards[] = [
                'region'   => $region,
                'roadmap'  => $roadmap,
                'measures' => count($sheet['measures']),
                'lines'    => $lines,
                'counts'   => $sheet['counts'],
                'statuses' => $statuses,
                'last'     => RoadmapChangeLog::where('roadmap_id', $roadmap->id)->with('user')->orderByDesc('created_at')->orderByDesc('id')->first(),
            ];
            foreach (['saved', 'carry', 'empty'] as $k) {
                $totals[$k] += $sheet['counts'][$k];
            }
            $totals['measures'] += count($sheet['measures']);
            $totals['lines']    += $lines;
        }

        return view('livewire.admin.roadmap-overview', [
            'cards'       => $cards,
            'totals'      => $totals,
            'periods'     => ProgressSheet::periodOptions(RoadmapsPage::YEAR),
            'periodLabel' => RoadmapPeriod::label($this->period),
        ]);
    }
}
```

- [ ] **Step 4: The view and the page shell**

`backend/resources/views/livewire/admin/roadmap-overview.blade.php`:

```blade
@php
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
@endphp
<div class="ad-shell">
  <header class="ad-head">
    <div class="ad-head-row">
      <div class="ad-titles">
        <h2 class="ad-title">Йўл хариталари · мониторинг</h2>
        <p class="ad-sub">{{ $periodLabel }} · {{ $totals['measures'] }} та чора-тадбир · {{ $totals['lines'] }} та индикатор</p>
      </div>
      <label class="ad-select">
        <span>Ой</span>
        <select wire:change="selectPeriod($event.target.value)" aria-label="Ҳисобот ойи">
          @foreach($periods as $p)
            <option value="{{ $p }}" @selected($p === $period)>{{ RoadmapPeriod::label($p) }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <div class="ad-strip">
      <span class="k saved"><i></i><b class="tnum">{{ $totals['saved'] }}</b> сақланган</span>
      <span class="k carry"><i></i><b class="tnum">{{ $totals['carry'] }}</b> ўтган ойдан</span>
      <span class="k empty"><i></i><b class="tnum">{{ $totals['empty'] }}</b> бўш</span>
    </div>
  </header>

  <div class="ad-cards">
    @foreach($cards as $c)
      @if(! $c['roadmap'])
        <article class="ad-card none" wire:key="ad-card-{{ $c['region']->code }}">
          <h3>{{ $c['region']->name_full }}</h3>
          <p class="muted">йўл харитаси йўқ</p>
        </article>
      @else
        @php $n = max(1, $c['measures']); $ct = $c['counts']; @endphp
        <article class="ad-card" wire:key="ad-card-{{ $c['region']->code }}"
                 data-region="{{ $c['region']->code }}" data-saved="{{ $ct['saved'] }}" data-carry="{{ $ct['carry'] }}" data-empty="{{ $ct['empty'] }}">
          <a class="stretch" href="{{ route('admin.roadmaps.entry', ['code' => $c['region']->code, 'davr' => $period]) }}" aria-label="{{ $c['region']->name_full }} — киритиш"></a>
          <div class="top">
            <h3>{{ $c['region']->name_full }}</h3>
            <span class="n">{{ $c['measures'] }} та чора-тадбир · {{ $c['lines'] }} та индикатор</span>
          </div>
          <div class="bar" aria-hidden="true">
            <i class="saved" style="width:{{ round($ct['saved'] / $n * 100, 1) }}%"></i><i class="carry" style="width:{{ round($ct['carry'] / $n * 100, 1) }}%"></i><i class="empty" style="width:{{ round($ct['empty'] / $n * 100, 1) }}%"></i>
          </div>
          <div class="legend tnum">
            <span class="saved">{{ $ct['saved'] }} сақланган</span>
            <span class="carry">{{ $ct['carry'] }} ўтган ойдан</span>
            <span class="empty">{{ $ct['empty'] }} бўш</span>
          </div>
          <div class="pills">
            @foreach(['done', 'in_progress', 'open'] as $s)
              <span class="wr-status {{ MeasureDisplay::statusChip($s)['cls'] }}"><i></i>{{ MeasureDisplay::statusChip($s)['label'] }} <b class="tnum">{{ $c['statuses'][$s] }}</b></span>
            @endforeach
          </div>
          <div class="foot">
            <span class="last">
              @if($c['last'])
                {{ $c['last']->user?->name ?? 'терминал' }} · {{ $c['last']->created_at->timezone('Asia/Tashkent')->format('d.m.Y H:i') }}
              @else
                ҳали ўзгариш йўқ
              @endif
            </span>
            <button type="button" class="ad-btn ghost small" wire:click="$dispatch('open-upload', { code: {{ $c['region']->code }} })">Excel’дан юклаш</button>
          </div>
        </article>
      @endif
    @endforeach
  </div>
</div>
```

`backend/resources/views/pages/admin/roadmaps.blade.php` (replace the placeholder):

```blade
@extends('layouts.app')

@section('content')
  <livewire:admin.roadmap-overview />
@endsection
```

- [ ] **Step 5: CSS (shared admin primitives + the overview)**

Append to `backend/public/css/portal.css`:

```css

/* ===================== admin panel: shared primitives ===================== */
.ad-shell{display:flex;flex-direction:column;gap:14px;min-width:0}
.ad-head{display:flex;flex-direction:column;gap:10px}
.ad-head-row{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.ad-titles{flex:1;min-width:200px}
.ad-title{font-size:20px;font-weight:800;letter-spacing:-.01em;margin:0;color:var(--ink)}
.ad-title .rg{font-weight:600;color:var(--muted)}
.ad-sub{margin:2px 0 0;font-size:12.5px;color:var(--muted)}
.ad-select{display:inline-flex;align-items:center;gap:8px;background:var(--paper);border:1px solid var(--line-strong);border-radius:999px;padding:5px 8px 5px 12px;box-shadow:var(--shadow-sm)}
.ad-select span{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.ad-select select{border:0;background:transparent;font:inherit;font-size:13px;font-weight:650;color:var(--ink);outline:none;max-width:220px}
.ad-btn{border:0;border-radius:999px;padding:8px 14px;font:inherit;font-size:12.5px;font-weight:750;cursor:pointer;color:#fff;background:var(--blue);transition:filter .15s,transform .15s;white-space:nowrap}
.ad-btn:hover{filter:brightness(1.06)}
.ad-btn:disabled{opacity:.45;cursor:default;filter:none}
.ad-btn.primary{background:var(--accent-grad);box-shadow:0 8px 20px rgba(23,105,224,.28)}
.ad-btn.ghost{background:var(--paper);color:var(--blue);border:1px solid var(--line-strong)}
.ad-btn.ghost:hover{background:var(--blue-soft)}
.ad-btn.danger{background:var(--task-red)}
.ad-btn.small{padding:5px 11px;font-size:11.5px}
.ad-ico{width:28px;height:28px;border:1px solid var(--line);border-radius:8px;background:var(--paper);color:var(--muted);font-size:13px;line-height:1;display:inline-grid;place-items:center;cursor:pointer;transition:color .15s,border-color .15s}
.ad-ico:hover{color:var(--blue);border-color:var(--blue)}
.ad-ico.danger:hover{color:var(--task-red);border-color:var(--task-red)}
.ad-ico:disabled{opacity:.35;cursor:default}
.ad-strip{display:flex;flex-wrap:wrap;gap:14px;font-size:12.5px;color:var(--muted)}
.ad-strip .k{display:inline-flex;align-items:center;gap:6px}
.ad-strip .k i{width:9px;height:9px;border-radius:50%;background:var(--grey-soft)}
.ad-strip .k.saved i{background:var(--task-green)}
.ad-strip .k.carry i{background:var(--task-amber)}
.ad-strip .k.empty i{background:var(--line-strong)}
.ad-strip b{color:var(--ink)}
.muted{color:var(--muted)}

/* overview cards */
.ad-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
.ad-card{position:relative;background:var(--paper);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow-sm);padding:16px 16px 14px;display:flex;flex-direction:column;gap:10px;transition:transform .18s ease,box-shadow .18s ease}
.ad-card:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(15,42,71,.10)}
.ad-card .stretch{position:absolute;inset:0;border-radius:16px;z-index:0}
.ad-card .stretch:focus-visible{outline:2px solid var(--blue);outline-offset:2px}
.ad-card > *:not(.stretch){position:relative;z-index:1;pointer-events:none}
.ad-card .foot button{pointer-events:auto}
.ad-card h3{margin:0;font-size:14.5px;font-weight:800;color:var(--ink)}
.ad-card .top .n{display:block;margin-top:2px;font-size:11.5px;color:var(--muted)}
.ad-card .bar{display:flex;height:8px;border-radius:99px;overflow:hidden;background:var(--grey-soft)}
.ad-card .bar i{display:block;height:100%}
.ad-card .bar .saved{background:linear-gradient(90deg,#01a358,#37c07a)}
.ad-card .bar .carry{background:linear-gradient(90deg,#d97706,#f2a341)}
.ad-card .bar .empty{background:var(--line-strong)}
.ad-card .legend{display:flex;flex-wrap:wrap;gap:10px;font-size:11.5px;font-weight:650}
.ad-card .legend .saved{color:var(--task-green)}
.ad-card .legend .carry{color:var(--task-amber)}
.ad-card .legend .empty{color:var(--muted)}
.ad-card .pills{display:flex;flex-wrap:wrap;gap:6px}
.ad-card .pills .wr-status b{margin-left:2px}
.ad-card .foot{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:2px;font-size:11.5px;color:var(--muted)}
.ad-card .foot .last{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ad-card.none{opacity:.7}
.ad-card.none .muted{margin:0;font-size:12.5px}
```

- [ ] **Step 6: Run the tests**

Run: `cd backend && php artisan test --filter="RoadmapOverviewTest|AuthTest"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Livewire/Admin/RoadmapOverview.php backend/resources/views/livewire/admin/roadmap-overview.blade.php backend/resources/views/pages/admin/roadmaps.blade.php backend/public/css/portal.css backend/tests/Feature/Admin/RoadmapOverviewTest.php
git commit -m "feat(admin): overview page — per-region entry state for the month, status split, last change

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 9: Entry grid — server side (`RoadmapEntry` component, views, partials)

**Files:**
- Create: `backend/app/Livewire/Admin/RoadmapEntry.php`
- Create: `backend/resources/views/livewire/admin/roadmap-entry.blade.php`, `backend/resources/views/livewire/admin/partials/correction-panel.blade.php`, `backend/resources/views/livewire/admin/partials/history-drawer.blade.php`
- Modify: `backend/resources/views/pages/admin/roadmap-entry.blade.php`
- Test: `backend/tests/Feature/Admin/RoadmapEntryTest.php`

The Alpine behaviour (`adEntry()`) and the grid CSS come in Task 10; the view already carries the `x-` attributes and data attributes that Task 10's JS reads, so write them exactly as shown. Until Task 10 the page renders but cells are static.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Livewire\Admin\RoadmapEntry;
use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasureLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Carbon::setTestNow('2026-09-15 10:00:00');
});
afterEach(fn () => Carbon::setTestNow());

test('the grid renders groups, measure rows with their state, and cells carrying the data the JS reads', function () {
    AdminFixture::roadmap();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 3.0, 2 => 10.0], [1 => 'август']);
    $l1 = AdminFixture::line($bogot, 1);
    $this->actingAs(AdminFixture::user());

    Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->assertSet('period', '2026-09')
        ->assertSee('Амалда киритиш')
        ->assertSee('Хоразм вилояти')
        ->assertSeeInOrder(['Вилоятда амалга ошириладиган йирик лойиҳалар', '«Куловот» каналини реконструкция қилиш.', 'Туманларда амалга ошириладиган лойиҳалар', 'Боғот тумани', 'Хўжаликлараро каналлар', 'Ички каналлар', 'Гурлан тумани', 'Насос агрегатлари'])
        ->assertSee('туман ҳокими Ж.Назаров')
        ->assertSeeHtml('data-measure="' . $bogot->id . '" data-state="carry" data-deadline="0"')
        ->assertSeeHtml('data-line="' . $l1->id . '" data-measure="' . $bogot->id . '" data-field="actual" data-plan="7.8" data-loaded="3" data-loaded-text="3" data-state="carry"')
        ->assertSeeHtml('value="август"')
        ->assertSee('2026 йил августдан кўчирилган')
        ->assertSeeHtml('data-state="empty"')
        ->assertSee('Батафсил (2 банд)')
        ->assertSee('2026 йил декабрь')
        ->assertSee('Қолганини ўзгаришсиз тасдиқлаш');

    $this->get('/admin/roadmaps/1733?davr=2026-08')->assertOk()->assertSee('2026 йил август')->assertSee('/js/ad-entry.js', false);
});

test('save applies the buffer, re-renders with saved cells and reports; an invalid cell is refused with its message', function () {
    AdminFixture::roadmap();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    $l1    = AdminFixture::line($bogot, 1);
    $l2    = AdminFixture::line($bogot, 2);
    $this->actingAs(AdminFixture::user());

    $c = Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->call('save', [$l1->id => ['actual' => '7,8', 'note' => 'тайёр'], $l2->id => ['actual' => '20']])
        ->assertReturned(['ok' => true, 'measures' => 1, 'changes' => 3])
        ->assertSet('flash', '1 та чора-тадбир сақланди')
        ->assertSet('version', 1)
        ->assertDispatched('ad-refresh')
        ->assertSeeHtml('data-measure="' . $bogot->id . '" data-state="saved"')
        ->assertSeeHtml('data-loaded="7.8" data-loaded-text="7,8" data-state="saved"');
    expect((float) RoadmapLineProgress::where('roadmap_measure_line_id', $l2->id)->where('report_period', '2026-09')->value('actual_value'))->toBe(20.0);
    expect($bogot->fresh()->status)->toBe('in_progress');

    $c->call('save', [$l2->id => ['actual' => 'x']])
        ->assertReturned(['ok' => false, 'message' => '«Ички каналлар»: рақам эмас'])
        ->assertSet('error', '«Ички каналлар»: рақам эмас')
        ->assertSee('«Ички каналлар»: рақам эмас');
    expect(RoadmapChangeLog::count())->toBe(3);
});

test('confirmRest, period switching and the history drawer', function () {
    AdminFixture::roadmap();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 7.8, 2 => 33.0]);
    $this->actingAs(AdminFixture::user());

    $c = Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->call('confirmRest')
        ->assertSet('flash', '4 та чора-тадбир ўзгаришсиз тасдиқланди')
        ->assertDispatched('ad-refresh')
        ->assertSeeHtml('data-measure="' . $bogot->id . '" data-state="saved"');
    expect($bogot->fresh()->status)->toBe('done');

    $c->call('selectPeriod', '2026-08')
        ->assertSet('period', '2026-08')
        ->assertSet('flash', '')
        ->assertSee('2026 йил август')
        ->call('selectPeriod', '2026-Q3')
        ->assertSet('period', '2026-09');

    $c->call('openHistory', $bogot->id)
        ->assertSet('openHistory', $bogot->id)
        ->assertSee('Ўзгаришлар тарихи')
        ->assertSee('Ўзгаришсиз тасдиқланди')
        ->assertSee('2 та қатор')
        ->call('closeHistory')
        ->assertSet('openHistory', null);
});

test('the correction panel loads the lines, edits them in memory, saves through ProgressEntry::correct and shows its errors', function () {
    AdminFixture::roadmap();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    $l1    = AdminFixture::line($bogot, 1);
    $l2    = AdminFixture::line($bogot, 2);
    AdminFixture::report($bogot, '2026-08', [1 => 3.9]);
    $this->actingAs(AdminFixture::user());

    $c = Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->call('openPanel', $bogot->id)
        ->assertSet('openPanel', $bogot->id)
        ->assertSet('panelLines', [
            ['id' => $l1->id, 'label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => '7,8', 'months' => 1],
            ['id' => $l2->id, 'label' => 'Ички каналлар', 'unit' => 'км', 'plan' => '33', 'months' => 0],
        ])
        ->assertSee('Индикаторларни тузатиш')
        ->assertSee('1 ойлик ҳисобот ҳам ўчирилади')
        ->call('panelAdd')
        ->set('panelLines.2.label', 'Гидропостлар')
        ->set('panelLines.2.unit', 'та')
        ->set('panelLines.2.plan', '4')
        ->call('panelMove', 2, -1)                       // [l1, new, l2]
        ->call('panelRemove', 2)                         // drops Ички каналлар
        ->call('panelSave')
        ->assertSet('openPanel', null)
        ->assertSet('flash', 'Индикаторлар янгиланди')
        ->assertSet('error', '')
        ->assertDispatched('ad-refresh');
    expect($bogot->fresh()->lines->map(fn ($l) => [$l->line_no, $l->label])->all())->toBe([[1, 'Хўжаликлараро каналлар'], [2, 'Гидропостлар']]);
    expect(RoadmapMeasureLine::find($l2->id))->toBeNull();

    $c->call('openPanel', $bogot->id)
        ->set('panelLines.0.label', '')
        ->call('panelSave')
        ->assertSet('openPanel', $bogot->id)
        ->assertSet('error', '1-қатор: индикатор номи бўш.')
        ->call('panelClose')
        ->assertSet('openPanel', null)
        ->assertSet('panelLines', []);
});

test('region switching redirects; an unknown region is 404; a guest is 403', function () {
    AdminFixture::roadmap();
    $user = AdminFixture::user();

    $this->actingAs($user);
    Livewire::test(RoadmapEntry::class, ['code' => 1733])
        ->call('switchRegion', '1733')
        ->assertRedirect(route('admin.roadmaps.entry', ['code' => 1733, 'davr' => '2026-09']));
    Livewire::test(RoadmapEntry::class, ['code' => 1703])->assertNotFound();
    $this->get('/admin/roadmaps/1703')->assertNotFound();

    auth()->logout();
    Livewire::test(RoadmapEntry::class, ['code' => 1733])->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `cd backend && php artisan test --filter=RoadmapEntryTest`
Expected: FAIL — component class not found.

- [ ] **Step 3: The component**

`backend/app/Livewire/Admin/RoadmapEntry.php`:

```php
<?php

namespace App\Livewire\Admin;

use App\Livewire\RoadmapsPage;
use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Services\Roadmaps\ProgressEntry;
use App\Services\Roadmaps\ProgressSheet;
use App\Support\Roadmaps\EntryValue;
use App\Support\Roadmaps\Roman;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin/roadmaps/{code} — the entry grid for one region and one month. Typing lives
 * in Alpine (public/js/ad-entry.js); this side renders the sheet, saves the buffer,
 * confirms the rest, edits indicator definitions and shows history. $version re-keys
 * every value cell after a write so the morph replaces them with fresh values.
 */
class RoadmapEntry extends Component
{
    public int $code;

    #[Url(as: 'davr', except: '')]
    public string $period = '';

    public int $version = 0;
    public string $flash = '';
    public string $error = '';
    public ?int $openPanel = null;
    /** @var list<array{id: ?int, label: string, unit: string, plan: string, months: int}> */
    public array $panelLines = [];
    public ?int $openHistory = null;

    public function mount(int $code): void
    {
        abort_unless(auth()->check(), 403);
        $this->code = $code;
        abort_if($this->roadmap() === null, 404);
        $this->period = RoadmapOverview::month($this->period);
    }

    private function roadmap(): ?Roadmap
    {
        return Roadmap::where('domain', RoadmapsPage::DOMAIN)->where('region_code', $this->code)->where('year', RoadmapsPage::YEAR)->first();
    }

    /** A measure of this road map, or 404. */
    private function measure(int $id): RoadmapMeasure
    {
        return RoadmapMeasure::where('roadmap_id', $this->roadmap()?->id)->findOrFail($id);
    }

    public function switchRegion(string $code): void
    {
        $this->redirectRoute('admin.roadmaps.entry', ['code' => (int) $code, 'davr' => $this->period]);
    }

    public function selectPeriod(string $period): void
    {
        $this->period      = RoadmapOverview::month($period);
        $this->openPanel   = null;
        $this->panelLines  = [];
        $this->openHistory = null;
        $this->flash       = '';
        $this->error       = '';
        $this->version++;
    }

    /**
     * @param  array<int|string, array{actual?: ?string, note?: ?string}> $edits the Alpine buffer
     * @return array{ok: bool, measures?: int, changes?: int, message?: string}
     */
    public function save(array $edits): array
    {
        abort_unless(auth()->check(), 403);
        $this->flash = '';
        $this->error = '';
        try {
            $r = ProgressEntry::save($this->roadmap(), $this->period, $edits, auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $this->flash = "{$r['measures']} та чора-тадбир сақланди";
        $this->version++;
        $this->dispatch('ad-refresh');

        return ['ok' => true, 'measures' => $r['measures'], 'changes' => $r['changes']];
    }

    public function confirmRest(): void
    {
        abort_unless(auth()->check(), 403);
        $n = ProgressEntry::confirmRest($this->roadmap(), $this->period, auth()->user());
        $this->flash = "{$n} та чора-тадбир ўзгаришсиз тасдиқланди";
        $this->error = '';
        $this->version++;
        $this->dispatch('ad-refresh');
    }

    public function openPanel(int $measureId): void
    {
        $m = $this->measure($measureId)->load('lines.progress');
        $this->panelLines = $m->lines->map(fn ($l) => [
            'id'     => $l->id,
            'label'  => $l->label,
            'unit'   => (string) $l->unit,
            'plan'   => EntryValue::format($l->plan_value),
            'months' => $l->progress->whereNotNull('actual_value')->count(),
        ])->values()->all();
        $this->openPanel   = $measureId;
        $this->openHistory = null;
        $this->error       = '';
    }

    public function panelAdd(): void
    {
        $this->panelLines[] = ['id' => null, 'label' => '', 'unit' => '', 'plan' => '', 'months' => 0];
    }

    public function panelRemove(int $i): void
    {
        if (isset($this->panelLines[$i])) {
            array_splice($this->panelLines, $i, 1);
        }
    }

    public function panelMove(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (! isset($this->panelLines[$i], $this->panelLines[$j])) {
            return;
        }
        [$this->panelLines[$i], $this->panelLines[$j]] = [$this->panelLines[$j], $this->panelLines[$i]];
    }

    public function panelClose(): void
    {
        $this->openPanel  = null;
        $this->panelLines = [];
    }

    public function panelSave(): void
    {
        abort_unless(auth()->check(), 403);
        if ($this->openPanel === null) {
            return;
        }
        try {
            ProgressEntry::correct($this->measure($this->openPanel), $this->panelLines, auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->flash = 'Индикаторлар янгиланди';
        $this->error = '';
        $this->panelClose();
        $this->version++;
        $this->dispatch('ad-refresh');
    }

    public function openHistory(int $measureId): void
    {
        $this->measure($measureId);
        $this->openHistory = $measureId;
        $this->openPanel   = null;
        $this->panelLines  = [];
    }

    public function closeHistory(): void
    {
        $this->openHistory = null;
    }

    #[On('progress-imported')]
    public function imported(): void
    {
        $this->flash = 'Excel файли юкланди';
        $this->error = '';
        $this->version++;
        $this->dispatch('ad-refresh');
    }

    public function render()
    {
        abort_unless(auth()->check(), 403);
        $roadmap      = $this->roadmap();
        $this->period = RoadmapOverview::month($this->period);
        $sheet        = ProgressSheet::build($roadmap, $this->period);

        $groups = [];
        foreach ($sheet['measures'] as $m) {
            $key = $m['section_no'] . ':' . ($m['district_id'] ?? 0);
            $groups[$key] ??= [
                'key'           => $key,
                'roman'         => Roman::of($m['section_no']),
                'section_title' => $m['section_title'],
                'district'      => $m['district_name'],
                'head'          => $m['district_head'],
                'measures'      => [],
            ];
            $groups[$key]['measures'][] = $m;
        }

        $history      = null;
        $historyTitle = '';
        if ($this->openHistory !== null) {
            $hm           = $this->measure($this->openHistory);
            $history      = ProgressEntry::history($hm);
            $historyTitle = $hm->title;
        }

        $loaded = Roadmap::where('domain', RoadmapsPage::DOMAIN)->where('year', RoadmapsPage::YEAR)->pluck('region_code');

        return view('livewire.admin.roadmap-entry', [
            'roadmap'      => $roadmap,
            'region'       => $roadmap->region,
            'regions'      => Region::whereIn('code', $loaded)->orderBy('sort_order')->get(),
            'periods'      => ProgressSheet::periodOptions($roadmap->year),
            'sheet'        => $sheet,
            'groups'       => array_values($groups),
            'today'        => now(ProgressSheet::TZ)->format('Y-m'),
            'history'      => $history,
            'historyTitle' => $historyTitle,
        ]);
    }
}
```

- [ ] **Step 4: The grid view**

`backend/resources/views/livewire/admin/roadmap-entry.blade.php`:

```blade
@php
    use App\Support\Roadmaps\EntryValue;
    use App\Support\Roadmaps\MeasureDisplay;
    use App\Support\Roadmaps\RoadmapPeriod;
    $stateLabels = ['saved' => 'сақланган', 'carry' => 'ўтган ойдан', 'empty' => 'бўш'];
@endphp
<div class="ad-shell ad-entry" x-data="adEntry()" x-on:keydown.window="hotkey($event)" x-on:ad-refresh.window="tick++">
  <header class="ad-head ad-sticky">
    <div class="ad-head-row">
      <div class="ad-titles">
        <h2 class="ad-title">Амалда киритиш <span class="rg">· {{ $region->name_full }}</span></h2>
        <p class="ad-sub">{{ RoadmapPeriod::label($period) }} · {{ count($sheet['measures']) }} та чора-тадбир · йил бошидан жами қийматлар</p>
      </div>
      <label class="ad-select">
        <span>Вилоят</span>
        <select wire:change="switchRegion($event.target.value)" aria-label="Вилоят">
          @foreach($regions as $r)
            <option value="{{ $r->code }}" @selected($r->code === $region->code)>{{ $r->name_full }}</option>
          @endforeach
        </select>
      </label>
      <label class="ad-select">
        <span>Ой</span>
        <select data-current="{{ $period }}" x-on:change="changePeriod($event)" aria-label="Ҳисобот ойи">
          @foreach($periods as $p)
            <option value="{{ $p }}" @selected($p === $period)>{{ RoadmapPeriod::label($p) }}</option>
          @endforeach
        </select>
      </label>
      <div class="ad-search"><input type="search" x-model="search" placeholder="Чора-тадбир, индикатор…" aria-label="Қидирув"></div>
      <button type="button" class="ad-btn ghost" wire:click="$dispatch('open-upload', { code: {{ $region->code }} })">Excel’дан юклаш</button>
    </div>
    <nav class="ad-pills" aria-label="Ҳолат бўйича фильтр">
      <button type="button" :class="filter === 'all' && 'on'" x-on:click="filter = 'all'">Барчаси <b class="tnum">{{ count($sheet['measures']) }}</b></button>
      <button type="button" :class="filter === 'unfilled' && 'on'" x-on:click="filter = 'unfilled'">Тўлдирилмаган <b class="tnum" x-text="count('unfilled')">—</b></button>
      <button type="button" :class="filter === 'edited' && 'on'" x-on:click="filter = 'edited'">Ўзгартирилган <b class="tnum" x-text="count('edited')">0</b></button>
      <button type="button" :class="filter === 'carry' && 'on'" x-on:click="filter = 'carry'">Ўтган ойдан <b class="tnum" x-text="count('carry')">{{ $sheet['counts']['carry'] }}</b></button>
      <button type="button" :class="filter === 'saved' && 'on'" x-on:click="filter = 'saved'">Сақланган <b class="tnum" x-text="count('saved')">{{ $sheet['counts']['saved'] }}</b></button>
    </nav>
  </header>

  @if($error !== '')
    <div class="ad-alert" role="alert" wire:key="err-{{ $version }}-{{ md5($error) }}">
      <span>{{ $error }}</span>
      <button type="button" class="ad-ico" wire:click="$set('error', '')" aria-label="Ёпиш">✕</button>
    </div>
  @endif

  <div class="ad-gridwrap">
    <table class="ad-grid">
      <thead>
        <tr>
          <th class="c-label">Индикатор</th><th class="c-unit">Ўлчов</th><th class="c-plan">Режа</th>
          <th class="c-actual">Амалда</th><th class="c-pct">%</th><th class="c-note">Изоҳ</th>
        </tr>
      </thead>
      @foreach($groups as $g)
        <tbody class="ad-group" wire:key="g-{{ $g['key'] }}">
          <tr><th colspan="6">
            <span class="rn">{{ $g['roman'] }}.</span> <span class="st">{{ $g['section_title'] }}</span>
            @if($g['district'])
              <span class="dn">{{ $g['district'] }}</span>
              @if($g['head'])<span class="hd">{{ $g['head'] }}</span>@endif
            @endif
          </th></tr>
        </tbody>
        @foreach($g['measures'] as $m)
          @php
            $chip      = MeasureDisplay::statusChip($m['status']);
            $pctShown  = MeasureDisplay::pshow($m['pct'], $m['status'] === 'done');
            $deadline  = MeasureDisplay::deadlineChip($m['deadline_text'], $roadmap->year, $m['status'], $today);
            $search    = mb_strtolower(implode(' ', array_merge([$m['title']], $m['details'], array_column($m['lines'], 'label'))));
            $panelOpen = $openPanel === $m['id'];
          @endphp
          <tbody class="ad-m" wire:key="m-{{ $m['id'] }}" data-measure="{{ $m['id'] }}" data-state="{{ $m['state'] }}" data-deadline="{{ $m['deadline_reached'] ? 1 : 0 }}"
                 data-search="{{ $search }}" :class="'st-' + statusClass({{ $m['id'] }})" x-show="show($el)" x-data="{ det: false }">
            <tr class="ad-mrow">
              <td colspan="6">
                <div class="ad-mhead">
                  <div class="wr-ring {{ $m['lines'] === [] ? 'na' : '' }}" role="img" aria-label="Бажарилиш">
                    <svg viewBox="0 0 44 44">
                      <circle class="tr" cx="22" cy="22" r="18"/>
                      <circle class="fg" cx="22" cy="22" r="18" :style="ringStyle({{ $m['id'] }})" style="stroke-dasharray:113.1;stroke-dashoffset:{{ MeasureDisplay::ringOffset($pctShown) }}"/>
                    </svg>
                    <b class="tnum" x-text="ringText({{ $m['id'] }})">{{ $pctShown === null ? '—' : $pctShown . '%' }}</b>
                  </div>
                  <span class="no tnum">{{ $m['seq_no'] }}</span>
                  <div class="ttl">
                    <div class="t">{{ $m['title'] }}</div>
                    @if($m['details'] !== [])
                      <button type="button" class="wr-more" x-on:click="det = !det" :aria-expanded="det">
                        <span class="c" :class="det && 'open'">▸</span>
                        <span x-text="det ? 'Ёпиш' : 'Батафсил ({{ count($m['details']) }} банд)'">Батафсил ({{ count($m['details']) }} банд)</span>
                      </button>
                      <div class="wr-details" x-show="det" x-cloak>@foreach($m['details'] as $line)<p>{{ $line }}</p>@endforeach</div>
                    @endif
                  </div>
                  <span class="wr-status" :class="statusClass({{ $m['id'] }})"><i></i><span x-text="statusLabel({{ $m['id'] }})">{{ $chip['label'] }}</span></span>
                  <span class="wr-tag {{ $deadline['cls'] }}">{{ $deadline['label'] }}</span>
                  <span class="ad-state" :class="mstate({{ $m['id'] }})" x-text="mstateLabel({{ $m['id'] }})">{{ $stateLabels[$m['state']] }}</span>
                  <span class="ad-tools">
                    <button type="button" class="ad-ico" title="Индикаторларни тузатиш" aria-label="Тузатиш" wire:click="openPanel({{ $m['id'] }})">✎</button>
                    <button type="button" class="ad-ico" title="Ўзгаришлар тарихи" aria-label="Тарих" wire:click="openHistory({{ $m['id'] }})">🕓</button>
                  </span>
                </div>
              </td>
            </tr>
            @forelse($m['lines'] as $l)
              @php $pct = $l['plan'] !== null && $l['plan'] != 0.0 && $l['actual'] !== null ? $l['actual'] / $l['plan'] * 100 : null; @endphp
              <tr class="ad-lrow" wire:key="l-{{ $l['id'] }}">
                <td class="c-label">{{ $l['label'] }}</td>
                <td class="c-unit">{{ $l['unit'] }}</td>
                <td class="c-plan tnum">{{ MeasureDisplay::fmt($l['plan']) }}</td>
                <td class="c-actual">
                  <div class="ad-cell actual {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'actual')" wire:key="ac-{{ $l['id'] }}-{{ $version }}"
                       @if($l['state'] === 'carry') title="{{ RoadmapPeriod::label($l['source_period']) }}дан кўчирилган" @endif>
                    <input type="text" inputmode="decimal" class="tnum" value="{{ EntryValue::format($l['actual']) }}" placeholder="—"
                           data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="actual" data-plan="{{ $l['plan'] ?? '' }}" data-loaded="{{ $l['actual'] ?? '' }}" data-loaded-text="{{ EntryValue::format($l['actual']) }}" data-state="{{ $l['state'] }}"
                           x-on:input="onInput($event)" x-on:keydown="cellKey($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — амалда">
                    <span class="msg" x-text="invalid[{{ $l['id'] }}] || ''"></span>
                  </div>
                </td>
                <td class="c-pct tnum"><span :class="pctClass({{ $l['id'] }})" x-text="pctText({{ $l['id'] }})">{{ $pct === null ? '—' : round($pct) . '%' }}</span></td>
                <td class="c-note">
                  <div class="ad-cell note {{ $l['state'] }}" :class="cellClass({{ $l['id'] }}, 'note')" wire:key="nc-{{ $l['id'] }}-{{ $version }}">
                    <input type="text" maxlength="500" value="{{ $l['note'] }}" placeholder="Изоҳ…"
                           data-line="{{ $l['id'] }}" data-measure="{{ $m['id'] }}" data-field="note" data-loaded-text="{{ $l['note'] }}"
                           x-on:input="onInput($event)" x-on:keydown="cellKey($event)" @disabled($panelOpen) aria-label="{{ $l['label'] }} — изоҳ">
                  </div>
                </td>
              </tr>
            @empty
              <tr class="ad-lrow none"><td colspan="6">Индикаторлар белгиланмаган — ✎ орқали қўшинг</td></tr>
            @endforelse
            @if($panelOpen)
              <tr class="ad-prow" wire:key="p-{{ $m['id'] }}"><td colspan="6">@include('livewire.admin.partials.correction-panel')</td></tr>
            @endif
          </tbody>
        @endforeach
      @endforeach
    </table>
  </div>

  <div class="ad-savebar" x-data="{ arm: false }">
    <span class="s">
      <b class="tnum" x-text="editedLines">0</b> та ўзгариш ·
      <span class="tnum" x-text="count('carry')">{{ $sheet['counts']['carry'] }}</span> чора-тадбир ўтган ойдан ·
      <span class="tnum" x-text="count('unfilled')">—</span> тўлдирилмаган
    </span>
    <span class="a">
      <button type="button" class="ad-btn ghost" x-show="editedLines > 0" x-on:click="discard()">Бекор қилиш</button>
      <template x-if="!arm">
        <button type="button" class="ad-btn ghost" x-on:click="arm = true" @disabled($sheet['counts']['confirmable'] === 0)>Қолганини ўзгаришсиз тасдиқлаш</button>
      </template>
      <template x-if="arm">
        <span class="ad-arm">
          <span>{{ $sheet['counts']['confirmable'] }} та чора-тадбир {{ RoadmapPeriod::label($period) }} учун ўтган ой қийматлари билан сақланади.</span>
          <button type="button" class="ad-btn" x-on:click="arm = false; $wire.confirmRest()">Ҳа, тасдиқлаш</button>
          <button type="button" class="ad-btn ghost" x-on:click="arm = false">Йўқ</button>
        </span>
      </template>
      <button type="button" class="ad-btn primary" :disabled="editedLines === 0 || saving" x-on:click="save()">
        <span x-text="saving ? 'Сақланмоқда…' : 'Сақлаш'">Сақлаш</span>
      </button>
    </span>
  </div>

  @if($flash !== '')
    <div class="ad-toast" role="status" wire:key="toast-{{ $version }}" x-data="{ on: true }" x-show="on" x-init="setTimeout(() => on = false, 4000)">{{ $flash }}</div>
  @endif
  <div class="ad-toast warn" role="status" x-show="jsToast !== ''" x-text="jsToast" x-cloak></div>

  @if($openHistory !== null)
    @include('livewire.admin.partials.history-drawer')
  @endif

  <livewire:admin.progress-upload :period="$period" />
</div>
```

- [ ] **Step 5: The two partials**

`backend/resources/views/livewire/admin/partials/correction-panel.blade.php`:

```blade
{{-- Inline editor for one measure's indicator definitions. Expects $panelLines from RoadmapEntry. --}}
<div class="ad-panel" x-data="{ armed: null }">
  <div class="h">
    <b>✎ Индикаторларни тузатиш</b>
    <span class="muted">Тартиб = рўйхат тартиби · ↑/↓ билан кўчиринг</span>
  </div>
  <div class="rows">
    <div class="r head"><span>Индикатор</span><span>Ўлчов</span><span>Режа</span><span></span></div>
    @foreach($panelLines as $i => $pl)
      <div class="r" wire:key="pl-{{ $i }}">
        <textarea rows="2" wire:model="panelLines.{{ $i }}.label" aria-label="Индикатор номи"></textarea>
        <input type="text" wire:model="panelLines.{{ $i }}.unit" maxlength="48" aria-label="Ўлчов">
        <input type="text" inputmode="decimal" class="tnum" wire:model="panelLines.{{ $i }}.plan" aria-label="Режа">
        <span class="t">
          <button type="button" class="ad-ico" wire:click="panelMove({{ $i }}, -1)" @disabled($i === 0) aria-label="Юқорига">↑</button>
          <button type="button" class="ad-ico" wire:click="panelMove({{ $i }}, 1)" @disabled($i === count($panelLines) - 1) aria-label="Пастга">↓</button>
          <template x-if="armed !== {{ $i }}">
            <button type="button" class="ad-ico danger" x-on:click="armed = {{ $i }}" aria-label="Ўчириш">🗑</button>
          </template>
          <template x-if="armed === {{ $i }}">
            <span class="ad-arm small">
              <span>{{ $pl['months'] > 0 ? $pl['months'] . ' ойлик ҳисобот ҳам ўчирилади.' : 'Ўчирилсинми?' }}</span>
              <button type="button" class="ad-btn danger small" x-on:click="armed = null; $wire.panelRemove({{ $i }})">Ўчириш</button>
              <button type="button" class="ad-btn ghost small" x-on:click="armed = null">Йўқ</button>
            </span>
          </template>
        </span>
      </div>
    @endforeach
  </div>
  <div class="f">
    <button type="button" class="ad-btn ghost" wire:click="panelAdd">+ Индикатор қўшиш</button>
    <span class="sp"></span>
    <button type="button" class="ad-btn ghost" wire:click="panelClose">Бекор</button>
    <button type="button" class="ad-btn primary" wire:click="panelSave">Сақлаш</button>
  </div>
</div>
```

`backend/resources/views/livewire/admin/partials/history-drawer.blade.php`:

```blade
{{-- Side panel with one measure's latest changes. Expects $history (Collection) and $historyTitle from RoadmapEntry. --}}
@php use App\Models\RoadmapChangeLog; @endphp
<aside class="ad-hist" role="dialog" aria-label="Ўзгаришлар тарихи">
  <div class="h">
    <b>Ўзгаришлар тарихи</b>
    <button type="button" class="ad-ico" wire:click="closeHistory" aria-label="Ёпиш">✕</button>
  </div>
  <p class="t">{{ $historyTitle }}</p>
  @forelse($history as $h)
    <div class="e" wire:key="h-{{ $h->id }}">
      <div class="w"><span class="tnum">{{ $h->created_at->timezone('Asia/Tashkent')->format('d.m.Y H:i') }}</span> · {{ $h->user?->name ?? 'терминал' }}</div>
      @php $lineLabel = $h->line_label ?? $h->line?->label; @endphp
      <div class="a">{{ RoadmapChangeLog::ACTION_LABELS[$h->action] ?? $h->action }}@if($lineLabel !== null) · {{ mb_strimwidth($lineLabel, 0, 60, '…') }}@endif</div>
      @if($h->old_value !== null || $h->new_value !== null)
        <div class="v"><s>{{ $h->old_value ?? '—' }}</s> → <b>{{ $h->new_value ?? '—' }}</b></div>
      @endif
    </div>
  @empty
    <p class="muted">Ҳали ўзгариш йўқ.</p>
  @endforelse
</aside>
```

- [ ] **Step 6: The page shell (loads the JS before Livewire boots Alpine)**

`backend/resources/views/pages/admin/roadmap-entry.blade.php` (replace the placeholder; the JS file itself is Task 10 — until it exists the `filemtime` call would throw, so create an empty `backend/public/js/ad-entry.js` now, Task 10 fills it):

```blade
@extends('layouts.app')

@section('content')
  <script src="/js/ad-entry.js?v={{ filemtime(public_path('js/ad-entry.js')) }}"></script>
  <livewire:admin.roadmap-entry :code="$code" />
@endsection
```

The `<livewire:admin.progress-upload>` tag inside the grid view needs the component to exist — create a minimal stub now so the page renders, Task 11 replaces it:

`backend/app/Livewire/Admin/ProgressUpload.php` (stub):

```php
<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProgressUpload extends Component
{
    #[Reactive]
    public string $period = '';

    public function render()
    {
        return view('livewire.admin.progress-upload');
    }
}
```

`backend/resources/views/livewire/admin/progress-upload.blade.php` (stub): `<div class="ad-upload-host"></div>`

- [ ] **Step 7: Run the tests**

Run: `cd backend && php artisan test --filter=RoadmapEntryTest`
Expected: PASS (5 tests).

- [ ] **Step 8: Commit**

```bash
git add backend/app/Livewire/Admin backend/resources/views/livewire/admin backend/resources/views/pages/admin/roadmap-entry.blade.php backend/public/js/ad-entry.js backend/tests/Feature/Admin/RoadmapEntryTest.php
git commit -m "feat(admin): entry grid component — sheet rendering, save, confirm rest, correction panel, history drawer

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 10: Entry grid — Alpine behaviour and CSS

**Files:**
- Modify: `backend/public/js/ad-entry.js` (fill the empty file), `backend/public/css/portal.css`

No PHP tests here (the JS runs in the browser); Task 12 exercises it in Chrome. Keep `parse`/`fmt` identical in behaviour to `EntryValue` (Task 3 tests are the reference).

- [ ] **Step 1: The Alpine component**

`backend/public/js/ad-entry.js`:

```js
/* Entry grid for /admin/roadmaps/{code}.
   Holds the edit buffer, computes live % / ring / status, drives keyboard flow and the
   client-side filters, and sends the buffer to Livewire on save. Every value is read
   from the DOM (data-* on the inputs) at evaluation time — Livewire re-keys the cells
   after a write, so nothing here caches server state. `tick` is bumped after each
   server re-render (the `ad-refresh` window event) so DOM-derived counts recompute. */
document.addEventListener('alpine:init', () => {
  Alpine.data('adEntry', () => ({
    edits: {},        // lineId -> { actual?: string, note?: string } — changed cells only
    invalid: {},      // lineId -> message for a cell that does not parse
    filter: 'all',    // all | unfilled | edited | carry | saved
    search: '',
    saving: false,
    jsToast: '',
    tick: 0,
    _timer: null,

    init() {
      window.addEventListener('beforeunload', (e) => {
        if (this.editedLines > 0) { e.preventDefault(); e.returnValue = ''; }
      });
    },

    // ---- numbers (mirror of App\Support\Roadmaps\EntryValue) ----
    parse(raw) {
      const s = String(raw ?? '').trim().replace(/[ \u00A0\u202F\u2009]/g, '').replace(',', '.');
      if (s === '') return null;
      if (!/^-?\d+(\.\d+)?$/.test(s)) throw new Error('рақам эмас');
      const v = parseFloat(s);
      if (v < 0) throw new Error('манфий бўлмасин');
      if (v > 1e12) throw new Error('жуда катта');
      return v;
    },
    sameNumber(a, b) { try { return this.parse(a) === this.parse(b); } catch { return false; } },

    // ---- DOM access ----
    lineEl(id) { return this.$root.querySelector(`input[data-field="actual"][data-line="${id}"]`); },
    inputs(mid) { return this.$root.querySelectorAll(`input[data-field="actual"][data-measure="${mid}"]`); },
    measureEls() { return this.$root.querySelectorAll('tbody.ad-m'); },
    actualOf(el) {
      const e = this.edits[el.dataset.line];
      if (e && 'actual' in e) { try { return this.parse(e.actual); } catch { return null; } }
      return el.dataset.loaded === '' ? null : parseFloat(el.dataset.loaded);
    },

    // ---- per line ----
    pct(id) {
      const el = this.lineEl(id);
      if (!el) return null;
      const plan = el.dataset.plan === '' ? null : parseFloat(el.dataset.plan);
      const actual = this.actualOf(el);
      if (plan === null || plan === 0 || actual === null) return null;
      return actual / plan * 100;
    },
    pctText(id) { this.tick; const p = this.pct(id); return p === null ? '—' : Math.round(p) + '%'; },
    pctClass(id) { this.tick; const p = this.pct(id); return p === null ? 't-none' : (p >= 100 ? 't-green' : (p >= 50 ? 't-amber' : 't-red')); },
    cellClass(id, field) {
      this.tick;
      if (field === 'actual' && this.invalid[id]) return 'invalid';
      const e = this.edits[id];
      return e && field in e ? 'edited' : '';
    },

    // ---- per measure (mirror of MeasureRecomputer::aggregate) ----
    agg(mid) {
      this.tick;
      const tbody = this.$root.querySelector(`tbody[data-measure="${mid}"]`);
      const deadline = !!tbody && tbody.dataset.deadline === '1';
      let total = 0, done = 0, hasActual = false, sum = 0;
      for (const el of this.inputs(mid)) {
        const plan = el.dataset.plan === '' ? null : parseFloat(el.dataset.plan);
        const actual = this.actualOf(el);
        if (actual !== null && actual !== 0) hasActual = true;
        if (plan === null) continue;
        total++;
        const p = (actual === null || plan === 0) ? null : actual / plan * 100;
        if (p !== null && p >= 100) done++;
        sum += Math.max(0, Math.min(100, p ?? 0));
      }
      let status = !hasActual ? 'in_progress' : (total > 0 && done === total ? 'done' : 'open');
      if (total === 0) status = 'in_progress';
      else if (status === 'open' && !deadline) status = 'in_progress';
      return { status, pct: hasActual && total > 0 ? sum / total : null };
    },
    shown(mid) {
      const a = this.agg(mid);
      if (a.pct === null) return null;
      const r = Math.round(a.pct);
      return a.status === 'done' ? r : Math.min(99, r);
    },
    ringText(mid) { const s = this.shown(mid); return s === null ? '—' : s + '%'; },
    ringStyle(mid) { const s = this.shown(mid) ?? 0; return `stroke-dasharray:113.1;stroke-dashoffset:${(113.1 * (1 - s / 100)).toFixed(1)}`; },
    statusClass(mid) { return { done: 'ok', in_progress: 'wait', open: 'bad' }[this.agg(mid).status]; },
    statusLabel(mid) { return { done: 'Бажарилди', in_progress: 'Бажарилмоқда', open: 'Бажарилмаган' }[this.agg(mid).status]; },
    mstate(mid) {
      this.tick;
      for (const el of this.$root.querySelectorAll(`input[data-measure="${mid}"]`)) {
        if (this.edits[el.dataset.line]) return 'edited';
      }
      const tbody = this.$root.querySelector(`tbody[data-measure="${mid}"]`);
      return tbody ? tbody.dataset.state : 'empty';
    },
    mstateLabel(mid) { return { saved: 'сақланган', carry: 'ўтган ойдан', empty: 'бўш', edited: 'ўзгартирилган' }[this.mstate(mid)]; },
    unfilled(mid) {
      for (const el of this.inputs(mid)) { if (this.actualOf(el) === null) return true; }
      return false;
    },

    // ---- filters and counts ----
    show(tbody) {
      this.tick;
      const q = this.search.trim().toLowerCase();
      if (q !== '' && !tbody.dataset.search.includes(q)) return false;
      const mid = tbody.dataset.measure;
      switch (this.filter) {
        case 'unfilled': return this.unfilled(mid);
        case 'edited': return this.mstate(mid) === 'edited';
        case 'carry': return this.mstate(mid) === 'carry';
        case 'saved': return this.mstate(mid) === 'saved';
        default: return true;
      }
    },
    count(kind) {
      this.tick;
      let n = 0;
      for (const tbody of this.measureEls()) {
        const mid = tbody.dataset.measure;
        if (kind === 'unfilled' ? this.unfilled(mid) : this.mstate(mid) === kind) n++;
      }
      return n;
    },
    get editedLines() { return Object.keys(this.edits).length; },

    // ---- typing ----
    onInput(ev) {
      const el = ev.target, id = el.dataset.line, field = el.dataset.field, v = el.value;
      const loaded = el.dataset.loadedText ?? '';
      const same = field === 'actual' ? this.sameNumber(v, loaded) : v === loaded;
      const cur = { ...(this.edits[id] || {}) };
      if (same) delete cur[field]; else cur[field] = v;
      if (Object.keys(cur).length === 0) delete this.edits[id]; else this.edits[id] = cur;
      if (field === 'actual') {
        try { this.parse(v); delete this.invalid[id]; } catch (e) { this.invalid[id] = e.message; }
      }
    },
    cellKey(ev) {
      const el = ev.target;
      if (ev.key === 'Escape') { el.value = el.dataset.loadedText ?? ''; this.onInput({ target: el }); return; }
      if (el.dataset.field !== 'actual') return;
      if (ev.key === 'Enter' || ev.key === 'ArrowDown') { ev.preventDefault(); this.move(el, 1); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); this.move(el, -1); }
    },
    move(el, dir) {
      const all = Array.from(this.$root.querySelectorAll('input[data-field="actual"]')).filter(i => !i.disabled && i.offsetParent !== null);
      const next = all[all.indexOf(el) + dir];
      if (next) { next.focus(); next.select(); }
    },
    hotkey(ev) {
      if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') { ev.preventDefault(); this.save(); }
    },
    changePeriod(ev) {
      if (this.editedLines > 0) {
        ev.target.value = ev.target.dataset.current;
        this.toast('Аввал сақланг ёки «Бекор қилиш»ни босинг');
        return;
      }
      this.$wire.selectPeriod(ev.target.value);
    },
    discard() {
      for (const el of this.$root.querySelectorAll('input[data-line]')) el.value = el.dataset.loadedText ?? '';
      this.edits = {};
      this.invalid = {};
    },

    // ---- save ----
    async save() {
      if (this.saving || this.editedLines === 0) return;
      const badId = Object.keys(this.invalid)[0];
      if (badId) {
        const el = this.lineEl(badId);
        if (el) el.focus();
        this.toast('Аввал хато катакни тузатинг: ' + this.invalid[badId]);
        return;
      }
      this.saving = true;
      try {
        const r = await this.$wire.save(this.edits);
        if (r && r.ok) { this.edits = {}; this.invalid = {}; }
        else if (r && r.message) this.toast(r.message);
      } finally {
        this.saving = false;
      }
    },
    toast(msg) {
      this.jsToast = msg;
      clearTimeout(this._timer);
      this._timer = setTimeout(() => { this.jsToast = ''; }, 4000);
    },
  }));
});
```

- [ ] **Step 2: The grid CSS**

Append to `backend/public/css/portal.css`:

```css

/* ===================== admin panel: entry grid ===================== */
.ad-sticky{position:sticky;top:0;z-index:5;background:linear-gradient(180deg,var(--bg) 85%,rgba(244,247,251,0));padding-bottom:6px;margin:-6px 0 0}
.ad-search input{border:1px solid var(--line-strong);border-radius:999px;padding:7px 14px;font:inherit;font-size:13px;min-width:220px;background:var(--paper);outline:none}
.ad-search input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(43,97,175,.14)}
.ad-pills{display:flex;flex-wrap:wrap;gap:6px}
.ad-pills button{border:1px solid var(--line);border-radius:999px;background:var(--paper);padding:5px 12px;font:inherit;font-size:12px;font-weight:700;color:var(--muted);cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.ad-pills button b{font-weight:800;color:var(--ink)}
.ad-pills button.on{background:var(--blue);border-color:var(--blue);color:#fff}
.ad-pills button.on b{color:#fff}
.ad-alert{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#fdecea;color:var(--task-red);border-radius:12px;padding:10px 14px;font-size:13px;font-weight:600}
.ad-gridwrap{background:var(--paper);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow-sm);overflow:auto}
.ad-grid{width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px}
.ad-grid thead th{position:sticky;top:0;z-index:2;background:#f7fafd;color:var(--muted);font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;text-align:left;padding:9px 10px;border-bottom:1px solid var(--line)}
.ad-grid .c-unit{width:72px}.ad-grid .c-plan{width:96px;text-align:right}.ad-grid .c-actual{width:124px}.ad-grid .c-pct{width:64px;text-align:right}.ad-grid .c-note{width:230px}
.ad-grid td{padding:5px 10px;border-bottom:1px solid #eef2f7;vertical-align:middle}
.ad-group th{position:sticky;top:33px;z-index:1;background:var(--paper);padding:14px 12px 6px;text-align:left;font-size:11.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--line)}
.ad-group .rn{color:var(--blue)}
.ad-group .dn{display:block;margin-top:2px;font-size:14px;font-weight:800;letter-spacing:0;text-transform:none;color:var(--ink)}
.ad-group .hd{margin-left:8px;font-size:12px;font-weight:600;letter-spacing:0;text-transform:none;color:var(--muted)}
.ad-mrow td{background:#fbfcfe;padding:9px 10px 7px}
.ad-mhead{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.ad-mhead .wr-ring{width:38px;height:38px;flex:0 0 auto}
.ad-m.st-wait .wr-ring .fg{stroke:#7c3aed}
.ad-m.st-bad .wr-ring .fg{stroke:var(--task-red)}
.ad-m.st-ok .wr-ring .fg{stroke:var(--task-green)}
.ad-m .wr-ring.na .fg{stroke:transparent}
.ad-mhead .no{font-size:11px;font-weight:800;color:var(--muted);background:var(--grey-soft);border-radius:6px;padding:3px 7px}
.ad-mhead .ttl{flex:1;min-width:240px}
.ad-mhead .ttl .t{font-size:13px;font-weight:700;color:var(--ink);line-height:1.4;overflow-wrap:anywhere}
.ad-mhead .wr-details{margin-top:6px}
.ad-state{font-size:11px;font-weight:700;border-radius:999px;padding:3px 9px;background:var(--grey-soft);color:var(--muted)}
.ad-state.saved{background:var(--green-soft);color:var(--task-green)}
.ad-state.carry{background:#fff4e0;color:#a35e00}
.ad-state.edited{background:#fff4e0;color:#a35e00;outline:1px solid var(--task-amber)}
.ad-state.empty{background:var(--grey-soft);color:var(--muted)}
.ad-tools{display:inline-flex;gap:6px;margin-left:auto}
.ad-lrow .c-label{color:var(--ink);line-height:1.4;overflow-wrap:anywhere}
.ad-lrow .c-unit{color:var(--muted)}
.ad-lrow .c-plan{text-align:right;font-weight:600}
.ad-lrow.none td{color:var(--muted);font-style:italic}
.ad-cell{position:relative}
.ad-cell input{width:100%;border:1px solid var(--line-strong);border-radius:8px;padding:6px 9px;font:inherit;font-size:13px;font-weight:600;color:var(--ink);background:var(--paper);outline:none;transition:border-color .15s,box-shadow .15s,background .15s}
.ad-cell.actual input{text-align:right}
.ad-cell.note input{font-weight:500;color:var(--muted)}
.ad-cell input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(43,97,175,.14)}
.ad-cell input:disabled{background:var(--grey-soft);color:var(--muted)}
.ad-cell.carry input{color:#98a4b3;font-style:italic;background:#fbfcfe}
.ad-cell.empty input{border-style:dashed}
.ad-cell.edited input{border-color:var(--task-amber);background:#fff8ec;color:var(--ink);font-style:normal}
.ad-cell.invalid input{border-color:var(--task-red);background:#fff5f4;color:var(--ink);font-style:normal}
.ad-cell .msg{display:block;font-size:10.5px;color:var(--task-red);min-height:0;line-height:1.2}
.ad-cell .msg:empty{display:none}
.c-pct span{font-weight:800}
.c-pct .t-green{color:var(--task-green)}.c-pct .t-amber{color:var(--task-amber)}.c-pct .t-red{color:var(--task-red)}.c-pct .t-none{color:var(--muted);font-weight:600}
.ad-savebar{position:sticky;bottom:12px;z-index:6;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#102033;color:#fff;border-radius:14px;padding:10px 14px;box-shadow:0 12px 30px rgba(16,32,51,.35)}
.ad-savebar .s{font-size:12.5px;color:rgba(255,255,255,.8)}
.ad-savebar .s b,.ad-savebar .s span{color:#fff}
.ad-savebar .a{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ad-savebar .ad-btn.ghost{background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.25)}
.ad-savebar .ad-btn.ghost:hover{background:rgba(255,255,255,.2)}
.ad-arm{display:inline-flex;align-items:center;gap:8px;font-size:12px;background:rgba(255,255,255,.1);border-radius:12px;padding:6px 10px}
.ad-arm.small{background:#fff4e0;color:#a35e00;font-size:11.5px;padding:4px 8px}
.ad-toast{position:fixed;right:22px;bottom:84px;z-index:20;background:var(--task-green);color:#fff;border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;box-shadow:0 12px 30px rgba(1,163,88,.35)}
.ad-toast.warn{background:var(--task-amber);box-shadow:0 12px 30px rgba(217,119,6,.35)}
.ad-prow td{background:#f4f8ff;padding:10px 12px 12px}
.ad-panel{background:var(--paper);border:1px solid #b9d3f5;border-radius:14px;padding:12px 14px;box-shadow:0 12px 30px rgba(31,79,149,.14)}
.ad-panel .h{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:8px;font-size:13px}
.ad-panel .h .muted{font-size:11.5px}
.ad-panel .rows{display:flex;flex-direction:column;gap:6px}
.ad-panel .r{display:grid;grid-template-columns:minmax(0,1fr) 90px 110px 150px;gap:8px;align-items:start}
.ad-panel .r.head{font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
.ad-panel textarea,.ad-panel input{width:100%;border:1px solid var(--line-strong);border-radius:8px;padding:6px 9px;font:inherit;font-size:12.5px;color:var(--ink);background:#fff;outline:none;resize:vertical}
.ad-panel textarea:focus,.ad-panel input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(43,97,175,.14)}
.ad-panel .t{display:inline-flex;gap:4px;align-items:center;flex-wrap:wrap}
.ad-panel .f{display:flex;gap:8px;align-items:center;margin-top:10px}
.ad-panel .f .sp{flex:1}
.ad-hist{position:fixed;top:0;right:0;bottom:0;width:min(420px,100%);z-index:30;background:var(--paper);border-left:1px solid var(--line);box-shadow:-16px 0 40px rgba(16,32,51,.18);padding:16px 18px;overflow:auto}
.ad-hist .h{display:flex;justify-content:space-between;align-items:center;font-size:15px;margin-bottom:6px}
.ad-hist .t{font-size:12.5px;color:var(--muted);margin:0 0 12px;line-height:1.4}
.ad-hist .e{border-top:1px dashed var(--line);padding:8px 0;font-size:12.5px}
.ad-hist .e .w{font-size:11px;color:var(--muted)}
.ad-hist .e .a{font-weight:700;color:var(--ink);margin-top:2px}
.ad-hist .e .v{margin-top:2px;color:var(--muted)}
.ad-hist .e .v s{color:var(--task-red)}
.ad-hist .e .v b{color:var(--task-green)}
@media (max-width:1100px){
  .ad-grid .c-note{width:160px}
  .ad-mhead .ttl{min-width:160px}
}
@media (max-width:800px){
  .ad-grid thead .c-unit,.ad-grid thead .c-plan,.ad-lrow .c-unit,.ad-lrow .c-plan{display:none}
  .ad-grid .c-note{width:130px}
}
```

- [ ] **Step 3: Smoke-check in the browser**

Run the dev server if it is not running (`php artisan serve` on :8000 is usually up), log in with the dev admin (Task 12 creates one — if you are here first, create it now: `php artisan admin:create admin@local.test --password=<generate one>` and put the line `# dev admin: admin@local.test / <password>` into `backend/.env`, never into a commit or chat). Open `/admin/roadmaps/1722`, type a number into an «Амалда» cell: the cell turns amber, the % and ring update, the save bar counts 1. Press Esc: it reverts. Nothing else is needed here; Task 12 does the full walkthrough.

- [ ] **Step 4: Commit**

```bash
git add backend/public/js/ad-entry.js backend/public/css/portal.css
git commit -m "feat(admin): entry grid behaviour — edit buffer, live percent/status, keyboard flow, filters, save

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 11: Upload panel (`ProgressUpload`)

**Files:**
- Modify: `backend/app/Livewire/Admin/ProgressUpload.php` (replace the stub), `backend/resources/views/livewire/admin/progress-upload.blade.php` (replace the stub), `backend/public/css/portal.css`
- Test: `backend/tests/Feature/Admin/ProgressUploadTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Livewire\Admin\ProgressUpload;
use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

test('the panel opens for a region, refuses a non-xlsx, previews a dry run without writing, then applies and notifies', function () {
    AdminFixture::roadmap();
    $user = AdminFixture::user();
    $this->actingAs($user);
    $file = AdminFixture::template('2026-09', ['G8' => 7.8]);

    try {
        $c = Livewire::test(ProgressUpload::class, ['period' => '2026-09'])
            ->assertSet('open', false)
            ->assertDontSee('Excel’дан юклаш')
            ->dispatch('open-upload', code: 1733)
            ->assertSet('open', true)
            ->assertSet('code', 1733)
            ->assertSet('uploadPeriod', '2026-09')
            ->assertSee('Хоразм вилояти')
            ->set('file', UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))
            ->call('check')
            ->assertHasErrors(['file'])
            ->assertSee('Фақат .xlsx файл')
            ->set('file', new UploadedFile($file, 'Хоразм.xlsx', XLSX_MIME, null, true))
            ->call('check')
            ->assertHasNoErrors()
            ->assertSet('report.ok', true)
            ->assertSet('report.written', false)
            ->assertSee('4/4')
            ->assertSee('Қўллаш');
        expect(RoadmapLineProgress::count())->toBe(0);

        $c->call('apply')
            ->assertSet('report.written', true)
            ->assertSet('done', '4 та чора-тадбир 2026 йил сентябрь учун юкланди')
            ->assertDispatched('progress-imported')
            ->assertSet('file', null);
        expect(RoadmapLineProgress::count())->toBe(5);
        expect(RoadmapChangeLog::where('action', 'import')->value('user_id'))->toBe($user->id);
        expect(RoadmapChangeLog::where('action', 'import')->value('new_value'))->toBe('Хоразм.xlsx · 4 та чора-тадбир · 1 та амалда');

        $c->call('close')->assertSet('open', false)->assertSet('report', null)->assertSet('done', '');
    } finally {
        @unlink($file);
    }
});

test('a broken file shows the error line and apply is refused; the month select is validated', function () {
    AdminFixture::roadmap();
    $this->actingAs(AdminFixture::user());
    $bad = AdminFixture::template('2026-09', ['G8' => 'кўп']);

    try {
        $c = Livewire::test(ProgressUpload::class, ['period' => '2026-09'])
            ->dispatch('open-upload', code: 1733)
            ->set('file', new UploadedFile($bad, 'bad.xlsx', XLSX_MIME, null, true))
            ->set('uploadPeriod', '2026-Q3')
            ->call('check')
            ->assertHasErrors(['uploadPeriod'])
            ->set('uploadPeriod', '2026-09')
            ->call('check')
            ->assertSet('report.ok', false)
            ->assertSee('G8')
            ->assertDontSee('Қўллаш');
        $c->call('apply')->assertSet('report.written', false);
        expect(RoadmapLineProgress::count())->toBe(0);
    } finally {
        @unlink($bad);
    }
});

test('a guest gets 403', function () {
    Livewire::test(ProgressUpload::class, ['period' => '2026-09'])->dispatch('open-upload', code: 1733)->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `cd backend && php artisan test --filter=ProgressUploadTest`
Expected: FAIL — `openFor`/`check` not defined on the stub.

- [ ] **Step 3: The component**

`backend/app/Livewire/Admin/ProgressUpload.php` (replace the stub):

```php
<?php

namespace App\Livewire\Admin;

use App\Livewire\RoadmapsPage;
use App\Models\Region;
use App\Services\Roadmaps\ImportReport;
use App\Services\Roadmaps\ProgressImport;
use App\Support\Roadmaps\RoadmapPeriod;
use App\Services\Roadmaps\ProgressSheet;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Slide-over that takes a filled roadmap:template xlsx: «Текшириш» runs the importer
 * as a dry run and shows its report; «Қўллаш» runs it for real. Both pages embed it
 * once and open it with the `open-upload` event.
 */
class ProgressUpload extends Component
{
    use WithFileUploads;

    #[Reactive]
    public string $period = '';

    public bool $open = false;
    public ?int $code = null;
    public string $uploadPeriod = '';
    public $file = null;
    /** @var array<string, mixed>|null ImportReport::toArray() */
    public ?array $report = null;
    public string $done = '';

    #[On('open-upload')]
    public function openFor(int $code): void
    {
        abort_unless(auth()->check(), 403);
        $this->reset('file', 'report', 'done');
        $this->code         = $code;
        $this->uploadPeriod = RoadmapOverview::month($this->period);
        $this->open         = true;
    }

    public function close(): void
    {
        $this->reset('file', 'report', 'done');
        $this->open = false;
    }

    public function check(): void
    {
        abort_unless(auth()->check(), 403);
        $this->validate(
            ['file' => ['required', 'file', 'max:12288'], 'uploadPeriod' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']],
            ['file.required' => 'Файлни танланг.', 'file.max' => 'Файл 12 МБ дан катта.', 'uploadPeriod.regex' => 'Ойни танланг.', 'uploadPeriod.required' => 'Ойни танланг.'],
        );
        // fileinfo guesses a PhpSpreadsheet workbook as zip on some machines — judge by the name the region gave it.
        if (! str_ends_with(mb_strtolower($this->file->getClientOriginalName()), '.xlsx')) {
            $this->addError('file', 'Фақат .xlsx файл.');

            return;
        }
        $this->done   = '';
        $this->report = $this->run(false)->toArray();
    }

    public function apply(): void
    {
        abort_unless(auth()->check(), 403);
        if ($this->file === null || $this->report === null || ! $this->report['ok']) {
            return;
        }
        $report       = $this->run(true);
        $this->report = $report->toArray();
        if ($report->ok()) {
            $this->done = "{$report->blocks} та чора-тадбир " . RoadmapPeriod::label($report->period) . ' учун юкланди';
            $this->file->delete();
            $this->file = null;
            $this->dispatch('progress-imported');
        }
    }

    private function run(bool $write): ImportReport
    {
        return (new ProgressImport())->run(
            $this->file->getRealPath(), $this->uploadPeriod, RoadmapsPage::YEAR, RoadmapsPage::DOMAIN, $write,
            auth()->user(), $this->file->getClientOriginalName(),
        );
    }

    public function render()
    {
        return view('livewire.admin.progress-upload', [
            'region'  => $this->code === null ? null : Region::where('code', $this->code)->first(),
            'periods' => ProgressSheet::periodOptions(RoadmapsPage::YEAR),
        ]);
    }
}
```

- [ ] **Step 4: The view**

`backend/resources/views/livewire/admin/progress-upload.blade.php` (replace the stub):

```blade
@php use App\Support\Roadmaps\RoadmapPeriod; @endphp
<div class="ad-upload-host">
  @if($open)
    <div class="ad-scrim" wire:click="close" aria-hidden="true"></div>
    <aside class="ad-upload" role="dialog" aria-label="Excel’дан юклаш">
      <div class="h">
        <div>
          <b>Excel’дан юклаш</b>
          <span class="muted">{{ $region?->name_full ?? '' }}</span>
        </div>
        <button type="button" class="ad-ico" wire:click="close" aria-label="Ёпиш">✕</button>
      </div>

      @if($done !== '')
        <div class="ad-done">✓ {{ $done }}</div>
      @else
        <label class="f">
          <span>Тўлдирилган шаблон (.xlsx)</span>
          <input type="file" accept=".xlsx" wire:model="file">
          @error('file')<em>{{ $message }}</em>@enderror
          <div wire:loading wire:target="file" class="muted">Файл юкланмоқда…</div>
        </label>
        <label class="f">
          <span>Ҳисобот ойи</span>
          <select wire:model="uploadPeriod">
            @foreach($periods as $p)<option value="{{ $p }}">{{ RoadmapPeriod::label($p) }}</option>@endforeach
          </select>
          @error('uploadPeriod')<em>{{ $message }}</em>@enderror
        </label>
        <div class="acts">
          <button type="button" class="ad-btn ghost" wire:click="check" wire:loading.attr="disabled">Текшириш</button>
          @if($report && $report['ok'])
            <button type="button" class="ad-btn primary" wire:click="apply" wire:loading.attr="disabled">Қўллаш</button>
          @endif
          <span wire:loading wire:target="check,apply" class="muted">Ишланмоқда…</span>
        </div>
      @endif

      @if($report)
        <div class="rep">
          @if($report['error'] !== null)
            <p class="err">{{ $report['error'] }}</p>
          @else
            <p class="ok">{{ $report['file'] }} · {{ RoadmapPeriod::label($report['period']) }} · {{ $report['blocks'] }} та чора-тадбир{{ $report['written'] ? ' · ёзилди' : ' · текширув, ҳали ёзилмади' }}</p>
            <div class="tbl">
              <table>
                <thead><tr><th>Вилоят</th><th>Файлда/жами</th><th>Қаторлар</th><th>Ўчирилди</th><th>Амалда</th><th>Бажарилди</th><th>Бажарилмоқда</th><th>Бажарилмаган</th></tr></thead>
                <tbody>
                  @foreach($report['rows'] as $row)
                    <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @foreach($report['warnings'] as $w)
              <p class="warn">{{ $w }}</p>
            @endforeach
          @endif
        </div>
      @endif
    </aside>
  @endif
</div>
```

- [ ] **Step 5: Mount the panel on the overview too**

In `backend/resources/views/livewire/admin/roadmap-overview.blade.php` insert, directly before the final closing `</div>` of the root element:

```blade
  <livewire:admin.progress-upload :period="$period" />
```

(The entry grid already mounts it — Task 9.)

- [ ] **Step 6: CSS**

Append to `backend/public/css/portal.css`:

```css

/* ===================== admin panel: upload slide-over ===================== */
.ad-scrim{position:fixed;inset:0;z-index:31;background:rgba(10,17,40,.35)}
.ad-upload{position:fixed;top:0;right:0;bottom:0;width:min(560px,100%);z-index:32;background:var(--paper);border-left:1px solid var(--line);box-shadow:-16px 0 40px rgba(16,32,51,.22);padding:18px 20px;overflow:auto;display:flex;flex-direction:column;gap:14px}
.ad-upload .h{display:flex;justify-content:space-between;align-items:flex-start;font-size:15px}
.ad-upload .h .muted{display:block;font-size:12.5px;margin-top:2px}
.ad-upload .f{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.ad-upload .f input[type=file],.ad-upload .f select{font:inherit;font-size:13px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--ink);border:1px solid var(--line-strong);border-radius:10px;padding:8px 10px;background:#fff}
.ad-upload .f em{font-style:normal;font-weight:600;text-transform:none;letter-spacing:0;color:var(--task-red)}
.ad-upload .f .muted{text-transform:none;letter-spacing:0;font-weight:500}
.ad-upload .acts{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.ad-done{background:var(--green-soft);color:var(--task-green);border-radius:12px;padding:12px 14px;font-weight:700}
.ad-upload .rep{border-top:1px solid var(--line);padding-top:12px;font-size:12.5px}
.ad-upload .rep .ok{color:var(--ink);font-weight:600;margin:0 0 8px}
.ad-upload .rep .err{color:var(--task-red);font-weight:700;background:#fdecea;border-radius:10px;padding:10px 12px;margin:0}
.ad-upload .rep .warn{color:#a35e00;background:#fff4e0;border-radius:10px;padding:8px 12px;margin:6px 0 0}
.ad-upload .tbl{overflow:auto}
.ad-upload table{border-collapse:collapse;width:100%;font-size:12px}
.ad-upload th,.ad-upload td{border-bottom:1px solid var(--line);padding:6px 8px;text-align:left;white-space:nowrap}
.ad-upload th{font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
```

- [ ] **Step 7: Run the tests**

Run: `cd backend && php artisan test --filter="ProgressUploadTest|RoadmapEntryTest|RoadmapOverviewTest"`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Livewire/Admin/ProgressUpload.php backend/resources/views/livewire/admin/progress-upload.blade.php backend/resources/views/livewire/admin/roadmap-overview.blade.php backend/public/css/portal.css backend/tests/Feature/Admin/ProgressUploadTest.php
git commit -m "feat(admin): upload panel — dry-run report then apply for the filled xlsx template

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 12: Browser walkthrough and polish

**Files:** whatever the walkthrough shows needs fixing (CSS, views, JS). No new files.

Use the Chrome tools (`mcp__claude-in-chrome__*`, loaded with one ToolSearch call). The dev server on `http://localhost:8000` serves the working tree. If the dev DB has no `roadmap_change_log` table yet: `php artisan migrate --force`.

- [ ] **Step 1: Dev admin**

Create the account with a generated password and keep the credentials out of the chat and out of git: run `php artisan admin:create admin@local.test --password=<generate 16 random characters>` and append `# dev admin: admin@local.test / <that password>` to `backend/.env` (gitignored). Use those values only in the browser's login form (this is the user's own application on localhost, with credentials created in this session).

- [ ] **Step 2: Walk the flows, screenshot each, fix what looks off**

1. `/admin/roadmaps` as a guest → redirected to `/login`. The login card is centred on the navy gradient; the logo sits in its navy block; wrong password shows «Почта ёки парол нотўғри.».
2. Log in → overview: 14 cards (13 with bars, Тошкент шаҳри muted), the month select shows «2026 йил сентябрь», the sidebar shows «Админ» active and «Чиқиш».
3. Open Сурхондарё → the grid: sticky header and group rows while scrolling, rings and pills, cells in the carry/empty look. Type `12,5` into a cell: amber, % turns, the ring and status pill react, the save bar counts. Enter moves down, Esc reverts, Ctrl+S saves. After the toast the cell is normal (saved) and the state chip says «сақланган». Reload the page: the value is still there.
4. Type a letter into a cell: red border and «рақам эмас» under the cell; «Сақлаш» toasts instead of saving.
5. Search «Дамариқ» → one measure stays; the pills filter; clear both.
6. ✎ on a measure: the panel opens below it, its inputs are disabled, ↑/↓ reorder, 🗑 arms then removes, «+ Индикатор қўшиш», «Сақлаш» → toast «Индикаторлар янгиланди» and the rows re-render. Use a throwaway line you add and then remove so the real data is not changed.
7. 🕓 → the history drawer lists what you just did with times in Tashkent time.
8. «Қолганини ўзгаришсиз тасдиқлаш» → the inline confirmation appears; press «Йўқ» (do **not** confirm on the dev DB with real data unless the user asked).
9. «Excel’дан юклаш» → choose a template written by `php artisan roadmap:template --region=1722 --period=2026-09 --out=<scratchpad>/1722.xlsx` (leave it unfilled), «Текшириш» → the report table and the «advanced with no «Амалда» values» warning appear; close without applying.
10. Public `/roadmaps` for the same region shows the value you saved in step 3.
11. «Чиқиш» → back to `/` as a guest; `/admin/roadmaps` redirects again.

Fix anything visibly wrong in the CSS/view/JS (a clipped column, a cell that does not turn amber, a sticky header overlapping a group row, a broken toast) and re-check that spot. Save a screenshot of the overview and of the grid to the scratchpad for the final report.

- [ ] **Step 3: Undo the walkthrough's data changes**

The value typed in step 3 is real data in the dev DB. Either set it back to what it was (or clear it and save), or leave it and say so in the final report. Delete the scratch template file.

- [ ] **Step 4: Commit the polish**

```bash
git add backend/public/css/portal.css backend/public/js/ad-entry.js backend/resources/views
git commit -m "fix(admin): visual polish from the browser walkthrough

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```
(Skip the commit if nothing changed.)

---

### Task 13: Docs, memory, full suite, finish

**Files:**
- Modify: `backend/docs/roadmap-monitoring.md`, `CLAUDE.md`, `docs/superpowers/specs/2026-09-30-roadmaps-admin-panel-design.md`
- Memory: `C:\Users\y.utepbergenov\.claude\projects\C--Users-y-utepbergenov-Desktop-hududlar-monitoringi-portali\memory\water-roadmaps-registry.md` + `MEMORY.md`

- [ ] **Step 1: Runbook section**

Insert into `backend/docs/roadmap-monitoring.md` after the «## The monthly loop» section (before «## What healthy output looks like»):

```markdown
## Entering values in the panel (`/admin/roadmaps`)

The panel does what the commands below do, from the browser. One admin account:

```powershell
cd backend
php artisan admin:create you@example.org          # prompts for the password twice; re-run to reset it
```

Log in at `/login`; «Админ» appears in the sidebar. The overview shows every region
for the chosen month with three counts — **сақланган** (the month is written),
**ўтган ойдан** (the fields are pre-filled from the latest earlier month, nothing
written yet), **бўш** (no value anywhere) — plus the status split and the last change.
Click a card to open the grid.

**The grid** is one region and one month. Under every measure its indicator lines:
label · unit · plan · «Амалда» · % · «Изоҳ». Cell looks: normal = saved for this
month; grey italic = carried over (hover shows the month); amber = you changed it and
have not saved; dashed = empty; red = does not parse (the message sits under the
cell). Type `12,5` or `12.5` or `1 240`; Enter/↓ and ↑ move between «Амалда» cells,
Esc restores a cell, Ctrl+S saves. The % cell, the ring and the status pill follow
your typing with the same rules the import uses.

**Сақлаш** writes every measure you touched **as a whole** for the month: your edits,
plus the carried-over values of its other lines (a half-reported measure would count
the missing lines as 0 %). Measures you did not touch stay «ўтган ойдан» until you
either edit them or press **«Қолганини ўзгаришсиз тасдиқлаш»**, which writes the
carried values of every remaining measure for the month (after an inline
confirmation). Notes carry over exactly like values.

**✎ (тузатиш)** edits a measure's indicator definitions in place — label, unit, plan,
order, add or remove lines. Removing a line deletes its reported months (the button
says how many). A changed plan rewrites that line's % for every reported month.
**🕓 (тарих)** lists the measure's changes: who, when, what, old → new. Every save,
confirmation, correction and upload is logged in `roadmap_change_log`.

**Excel’дан юклаш** takes the filled template: choose the file and the month,
«Текшириш» shows exactly what `import:roadmap-progress --dry-run` would print (table,
warnings, or the abort reason in red) without writing, «Қўллаш» applies it. The
commands keep working and are what the panel calls.
```

- [ ] **Step 2: CLAUDE.md**

In the routes table of `CLAUDE.md` add after the `/roadmaps` row:

```markdown
| `/login` · `/logout` | `Auth\LoginController` | The portal's single admin login (session auth, `users` table; account via `php artisan admin:create`). Public pages stay public |
| `/admin/roadmaps` | `Admin\RoadmapOverview` | Auth-only. Per-region entry state for a month (saved · ўтган ойдан · бўш), status split, last change; card → grid |
| `/admin/roadmaps/{code}` | `Admin\RoadmapEntry` (+ `Admin\ProgressUpload`) | Auth-only entry grid: type «Амалда»/«Изоҳ» per indicator line (Alpine in `public/js/ad-entry.js`), whole-measure saves through `ProgressEntry`/`MeasureLineSync`, «confirm the rest», inline indicator corrections, history drawer, xlsx upload with dry-run preview (`ProgressImport`, shared with `import:roadmap-progress`). CSS block `ad-` |
```

In the pipeline list, item 4 (water road maps), append one sentence: `The admin panel (`/admin/roadmaps`) writes the same tables through `ProgressEntry` and reuses `ProgressImport` for uploads; every change is logged in `roadmap_change_log`.`

Also update the line about styling to mention `ad-` among the namespaced blocks (`sec-`, `wr-`, `ad-`).

- [ ] **Step 3: Spec as-built note**

Insert after the `**Scope:**` line of the spec, a blockquote:

```markdown
> **As-built (2026-09-30):** upload limit is Livewire's default 12 MB (largest real file
> 150 KB); the CLI import now also logs an `import` entry (user null → «терминал»); the
> overview computes counts with `ProgressSheet::build()` per region rather than one
> aggregate query; no native `confirm()` dialogs — destructive buttons arm on first click
> and execute on the second; `ProgressSheet::periodOptions()` takes the year, not the
> road map; the correction panel is refused an empty line list (keep at least one line).
```

- [ ] **Step 4: Memory**

Append to the memory note `water-roadmaps-registry.md` a dated paragraph: admin panel shipped 2026-09-30 (branch `roadmap-admin` → main), routes, `admin:create`, the whole-measure save rule, the change log, `ProgressImport` shared with the CLI, the dev admin exists in the dev DB (`admin@local.test`, password in `backend/.env` comment), and the pending items (regions' monthly cycle now via the panel; sidebar link for public `/roadmaps` still pending). Update the `MEMORY.md` hook line accordingly.

- [ ] **Step 5: Full suite**

Run from `backend/` in the foreground: `php artisan test` (≈10 minutes; the shared test DB must not be used by anything else).
Expected: all green, roughly 700 tests.

- [ ] **Step 6: Commit and finish**

```bash
git add CLAUDE.md backend/docs/roadmap-monitoring.md docs/superpowers/specs/2026-09-30-roadmaps-admin-panel-design.md
git commit -m "docs(admin): runbook section, routes, as-built note for the road-map admin panel

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

Then use superpowers:finishing-a-development-branch (merge `roadmap-admin` into `main`, push, delete the branch — the user's standing instruction for this project is "push all changes to main").
