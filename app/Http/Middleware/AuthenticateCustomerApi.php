<?php

namespace App\Http\Middleware;

use App\Services\PlatformCustomerBinder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCustomerApi
{
    public function __construct(private readonly PlatformCustomerBinder $binder) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('X-Karnacab-Website-Signature')) {
            $request->setUserResolver(fn () => $this->binder->fromWebsiteRequest($request));

            return $next($request);
        }

        if ($request->user()) {
            return $next($request);
        }

        abort(401, 'Sign in to book a ride');
    }
}
