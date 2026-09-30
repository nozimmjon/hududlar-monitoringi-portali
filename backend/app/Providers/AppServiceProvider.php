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
        // Two limits: five attempts a minute per e-mail + IP, and twenty attempts per fifteen minutes
        // per e-mail alone — the second one keeps a forged X-Forwarded-For from widening the per-IP
        // limit into an account-wide bypass. Either limit hitting gets the same form back with a message.
        RateLimiter::for('login', function (Request $request) {
            // An array or a nested value in `email` is not a login attempt worth keying by; it
            // buckets under '' and fails validation anyway.
            $raw   = $request->input('email');
            $email = is_string($raw) ? mb_strtolower(trim($raw)) : '';

            $response = function (Request $request, array $headers) {
                // The fifteen-minute limit hands back a wait nobody counts in seconds.
                $seconds = (int) ($headers['Retry-After'] ?? 60);
                $wait    = $seconds > 120 ? ((int) ceil($seconds / 60)) . ' дақиқадан' : "{$seconds} сониядан";

                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => "Жуда кўп уриниш — {$wait} кейин қайта уриниб кўринг."]);
            };

            return [
                Limit::perMinute(5)->by($email . '|' . $request->ip())->response($response),
                Limit::perMinutes(15, 20)->by($email)->response($response),
            ];
        });
    }
}
