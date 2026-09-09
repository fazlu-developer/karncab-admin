<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\CouponEngine;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->coupons->catalog());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['coupons' => $this->coupons->list($request->user(), $request->query())]);
    }

    public function offers(Request $request): JsonResponse
    {
        return response()->json(['offers' => $this->coupons->offers($request->user())]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->coupons->one($request->user(), $id));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->coupons->upsert($request->user(), $this->payload($request, true)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json($this->coupons->upsert($request->user(), $this->payload($request, false), $id));
    }

    public function preview(Request $request): JsonResponse
    {
        return response()->json($this->coupons->preview($request->user(), $request->all()));
    }

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:32'],
            'discount_paise' => ['prohibited'],
            'discountPaise' => ['prohibited'],
            'couponDiscountPaise' => ['prohibited'],
        ]);

        return response()->json($this->coupons->apply($request->user(), $data + $request->all()));
    }

    public function loyalty(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()->nest_user_id ?: $request->user()->id);
        if ($request->user()->can('platform.admin')) {
            return response()->json(['accounts' => $this->coupons->loyaltyAccounts()]);
        }

        return response()->json($this->coupons->loyaltyFor($userId));
    }

    public function earnLoyalty(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'points' => ['prohibited'],
        ]);

        return response()->json($this->coupons->earnLoyalty($request->user(), (int) $data['booking_id']));
    }

    public function previewLoyalty(Request $request): JsonResponse
    {
        return response()->json($this->coupons->previewLoyalty($request->user(), $request->all()));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'code' => [$creating ? 'required' : 'nullable', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', 'in:'.implode(',', CouponEngine::KINDS)],
            'percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'amount_paise' => ['nullable', 'integer', 'min:0'],
            'max_discount_paise' => ['nullable', 'integer', 'min:0'],
            'min_fare_paise' => ['nullable', 'integer', 'min:0'],
            'product' => ['nullable', 'in:'.implode(',', CouponEngine::PRODUCTS)],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'audience' => ['required', 'in:'.implode(',', array_keys(CouponEngine::AUDIENCES))],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date'],
            'active' => ['nullable', 'boolean'],
            'discount_paise' => ['prohibited'],
        ]);
        $data['product'] = ($data['product'] ?? null) ?: null;
        if ($request->exists('active')) {
            $data['active'] = $request->boolean('active');
        }

        return $data;
    }
}
