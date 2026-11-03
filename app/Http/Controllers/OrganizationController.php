<?php

namespace App\Http\Controllers;

use App\Services\DistrictHeadKycStore;
use App\Services\StateHeadKycStore;
use App\Services\OrganizationAudit;
use App\Services\TerritoryDirectory;
use App\Support\PlatformSettings;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationController extends Controller
{
    public function heads(Request $request, TerritoryDirectory $directory): View
    {
        abort_unless($request->user()?->can('territory_heads.view'), 403);
        $data = $directory->heads($request->user());
        $q = trim((string) $request->query('q', ''));

        return view('organization.heads', [
            'states' => $this->paginateRows($this->filterHeadRows($data['states'], $q), 15, 'state_page'),
            'districts' => $this->paginateRows($this->filterHeadRows($data['districts'], $q), 15, 'district_page'),
            'filters' => ['q' => $q],
        ]);
    }

    public function states(Request $request): View
    {
        abort_unless($request->user()?->can('state.view'), 403);
        $this->ensurePhotoColumn('states');
        $states = DB::connection('platform')->table('states')->orderBy('name');
        $actor = $request->user();
        if ($actor?->isStateHead() && $actor->state_id) {
            $states->where('id', $actor->state_id);
        } elseif ($actor?->isDistrictHead() && $actor->state_id) {
            $states->where('id', $actor->state_id);
        }
        $filters = ['q' => trim((string) $request->query('q', ''))];
        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $hasCode = Schema::connection('platform')->hasColumn('states', 'code');
            $states->where(function ($inner) use ($term, $hasCode) {
                $inner->where('name', 'like', $term);
                if ($hasCode) {
                    $inner->orWhere('code', 'like', $term);
                }
            });
        }
        $states = $states->paginate(20)->withQueryString();

        return view('organization.states', compact('states', 'filters'));
    }

    public function storeState(Request $request): RedirectResponse
    {
        abort_if($request->user()?->isStateHead(), 403);
        abort_unless($request->user()?->can('state.create') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:12'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);
        $payload = PlatformSettings::filter('states', [
            'name' => trim($data['name']),
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'status' => $data['status'] ?? 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::connection('platform')->table('states')->insertGetId($payload);
        $this->saveTerritoryPhoto('states', $id, $request->file('photo'), null);
        OrganizationAudit::record($request->user(), 'state.create', 'state', $id, null, $payload);

        return back()->with('status', 'State added. KarnaRide can serve this territory after districts are added.');
    }

    public function updateState(Request $request, int $state): RedirectResponse
    {
        abort_if($request->user()?->isStateHead(), 403);
        abort_unless($request->user()?->can('state.update') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:12'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
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
        $this->saveTerritoryPhoto('states', $state, $request->file('photo'), $old['photo_url'] ?? null);
        OrganizationAudit::record($request->user(), 'state.update', 'state', $state, $old, $payload);

        return back()->with('status', 'State updated.');
    }

    public function districts(Request $request): View
    {
        abort_unless($request->user()?->can('district.view'), 403);
        $this->ensurePhotoColumn('districts');
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'state_id' => (string) $request->query('state_id', ''),
            'status' => (string) $request->query('status', ''),
        ];
        $q = DB::connection('platform')->table('districts')
            ->join('states', 'states.id', '=', 'districts.state_id')
            ->select('districts.*', 'states.name as state_name')
            ->orderBy('states.name')
            ->orderBy('districts.name');
        $actor = $request->user();
        if ($actor?->isDistrictHead() && $actor->district_id) {
            $q->where('districts.id', $actor->district_id);
            $filters['state_id'] = (string) ($actor->state_id ?? '');
        } elseif ($actor?->isStateHead() && $actor->state_id) {
            $q->where('districts.state_id', $actor->state_id);
            $filters['state_id'] = (string) $actor->state_id;
        }
        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('districts.name', 'like', $term)
                    ->orWhere('districts.code', 'like', $term)
                    ->orWhere('states.name', 'like', $term);
            });
        }
        if ($filters['state_id'] !== '' && ctype_digit($filters['state_id'])) {
            $q->where('districts.state_id', (int) $filters['state_id']);
        }
        if (in_array($filters['status'], ['ACTIVE', 'INACTIVE'], true)) {
            $q->where('districts.status', $filters['status']);
        }
        $states = DB::connection('platform')->table('states')->orderBy('name')->get();

        return view('organization.districts', [
            'districts' => $q->paginate(20)->withQueryString(),
            'states' => $states,
            'filters' => $filters,
        ]);
    }

    public function storeDistrict(Request $request): RedirectResponse
    {
        abort_if($request->user()?->isStateHead() || $request->user()?->isDistrictHead(), 403);
        abort_unless($request->user()?->can('district.create') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'state_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
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
        try {
            $id = DB::connection('platform')->table('districts')->insertGetId($payload);
        } catch (QueryException $exception) {
            return $this->districtSaveError($exception);
        }
        $this->saveTerritoryPhoto('districts', $id, $request->file('photo'), null);
        OrganizationAudit::record($request->user(), 'district.create', 'district', $id, null, $payload);

        return back()->with('status', 'District added to KarnaRide service coverage.');
    }

    public function updateDistrict(Request $request, int $district): RedirectResponse
    {
        abort_if($request->user()?->isStateHead() || $request->user()?->isDistrictHead(), 403);
        abort_unless($request->user()?->can('district.update') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'state_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);
        $old = (array) DB::connection('platform')->table('districts')->where('id', $district)->first();
        abort_if($old === [], 404);
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = strtoupper(trim((string) ($old['code'] ?? '')));
        }
        $payload = PlatformSettings::filter('districts', [
            'state_id' => $data['state_id'],
            'name' => trim($data['name']),
            'code' => $code,
            'status' => $data['status'] ?? 'ACTIVE',
            'updated_at' => now(),
        ]);
        try {
            DB::connection('platform')->table('districts')->where('id', $district)->update($payload);
        } catch (QueryException $exception) {
            return $this->districtSaveError($exception);
        }
        $this->saveTerritoryPhoto('districts', $district, $request->file('photo'), $old['photo_url'] ?? null);
        OrganizationAudit::record($request->user(), 'district.update', 'district', $district, $old, $payload);

        return back()->with('status', 'District updated.');
    }

    public function districtKyc(Request $request): View
    {
        abort_unless($request->user()?->can('district_kyc.view'), 403);
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
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'doc_type' => (string) $request->query('doc_type', ''),
        ];
        $this->filterKyc($q, $filters, 'd.name');
        $docs = $q->paginate(15)->withQueryString();

        return view('organization.district-kyc', [
            'docs' => $docs,
            'heads' => $heads,
            'filters' => $filters,
            'canUploadForOthers' => $actor && $actor->role !== 'DISTRICT_HEAD',
        ]);
    }

    public function storeDistrictKyc(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('district_kyc.view'), 403);
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
        abort_unless($request->user()?->can('district_kyc.view'), 403);
        DistrictHeadKycStore::ensureTable();
        $row = DB::connection('platform')->table('district_head_kyc')->where('id', $id)->first();
        abort_if(! $row, 404);
        $path = (string) $row->path;
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path);
        }
        $absolute = app(\App\Services\KycFileStore::class)->absolutePath($path, false);
        abort_unless($absolute && is_file($absolute), 404);

        return response()->file($absolute);
    }

    public function reviewDistrictKyc(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('district_kyc.view'), 403);
        $status = $request->validate(['status' => ['required', 'in:verified,rejected,submitted']])['status'];
        $row = DB::connection('platform')->table('district_head_kyc')->where('id', $id)->first();
        abort_if(! $row, 404);
        DB::connection('platform')->table('district_head_kyc')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
        if ($status === 'verified' && Schema::connection('platform')->hasTable('franchises') && $row->user_id) {
            DB::connection('platform')->table('franchises')
                ->where('owner_user_id', $row->user_id)
                ->where('kind', 'DISTRICT_HEAD')
                ->where('status', '!=', 'TERMINATED')
                ->update(['kyc_status' => 'verified', 'updated_at' => now()]);
        }

        return back()->with('status', 'KYC marked '.$status.'.');
    }

    public function stateKyc(Request $request): View
    {
        abort_unless($request->user()?->can('state_kyc.view'), 403);
        StateHeadKycStore::ensureTable();
        $heads = StateHeadKycStore::headsFor($request->user());
        $selectedUserId = (int) $request->query('user_id', 0);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'doc_type' => (string) $request->query('doc_type', ''),
            'user_id' => $selectedUserId,
        ];
        $columns = [
            'k.id', 'k.user_id', 'k.doc_type', 'k.path', 'k.status', 'k.created_at',
            'u.name as user_name', 'u.email as user_email', 's.name as state_name',
        ];
        if (Schema::connection('platform')->hasColumn('state_head_kyc', 'original_name')) {
            $columns[] = 'k.original_name';
        }
        $docs = DB::connection('platform')->table('state_head_kyc as k')
            ->leftJoin('users as u', 'u.id', '=', 'k.user_id')
            ->leftJoin('states as s', 's.id', '=', 'k.state_id')
            ->when($request->user()?->isStateHead() && $request->user()->state_id, fn ($query) => $query->where('k.state_id', $request->user()->state_id))
            ->when($selectedUserId > 0, fn ($query) => $query->where('k.user_id', $selectedUserId))
            ->orderByDesc('k.id')
            ->select($columns);
        $this->filterKyc($docs, $filters, 's.name');
        $docs = $docs->paginate(15)->withQueryString();

        return view('organization.state-kyc', compact('docs', 'heads', 'selectedUserId', 'filters'));
    }

    public function storeStateKyc(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('state_kyc.view'), 403);
        $request->validate([
            'user_id' => ['required', 'integer'],
            'doc_type' => ['required', 'string', 'max:40'],
            'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);
        $saved = StateHeadKycStore::saveFromRequest($request, (int) $request->input('user_id'), $request->user()?->id);
        abort_if($saved === 0, 422, 'Choose a State Head and a document file.');

        return redirect()
            ->route('organization.state-kyc', ['user_id' => (int) $request->input('user_id')])
            ->with('status', 'State Head KYC uploaded.');
    }

    public function stateKycFile(Request $request, int $id): StreamedResponse
    {
        abort_unless($request->user()?->can('state_kyc.view'), 403);
        StateHeadKycStore::ensureTable();
        $row = DB::connection('platform')->table('state_head_kyc')->where('id', $id)->first();
        abort_if(! $row, 404);
        abort_unless(Storage::disk('public')->exists($row->path), 404);

        return Storage::disk('public')->response($row->path);
    }

    public function reviewStateKyc(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('state_kyc.view'), 403);
        $status = $request->validate(['status' => ['required', 'in:verified,rejected,submitted']])['status'];
        DB::connection('platform')->table('state_head_kyc')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        return back()->with('status', 'State Head KYC marked '.$status.'.');
    }

    public function replaceStateKyc(Request $request, int $id): RedirectResponse
    {
        return $this->replaceKyc($request, 'state_head_kyc', $id);
    }

    public function destroyStateKyc(Request $request, int $id): RedirectResponse
    {
        return $this->destroyKyc($request, 'state_head_kyc', $id);
    }

    public function replaceDistrictKyc(Request $request, int $id): RedirectResponse
    {
        return $this->replaceKyc($request, 'district_head_kyc', $id);
    }

    public function destroyDistrictKyc(Request $request, int $id): RedirectResponse
    {
        return $this->destroyKyc($request, 'district_head_kyc', $id);
    }

    private function replaceKyc(Request $request, string $table, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can($table === 'state_head_kyc' ? 'state_kyc.view' : 'district_kyc.view'), 403);
        $row = DB::connection('platform')->table($table)->where('id', $id)->first();
        abort_if(! $row, 404);
        $request->merge([
            'user_id' => $row->user_id,
            'doc_type' => $row->doc_type,
        ]);
        $request->validate([
            'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);
        $saved = $table === 'state_head_kyc'
            ? StateHeadKycStore::saveFromRequest($request, (int) $row->user_id, $request->user()?->id)
            : DistrictHeadKycStore::saveFromRequest($request, (int) $row->user_id, $request->user()?->id);
        abort_if($saved === 0, 422, 'Choose a replacement file.');
        if (is_string($row->path) && $row->path !== '' && Storage::disk('public')->exists($row->path)) {
            Storage::disk('public')->delete($row->path);
        }
        DB::connection('platform')->table($table)->where('id', $id)->delete();

        return back()->with('status', 'KYC file replaced.');
    }

    private function destroyKyc(Request $request, string $table, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can($table === 'state_head_kyc' ? 'state_kyc.view' : 'district_kyc.view'), 403);
        $row = DB::connection('platform')->table($table)->where('id', $id)->first();
        abort_if(! $row, 404);
        if (is_string($row->path) && $row->path !== '' && Storage::disk('public')->exists($row->path)) {
            Storage::disk('public')->delete($row->path);
        }
        DB::connection('platform')->table($table)->where('id', $id)->delete();

        return back()->with('status', 'KYC file deleted.');
    }

    private function ensurePhotoColumn(string $table): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable($table) && ! $schema->hasColumn($table, 'photo_url')) {
            $schema->table($table, function ($blueprint) {
                $blueprint->string('photo_url', 255)->nullable();
            });
        }
    }

    private function saveTerritoryPhoto(string $table, int $id, $file, mixed $current): void
    {
        if (! $file) {
            return;
        }
        $this->ensurePhotoColumn($table);
        $path = \App\Support\PublicMedia::storagePath(\App\Support\StoredUpload::replace($file, 'territory', is_string($current) ? $current : null, 'territory'));
        DB::connection('platform')->table($table)->where('id', $id)->update(PlatformSettings::filter($table, [
            'photo_url' => $path,
            'updated_at' => now(),
        ]));
    }

    /**
     * @param  list<object>  $rows
     * @return list<object>
     */
    private function filterHeadRows(array $rows, string $q): array
    {
        if ($q === '') {
            return $rows;
        }
        $needle = mb_strtolower($q);

        return array_values(array_filter($rows, function ($row) use ($needle) {
            $hay = mb_strtolower(implode(' ', [
                $row->name ?? '',
                $row->code ?? '',
                $row->state_name ?? '',
                $row->head_name ?? '',
                $row->head_phone ?? '',
                $row->head_email ?? '',
            ]));

            return str_contains($hay, $needle);
        }));
    }

    /**
     * @param  list<object>  $rows
     */
    private function paginateRows(array $rows, int $perPage, string $pageName): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => $pageName, 'query' => request()->query()],
        );
    }

    /**
     * @param  array{q: string, status: string, doc_type: string}  $filters
     */
    private function filterKyc($query, array $filters, string $placeColumn): void
    {
        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($inner) use ($term, $placeColumn) {
                $inner->where('u.name', 'like', $term)
                    ->orWhere('u.email', 'like', $term)
                    ->orWhere('k.doc_type', 'like', $term)
                    ->orWhere($placeColumn, 'like', $term);
            });
        }
        if (in_array($filters['status'], ['submitted', 'verified', 'rejected'], true)) {
            $query->where('k.status', $filters['status']);
        }
        if (in_array($filters['doc_type'], ['PAN', 'AADHAAR', 'APPOINTMENT', 'ADDRESS', 'GST', 'PHOTO'], true)) {
            $query->where('k.doc_type', $filters['doc_type']);
        }
    }

    private function districtSaveError(QueryException $exception): RedirectResponse
    {
        report($exception);
        $message = $exception->getMessage();
        if (str_contains($message, 'Duplicate') || str_contains($message, 'districts_code')) {
            return back()->withErrors(['code' => 'That district code is already used. Choose a different code.'])->withInput();
        }
        if (str_contains($message, 'Data too long') || str_contains($message, '1406')) {
            return back()->withErrors(['code' => 'The code must be 16 characters or fewer.'])->withInput();
        }

        return back()->withErrors(['name' => 'Could not save this district. Check the code and try again.'])->withInput();
    }
}
