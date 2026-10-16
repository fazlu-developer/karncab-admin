<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\PlatformPermission;
use App\Services\OrganizationAudit;
use App\Services\SiteBrandingService;
use App\Services\VehicleCatalogAdmin;
use App\Support\CorporatePlanSchema;
use App\Support\PlatformSettings;
use App\Support\StoredUpload;
use App\Support\TravelPackageSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly SiteBrandingService $branding,
        private readonly VehicleCatalogAdmin $vehicles,
    ) {}

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
            'customerPlayStoreUrl' => ['nullable', 'string', 'max:255'],
            'driverPlayStoreUrl' => ['nullable', 'string', 'max:255'],
            'customerAppVersion' => ['nullable', 'string', 'max:32'],
            'driverAppVersion' => ['nullable', 'string', 'max:32'],
            'highAlertMessage' => ['nullable', 'string', 'max:240'],
            'highAlertUntil' => ['nullable', 'string', 'max:40'],
            'customerMaintenance' => ['nullable', 'boolean'],
            'driverMaintenance' => ['nullable', 'boolean'],
            'customerForceUpdate' => ['nullable', 'boolean'],
            'driverForceUpdate' => ['nullable', 'boolean'],
            'highAlertEnabled' => ['nullable', 'boolean'],
            'footerBlurb' => ['nullable', 'string', 'max:400'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'customer_app_logo' => ['nullable', 'image', 'max:4096'],
            'driver_app_logo' => ['nullable', 'image', 'max:4096'],
            'admin_logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'og' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable'],
            'remove_customer_app_logo' => ['nullable'],
            'remove_driver_app_logo' => ['nullable'],
            'remove_admin_logo' => ['nullable'],
            'remove_favicon' => ['nullable'],
            'remove_og' => ['nullable'],
        ]);
        $this->branding->save($data, $request->file('logo'), $request->file('favicon'), $request->file('og'), $request->file('admin_logo'), $request->file('customer_app_logo'), $request->file('driver_app_logo'));
        OrganizationAudit::record($request->user(), 'branding.update', 'cms_site', 'site', null, ['name' => $data['name']], 'cms');

        return back()->with('status', 'Website, SEO, logo and contact details saved. Public pages pick this up from cms_site.');
    }

    public function services(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $this->branding->ensureCatalogTable();
        $this->vehicles->ensure();
        $this->ensureCanonicalServices();
        $rows = DB::connection('platform')->table('catalog_services')->orderBy('sort_order')->orderBy('title')->get();

        return view('ops.services', [
            'rows' => $rows,
            'offers' => PlatformSettings::json('cms_home_offers'),
            'vehicleTypes' => $this->vehicleTypeOptions(),
            'vehicleFamilies' => $this->vehicles->families(),
            'canonicalKeys' => array_column($this->canonicalServices(), 'key'),
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $this->branding->ensureCatalogTable();
        $this->ensureCanonicalServices();

        return back()->withErrors(['title' => 'Only the 12 customer services are allowed. Extra rows are removed automatically.']);
    }

    public function updateService(Request $request, int $service): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'service_group' => ['required', 'in:RIDE,PARCEL'],
            'category_key' => ['nullable', 'string', 'max:40'],
            'vehicle_keys' => ['nullable', 'array'],
            'vehicle_keys.*' => ['string', 'max:40'],
            'sort_order' => ['nullable', 'integer'],
            'seats' => ['nullable', 'integer', 'min:0', 'max:60'],
            'active' => ['nullable'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable'],
        ]);
        $existing = DB::connection('platform')->table('catalog_services')->where('id', $service)->first();
        $imageUrl = null;
        $clearImage = $request->boolean('remove_image') || $request->file('image');
        if ($clearImage) {
            StoredUpload::forget($existing?->image_url);
        }
        if ($request->file('image')) {
            $imageUrl = StoredUpload::replace($request->file('image'), 'services', null, 'svc');
        }
        $payload = PlatformSettings::filter('catalog_services', [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'service_group' => $data['service_group'],
            'category_key' => strtoupper((string) ($data['category_key'] ?? '')),
            'vehicle_categories' => $this->encodeVehicleKeys($request->input('vehicle_keys', [])),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'seats' => (int) ($data['seats'] ?? 4),
            'active' => $this->onAppFlag($request->input('active')),
            'updated_at' => now(),
        ]);
        if ($imageUrl !== null) {
            $payload['image_url'] = $imageUrl;
        } elseif ($clearImage) {
            $payload['image_url'] = null;
        }
        DB::connection('platform')->table('catalog_services')->where('id', $service)->update($payload);

        return back()->with('status', 'Service updated.');
    }

    public function destroyService(Request $request, int $service): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $existing = DB::connection('platform')->table('catalog_services')->where('id', $service)->first();
        StoredUpload::forget($existing?->image_url);
        DB::connection('platform')->table('catalog_services')->where('id', $service)->delete();
        OrganizationAudit::record($request->user(), 'service.delete', 'catalog_service', $service, (array) $existing, null, 'catalog');

        return back()->with('status', 'Service removed. Apps stop offering that vehicle category.');
    }

    public function storeVehicleType(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'family_key' => ['required', 'string', 'max:40'],
            'family_label' => ['nullable', 'string', 'max:80'],
            'new_family_key' => ['nullable', 'string', 'max:40'],
            'new_family_label' => ['nullable', 'string', 'max:80'],
            'key' => ['nullable', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:80'],
            'seats' => ['nullable', 'integer', 'min:0', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'blurb' => ['nullable', 'string', 'max:180'],
            'active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
        if (($data['family_key'] ?? '') === '__NEW__') {
            $data['family_key'] = $data['new_family_key'] ?: $data['new_family_label'] ?: 'LOADING';
            $data['family_label'] = $data['new_family_label'] ?: $data['family_key'];
        } else {
            foreach ($this->vehicles->families(false) as $family) {
                if ($family['key'] === strtoupper((string) $data['family_key'])) {
                    $data['family_label'] = $family['label'];
                    break;
                }
            }
        }
        $data['active'] = $request->boolean('active', true);
        if ($request->file('image')) {
            $data['image_url'] = StoredUpload::replace($request->file('image'), 'vehicles', null, 'veh');
        }
        $id = $this->vehicles->store($data);
        OrganizationAudit::record($request->user(), 'vehicle_type.create', 'catalog_vehicle_type', $id, null, $data, 'catalog');

        return back()->with('status', 'Vehicle type added. Tick it on a service so the customer app and fare page show it.');
    }

    public function updateVehicleType(Request $request, int $vehicle): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'family_key' => ['required', 'string', 'max:40'],
            'family_label' => ['nullable', 'string', 'max:80'],
            'key' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:80'],
            'seats' => ['nullable', 'integer', 'min:0', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'blurb' => ['nullable', 'string', 'max:180'],
            'active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable'],
        ]);
        foreach ($this->vehicles->families(false) as $family) {
            if ($family['key'] === strtoupper((string) $data['family_key'])) {
                $data['family_label'] = $data['family_label'] ?: $family['label'];
                break;
            }
        }
        $data['active'] = $request->boolean('active');
        $existing = DB::connection('platform')->table('catalog_vehicle_types')->where('id', $vehicle)->first();
        if ($request->boolean('remove_image') || $request->file('image')) {
            StoredUpload::forget($existing?->image_url);
            $data['image_url'] = $request->file('image')
                ? StoredUpload::replace($request->file('image'), 'vehicles', null, 'veh')
                : null;
        }
        $this->vehicles->update($vehicle, $data);

        return back()->with('status', 'Vehicle type updated.');
    }

    public function updateVehicleFamily(Request $request, string $family): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $request->validate([
            'family_label' => ['nullable', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable'],
        ]);
        $key = strtoupper($family);
        $current = DB::connection('platform')->table('catalog_vehicle_types')->where('family_key', $key)->first();
        if ($request->filled('family_label')) {
            DB::connection('platform')->table('catalog_vehicle_types')->where('family_key', $key)->update([
                'family_label' => $request->string('family_label'),
                'updated_at' => now(),
            ]);
        }
        if ($request->boolean('remove_image') || $request->file('image')) {
            StoredUpload::forget($current?->family_image_url);
            $url = $request->file('image')
                ? StoredUpload::replace($request->file('image'), 'vehicles', $current?->family_image_url, 'fam')
                : null;
            $this->vehicles->saveFamilyImage($key, $url);
        }

        return back()->with('status', 'Vehicle category image saved. Apps use it when a type has no photo.');
    }

    public function destroyVehicleType(Request $request, int $vehicle): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $existing = DB::connection('platform')->table('catalog_vehicle_types')->where('id', $vehicle)->first();
        StoredUpload::forget($existing?->image_url);
        $this->vehicles->destroy($vehicle);
        OrganizationAudit::record($request->user(), 'vehicle_type.delete', 'catalog_vehicle_type', $vehicle, null, null, 'catalog');

        return back()->with('status', 'Vehicle type removed.');
    }

    public function storeOffer(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:32'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
        $rows = PlatformSettings::json('cms_home_offers');
        $rows[] = [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? '',
            'code' => $data['code'] ?? '',
            'linkUrl' => $data['link_url'] ?? '',
            'imageUrl' => $request->file('image') ? $this->absoluteUpload(StoredUpload::replace($request->file('image'), 'services', null, 'svc')) : '',
        ];
        PlatformSettings::put('cms_home_offers', json_encode(array_values($rows), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return back()->with('status', 'Offer added. The customer app home and offers screen will show it.');
    }

    public function destroyOffer(Request $request, int $offer): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $rows = PlatformSettings::json('cms_home_offers');
        if (isset($rows[$offer]) && is_array($rows[$offer])) {
            StoredUpload::forget($rows[$offer]['imageUrl'] ?? null);
            array_splice($rows, $offer, 1);
            PlatformSettings::put('cms_home_offers', json_encode(array_values($rows), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return back()->with('status', 'Offer removed and its image deleted from storage.');
    }

    private function absoluteUpload(string $path): string
    {
        return rtrim((string) env('ADMIN_PUBLIC_URL', 'https://admin.karnaride.in'), '/').'/'.ltrim($path, '/');
    }

    public function travel(Request $request): View
    {
        abort_unless($request->user()?->can('travel.view') || $request->user()?->can('platform.admin'), 403);
        TravelPackageSchema::ensure('platform');
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
        TravelPackageSchema::ensure('platform');
        abort_unless(Schema::connection('platform')->hasTable('travel_packages'), 422, 'Travel packages table is not available.');
        $data = $this->validateTravel($request);
        $imageUrl = $request->file('image') ? $this->absoluteUpload(StoredUpload::replace($request->file('image'), 'travel', null, 'tour')) : null;
        $payload = PlatformSettings::filter('travel_packages', $this->travelPayload($data, $imageUrl, true));
        $id = DB::connection('platform')->table('travel_packages')->insertGetId($payload);
        OrganizationAudit::record($request->user(), 'travel.create', 'travel_package', $id, null, $payload, 'travel');

        return back()->with('status', 'Travel package saved. It will show in the customer Travel & Tour service.');
    }

    public function updateTravel(Request $request, int $package): RedirectResponse
    {
        abort_unless($request->user()?->can('travel.edit') || $request->user()?->can('platform.admin'), 403);
        TravelPackageSchema::ensure('platform');
        $data = $this->validateTravel($request);
        $existing = DB::connection('platform')->table('travel_packages')->where('id', $package)->first();
        $clearImage = $request->boolean('remove_image') || $request->file('image');
        if ($clearImage) {
            StoredUpload::forget($existing?->image_url);
        }
        $imageUrl = $request->file('image') ? $this->absoluteUpload(StoredUpload::replace($request->file('image'), 'travel', null, 'tour')) : null;
        $payload = PlatformSettings::filter('travel_packages', $this->travelPayload($data, $imageUrl, false));
        if ($imageUrl === null && ! $clearImage) {
            unset($payload['image_url']);
        }
        if ($clearImage && $imageUrl === null) {
            $payload['image_url'] = null;
        }
        DB::connection('platform')->table('travel_packages')->where('id', $package)->update($payload);

        return back()->with('status', 'Travel package updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTravel(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'destination' => ['nullable', 'string', 'max:160'],
            'origin' => ['nullable', 'string', 'max:160'],
            'region' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:40'],
            'price_rupees' => ['nullable', 'numeric', 'min:0'],
            'nights' => ['nullable', 'integer', 'min:0', 'max:30'],
            'duration_label' => ['nullable', 'string', 'max:40'],
            'km_included' => ['nullable', 'integer', 'min:0'],
            'vehicle_category' => ['nullable', 'string', 'max:40'],
            'min_pax' => ['nullable', 'integer', 'min:1', 'max:40'],
            'highlights' => ['nullable', 'string', 'max:4000'],
            'inclusions' => ['nullable', 'string', 'max:4000'],
            'exclusions' => ['nullable', 'string', 'max:4000'],
            'itinerary' => ['nullable', 'string', 'max:8000'],
            'status' => ['required', 'in:DRAFT,PUBLISHED,ARCHIVED'],
            'popular' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function travelPayload(array $data, ?string $imageUrl, bool $creating): array
    {
        $vehicle = strtoupper((string) ($data['vehicle_category'] ?? 'SEDAN'));
        $nights = (int) ($data['nights'] ?? 0);
        $days = $nights > 0 ? $nights + 1 : 1;
        $label = $data['duration_label'] ?? ($nights > 0 ? $nights.'N / '.$days.'D' : '1D');
        $itinerary = [];
        foreach (preg_split('/\r\n|\n/', (string) ($data['itinerary'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 3));
            $itinerary[] = [
                'day' => $parts[0] ?? '',
                'title' => $parts[1] ?? $line,
                'detail' => $parts[2] ?? '',
            ];
        }
        $highlights = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', (string) ($data['highlights'] ?? '')) ?: [])));
        $row = [
            'title' => $data['title'],
            'name' => $data['title'],
            'destination' => $data['destination'] ?? null,
            'origin' => $data['origin'] ?? 'Saharsa, Bihar',
            'region' => $data['region'] ?? null,
            'category' => strtoupper((string) ($data['category'] ?? 'OUTSTATION')),
            'price_paise' => (int) round(((float) ($data['price_rupees'] ?? 0)) * 100),
            'nights' => $nights,
            'duration_label' => $label,
            'km_included' => (int) ($data['km_included'] ?? 0),
            'vehicle_category' => $vehicle,
            'vehicle_label' => match ($vehicle) {
                'TRAVELLER' => 'Traveller',
                'SUV' => 'SUV',
                default => 'Private Cab',
            },
            'min_pax' => (int) ($data['min_pax'] ?? 2),
            'highlights' => json_encode($highlights),
            'inclusions' => $data['inclusions'] ?? null,
            'exclusions' => $data['exclusions'] ?? null,
            'itinerary' => json_encode($itinerary),
            'image_url' => $imageUrl,
            'popular' => ! empty($data['popular']),
            'status' => $data['status'],
            'updated_at' => now(),
        ];
        if ($creating) {
            $row['created_at'] = now();
        }

        return $row;
    }

    public function corporatePlans(Request $request): View
    {
        abort_unless($request->user()?->can('corporate.view') || $request->user()?->can('platform.admin'), 403);
        CorporatePlanSchema::ensure('platform');
        $plans = Schema::connection('platform')->hasTable('corporate_plans')
            ? DB::connection('platform')->table('corporate_plans')->orderBy('sort_order')->orderByDesc('id')->get()
            : collect();
        $bookings = Schema::connection('platform')->hasTable('corporate_travel_bookings')
            ? DB::connection('platform')->table('corporate_travel_bookings')->orderByDesc('id')->limit(40)->get()
            : collect();

        return view('ops.corporate-plans', compact('plans', 'bookings'));
    }

    public function storeCorporatePlan(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('corporate.edit') || $request->user()?->can('platform.admin'), 403);
        CorporatePlanSchema::ensure('platform');
        $data = $this->validateCorporatePlan($request);
        $payload = PlatformSettings::filter('corporate_plans', $this->corporatePlanPayload($data, true));
        $id = DB::connection('platform')->table('corporate_plans')->insertGetId($payload);
        OrganizationAudit::record($request->user(), 'corporate.plan.create', 'corporate_plan', $id, null, $payload, 'corporate');

        return back()->with('status', 'Corporate plan saved. It will show in the customer Corporate Travel service.');
    }

    public function updateCorporatePlan(Request $request, int $plan): RedirectResponse
    {
        abort_unless($request->user()?->can('corporate.edit') || $request->user()?->can('platform.admin'), 403);
        CorporatePlanSchema::ensure('platform');
        $data = $this->validateCorporatePlan($request);
        $payload = PlatformSettings::filter('corporate_plans', $this->corporatePlanPayload($data, false));
        DB::connection('platform')->table('corporate_plans')->where('id', $plan)->update($payload);

        return back()->with('status', 'Corporate plan updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCorporatePlan(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'plan_key' => ['nullable', 'string', 'max:40'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'pricing_mode' => ['required', 'in:VEHICLE,FIXED,QUOTE'],
            'price_rupees' => ['nullable', 'numeric', 'min:0'],
            'price_label' => ['nullable', 'string', 'max:80'],
            'gst_percent' => ['nullable', 'numeric', 'min:0', 'max:28'],
            'highlights' => ['nullable', 'string', 'max:4000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99'],
            'status' => ['required', 'in:DRAFT,PUBLISHED,ARCHIVED'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function corporatePlanPayload(array $data, bool $creating): array
    {
        $highlights = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', (string) ($data['highlights'] ?? '')) ?: [])));
        $row = [
            'title' => $data['title'],
            'plan_key' => strtoupper((string) ($data['plan_key'] ?: Str::slug($data['title'], '_'))),
            'subtitle' => $data['subtitle'] ?? null,
            'pricing_mode' => $data['pricing_mode'],
            'price_paise' => (int) round(((float) ($data['price_rupees'] ?? 0)) * 100),
            'price_label' => $data['price_label'] ?? null,
            'gst_percent' => (float) ($data['gst_percent'] ?? 5),
            'highlights' => json_encode($highlights),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'updated_at' => now(),
        ];
        if ($creating) {
            $row['created_at'] = now();
        }

        return $row;
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
            'district_id' => $data['district_id']
                ?: \App\Platform\DistrictLocator::resolve(
                    isset($data['pickup_lat']) ? (float) $data['pickup_lat'] : null,
                    isset($data['pickup_lng']) ? (float) $data['pickup_lng'] : null,
                    (string) $data['pickup_text'],
                    $request->user()?->state_id ? (int) $request->user()->state_id : null,
                ),
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

    public function assignDrivers(Request $request): View
    {
        abort_unless($request->user()?->can('bookings.view') || $request->user()?->can('bookings.manage') || $request->user()?->can('platform.admin'), 403);
        $payload = app(\App\Services\PlatformOpsService::class)->scheduledAssignments($request->user(), $request->query('product'));

        return view('ops.assign-drivers', [
            'remaining' => $payload['remaining'],
            'assigned' => $payload['assigned'],
            'drivers' => $payload['drivers'],
            'product' => $request->query('product'),
        ]);
    }

    public function assignDriverToBooking(Request $request, int $booking): RedirectResponse
    {
        abort_unless($request->user()?->can('bookings.manage') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'driver_id' => ['required', 'integer'],
        ]);
        app(\App\Services\PlatformOpsService::class)->assignBookingDriver($booking, (int) $data['driver_id'], $request->user());
        OrganizationAudit::record($request->user(), 'booking.assign_driver', 'booking', $booking, null, ['driverId' => $data['driver_id']], 'bookings');

        return back()->with('status', 'Driver assigned. Customer and driver were notified.');
    }

    public function settings(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);

        $methods = strtoupper((string) (PlatformSettings::get('driver_withdraw_methods', 'UPI,BANK')));

        return view('ops.settings', [
            'radiusKm' => (float) (PlatformSettings::get('driver_search_radius_km') ?? PlatformSettings::get('driver_offer_radius_km', '20')),
            'timeoutSeconds' => (int) (PlatformSettings::get('ride_request_timeout_seconds', '600')),
            'walletMinRupees' => ((int) (PlatformSettings::get('driver_wallet_min_paise', '0'))) / 100,
            'walletMinPercent' => (float) (PlatformSettings::get('driver_wallet_min_fare_percent', '0')),
            'walletCoverCommission' => PlatformSettings::get('driver_wallet_must_cover_commission', '1') !== '0',
            'topupMinRupees' => (int) (PlatformSettings::get('driver_topup_min_rupees', '100')),
            'withdrawMinRupees' => (int) (PlatformSettings::get('driver_withdraw_min_rupees', '500')),
            'withdrawMethods' => array_values(array_filter(preg_split('/[,\s]+/', $methods) ?: [])),
            'leadsEmail' => PlatformSettings::get('leads_notify_email', ''),
            'bookingEmail' => PlatformSettings::get('booking_notify_email', 'karnaride@gmail.com'),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'driver_search_radius_km' => ['required', 'numeric', 'min:1', 'max:100'],
            'ride_request_timeout_seconds' => ['required', 'integer', 'min:10', 'max:900'],
            'driver_wallet_min_rupees' => ['nullable', 'numeric', 'min:0'],
            'driver_wallet_min_fare_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'driver_wallet_must_cover_commission' => ['nullable', 'boolean'],
            'driver_topup_min_rupees' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'driver_withdraw_min_rupees' => ['nullable', 'integer', 'min:1', 'max:500000'],
            'withdraw_methods' => ['nullable', 'array'],
            'withdraw_methods.*' => ['in:UPI,BANK,CASH'],
            'leads_notify_email' => ['nullable', 'email', 'max:180'],
            'booking_notify_email' => ['nullable', 'email', 'max:180'],
        ]);
        PlatformSettings::put('driver_search_radius_km', (string) $data['driver_search_radius_km']);
        PlatformSettings::put('driver_offer_radius_km', (string) $data['driver_search_radius_km']);
        PlatformSettings::put('ride_request_timeout_seconds', (string) $data['ride_request_timeout_seconds']);
        PlatformSettings::put('driver_wallet_min_paise', (string) ((int) round(((float) ($data['driver_wallet_min_rupees'] ?? 0)) * 100)));
        PlatformSettings::put('driver_wallet_min_fare_percent', (string) ($data['driver_wallet_min_fare_percent'] ?? 0));
        PlatformSettings::put('driver_wallet_must_cover_commission', $request->boolean('driver_wallet_must_cover_commission') ? '1' : '0');
        PlatformSettings::put('driver_topup_min_rupees', (string) max(1, (int) ($data['driver_topup_min_rupees'] ?? 100)));
        PlatformSettings::put('driver_withdraw_min_rupees', (string) max(1, (int) ($data['driver_withdraw_min_rupees'] ?? 500)));
        $methods = array_values(array_unique($data['withdraw_methods'] ?? []));
        PlatformSettings::put('driver_withdraw_methods', $methods !== [] ? implode(',', $methods) : 'UPI');
        if (! empty($data['leads_notify_email'])) {
            PlatformSettings::put('leads_notify_email', $data['leads_notify_email']);
        }
        PlatformSettings::put('booking_notify_email', $data['booking_notify_email'] ?? 'karnaride@gmail.com');

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

    /**
     * @return list<array{key: string, label: string}>
     */
    private function vehicleTypeOptions(): array
    {
        return $this->vehicles->options(false);
    }

    /**
     * @param  list<mixed>  $keys
     */
    private function onAppFlag(mixed $value): int
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (filter_var($item, FILTER_VALIDATE_BOOLEAN)) {
                    return 1;
                }
            }

            return 0;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function encodeVehicleKeys(array $keys): string
    {
        $clean = [];
        $allowed = array_column($this->vehicleTypeOptions(), 'key');
        foreach ($keys as $key) {
            $key = strtoupper(trim((string) $key));
            if ($key !== '' && ($allowed === [] || in_array($key, $allowed, true))) {
                $clean[] = $key;
            }
        }

        return json_encode(array_values(array_unique($clean))) ?: '[]';
    }

    /**
     * @return list<string>
     */
    public static function selectedVehicleKeys(object $row): array
    {
        $keys = [];
        $raw = $row->vehicle_categories ?? '';
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $parts = is_array($decoded) ? $decoded : (preg_split('/[\s,]+/', $raw) ?: []);
            foreach ($parts as $part) {
                $key = strtoupper(trim((string) $part));
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }
        $legacy = strtoupper((string) ($row->category_key ?? ''));
        if ($legacy !== '' && ! in_array($legacy, ['CITY_RIDE', 'LOCAL_CAB', 'ONE_WAY', 'ROUND_WAY', 'RENTAL', 'SCHEDULE', 'OUTSTATION', 'AIRPORT', 'RAILWAY', 'MULTI_STOP', 'PARCEL', 'TRAVEL', 'BULK', 'CORPORATE'], true)) {
            $keys[] = $legacy;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<array{title: string, slug: string, group: string, key: string, sort: int, subtitle: string}>
     */
    private function canonicalServices(): array
    {
        return [
            ['title' => 'City Ride', 'slug' => 'city-ride', 'group' => 'RIDE', 'key' => 'LOCAL_CAB', 'sort' => 10, 'subtitle' => 'Local trips in the city'],
            ['title' => 'One Way', 'slug' => 'one-way', 'group' => 'RIDE', 'key' => 'ONE_WAY', 'sort' => 20, 'subtitle' => 'Outstation one way'],
            ['title' => 'Round Way', 'slug' => 'round-way', 'group' => 'RIDE', 'key' => 'ROUND_WAY', 'sort' => 30, 'subtitle' => 'Go and return'],
            ['title' => 'Parcel Delivery', 'slug' => 'parcel-delivery', 'group' => 'PARCEL', 'key' => 'PARCEL', 'sort' => 40, 'subtitle' => 'Send packages'],
            ['title' => 'Airport Transfer', 'slug' => 'airport-transfer', 'group' => 'RIDE', 'key' => 'AIRPORT', 'sort' => 50, 'subtitle' => 'To and from the airport'],
            ['title' => 'Railway Transfer', 'slug' => 'railway-transfer', 'group' => 'RIDE', 'key' => 'RAILWAY', 'sort' => 60, 'subtitle' => 'To and from the station'],
            ['title' => 'Cab Rental', 'slug' => 'cab-rental', 'group' => 'RIDE', 'key' => 'RENTAL', 'sort' => 70, 'subtitle' => 'Hourly packages'],
            ['title' => 'Schedule Ride', 'slug' => 'schedule-ride', 'group' => 'RIDE', 'key' => 'SCHEDULE', 'sort' => 80, 'subtitle' => 'Book for later'],
            ['title' => 'Multi Stop Ride', 'slug' => 'multi-stop-ride', 'group' => 'RIDE', 'key' => 'MULTI_STOP', 'sort' => 90, 'subtitle' => 'Several stops, one fare'],
            ['title' => 'Travel & Tours', 'slug' => 'travel-tours', 'group' => 'RIDE', 'key' => 'TRAVEL', 'sort' => 100, 'subtitle' => 'Holiday packages'],
            ['title' => 'Bulk Booking', 'slug' => 'bulk-booking', 'group' => 'RIDE', 'key' => 'BULK', 'sort' => 110, 'subtitle' => 'Many cabs at once'],
            ['title' => 'Corporate Travel', 'slug' => 'corporate-travel', 'group' => 'RIDE', 'key' => 'CORPORATE', 'sort' => 120, 'subtitle' => 'Company accounts'],
        ];
    }

    private function ensureCanonicalServices(): void
    {
        $db = DB::connection('platform');
        if (! $db->getSchemaBuilder()->hasTable('catalog_services')) {
            return;
        }
        $now = now();
        $rideKeys = ['BIKE', 'AUTO', 'E_RICKSHAW', 'MINI', 'SEDAN', 'SUV', 'TRAVELLER'];
        $parcelKeys = ['BIKE', 'AUTO', 'PICKUP', 'VAN', 'TRUCK'];
        $tourKeys = ['MINI', 'SEDAN', 'SUV', 'TRAVELLER'];
        $keepIds = [];
        foreach ($this->canonicalServices() as $item) {
            $exists = $db->table('catalog_services')
                ->whereNotIn('id', $keepIds ?: [0])
                ->where(function ($q) use ($item) {
                    $q->where('category_key', $item['key'])->orWhere('slug', $item['slug']);
                })
                ->first();
            if (! $exists) {
                foreach ($db->table('catalog_services')->whereNotIn('id', $keepIds ?: [0])->get() as $row) {
                    $mapped = VehicleCatalogAdmin::fareProduct((string) ($row->category_key ?? ''), (string) ($row->title ?? ''), (string) ($row->slug ?? ''));
                    if ($mapped === $item['key']) {
                        $exists = $row;
                        break;
                    }
                }
            }
            if (! $exists) {
                $vehicles = match ($item['key']) {
                    'PARCEL' => $parcelKeys,
                    'TRAVEL', 'BULK', 'CORPORATE' => $tourKeys,
                    default => $rideKeys,
                };
                $id = $db->table('catalog_services')->insertGetId(PlatformSettings::filter('catalog_services', [
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'subtitle' => $item['subtitle'],
                    'service_group' => $item['group'],
                    'category_key' => $item['key'],
                    'vehicle_categories' => json_encode($vehicles),
                    'sort_order' => $item['sort'],
                    'seats' => 4,
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $keepIds[] = (int) $id;
                continue;
            }
            $keepIds[] = (int) $exists->id;
            $schema = Schema::connection('platform');
            $patch = ['updated_at' => $now];
            if ($schema->hasColumn('catalog_services', 'category_key')) {
                $patch['category_key'] = $item['key'];
            }
            if ($schema->hasColumn('catalog_services', 'sort_order')) {
                $patch['sort_order'] = $item['sort'];
            }
            if ($schema->hasColumn('catalog_services', 'service_group')) {
                $patch['service_group'] = $item['group'];
            }
            if ($schema->hasColumn('catalog_services', 'slug') && trim((string) ($exists->slug ?? '')) === '') {
                $patch['slug'] = $item['slug'];
            }
            $db->table('catalog_services')->where('id', $exists->id)->update($patch);
        }
        if (count($keepIds) === 12) {
            $db->table('catalog_services')->whereNotIn('id', $keepIds)->delete();
        }
    }
}
