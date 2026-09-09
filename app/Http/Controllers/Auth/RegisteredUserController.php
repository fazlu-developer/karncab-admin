<?php

namespace App\Http\Controllers\Auth;

use App\Events\OperatorRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterOperatorRequest;
use App\Repositories\OperatorRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterOperatorRequest $request, OperatorRepository $operators): RedirectResponse
    {
        $user = $operators->createOperator([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        event(new OperatorRegistered($user));
        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
