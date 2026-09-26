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
        if ($module === 'vehicles') {
            abort_unless($request->user()?->can('vehicles.view'), 403);

            return redirect()->route('vehicles.index', $request->query());
        }
        if ($module === 'trips') {
            abort_unless($request->user()?->can('bookings.view'), 403);

            return redirect()->route('ops.module', 'bookings');
        }
        if ($module === 'map') {
            abort_unless($request->user()?->can('vehicles.view') || $request->user()?->can('tracking.view'), 403);

            return redirect()->route('live.map');
        }
        if ($module === 'assignments' || $module === 'driver-leave') {
            abort_unless($request->user()?->can('fleet.view'), 403);
            if ($request->user()?->isFleetOwner()) {
                return redirect()->route($module === 'driver-leave' ? 'fleet.drivers' : 'fleet.vehicles');
            }
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
        if ($module === 'leads') {
            abort_unless($request->user()?->can('customers.view') || $request->user()?->can('platform.admin'), 403);

            return redirect()->route('leads.index');
        }
        if ($module === 'notifications') {
            abort_unless($request->user()?->can('platform.admin'), 403);

            return redirect()->route('notifications.index');
        }
        if ($module === 'reports') {
            abort_unless($request->user()?->can('reports.view'), 403);

            return redirect()->route('reports.index');
        }
        if ($module === 'fare') {
            abort_unless($request->user()?->can('fare.manage'), 403);

            return redirect()->route('fare.index');
        }
        if ($module === 'branding') {
            abort_unless($request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.branding');
        }
        if ($module === 'services') {
            abort_unless($request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.services');
        }
        if ($module === 'travel') {
            abort_unless($request->user()?->can('travel.view') || $request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.travel');
        }
        if ($module === 'corporate-plans') {
            abort_unless($request->user()?->can('corporate.view') || $request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.corporate-plans');
        }
        if ($module === 'parcels') {
            abort_unless($request->user()?->can('parcels.view') || $request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.parcels');
        }
        if ($module === 'bulk') {
            abort_unless($request->user()?->can('bookings.manage') || $request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.manual-bookings');
        }
        if ($module === 'assign-drivers') {
            abort_unless($request->user()?->can('bookings.manage') || $request->user()?->can('platform.admin') || $request->user()?->can('bookings.view'), 403);

            return redirect()->route('ops.assign-drivers');
        }
        if ($module === 'settings') {
            abort_unless($request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.settings');
        }
        if ($module === 'roles') {
            abort_unless($request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.roles');
        }
        if ($module === 'audit') {
            abort_unless($request->user()?->can('platform.admin'), 403);

            return redirect()->route('ops.audit');
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
        $drivers = [];
        try {
            $booking = $this->ops->booking((int) $id, $request->user());
            $drivers = $this->ops->assignableDrivers($request->user());
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return view('ops.booking', [
            'booking' => $booking,
            'error' => $error,
            'drivers' => $drivers,
        ]);
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
            if (is_array($value) && array_is_list($value)) {
                return array_map(fn ($row) => is_array($row) ? $this->flattenRow($row) : ['value' => $row], $value);
            }
        }

        $kv = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $kv[] = ['Field' => $this->label($key), 'Value' => $this->cell($value)];
        }

        return $kv;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function flattenRow(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (in_array($key, ['stateId', 'districtId'], true)) {
                continue;
            }
            $out[$this->label((string) $key)] = $this->cell($value);
        }

        return $out;
    }

    private function label(string $key): string
    {
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', str_replace('_', ' ', $key)) ?? $key;

        return ucwords($spaced);
    }

    private function cell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (! is_array($value)) {
            return (string) $value;
        }
        if ($value === []) {
            return '—';
        }
        if (array_is_list($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (! is_array($item)) {
                    $parts[] = (string) $item;
                    continue;
                }
                $parts[] = (string) ($item['name'] ?? $item['title'] ?? $item['label'] ?? $item['key'] ?? reset($item) ?: '');
            }

            return implode(', ', array_filter($parts, fn ($part) => $part !== ''));
        }
        $parts = [];
        foreach ($value as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $parts[] = $this->label((string) $k).': '.$this->cell($v);
            }
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
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
