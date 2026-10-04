<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StoredUpload
{
    public static function replace(UploadedFile $file, string $folder, ?string $previous = null, string $prefix = 'file'): string
    {
        self::forget($previous);
        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: 'jpg'));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';
        $name = $prefix.'-'.Str::lower(Str::random(8)).'.'.$ext;
        $relative = 'uploads/'.trim($folder, '/').'/'.$name;
        $primary = public_path($relative);
        if (! is_dir(dirname($primary))) {
            mkdir(dirname($primary), 0775, true);
        }
        $file->move(dirname($primary), $name);
        foreach (array_slice(self::roots(), 1) as $root) {
            if (! is_dir(dirname($root))) {
                continue;
            }
            $dest = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0775, true);
            }
            @copy($primary, $dest);
        }

        return '/'.$relative;
    }

    public static function forget(?string $url): void
    {
        $relative = self::relative($url);
        if ($relative === null) {
            return;
        }
        foreach (self::roots() as $root) {
            $full = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    private static function relative(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH);
        $path = ltrim(str_replace('\\', '/', $path ?: $url), '/');
        if (! str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    private static function roots(): array
    {
        $base = dirname(base_path());

        return [
            public_path(),
            $base.DIRECTORY_SEPARATOR.'website'.DIRECTORY_SEPARATOR.'public',
            $base.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
        ];
    }
}
