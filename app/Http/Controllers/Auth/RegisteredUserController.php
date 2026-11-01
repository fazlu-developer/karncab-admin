<?php

namespace App\Http\Controllers\Auth;

use App\Events\OperatorRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterOperatorRequest;
use App\Repositories\OperatorRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterOperatorRequest $request, OperatorRepository $operators): RedirectResponse
    {
        $comment = trim((string) $request->validated('comment'));
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'access_note')) {
            Schema::table('users', function ($table) {
                $table->string('access_note', 500)->nullable();
            });
        }
        $user = $operators->createOperator([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'access_note' => $comment !== '' ? $comment : null,
        ]);
        $this->storeAccessLead($request->validated('name'), $request->validated('email'), $comment);

        event(new OperatorRegistered($user));
        Auth::login($user);

        return redirect()->route('dashboard');
    }

    private function storeAccessLead(string $name, string $email, string $comment): void
    {
        if (! Schema::connection('platform')->hasTable('leads')) {
            return;
        }
        $row = [
            'type' => 'OPERATOR_ACCESS',
            'status' => 'NEW',
            'name' => $name,
            'email' => $email,
            'message' => $comment !== '' ? $comment : 'Operator access request',
        ];
        $schema = Schema::connection('platform');
        if ($schema->hasColumn('leads', 'created_at')) {
            $row['created_at'] = now();
        }
        if ($schema->hasColumn('leads', 'updated_at')) {
            $row['updated_at'] = now();
        }
        $allowed = [];
        foreach ($row as $column => $value) {
            if ($schema->hasColumn('leads', $column)) {
                $allowed[$column] = $value;
            }
        }
        if ($allowed !== []) {
            DB::connection('platform')->table('leads')->insert($allowed);
        }
    }
}
