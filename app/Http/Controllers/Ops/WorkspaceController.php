<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\PlatformPermission;
use App\Services\OrganizationAudit;
use App\Services\SiteBrandingService;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __construct(private readonly SiteBrandingService $branding) {}

    public function branding(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);

        return view('ops.branding', ['site' => $this->branding->form()]);
    }

    public function saveBranding(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'defaultSeoTitle' => ['nullable', 'string', 'max:80'],
            'defaultSeoDescription' => ['nullable', 'string', 'max:180'],
            'canonicalHost' => ['nullable', 'string', 'max:180'],
            'contactEmail' => ['nullable', 'email', 'max:180'],
            'contactPhone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'mapEmbed' => ['nullable', 'string', 'max:2000'],
            'facebookUrl' => ['nullable', 'string', 'max:255'],
            'instagramUrl' => ['nullable', 'string', 'max:255'],
            'youtubeUrl' => ['nullable', 'string', 'max:255'],
            'whatsappUrl' => ['nullable', 'string', 'max:255'],
            'playStoreUrl' => ['nullable', 'string', 'max:255'],
            'appStoreUrl' => ['nullable', 'string', 'max:255'],
            'footerBlurb' => ['nullable', 'string', 'max:400'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'admin_logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'og' => ['nullable', 'image', 'max:2048'],
        ]);
        $this->branding->save($data, $request->file('logo'), $request->file('favicon'), $request->file('og'), $request->file('admin_logo'));
        OrganizationAudit::record($request->user(), 'branding.update', 'cms_site', 'site', null, ['name' => $data['name']], 'cms');

        return back()->with('status', 'Website, SEO, logo and contact details saved. Public pages pick this up from cms_site.');
    }

    public function services(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $this->branding->ensureCatalogTable();
        $this->seedDefaultServices();
        $rows = DB::connection('platform')->table('catalog_services')->orderBy('service_group')->orderBy('sort_order')->orderBy('title')->get();

        return view('ops.services', ['rows' => $rows]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $this->branding->ensureCatalogTable();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'service_group' => ['required', 'in:RIDE,PARCEL'],
            'category_key' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['nullable', 'boolean'],
        ]);
        $slug = Str::slug($data['slug'] ?? $data['title']);
        $payload = PlatformSettings::filter('catalog_services', [
            'title' => $data['title'],
            'slug' => $slug !== '' ? $slug : Str::slug($data['title']),
            'subtitle' => $data['subtitle'] ?? null,
            'service_group' => $data['service_group'],
            'category_key' => strtoupper((string) ($data['category_key'] ?? '')),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => $request->boolean('active', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::connection('platform')->table('catalog_services')->insertGetId($payload);
        OrganizationAudit::record($request->user(), 'service.create', 'catalog_service', $id, null, $payload, 'catalog');

        return back()->with('status', 'Service added. Apps and the website can use this category.');
    }

    public function updateService(Request $request, int $service): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'service_group' => ['required', 'in:RIDE,PARCEL'],
            'category_key' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ]);
        $payload = PlatformSettings::filter('catalog_services', [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'service_group' => $data['service_group'],
            'category_key' => strtoupper((string) ($data['category_key'] ?? '')),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => $request->boolean('active'),
            'updated_at' => now(),
        ]);
        DB::connection('platform')->table('catalog_services')->where('id', $service)->update($payload);

        return back()->with('status', 'Service updated.');
    }

    public function travel(Request $request): View
    {
        abort_unless($request->user()?->can('travel.view') || $request->user()?->can('platform.admin'), 403);
        $packages = Schema::connection('platform')->hasTable('travel_packages')
            ? DB::connection('platform')->table('travel_packages')->orderByDesc('id')->limit(200)->get()
            : collect();
        $bookings = Schema::connection('platform')->hasTable('travel_bookings')
            ? DB::connection('platform')->table('travel_bookings')->orderByDesc('id')->limit(50)->get()
            : collect();

        return view('ops.travel', compact('packages', 'bookings'));
    }

    public function storeTravel(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('travel.edit') || $request->user()?->can('platform.admin'), 403);
        abort_unless(Schema::connection('platform')->hasTable('travel_packages'), 422, 'Travel packages table is not available.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'destination' => ['nullable', 'string', 'max:160'],
            'price_rupees' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:DRAFT,PUBLISHED,ARCHIVED'],
        ]);
        $payload = PlatformSettings::filter('travel_packages', [
            'title' => $data['title'],
            'name' => $data['title'],
            'destination' => $data['destination'] ?? null,
            'price_paise' => (int) round(((float) ($data['price_rupees'] ?? 0)) * 100),
            'status' => $data['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::connection('platform')->table('travel_packages')->insertGetId($payload);
        OrganizationAudit::record($request->user(), 'travel.create', 'travel_package', $id, null, $payload, 'travel');

        return back()->with('status', 'Travel package saved.');
    }

    public function updateTravel(Request $request, int $package): RedirectResponse
    {
        abort_unless($request->user()?->can('travel.edit') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'destination' => ['nullable', 'string', 'max:160'],
            'price_rupees' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:DRAFT,PUBLISHED,ARCHIVED'],
        ]);
        $payload = PlatformSettings::filter('travel_packages', [
            'title' => $data['title'],
            'name' => $data['title'],
            'destination' => $data['destination'] ?? null,
            'price_paise' => (int) round(((float) ($data['price_rupees'] ?? 0)) * 100),
            'status' => $data['status'],
            'updated_at' => now(),
        ]);
        DB::connection('platform')->table('travel_packages')->where('id', $package)->update($payload);

        return back()->with('status', 'Travel package updated.');
    }

    public function parcels(Request $request): View
    {
        abort_unless($request->user()?->can('parcels.view') || $request->user()?->can('platform.admin'), 403);
        $rows = Schema::connection('platform')->hasTable('parcel_shipments')
            ? DB::connection('platform')->table('parcel_shipments')->orderByDesc('id')->limit(200)->get()
            : collect();

        return view('ops.parcels', ['rows' => $rows]);
    }

    public function storeParcel(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('parcels.manage') || $request->user()?->can('platform.admin'), 403);
        abort_unless(Schema::connection('platform')->hasTable('parcel_shipments'), 422, 'Parcel table is not available.');
        $data = $request->validate([
            'pickup_text' => ['required', 'string', 'max:180'],
            'drop_text' => ['required', 'string', 'max:180'],
            'customer_id' => ['nullable', 'integer'],
            'quote_rupees' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);
        $payload = PlatformSettings::filter('parcel_shipments', [
            'public_ref' => 'PR'.strtoupper(Str::random(8)),
            'pickup_text' => $data['pickup_text'],
            'drop_text' => $data['drop_text'],
            'customer_id' => $data['customer_id'] ?? null,
            'quote_paise' => (int) round(((float) ($data['quote_rupees'] ?? 0)) * 100),
            'status' => $data['status'] ?? 'CREATED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('platform')->table('parcel_shipments')->insert($payload);

        return back()->with('status', 'Parcel shipment created.');
    }

    public function updateParcel(Request $request, int $parcel): RedirectResponse
    {
        abort_unless($request->user()?->can('parcels.manage') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'status' => ['required', 'string', 'max:40'],
            'driver_id' => ['nullable', 'integer'],
        ]);
        $payload = PlatformSettings::filter('parcel_shipments', [
            'status' => $data['status'],
            'driver_id' => $data['driver_id'] ?? null,
            'updated_at' => now(),
        ]);
        DB::connection('platform')->table('parcel_shipments')->where('id', $parcel)->update($payload);

        return back()->with('status', 'Parcel status updated.');
    }

    public function manualBookings(Request $request): View
    {
        abort_unless($request->user()?->can('bookings.view') || $request->user()?->can('platform.admin'), 403);
        $rows = DB::connection('platform')->table('bookings')->orderByDesc('id')->limit(80)->get();
        $customers = DB::connection('platform')->table('users')->where('role', 'CUSTOMER')->orderByDesc('id')->limit(80)->get(['id', 'name', 'phone']);
        $districts = DB::connection('platform')->table('districts')->orderBy('name')->get(['id', 'name']);

        return view('ops.manual-bookings', compact('rows', 'customers', 'districts'));
    }

    public function storeManualBooking(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookings.manage') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'pickup_text' => ['required', 'string', 'max:180'],
            'drop_text' => ['required', 'string', 'max:180'],
            'product' => ['required', 'string', 'max:40'],
            'category' => ['required', 'string', 'max:40'],
            'quote_rupees' => ['nullable', 'numeric', 'min:0'],
            'district_id' => ['nullable', 'integer'],
            'pickup_lat' => ['nullable', 'numeric'],
            'pickup_lng' => ['nullable', 'numeric'],
            'drop_lat' => ['nullable', 'numeric'],
            'drop_lng' => ['nullable', 'numeric'],
            'passenger_name' => ['nullable', 'string', 'max:80'],
            'passenger_phone' => ['nullable', 'string', 'max:20'],
        ]);
        abort_unless(DB::connection('platform')->table('users')->where('id', $data['customer_id'])->where('role', 'CUSTOMER')->exists(), 422, 'Select a customer account.');
        $payload = PlatformSettings::filter('bookings', [
            'public_ref' => 'KC'.strtoupper(Str::random(8)),
            'customer_id' => $data['customer_id'],
            'district_id' => $data['district_id'] ?? null,
            'product' => strtoupper($data['product']),
            'category' => strtoupper($data['category']),
            'status' => 'SEARCHING',
            'pickup_text' => $data['pickup_text'],
            'drop_text' => $data['drop_text'],
            'quote_paise' => (int) round(((float) ($data['quote_rupees'] ?? 0)) * 100),
            'pickup_lat' => $data['pickup_lat'] ?? null,
            'pickup_lng' => $data['pickup_lng'] ?? null,
            'drop_lat' => $data['drop_lat'] ?? null,
            'drop_lng' => $data['drop_lng'] ?? null,
            'passenger_name' => $data['passenger_name'] ?? null,
            'passenger_phone' => $data['passenger_phone'] ?? null,
            'start_otp' => (string) random_int(1000, 9999),
            'end_otp' => (string) random_int(1000, 9999),
            'payment_mode' => 'CASH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::connection('platform')->table('bookings')->insertGetId($payload);
        OrganizationAudit::record($request->user(), 'booking.manual', 'booking', $id, null, $payload, 'bookings');

        return back()->with('status', 'Manual booking created as SEARCHING. Nearby online drivers can accept it.');
    }

    public function settings(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);

        return view('ops.settings', [
            'radiusKm' => (float) (PlatformSettings::get('driver_search_radius_km') ?? PlatformSettings::get('driver_offer_radius_km', '20')),
            'timeoutSeconds' => (int) (PlatformSettings::get('ride_request_timeout_seconds', '30')),
            'walletMinRupees' => ((int) (PlatformSettings::get('driver_wallet_min_paise', '0'))) / 100,
            'walletMinPercent' => (float) (PlatformSettings::get('driver_wallet_min_fare_percent', '0')),
            'walletCoverCommission' => PlatformSettings::get('driver_wallet_must_cover_commission', '1') !== '0',
            'leadsEmail' => PlatformSettings::get('leads_notify_email', ''),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'driver_search_radius_km' => ['required', 'numeric', 'min:1', 'max:100'],
            'ride_request_timeout_seconds' => ['required', 'integer', 'min:10', 'max:300'],
            'driver_wallet_min_rupees' => ['nullable', 'numeric', 'min:0'],
            'driver_wallet_min_fare_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'driver_wallet_must_cover_commission' => ['nullable', 'boolean'],
            'leads_notify_email' => ['nullable', 'email', 'max:180'],
        ]);
        PlatformSettings::put('driver_search_radius_km', (string) $data['driver_search_radius_km']);
        PlatformSettings::put('driver_offer_radius_km', (string) $data['driver_search_radius_km']);
        PlatformSettings::put('ride_request_timeout_seconds', (string) $data['ride_request_timeout_seconds']);
        PlatformSettings::put('driver_wallet_min_paise', (string) ((int) round(((float) ($data['driver_wallet_min_rupees'] ?? 0)) * 100)));
        PlatformSettings::put('driver_wallet_min_fare_percent', (string) ($data['driver_wallet_min_fare_percent'] ?? 0));
        PlatformSettings::put('driver_wallet_must_cover_commission', $request->boolean('driver_wallet_must_cover_commission') ? '1' : '0');
        if (! empty($data['leads_notify_email'])) {
            PlatformSettings::put('leads_notify_email', $data['leads_notify_email']);
        }

        return back()->with('status', 'Settings saved as labeled fields. JSON is not required.');
    }

    public function roles(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $matrix = [];
        foreach (OperatorRole::all() as $role) {
            $matrix[] = [
                'role' => $role,
                'permissions' => PlatformPermission::forRole($role),
            ];
        }
        $managers = User::query()->where('role', OperatorRole::MANAGER)->orderBy('name')->get();

        return view('ops.roles', [
            'matrix' => $matrix,
            'catalog' => PlatformPermission::catalog(),
            'managers' => $managers,
        ]);
    }

    public function saveRoleExtras(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        abort_unless($user->role === OperatorRole::MANAGER, 422, 'Extra permissions can be assigned to Manager users from this screen.');
        $abilities = array_values(array_filter((array) $request->input('abilities', []), 'is_string'));
        $user->syncAbilities($abilities);
        OrganizationAudit::record($request->user(), 'roles.extras', 'user', $user->id, null, ['abilities' => $abilities], 'access');

        return back()->with('status', 'Manager extra permissions saved.');
    }

    public function audit(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $q = DB::connection('platform')->table('platform_audit_events')->orderByDesc('id');
        if ($request->filled('domain')) {
            $q->where('domain', $request->string('domain'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('action', 'like', $term)
                    ->orWhere('entity_type', 'like', $term)
                    ->orWhere('entity_id', 'like', $term);
            });
        }
        $rows = $q->limit(250)->get();

        return view('ops.audit', ['rows' => $rows, 'query' => $request->only(['domain', 'q'])]);
    }

    private function seedDefaultServices(): void
    {
        $db = DB::connection('platform');
        if ($db->table('catalog_services')->count() > 0) {
            return;
        }
        $now = now();
        foreach ([
            ['Bike', 'bike', 'RIDE', 'BIKE', 10],
            ['Auto', 'auto', 'RIDE', 'AUTO', 20],
            ['Mini', 'mini', 'RIDE', 'MINI', 30],
            ['Sedan', 'sedan', 'RIDE', 'SEDAN', 40],
            ['SUV', 'suv', 'RIDE', 'SUV', 50],
            ['Traveller', 'traveller', 'RIDE', 'TRAVELLER', 60],
            ['Parcel local', 'parcel-local', 'PARCEL', 'PARCEL', 70],
        ] as [$title, $slug, $group, $key, $sort]) {
            $db->table('catalog_services')->insert(PlatformSettings::filter('catalog_services', [
                'title' => $title,
                'slug' => $slug,
                'subtitle' => $title.' on KarnaCab',
                'service_group' => $group,
                'category_key' => $key,
                'sort_order' => $sort,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
}
