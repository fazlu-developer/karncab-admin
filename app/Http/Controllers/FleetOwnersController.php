<?php

namespace App\Http\Controllers;

use App\Services\FleetOwnerDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FleetOwnersController extends Controller
{
    public function __construct(private readonly FleetOwnerDirectoryService $fleets) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('fleet.view') || $request->user()?->can('fleet_owner.view'), 403);

        return view('fleet_owners.index', [
            'owners' => $this->fleets->list($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('fleet.manage') || $request->user()?->can('fleet_owner.create'), 403);

        return view('fleet_owners.form', $this->fleets->formMeta($request->user()) + [
            'owner' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('fleet.manage') || $request->user()?->can('fleet_owner.create'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trade_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'address' => ['nullable', 'string', 'max:255'],
            'state_id' => ['required', 'integer'],
            'district_id' => ['required', 'integer'],
            'franchise_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:ACTIVE,PENDING,SUSPENDED'],
        ]);
        $this->fleets->create($request->user(), $data);

        return redirect()->route('fleet-owners.index')->with('status', 'Fleet Owner created. Multiple fleet owners are allowed in the same district.');
    }

    public function show(Request $request, int $id): View
    {
        abort_unless($request->user()?->can('fleet.view') || $request->user()?->can('fleet_owner.view'), 403);

        return view('fleet_owners.show', [
            'owner' => $this->fleets->find($request->user(), $id),
        ]);
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('fleet.manage') || $request->user()?->can('fleet_owner.create'), 403);
        $approve = $request->boolean('approve');
        $this->fleets->verify($request->user(), $id, $approve, $request->input('reason'));

        return redirect()->route('fleet-owners.show', $id)->with('status', $approve ? 'Fleet owner verified. They can now use the fleet app.' : 'Fleet owner application rejected.');
    }
}
