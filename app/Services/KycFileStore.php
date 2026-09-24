<?php

namespace App\Services;

class KycFileStore
{
    public function put(int $driverId, string $extension, string $binary): string
    {
        $path = 'kyc/'.$driverId.'/'.bin2hex(random_bytes(16)).'.'.$extension;
        abort_unless($this->write($path, $binary), 500, 'Could not save the attachment. Check that storage/app/public is writable.');

        return $path;
    }

    public function write(string $key, string $binary): bool
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        abort_if($key === '' || str_contains($key, '..'), 404);
        $ok = false;
        foreach ($this->absoluteCandidates($key) as $full) {
            try {
                $dir = dirname($full);
                if (! is_dir($dir) && ! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
                    continue;
                }
                if (@file_put_contents($full, $binary) !== false) {
                    $ok = true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $ok;
    }

    public function absolutePath(?string $key): ?string
    {
        $key = ltrim(str_replace('\\', '/', (string) $key), '/');
        if ($key === '' || str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
            return null;
        }
        foreach ($this->absoluteCandidates($key) as $full) {
            if (is_file($full) && filesize($full) > 0) {
                return $full;
            }
        }

        return $this->pullFromApi($key);
    }

    /**
     * @return list<string>
     */
    private function absoluteCandidates(string $key): array
    {
        $roots = [
            storage_path('app/public'),
            storage_path('app/private'),
            base_path('../api/storage/app/public'),
            base_path('../api/storage/app/private'),
            dirname(base_path()).'/api/storage/app/public',
            dirname(base_path()).'/api/storage/app/private',
            dirname(base_path(), 2).'/api/storage/app/public',
            dirname(base_path(), 2).'/api/storage/app/private',
        ];
        $extra = trim((string) env('KYC_STORAGE_ROOT', ''));
        if ($extra !== '') {
            $roots[] = rtrim($extra, '/\\');
        }

        return array_values(array_unique(array_map(
            fn ($root) => rtrim(str_replace('\\', '/', $root), '/').'/'.$key,
            $roots,
        )));
    }

    private function pullFromApi(string $key): ?string
    {
        $base = rtrim((string) config('services.api_public', 'https://api.karnacab.in'), '/');
        $url = $base.'/storage/'.$key;
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(12)->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $type = strtolower((string) $response->header('Content-Type'));
        $body = $response->body();
        if ($body === '' || str_contains($type, 'text/html')) {
            return null;
        }
        $dest = storage_path('app/public/'.$key);
        $dir = dirname($dest);
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
            return null;
        }
        if (@file_put_contents($dest, $body) === false) {
            return null;
        }

        return $dest;
    }
}
