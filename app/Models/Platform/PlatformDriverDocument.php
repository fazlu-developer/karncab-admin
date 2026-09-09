<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformDriverDocument extends Model
{
    protected $connection = 'platform';

    protected $table = 'driver_documents';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(PlatformDriver::class, 'driver_id');
    }

    public function label(): string
    {
        return match ($this->type) {
            'LICENSE' => 'Driving licence',
            'RC' => 'Vehicle registration',
            'INSURANCE' => 'Insurance',
            'SELFIE' => 'Driver photo',
            'ID_PROOF' => 'ID proof',
            'PERMIT' => 'Permit',
            'FITNESS' => 'Fitness certificate',
            'PUC' => 'Pollution certificate',
            default => $this->type,
        };
    }

    public function fileUrl(): ?string
    {
        $key = (string) $this->storage_key;
        if ($key === '') {
            return null;
        }
        if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
            return $key;
        }
        if (str_starts_with($key, 'cld:')) {
            $parts = explode(':', $key, 3);
            $resource = $parts[1] ?? 'image';
            $publicId = $parts[2] ?? '';
            $cloud = config('services.cloudinary.cloud', 'fjq1hs8g');
            if ($publicId === '') {
                return null;
            }

            return "https://res.cloudinary.com/{$cloud}/{$resource}/upload/{$publicId}";
        }

        return null;
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
