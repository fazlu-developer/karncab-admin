<?php

namespace App\Http\Controllers;

use App\Services\DistrictHeadKycStore;
use App\Services\OrganizationAudit;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return back()->with('status', 'State added. KarnaRide can serve this territory after districts are added.');
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

        return back()->with('status', 'District added to KarnaRide service coverage.');
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

    public function districtKyc(Request $request): View
    {
        abort_unless($request->user()?->can('kyc.view'), 403);
        DistrictHeadKycStore::ensureTable();
        $actor = $request->user();
        $heads = DistrictHeadKycStore::headsFor($actor);
        $q = DB::connection('platform')->table('district_head_kyc as k')
            ->leftJoin('users as u', 'u.id', '=', 'k.user_id')
            ->leftJoin('districts as d', 'd.id', '=', 'k.district_id')
            ->orderByDesc('k.id')
            ->select([
                'k.id', 'k.user_id', 'k.doc_type', 'k.path', 'k.status', 'k.created_at',
                'u.name as user_name', 'u.email as user_email', 'd.name as district_name',
            ]);
        if ($actor?->role === 'DISTRICT_HEAD') {
            $q->where('k.user_id', $actor->nest_user_id ?: $actor->id);
        } elseif ($actor?->isStateHead() && $actor->state_id) {
            $q->where('k.state_id', $actor->state_id);
        }
        $docs = $q->limit(120)->get();

        return view('organization.district-kyc', [
            'docs' => $docs,
            'heads' => $heads,
            'canUploadForOthers' => $actor && $actor->role !== 'DISTRICT_HEAD',
        ]);
    }

    public function storeDistrictKyc(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('kyc.view'), 403);
        $actor = $request->user();
        $userId = (int) $request->input('user_id');
        if ($actor?->role === 'DISTRICT_HEAD') {
            $userId = (int) ($actor->nest_user_id ?: $actor->id);
        } else {
            $request->validate([
                'user_id' => ['required', 'integer'],
                'doc_type' => ['required', 'string', 'max:40'],
                'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,pdf,webp'],
            ]);
        }
        $saved = DistrictHeadKycStore::saveFromRequest($request, $userId, $actor?->id);
        abort_if($saved === 0, 422, 'Choose a District Head and a document file.');

        return back()->with('status', 'District Head KYC uploaded for that account.');
    }

    public function districtKycFile(Request $request, int $id): StreamedResponse
    {
        abort_unless($request->user()?->can('kyc.view'), 403);
        DistrictHeadKycStore::ensureTable();
        $row = DB::connection('platform')->table('district_head_kyc')->where('id', $id)->first();
        abort_if(! $row, 404);
        abort_unless(Storage::disk('public')->exists($row->path), 404);

        return Storage::disk('public')->response($row->path);
    }

    public function reviewDistrictKyc(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('kyc.approve') || $request->user()?->can('platform.admin'), 403);
        $status = $request->validate(['status' => ['required', 'in:verified,rejected,submitted']])['status'];
        DB::connection('platform')->table('district_head_kyc')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        return back()->with('status', 'KYC marked '.$status.'.');
    }
}
