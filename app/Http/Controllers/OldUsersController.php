<?php

namespace App\Http\Controllers;

use App\Services\OldUsersService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OldUsersController extends Controller
{
    public function __construct(private readonly OldUsersService $oldUsers) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('users.view') || $request->user()?->can('platform.admin'), 403);
        $q = trim((string) $request->query('q', ''));
        $role = strtoupper((string) $request->query('role', ''));
        $tag = (string) $request->query('tag', '');
        $perPage = (int) $request->query('per_page', 25);
        $payload = $this->oldUsers->listing($q, $role, $tag, $perPage);

        return view('old-users.index', [
            'rows' => $payload['rows'],
            'counts' => $payload['counts'],
            'filters' => ['q' => $q, 'role' => $role, 'tag' => $tag, 'per_page' => in_array($perPage, [25, 50, 100], true) ? $perPage : 25],
            'source' => $this->oldUsers->resolveLegacyDatabase(),
            'sources' => $this->oldUsers->discoverLegacyCounts(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('users.view') || $request->user()?->can('platform.admin'), 403);
        if ($request->hasFile('dump')) {
            $file = $request->file('dump');
            abort_unless($file?->isValid(), 422, 'Upload a valid CSV or SQL file.');
            $result = $this->oldUsers->importUpload($file->getRealPath(), $file->getClientOriginalName());

            return back()->with('status', 'Imported '.$result['imported'].' users from upload ('.$result['source'].'). Table now has '.$result['total'].' old users.');
        }
        $result = $this->oldUsers->import();

        return back()->with('status', 'Imported '.$result['imported'].' of '.$result['source_count'].' rows from '.$result['source'].'. Table now has '.$result['total'].' old users.');
    }
}
