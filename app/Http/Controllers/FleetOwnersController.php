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

    public function edit(Request $request, int $id): View
    {
        abort_unless($request->user()?->can('fleet.manage') || $request->user()?->can('fleet_owner.create'), 403);

        return view('fleet_owners.form', $this->fleets->formMeta($request->user()) + [
            'owner' => $this->fleets->find($request->user(), $id),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('fleet.manage') || $request->user()?->can('fleet_owner.create'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trade_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8'],
            'address' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'company_type' => ['nullable', 'string', 'max:40'],
            'state_id' => ['required', 'integer'],
            'district_id' => ['required', 'integer'],
            'franchise_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:ACTIVE,PENDING,SUSPENDED'],
        ]);
        $this->fleets->update($request->user(), $id, $data);

        return redirect()->route('fleet-owners.show', $id)->with('status', 'Fleet owner details updated.');
    }

    public function storeDocument(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('fleet.manage') || $request->user()?->can('fleet_owner.create'), 403);
        $data = $request->validate([
            'type' => ['required', 'in:GST,PAN,COMPANY_REG,ADDRESS_PROOF,BANK,TRADE_LICENSE'],
            'file' => ['required', 'file', 'max:8192'],
        ]);
        $file = $request->file('file');
        abort_unless($file, 422, 'Upload a file.');
        $binary = file_get_contents($file->getRealPath() ?: $file->getPathname()) ?: '';
        abort_unless($binary !== '', 422, 'Upload a file.');
        $this->fleets->storeDocument(
            $request->user(),
            $id,
            $data,
            $binary,
            $file->getMimeType() ?: 'application/octet-stream',
            $file->getClientOriginalName() ?: 'document',
        );

        return redirect()->route('fleet-owners.show', $id)->with('status', 'Attachment saved.');
    }

    public function documentFile(Request $request, int $id, string $type): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->can('fleet.view') || $request->user()?->can('fleet_owner.view'), 403);
        $owner = $this->fleets->find($request->user(), $id);
        $docs = json_decode((string) ($owner->documents_json ?? '[]'), true) ?: [];
        $match = null;
        foreach ($docs as $doc) {
            if (strtoupper((string) ($doc['type'] ?? '')) === strtoupper($type)) {
                $match = $doc;
            }
        }
        abort_unless(is_array($match), 404);
        $key = (string) ($match['storageKey'] ?? '');
        $path = app(\App\Services\KycFileStore::class)->absolutePath($key);
        if ($path) {
            return response()->file($path, ['Content-Type' => $match['mime'] ?? 'application/octet-stream']);
        }
        $base = rtrim((string) config('services.api_public', 'https://api.karnacab.in'), '/');
        if ($key !== '') {
            return redirect()->away($base.'/storage/'.ltrim($key, '/'));
        }
        abort(404, 'Attachment file is missing.');
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('fleet.manage') || $request->user()?->can('fleet_owner.create'), 403);
        $approve = $request->boolean('approve');
        $this->fleets->verify($request->user(), $id, $approve, $request->input('reason'));

        return redirect()->route('fleet-owners.show', $id)->with('status', $approve ? 'Fleet owner verified. They can now use the fleet app.' : 'Fleet owner application rejected.');
    }
}
