<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\RouteAbility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceRouteAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }
        if ($request->routeIs('dashboard')) {
            $home = $user->homeRoute();
            if ($home !== 'dashboard') {
                return redirect()->route($home);
            }
        }
        if ($user->role === OperatorRole::SUPER_ADMIN) {
            return $next($request);
        }
        $abilities = RouteAbility::anyOf($request);
        if ($abilities === null) {
            return $next($request);
        }
        foreach ($abilities as $ability) {
            if ($user->can($ability)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
