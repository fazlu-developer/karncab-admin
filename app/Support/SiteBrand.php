<?php

namespace App\Support;

final class SiteBrand
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        $site = PlatformSettings::json('cms_site', []);
        $name = (string) ($site['name'] ?? 'KarnaCab');
        $logo = self::url((string) ($site['adminLogoUrl'] ?? $site['logoUrl'] ?? ''));
        $publicLogo = self::url((string) ($site['logoUrl'] ?? ''));
        $favicon = self::url((string) ($site['faviconUrl'] ?? ''));

        return [
            'name' => $name,
            'tagline' => (string) ($site['tagline'] ?? ''),
            'logoUrl' => $logo !== '' ? $logo : asset('branding/karnacab-logo-full.png'),
            'publicLogoUrl' => $publicLogo !== '' ? $publicLogo : asset('branding/karnacab-logo-full.png'),
            'faviconUrl' => $favicon !== '' ? $favicon : asset('favicon-32.png'),
            'icoUrl' => $favicon !== '' ? $favicon : asset('favicon.ico'),
            'seoTitle' => (string) ($site['defaultSeoTitle'] ?? $name),
            'seoDescription' => (string) ($site['defaultSeoDescription'] ?? ''),
        ];
    }

    private static function url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        return asset($value);
    }
}
