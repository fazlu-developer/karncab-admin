<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginOperatorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginOperatorRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        $remember = $request->boolean('remember');
        if (! Auth::attempt($credentials, $remember)) {
            $provisioned = app(\App\Services\OperatorProvisioner::class)->acceptPlatformPassword(
                (string) $credentials['email'],
                (string) $credentials['password'],
            );
            if (! $provisioned || ! Auth::attempt($credentials, $remember)) {
                return back()->withErrors([
                    'email' => 'These credentials do not match our records.',
                ])->onlyInput('email');
            }
        }

        $request->session()->regenerate();
        $user = $request->user();
        if ($user) {
            \App\Services\OperatorTerritorySync::apply($user);
        }

        return redirect()->intended(route($request->user()->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
