<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Platform\OpsNav;
use App\Services\PlatformOpsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class ModuleController extends Controller
{
    public function __construct(private readonly PlatformOpsService $ops) {}

    public function show(Request $request, string $module): View|RedirectResponse
    {
        if ($module === 'users') {
            abort_unless($request->user()?->can('users.view'), 403);

            return redirect()->route('users.index');
        }
        if ($module === 'drivers') {
            abort_unless($request->user()?->can('drivers.view'), 403);

            return redirect()->route('drivers.index');
        }
        if (in_array($module, ['fleet', 'vehicles'], true) && $request->user()?->isFleetOwner()) {
            return redirect()->route($module === 'vehicles' ? 'fleet.vehicles' : 'fleet.dashboard');
        }
        if ($module === 'map') {
            abort_unless($request->user()?->can('vehicles.view'), 403);

            return redirect()->route('live.map');
        }
        if ($module === 'wallets') {
            abort_unless($request->user()?->can('wallet.view'), 403);

            return redirect()->route('wallets.index');
        }
        if ($module === 'commission') {
            abort_unless($request->user()?->can('payments.view') || $request->user()?->can('wallet.view'), 403);

            return redirect()->route('wallets.commission');
        }
        if ($module === 'payments') {
            abort_unless($request->user()?->can('payments.view'), 403);

            return redirect()->route('payments.index');
        }
        if ($module === 'advertising') {
            abort_unless($request->user()?->can('advertising.view'), 403);

            return redirect()->route('ads.index');
        }
        if ($module === 'coupons') {
            abort_unless($request->user()?->can('platform.admin'), 403);

            return redirect()->route('coupons.index');
        }
        if ($module === 'safety') {
            abort_unless($request->user()?->can('safety.view'), 403);

            return redirect()->route('safety.index');
        }
        if ($module === 'complaints') {
            abort_unless($request->user()?->can('safety.view'), 403);

            return redirect()->route('support.index');
        }
        if ($module === 'notifications') {
            abort_unless($request->user()?->can('platform.admin'), 403);

            return redirect()->route('notifications.index');
        }
        if ($module === 'reports') {
            abort_unless($request->user()?->can('reports.view'), 403);

            return redirect()->route('reports.index');
        }

        $def = OpsNav::find($module);
        abort_if($def === null, 404);
        if ($def['ability']) {
            abort_unless($request->user()?->can($def['ability']), 403);
        }

        $error = null;
        $payload = [];
        try {
            $payload = $this->ops->listing($module, $request->query(), $request->user());
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return view('ops.module', [
            'def' => $def,
            'payload' => $payload,
            'rows' => $this->rows($payload),
            'error' => $error,
            'query' => $request->query(),
        ]);
    }

    public function export(Request $request, string $module): Response
    {
        abort_unless($request->user()?->can('reports.export'), 403);
        $def = OpsNav::find($module);
        abort_if($def === null || $module === 'dashboard', 404);
        if ($def['ability']) {
            abort_unless($request->user()?->can($def['ability']), 403);
        }

        $rows = $this->ops->exportRows($module, $request->query(), $request->user());

        return $this->csv($module, $rows);
    }

    public function showBooking(Request $request, string $id): View
    {
        abort_unless($request->user()?->can('bookings.view'), 403);
        $error = null;
        $booking = [];
        try {
            $booking = $this->ops->booking((int) $id, $request->user());
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return view('ops.booking', ['booking' => $booking, 'error' => $error]);
    }

    public function announce(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:500'],
            'audience' => ['required', 'in:customer,driver,ops'],
        ]);
        $this->ops->announce($data, $request->user());

        return back()->with('status', 'Announcement queued.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function rows(array $payload): array
    {
        foreach ($payload as $value) {
            if (is_array($value) && $value !== [] && array_is_list($value) && is_array($value[0] ?? null)) {
                return $value;
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function csv(string $module, array $rows): Response
    {
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
}
