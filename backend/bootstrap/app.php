<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetFrameAncestors::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/admin/roadmaps');

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A login form that sat open until its session expired throws TokenMismatchException;
        // a bare 419 page tells the visitor nothing. Send them back to the form with the reason.
        // Matched on the status, not on the class: Handler::render() runs prepareException()
        // — which turns TokenMismatchException into a 419 HttpException — BEFORE the render
        // callbacks, so a callback typed on TokenMismatchException would never fire.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException || $request->expectsJson()) {
                return null;                      // anything else keeps the framework's own answer
            }

            return redirect()->route('login')->withErrors(['email' => 'Сессия муддати тугади — қайта киринг.']);
        });
    })->create();
