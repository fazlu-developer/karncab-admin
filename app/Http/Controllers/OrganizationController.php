<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function states(Request $request): View
    {
        abort_unless($request->user()?->can('state.view') || $request->user()?->can('platform.admin') || $request->user()?->can('state.operate'), 403);
        $states = DB::connection('platform')->table('states')->orderBy('name')->get();

        return view('organization.states', compact('states'));
    }

    public function districts(Request $request): View
    {
        abort_unless($request->user()?->can('district.view') || $request->user()?->can('platform.admin') || $request->user()?->can('state.operate'), 403);
        $q = DB::connection('platform')->table('districts')
            ->join('states', 'states.id', '=', 'districts.state_id')
            ->select('districts.*', 'states.name as state_name')
            ->orderBy('states.name')
            ->orderBy('districts.name');
        $actor = $request->user();
        if ($actor?->isStateHead() && $actor->state_id) {
            $q->where('districts.state_id', $actor->state_id);
        }

        return view('organization.districts', ['districts' => $q->get()]);
    }
}
