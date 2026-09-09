<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformPayloadResource;
use App\Http\Resources\SessionResource;
use App\Services\PlatformOpsService;
use Illuminate\Http\Request;
use Throwable;

class PlatformSessionController extends Controller
{
    public function show(Request $request): SessionResource
    {
        return new SessionResource($request->user());
    }

    public function platformHealth(PlatformOpsService $ops): PlatformPayloadResource
    {
        $this->authorize('bookings.read');

        try {
            $ops->ping();
            $payload = ['status' => 'ok', 'source' => 'platform'];
        } catch (Throwable $exception) {
            $payload = ['status' => 'down', 'source' => 'platform', 'error' => $exception->getMessage()];
        }

        return new PlatformPayloadResource($payload);
    }
}
