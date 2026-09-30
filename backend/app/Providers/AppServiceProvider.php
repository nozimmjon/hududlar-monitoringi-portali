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
