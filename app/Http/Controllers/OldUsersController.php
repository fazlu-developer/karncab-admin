<?php

namespace App\Http\Controllers;

use App\Services\OldUsersService;
use App\Support\SimpleXlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $removed = $this->oldUsers->purgeTestRows();
        if ($removed > 0) {
            $payload = $this->oldUsers->listing($q, $role, $tag, $perPage);
        }

        return view('old-users.index', [
            'rows' => $payload['rows'],
            'counts' => $payload['counts'],
            'filters' => ['q' => $q, 'role' => $role, 'tag' => $tag, 'per_page' => in_array($perPage, [25, 50, 100], true) ? $perPage : 25],
            'source' => $this->oldUsers->resolveLegacyDatabase(),
            'sources' => $this->oldUsers->discoverLegacyCounts(),
            'purgedTest' => $removed,
        ]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()?->can('users.view') || $request->user()?->can('platform.admin') || $request->user()?->can('old_users.view'), 403);
        $rows = $this->oldUsers->exportRows(
            trim((string) $request->query('q', '')),
            strtoupper((string) $request->query('role', '')),
            (string) $request->query('tag', ''),
        );
        $sheet = [];
        foreach ($rows as $row) {
            $dial = (string) $row['dial'];
            $sheet[] = [
                (string) $row['sourceId'],
                (string) $row['name'],
                (string) $row['role'],
                (string) $row['roleId'],
                (string) $row['phone'],
                $dial !== '' ? '=HYPERLINK("tel:+'.$dial.'","Call")' : '',
                $dial !== '' ? '=HYPERLINK("https://wa.me/'.$dial.'","WhatsApp")' : '',
                (string) $row['email'],
                (string) $row['city'],
                (string) $row['district'],
                (string) $row['state'],
                (string) $row['gender'],
                (string) $row['registered'],
                (string) $row['platformRole'],
            ];
        }
        $binary = SimpleXlsx::binary([
            'Old ID', 'Name', 'Role', 'Role ID', 'Phone', 'Call', 'WhatsApp', 'Email', 'City', 'District', 'State', 'Gender', 'New app', 'Platform role',
        ], $sheet);
        $name = 'KarnaRide-Old-Users-'.now()->format('Y-m-d').'.xlsx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('users.view') || $request->user()?->can('platform.admin'), 403);
        if ($request->hasFile('dump')) {
            $file = $request->file('dump');
            abort_unless($file?->isValid(), 422, 'Upload a valid CSV or SQL file.');
            $result = $this->oldUsers->importUpload($file->getRealPath(), $file->getClientOriginalName());
            $this->oldUsers->purgeTestRows();

            return back()->with('status', 'Imported '.$result['imported'].' users from upload ('.$result['source'].'). Table now has '.$result['total'].' old users.');
        }
        $result = $this->oldUsers->import();
        $this->oldUsers->purgeTestRows();

        return back()->with('status', 'Imported '.$result['imported'].' of '.$result['source_count'].' rows from '.$result['source'].'. Table now has '.$result['total'].' old users.');
    }
}
