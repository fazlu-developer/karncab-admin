<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PlatformOpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OpsController extends Controller
{
    public function __construct(private readonly PlatformOpsService $ops) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->ops->dashboard($request->user()));
    }

    public function index(Request $request, string $module): JsonResponse
    {
        $this->assertModule($request, $module);

        return response()->json($this->ops->listing($module, $request->query(), $request->user()));
    }

    public function export(Request $request, string $module): Response
    {
        abort_unless($request->user()?->can('reports.export'), 403);
        $this->assertModule($request, $module);
        $rows = $this->ops->exportRows($module, $request->query(), $request->user());
        $handle = fopen('php://temp', 'r+');
        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row));
            }
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$module.'-export.csv"',
        ]);
    }

    public function showUser(Request $request, string $id): JsonResponse
    {
        abort_unless($request->user()?->can('users.view'), 403);

        return response()->json($this->ops->user((int) $id, $request->user()));
    }

    public function storeUser(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('users.create'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string'],
        ]);

        return response()->json($this->ops->createUser($data, $request->user()), 201);
    }

    public function patchUser(Request $request, string $id): JsonResponse
    {
        abort_unless($request->user()?->can('users.edit'), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string'],
        ]);

        return response()->json($this->ops->patchUser((int) $id, $data, $request->user()));
    }

    public function showBooking(Request $request, string $id): JsonResponse
    {
        abort_unless($request->user()?->can('bookings.view'), 403);

        return response()->json($this->ops->booking((int) $id, $request->user()));
    }

    public function patchDriver(Request $request, string $id): JsonResponse
    {
        $action = $request->input('action');
        if ($action === 'approve') {
            abort_unless($request->user()?->can('drivers.approve'), 403);
        } else {
            abort_unless($request->user()?->can('drivers.edit') || $request->user()?->can('drivers.write'), 403);
        }
        $data = $request->validate([
            'action' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
            'fleetOwnerId' => ['nullable', 'string'],
            'vehicleId' => ['nullable', 'string'],
        ]);
        $this->ops->patchDriver((int) $id, $data, $request->user());

        return response()->json(['ok' => true]);
    }

    public function announce(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:500'],
            'audience' => ['required', 'in:customer,driver,ops'],
        ]);
        $count = $this->ops->announce($data, $request->user());

        return response()->json(['ok' => true, 'recipients' => $count]);
    }

    private function assertModule(Request $request, string $module): void
    {
        $def = \App\Platform\OpsNav::find($module);
        abort_if($def === null || $module === 'dashboard', 404);
        if ($def['ability']) {
            abort_unless($request->user()?->can($def['ability']), 403);
        }
    }
}
