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
        abort_unless($request->user()?->isPrivilegedOperator(), 403);

        $states = DB::connection('platform')->table('states')->orderBy('name')->get();

        return view('state.assignments', [
            'operators' => User::query()->where('role', OperatorRole::STATE_HEAD)->orderBy('name')->get(),
            'states' => $states,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isPrivilegedOperator(), 403);
        abort_unless($user->role === OperatorRole::STATE_HEAD, 422, 'Only State Head accounts can be assigned a state.');
        $data = $request->validate([
            'state_id' => ['required', 'integer', 'min:1'],
        ]);
        $exists = DB::connection('platform')->table('states')->where('id', $data['state_id'])->exists();
        abort_unless($exists, 422, 'Unknown state.');
        $user->update(['state_id' => (int) $data['state_id'], 'district_id' => null]);

        return back()->with('status', $user->name.' is assigned to the selected state.');
    }
}
