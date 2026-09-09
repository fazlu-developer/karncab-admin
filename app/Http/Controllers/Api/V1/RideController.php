<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\RideCatalog;
use App\Services\RideBookingService;
use App\Services\RideQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function __construct(
        private readonly RideQuoteService $quotes,
        private readonly RideBookingService $bookings,
    ) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->quotes->catalog());
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string'],
            'category' => ['required', 'string'],
            'distanceKm' => ['nullable', 'numeric', 'min:0', 'max:4000'],
            'pickupText' => ['nullable', 'string', 'max:255'],
            'dropText' => ['nullable', 'string', 'max:255'],
            'pickupLat' => ['nullable', 'numeric'],
            'pickupLng' => ['nullable', 'numeric'],
            'dropLat' => ['nullable', 'numeric'],
            'dropLng' => ['nullable', 'numeric'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:24'],
            'districtId' => ['nullable', 'integer'],
            'waitMinutes' => ['nullable', 'integer', 'min:0'],
            'night' => ['nullable', 'boolean'],
            'roundTrip' => ['nullable', 'boolean'],
            'couponCode' => ['nullable', 'string', 'max:40'],
            'totalPaise' => ['prohibited'],
            'discountPaise' => ['prohibited'],
            'couponDiscountPaise' => ['prohibited'],
        ]);
        RideCatalog::assertProduct($data['product']);
        RideCatalog::assertCategory($data['category']);

        return response()->json($this->quotes->quote($data));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string'],
            'category' => ['required', 'string'],
            'pickupText' => ['required', 'string', 'max:255'],
            'dropText' => ['required', 'string', 'max:255'],
            'pickupLat' => ['nullable', 'numeric'],
            'pickupLng' => ['nullable', 'numeric'],
            'dropLat' => ['nullable', 'numeric'],
            'dropLng' => ['nullable', 'numeric'],
            'distanceKm' => ['nullable', 'numeric', 'min:0', 'max:4000'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:24'],
            'scheduledAt' => ['nullable', 'date'],
            'returnAt' => ['nullable', 'date'],
            'passengerName' => ['required', 'string', 'max:120'],
            'passengerPhone' => ['required', 'string', 'max:20'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'flightNumber' => ['nullable', 'string', 'max:32'],
            'trainNumber' => ['nullable', 'string', 'max:32'],
            'terminal' => ['nullable', 'string', 'max:80'],
            'districtId' => ['nullable', 'integer'],
            'couponCode' => ['nullable', 'string', 'max:40'],
            'bookedForOther' => ['nullable', 'boolean'],
            'totalPaise' => ['prohibited'],
            'discountPaise' => ['prohibited'],
            'couponDiscountPaise' => ['prohibited'],
        ]);

        return response()->json($this->bookings->create($request->user(), $data), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->bookings->one($request->user(), $id));
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
            'status' => ['prohibited'],
        ]);

        return response()->json($this->bookings->pay($request->user(), $id, $data), 201);
    }
}
