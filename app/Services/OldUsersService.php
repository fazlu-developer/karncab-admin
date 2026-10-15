<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OldUsersService
{
    /**
     * @return array{imported: int, source: ?string, source_count: int, total: int}
     */
    public function import(): array
    {
        $this->ensureTable();
        $source = $this->resolveLegacyDatabase();
        abort_unless($source, 422, 'Old website database not found. Expected karnacab_website or krna_cab.');
        $this->bindLegacyDatabase($source);
        abort_unless(Schema::connection('legacy_website')->hasTable('users'), 422, 'No users table in '.$source);

        $sourceCount = (int) DB::connection('legacy_website')->table('users')->count();
        $imported = 0;
        $now = now();
        DB::connection('legacy_website')->table('users')->orderBy('id')->chunk(200, function ($rows) use (&$imported, $now, $source) {
            foreach ($rows as $row) {
                $this->upsertRow($row, $source, $now);
                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'source' => $source,
            'source_count' => $sourceCount,
            'total' => (int) DB::connection('platform')->table('old_users')->count(),
        ];
    }

    /**
     * @return array{imported: int, source: string, source_count: int, total: int}
     */
    public function importUpload(string $path, string $originalName): array
    {
        $this->ensureTable();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        abort_unless(in_array($ext, ['csv', 'txt', 'sql'], true), 422, 'Upload a phpMyAdmin CSV or SQL export of the users table.');

        if ($ext === 'sql') {
            $this->importSqlDump($path);

            return $this->import();
        }

        $imported = $this->importCsv($path);
        $total = (int) DB::connection('platform')->table('old_users')->count();

        return [
            'imported' => $imported,
            'source' => 'csv_upload',
            'source_count' => $imported,
            'total' => $total,
        ];
    }

    /**
     * @return array{rows: LengthAwarePaginator, counts: array<string, int>}
     */
    public function listing(string $q = '', string $role = '', string $tag = '', int $perPage = 25): array
    {
        $this->ensureTable();
        if (DB::connection('platform')->table('old_users')->count() === 0) {
            try {
                $this->import();
            } catch (Throwable) {
            }
        }

        $registeredPhones = $this->platformPhones();
        $phoneList = array_keys($registeredPhones);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;

        $query = DB::connection('platform')->table('old_users')->orderByDesc('source_id');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('district', 'like', $like)
                    ->orWhere('state', 'like', $like)
                    ->orWhere('old_role', 'like', $like);
                if (ctype_digit($q)) {
                    $inner->orWhere('source_id', (int) $q)
                        ->orWhere('role_id', (int) $q);
                }
            });
        }
        if ($role === 'CUSTOMER') {
            $query->where('role_id', 3);
        } elseif ($role === 'DRIVER') {
            $query->where('role_id', 2);
        } elseif ($role === 'ADMIN') {
            $query->where('role_id', 1);
        } elseif ($role === 'OTHER') {
            $query->whereNotIn('role_id', [1, 2, 3]);
        }
        if ($tag === 'registered') {
            if ($phoneList === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('phone', $phoneList);
            }
        } elseif ($tag === 'pending') {
            $query->where(function ($inner) use ($phoneList) {
                $inner->whereNull('phone')->orWhere('phone', '');
                if ($phoneList !== []) {
                    $inner->orWhereNotIn('phone', $phoneList);
                }
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(function ($row) use ($registeredPhones) {
            $phone = $this->digits((string) ($row->phone ?? ''));
            $match = $phone !== '' ? ($registeredPhones[$phone] ?? null) : null;

            return [
                'id' => (int) $row->id,
                'sourceId' => (int) $row->source_id,
                'name' => $row->name,
                'phone' => $row->phone,
                'email' => $row->email,
                'gender' => $row->gender,
                'city' => $row->city,
                'district' => $row->district,
                'state' => $row->state,
                'roleId' => (int) $row->role_id,
                'oldRole' => $row->old_role,
                'registered' => $match !== null,
                'platformRole' => $match['role'] ?? null,
                'platformName' => $match['name'] ?? null,
                'sourceCreatedAt' => $row->source_created_at,
            ];
        });

        return ['rows' => $paginator, 'counts' => $this->counts($phoneList)];
    }

    public function ensureTable(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('old_users')) {
            return;
        }
        $schema->create('old_users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('source_db', 64)->nullable();
            $table->unsignedInteger('role_id')->default(0);
            $table->string('old_role', 24)->nullable();
            $table->string('name', 180);
            $table->string('phone', 20)->nullable()->index();
            $table->string('email', 180)->nullable();
            $table->string('gender', 24)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('status', 24)->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamps();
        });
    }

    public function resolveLegacyDatabase(): ?string
    {
        $counts = $this->discoverLegacyCounts();
        if ($counts === []) {
            $preferred = env('LEGACY_WEBSITE_DB');

            return is_string($preferred) && $preferred !== '' ? $preferred : null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @return array<string, int>
     */
    public function discoverLegacyCounts(): array
    {
        $names = [];
        try {
            $names = collect(DB::connection('mysql')->select('SHOW DATABASES'))
                ->map(fn ($row) => (string) (array_values((array) $row)[0] ?? ''))
                ->all();
        } catch (Throwable) {
            $fallback = array_values(array_unique(array_filter([
                env('LEGACY_WEBSITE_DB'),
                'karnacab_website',
                'krna_cab',
            ])));

            return array_fill_keys($fallback, 0);
        }

        $skip = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];
        $hits = [];
        foreach ($names as $name) {
            if ($name === '' || in_array(strtolower($name), $skip, true)) {
                continue;
            }
            $looksLegacy = (bool) preg_match('/karna|krna|cab|website/i', $name);
            $preferred = env('LEGACY_WEBSITE_DB');
            if (! $looksLegacy && $name !== $preferred) {
                continue;
            }
            try {
                $this->bindLegacyDatabase($name);
                if (! Schema::connection('legacy_website')->hasTable('users')) {
                    continue;
                }
                $cols = Schema::connection('legacy_website')->getColumnListing('users');
                if (! in_array('role_id', $cols, true)) {
                    continue;
                }
                $hits[$name] = (int) DB::connection('legacy_website')->table('users')->count();
            } catch (Throwable) {
                continue;
            }
        }

        return $hits;
    }

    public function bindLegacyDatabase(string $database): void
    {
        config(['database.connections.legacy_website.database' => $database]);
        DB::purge('legacy_website');
    }

    /**
     * @param  list<string>  $phoneList
     * @return array<string, int>
     */
    private function counts(array $phoneList): array
    {
        $table = DB::connection('platform')->table('old_users');
        $all = (int) (clone $table)->count();
        $customer = (int) (clone $table)->where('role_id', 3)->count();
        $driver = (int) (clone $table)->where('role_id', 2)->count();
        $registered = $phoneList === [] ? 0 : (int) (clone $table)->whereIn('phone', $phoneList)->count();

        return [
            'all' => $all,
            'customer' => $customer,
            'driver' => $driver,
            'registered' => $registered,
            'pending' => max(0, $all - $registered),
        ];
    }

    private function importCsv(string $path): int
    {
        $handle = fopen($path, 'r');
        abort_unless($handle, 422, 'Could not read the uploaded CSV.');
        $first = fgets($handle);
        abort_unless(is_string($first) && $first !== '', 422, 'CSV file is empty.');
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = str_getcsv($first, $delimiter);
        $header = array_map(fn ($col) => strtolower(trim((string) $col, " \t\n\r\0\x0B\"'`")), $header);
        abort_unless($header !== [], 422, 'CSV has no header row.');

        $now = now();
        $imported = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }
            if (count($data) < 2) {
                continue;
            }
            $assoc = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = $data[$i] ?? '';
            }
            if ($assoc === []) {
                continue;
            }
            $this->upsertRow((object) $assoc, 'csv_upload', $now);
            $imported++;
        }
        fclose($handle);

        return $imported;
    }

    private function importSqlDump(string $path): void
    {
        DB::connection('mysql')->statement('CREATE DATABASE IF NOT EXISTS `karnacab_website` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $mysql = $this->mysqlBinary();
        abort_unless($mysql, 422, 'mysql client not found. Upload a CSV export of users instead.');
        $user = (string) config('database.connections.mysql.username');
        $pass = (string) config('database.connections.mysql.password');
        $host = (string) config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port');
        $cmd = [$mysql, '-h', $host, '-P', $port, '-u', $user];
        if ($pass !== '') {
            $cmd[] = '-p'.$pass;
        }
        $cmd[] = 'karnacab_website';
        $process = proc_open($cmd, [
            0 => ['file', $path, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        abort_unless(is_resource($process), 422, 'Could not import the SQL dump.');
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        abort_unless($code === 0, 422, 'SQL import failed. Export only the users table as CSV. '.trim((string) $stderr));
        $this->bindLegacyDatabase('karnacab_website');
    }

    private function mysqlBinary(): ?string
    {
        $candidates = [
            'd:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'mysql',
        ];
        foreach ($candidates as $bin) {
            if ($bin === 'mysql' || is_file($bin)) {
                return $bin;
            }
        }

        return null;
    }

    private function upsertRow(object $row, string $source, mixed $now): void
    {
        $sourceId = (int) ($row->id ?? $row->source_id ?? 0);
        if ($sourceId < 1) {
            return;
        }
        $roleId = (int) ($row->role_id ?? $row->role ?? 0);
        $phone = $this->digits((string) ($row->mobile_no ?? $row->phone ?? $row->mobile ?? ''));
        DB::connection('platform')->table('old_users')->updateOrInsert(
            ['source_id' => $sourceId],
            [
                'source_db' => $source,
                'role_id' => $roleId,
                'old_role' => $this->roleLabel($roleId),
                'name' => trim((string) ($row->name ?? '')) ?: 'Unknown',
                'phone' => $phone !== '' ? $phone : null,
                'email' => $this->nullable((string) ($row->email ?? '')),
                'gender' => $this->nullable((string) ($row->gender ?? '')),
                'city' => $this->nullable((string) ($row->city ?? '')),
                'district' => $this->nullable((string) ($row->district ?? '')),
                'state' => $this->nullable((string) ($row->state ?? $row->ride_state ?? '')),
                'status' => (string) ($row->status ?? '1'),
                'source_created_at' => $this->timestamp($row->created_at ?? null),
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    /**
     * @return array<string, array{role: string, name: string}>
     */
    private function platformPhones(): array
    {
        if (! Schema::connection('platform')->hasTable('users')) {
            return [];
        }
        $out = [];
        foreach (DB::connection('platform')->table('users')->get(['name', 'phone', 'role']) as $row) {
            $phone = $this->digits((string) ($row->phone ?? ''));
            if ($phone === '') {
                continue;
            }
            $out[$phone] = [
                'role' => (string) $row->role,
                'name' => (string) $row->name,
            ];
        }

        return $out;
    }

    private function roleLabel(int $roleId): string
    {
        return match ($roleId) {
            2 => 'DRIVER',
            3 => 'CUSTOMER',
            1 => 'ADMIN',
            default => 'OTHER',
        };
    }

    private function digits(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' || $value === 'NULL' ? null : $value;
    }

    private function timestamp(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '0000-00-00') || strtoupper($raw) === 'NULL') {
            return null;
        }
        $time = strtotime($raw);

        return $time ? date('Y-m-d H:i:s', $time) : null;
    }
}
