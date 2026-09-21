<?php

namespace App\Http\Controllers;

use App\Services\OrganizationAudit;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
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

    public function storeState(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('state.create') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:12'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
        ]);
        $payload = PlatformSettings::filter('states', [
            'name' => trim($data['name']),
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'status' => $data['status'] ?? 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::connection('platform')->table('states')->insertGetId($payload);
        OrganizationAudit::record($request->user(), 'state.create', 'state', $id, null, $payload);

        return back()->with('status', 'State added. KarnaCab can serve this territory after districts are added.');
    }

    public function updateState(Request $request, int $state): RedirectResponse
    {
        abort_unless($request->user()?->can('state.update') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:12'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
        ]);
        $old = (array) DB::connection('platform')->table('states')->where('id', $state)->first();
        abort_if($old === [], 404);
        $payload = PlatformSettings::filter('states', [
            'name' => trim($data['name']),
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'status' => $data['status'] ?? ($old['status'] ?? 'ACTIVE'),
            'updated_at' => now(),
        ]);
        DB::connection('platform')->table('states')->where('id', $state)->update($payload);
        OrganizationAudit::record($request->user(), 'state.update', 'state', $state, $old, $payload);

        return back()->with('status', 'State updated.');
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
        $states = DB::connection('platform')->table('states')->orderBy('name')->get();

        return view('organization.districts', ['districts' => $q->get(), 'states' => $states]);
    }

    public function storeDistrict(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('district.create') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'state_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
        ]);
        abort_unless(DB::connection('platform')->table('states')->where('id', $data['state_id'])->exists(), 422, 'Select a valid state.');
        $payload = PlatformSettings::filter('districts', [
            'state_id' => $data['state_id'],
            'name' => trim($data['name']),
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'status' => $data['status'] ?? 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::connection('platform')->table('districts')->insertGetId($payload);
        OrganizationAudit::record($request->user(), 'district.create', 'district', $id, null, $payload);

        return back()->with('status', 'District added to KarnaCab service coverage.');
    }

    public function updateDistrict(Request $request, int $district): RedirectResponse
    {
        abort_unless($request->user()?->can('district.update') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'state_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
        ]);
        $old = (array) DB::connection('platform')->table('districts')->where('id', $district)->first();
        abort_if($old === [], 404);
        $payload = PlatformSettings::filter('districts', [
            'state_id' => $data['state_id'],
            'name' => trim($data['name']),
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'status' => $data['status'] ?? 'ACTIVE',
            'updated_at' => now(),
        ]);
        DB::connection('platform')->table('districts')->where('id', $district)->update($payload);
        OrganizationAudit::record($request->user(), 'district.update', 'district', $district, $old, $payload);

        return back()->with('status', 'District updated.');
    }
}
