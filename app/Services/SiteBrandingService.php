<?php

namespace App\Services;

use App\Support\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SiteBrandingService
{
    /**
     * @return array<string, mixed>
     */
    public function form(): array
    {
        $site = PlatformSettings::json('cms_site', []);

        return [
            'name' => $site['name'] ?? 'KarnaCab',
            'tagline' => $site['tagline'] ?? '',
            'defaultSeoTitle' => $site['defaultSeoTitle'] ?? 'KarnaCab',
            'defaultSeoDescription' => $site['defaultSeoDescription'] ?? '',
            'canonicalHost' => $site['canonicalHost'] ?? 'https://karnacab.in',
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
        if ($logo) {
            $site['logoUrl'] = $this->store($logo, 'logo');
        }
        if ($customerLogo) {
            $site['customerAppLogoUrl'] = $this->store($customerLogo, 'customer-app');
        }
        if ($driverLogo) {
            $site['driverAppLogoUrl'] = $this->store($driverLogo, 'driver-app');
        }
        if ($adminLogo) {
            $site['adminLogoUrl'] = $this->store($adminLogo, 'admin-logo');
        }
        if ($favicon) {
            $site['faviconUrl'] = $this->store($favicon, 'favicon');
        }
        if ($og) {
            $site['ogImage'] = $this->store($og, 'og');
        }
        if (array_key_exists('mapEmbed', $site) && is_string($site['mapEmbed'])) {
            $embed = $site['mapEmbed'];
            $embed = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $embed) ?? '';
            $site['mapEmbed'] = $embed;
        }
        PlatformSettings::put('cms_site', json_encode($site, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function store(UploadedFile $file, string $kind): string
    {
        $dir = public_path('uploads/branding');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = $kind.'-'.Str::lower(Str::random(8)).'.'.$ext;
        $file->move($dir, $name);
        $websiteDir = dirname(base_path()).DIRECTORY_SEPARATOR.'website'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'branding';
        if (is_dir(dirname($websiteDir))) {
            if (! is_dir($websiteDir)) {
                mkdir($websiteDir, 0775, true);
            }
            @copy($dir.DIRECTORY_SEPARATOR.$name, $websiteDir.DIRECTORY_SEPARATOR.$name);
        }

        return '/uploads/branding/'.$name;
    }

    public function ensureCatalogTable(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('catalog_services')) {
            if (! $schema->hasColumn('catalog_services', 'image_url')) {
                $schema->table('catalog_services', function ($table) {
                    $table->string('image_url', 255)->nullable();
                });
            }

            return;
        }
        $schema->create('catalog_services', function ($table) {
            $table->id();
            $table->string('slug', 80);
            $table->string('title', 120);
            $table->string('subtitle', 180)->nullable();
            $table->string('service_group', 24)->default('RIDE');
            $table->string('category_key', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
}
