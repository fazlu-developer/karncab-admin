<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'customer.api' => \App\Http\Middleware\AuthenticateCustomerApi::class,
            'ability' => \App\Http\Middleware\EnforceRouteAbility::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/v1/payments/webhooks/*',
            'api/v1/places/*',
            'api/v1/rides/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired. Please sign in again.'], 419);
            }

            return redirect()->route('login')->with('status', 'Your session expired. Sign in and try again.');
        });
    })->create();
