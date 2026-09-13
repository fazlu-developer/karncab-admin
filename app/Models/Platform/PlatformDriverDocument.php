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

    public function fileUrl(): ?string
    {
        $key = (string) $this->storage_key;
        if ($key === '') {
            return null;
        }
        if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
            return $key;
        }
        $base = rtrim((string) config('services.api_public', env('API_PUBLIC_URL', 'http://127.0.0.1:8003')), '/');

        return $base.'/storage/'.ltrim($key, '/');
    }

    public function label(): string
    {
        return match ($this->type) {
            'LICENSE', 'LICENSE_FRONT' => 'Driving licence (front)',
            'LICENSE_BACK' => 'Driving licence (back)',
            'RC' => 'Vehicle RC',
            'INSURANCE' => 'Insurance certificate',
            'SELFIE' => 'Driver photo',
            'ID_PROOF' => 'ID proof',
            'AADHAAR_FRONT' => 'Aadhaar front',
            'AADHAAR_BACK' => 'Aadhaar back',
            'PAN' => 'PAN card',
            'PERMIT' => 'Commercial permit',
            'FITNESS' => 'Fitness certificate',
            'PUC', 'POLLUTION' => 'Pollution certificate',
            'VEHICLE_PHOTO' => 'Vehicle photo',
            'VEHICLE_DRIVER_PHOTO' => 'Vehicle with driver',
            default => $this->type,
        };
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
