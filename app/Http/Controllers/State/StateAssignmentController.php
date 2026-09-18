<?php

namespace App\Http\Controllers\State;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StateAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isPrivilegedOperator() || $request->user()?->can('state_head.view'), 403);

        $states = DB::connection('platform')->table('states')->orderBy('name')->get();
        $occupied = [];
        foreach ($states as $state) {
            $holder = User::query()
                ->where('role', OperatorRole::STATE_HEAD)
                ->where('status', 'ACTIVE')
                ->where('state_id', $state->id)
                ->first();
            $occupied[$state->id] = $holder?->name;
        }

        return view('state.assignments', [
            'operators' => User::query()->where('role', OperatorRole::STATE_HEAD)->orderBy('name')->get(),
            'states' => $states,
            'occupied' => $occupied,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isPrivilegedOperator() || $request->user()?->can('state_head.update'), 403);
        abort_unless($user->role === OperatorRole::STATE_HEAD, 422, 'Only State Head accounts can be assigned a state.');
        $data = $request->validate([
            'state_id' => ['required', 'integer', 'min:1'],
            'transfer' => ['nullable', 'boolean'],
        ]);
        $exists = DB::connection('platform')->table('states')->where('id', $data['state_id'])->exists();
        abort_unless($exists, 422, 'Unknown state.');
        try {
            app(\App\Services\StateHeadSeat::class)->assign(
                $user,
                (int) $data['state_id'],
                $request->user(),
                $request->boolean('transfer'),
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return back()->withErrors(['state_id' => $exception->getMessage()]);
        }

        return back()->with('status', $user->name.' is assigned to the selected state.');
    }
}
