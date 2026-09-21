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
            $dir = dirname($full);
            if (! is_dir($dir) && ! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
                continue;
            }
            if (@file_put_contents($full, $binary) !== false) {
                $ok = true;
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

        return null;
    }

    /**
     * @return list<string>
     */
    private function absoluteCandidates(string $key): array
    {
        return array_values(array_unique([
            storage_path('app/public/'.$key),
            storage_path('app/private/'.$key),
            base_path('../api/storage/app/public/'.$key),
            base_path('../api/storage/app/private/'.$key),
        ]));
    }
}
