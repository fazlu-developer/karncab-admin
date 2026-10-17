<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class StateHeadKycStore
{
    /** @var list<string> */
    public const TYPES = ['PAN', 'AADHAAR', 'APPOINTMENT', 'ADDRESS', 'GST', 'PHOTO'];

    public static function ensureTable(): void
    {
        if (Schema::connection('platform')->hasTable('state_head_kyc')) {
            return;
        }
        Schema::connection('platform')->create('state_head_kyc', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('doc_type', 40);
            $table->string('path', 255);
            $table->string('original_name', 180)->nullable();
            $table->string('status', 24)->default('submitted');
            $table->timestamps();
        });
    }

    /**
     * @return list<object>
     */
    public static function headsFor(?object $actor): array
    {
        $q = DB::connection('platform')->table('users')
            ->where('role', 'STATE_HEAD')
            ->orderBy('name');
        if ($actor && method_exists($actor, 'isStateHead') && $actor->isStateHead() && $actor->nest_user_id) {
            $q->where('id', $actor->nest_user_id);
        }

        return $q->get(['id', 'name', 'email', 'phone', 'state_id'])->all();
    }

    public static function saveFromRequest(Request $request, int $userId, ?int $uploadedBy = null): int
    {
        self::ensureTable();
        $head = DB::connection('platform')->table('users')->where('id', $userId)->where('role', 'STATE_HEAD')->first();
        abort_if($head === null, 422, 'Select a State Head account.');
        $saved = 0;
        $map = [
            'kyc_pan' => 'PAN',
            'kyc_aadhaar' => 'AADHAAR',
            'kyc_appointment' => 'APPOINTMENT',
            'kyc_address' => 'ADDRESS',
            'kyc_gst' => 'GST',
            'kyc_photo' => 'PHOTO',
        ];
        foreach ($map as $field => $type) {
            $file = $request->file($field);
            if ($file instanceof UploadedFile && $file->isValid()) {
                self::insert($head, $type, $file, $uploadedBy);
                $saved++;
            }
        }
        $single = $request->file('file');
        if ($single instanceof UploadedFile && $single->isValid()) {
            $type = strtoupper((string) $request->input('doc_type', 'PAN'));
            if (! in_array($type, self::TYPES, true)) {
                $type = 'PAN';
            }
            self::insert($head, $type, $single, $uploadedBy);
            $saved++;
        }

        return $saved;
    }

    private static function insert(object $head, string $type, UploadedFile $file, ?int $uploadedBy): void
    {
        $path = $file->store('state-kyc', 'public');
        $row = [
            'user_id' => (int) $head->id,
            'state_id' => $head->state_id,
            'doc_type' => $type,
            'path' => $path,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::connection('platform')->hasColumn('state_head_kyc', 'uploaded_by')) {
            $row['uploaded_by'] = $uploadedBy;
        }
        if (Schema::connection('platform')->hasColumn('state_head_kyc', 'original_name')) {
            $row['original_name'] = $file->getClientOriginalName();
        }
        DB::connection('platform')->table('state_head_kyc')->insert($row);
    }

    public static function publicUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }
}
