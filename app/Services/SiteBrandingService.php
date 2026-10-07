<?php

namespace App\Services;

use App\Support\PlatformSettings;
use App\Support\StoredUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteBrandingService
{
    /**
     * @return array<string, mixed>
     */
    public function form(): array
    {
        $site = PlatformSettings::json('cms_site', []);

        return [
            'name' => $site['name'] ?? 'KarnaRide',
            'tagline' => $site['tagline'] ?? '',
            'defaultSeoTitle' => $site['defaultSeoTitle'] ?? 'KarnaRide',
            'defaultSeoDescription' => $site['defaultSeoDescription'] ?? '',
            'canonicalHost' => $site['canonicalHost'] ?? 'https://karnaride.in',
            'contactEmail' => $site['contactEmail'] ?? '',
            'contactPhone' => $site['contactPhone'] ?? '',
            'address' => $site['address'] ?? '',
            'mapEmbed' => $site['mapEmbed'] ?? '',
            'facebookUrl' => $site['facebookUrl'] ?? '',
            'instagramUrl' => $site['instagramUrl'] ?? '',
            'youtubeUrl' => $site['youtubeUrl'] ?? '',
            'whatsappUrl' => $site['whatsappUrl'] ?? '',
            'playStoreUrl' => $site['playStoreUrl'] ?? '',
            'appStoreUrl' => $site['appStoreUrl'] ?? '',
            'customerAppLogoUrl' => $site['customerAppLogoUrl'] ?? '',
            'driverAppLogoUrl' => $site['driverAppLogoUrl'] ?? '',
            'customerPlayStoreUrl' => $site['customerPlayStoreUrl'] ?? ($site['playStoreUrl'] ?? ''),
            'driverPlayStoreUrl' => $site['driverPlayStoreUrl'] ?? '',
            'customerAppVersion' => $site['customerAppVersion'] ?? '1.0.4',
            'driverAppVersion' => $site['driverAppVersion'] ?? '1.0.4',
            'customerMaintenance' => (bool) ($site['customerMaintenance'] ?? false),
            'driverMaintenance' => (bool) ($site['driverMaintenance'] ?? false),
            'customerForceUpdate' => (bool) ($site['customerForceUpdate'] ?? false),
            'driverForceUpdate' => (bool) ($site['driverForceUpdate'] ?? false),
            'highAlertEnabled' => (bool) ($site['highAlertEnabled'] ?? false),
            'highAlertMessage' => $site['highAlertMessage'] ?? '',
            'highAlertUntil' => $site['highAlertUntil'] ?? '',
            'footerBlurb' => $site['footerBlurb'] ?? '',
            'logoUrl' => $site['logoUrl'] ?? '',
            'faviconUrl' => $site['faviconUrl'] ?? '',
            'ogImage' => $site['ogImage'] ?? '',
            'adminLogoUrl' => $site['adminLogoUrl'] ?? ($site['logoUrl'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?UploadedFile $logo = null, ?UploadedFile $favicon = null, ?UploadedFile $og = null, ?UploadedFile $adminLogo = null, ?UploadedFile $customerLogo = null, ?UploadedFile $driverLogo = null): void
    {
        $site = $this->form();
        foreach ($site as $key => $current) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $site[$key] = trim($data[$key]);
            }
        }
        foreach (['customerMaintenance', 'driverMaintenance', 'customerForceUpdate', 'driverForceUpdate', 'highAlertEnabled'] as $flag) {
            $site[$flag] = filter_var($data[$flag] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        $this->swapImage($site, 'logoUrl', $logo, 'logo', ! empty($data['remove_logo']));
        $this->swapImage($site, 'customerAppLogoUrl', $customerLogo, 'customer-app', ! empty($data['remove_customer_app_logo']));
        $this->swapImage($site, 'driverAppLogoUrl', $driverLogo, 'driver-app', ! empty($data['remove_driver_app_logo']));
        $this->swapImage($site, 'adminLogoUrl', $adminLogo, 'admin-logo', ! empty($data['remove_admin_logo']));
        $this->swapImage($site, 'faviconUrl', $favicon, 'favicon', ! empty($data['remove_favicon']));
        $this->swapImage($site, 'ogImage', $og, 'og', ! empty($data['remove_og']));
        if (array_key_exists('mapEmbed', $site) && is_string($site['mapEmbed'])) {
            $embed = $site['mapEmbed'];
            $embed = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $embed) ?? '';
            $site['mapEmbed'] = $embed;
        }
        PlatformSettings::put('cms_site', json_encode($site, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function swapImage(array &$site, string $key, ?UploadedFile $file, string $prefix, bool $remove): void
    {
        $current = $site[$key] ?? null;
        if ($file) {
            $site[$key] = StoredUpload::replace($file, 'branding', is_string($current) ? $current : null, $prefix);

            return;
        }
        if ($remove) {
            StoredUpload::forget(is_string($current) ? $current : null);
            $site[$key] = '';
        }
    }

    public function ensureCatalogTable(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('catalog_services')) {
            $schema->create('catalog_services', function ($table) {
                $table->id();
                $table->string('slug', 80);
                $table->string('title', 120);
                $table->string('subtitle', 180)->nullable();
                $table->string('service_group', 24)->default('RIDE');
                $table->string('category_key', 40)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->string('image_url', 255)->nullable();
                $table->timestamps();
            });

            return;
        }
        if (! $schema->hasColumn('catalog_services', 'image_url')) {
            $schema->table('catalog_services', function ($table) {
                $table->string('image_url', 255)->nullable();
            });
        }
        if (! $schema->hasColumn('catalog_services', 'category_key')) {
            $schema->table('catalog_services', function ($table) {
                $table->string('category_key', 40)->nullable();
            });
            foreach (DB::connection('platform')->table('catalog_services')->get() as $row) {
                $raw = preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($row->slug ?? $row->title ?? '')) ?: '';
                $key = strtoupper(trim($raw, '_'));
                DB::connection('platform')->table('catalog_services')->where('id', $row->id)->update([
                    'category_key' => $key !== '' ? $key : null,
                ]);
            }
        }
    }
}
