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
            $email = (string) $request->string('email')->lower();

            $response = function (Request $request, array $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);

                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => "Жуда кўп уриниш — {$seconds} сониядан кейин қайта уриниб кўринг."]);
            };

            return [
                Limit::perMinute(5)->by($email . '|' . $request->ip())->response($response),
                Limit::perMinutes(15, 20)->by($email)->response($response),
            ];
        });
    }
}
