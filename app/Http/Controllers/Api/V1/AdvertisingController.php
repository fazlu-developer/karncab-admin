<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\AdPolicy;
use App\Services\AdvertisingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdvertisingController extends Controller
{
    public function __construct(private readonly AdvertisingService $ads) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->ads->catalog());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['campaigns' => $this->ads->list($request->user(), $request->query())]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->ads->one($request->user(), $id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'business_name' => ['required', 'string', 'min:2', 'max:160'],
            'business_info' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'in:'.implode(',', array_keys(AdPolicy::CATEGORIES))],
            'campaign_type' => ['required', 'in:'.implode(',', array_keys(AdPolicy::TYPES))],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'target_city' => ['nullable', 'string', 'max:80'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date'],
            'budget_rupees' => ['required', 'integer', 'min:1'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'status' => ['prohibited'],
        ]);

        return response()->json($this->ads->create($request->user(), $data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'min:2', 'max:160'],
            'business_name' => ['nullable', 'string', 'min:2', 'max:160'],
            'business_info' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(AdPolicy::CATEGORIES))],
            'campaign_type' => ['nullable', 'in:'.implode(',', array_keys(AdPolicy::TYPES))],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'target_city' => ['nullable', 'string', 'max:80'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'budget_rupees' => ['nullable', 'integer', 'min:1'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'status' => ['prohibited'],
        ]);

        return response()->json($this->ads->update($request->user(), $id, $data));
    }

    public function banner(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'banner' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        return response()->json($this->ads->uploadBanner($request->user(), $id, $request->file('banner')));
    }

    public function bannerFile(Request $request, int $id): Response
    {
        $file = $this->ads->bannerFile($request->user(), $id);

        return response()->file($file['path'], ['Content-Type' => $file['mime']]);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->ads->review($request->user(), $id, $data));
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        return response()->json($this->ads->pause($request->user(), $id));
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        return response()->json($this->ads->resume($request->user(), $id));
    }

    public function serve(Request $request): JsonResponse
    {
        return response()->json($this->ads->serve($request->user(), $request->query()));
    }

    public function impression(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'placement' => ['required', 'string'],
        ]);

        return response()->json($this->ads->track($request->user(), $id, 'impression', $data));
    }

    public function click(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'placement' => ['required', 'string'],
        ]);

        return response()->json($this->ads->track($request->user(), $id, 'click', $data));
    }
}
