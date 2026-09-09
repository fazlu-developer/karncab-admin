<?php

namespace App\Http\Controllers;

use App\Platform\CouponEngine;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponsController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    public function index(Request $request): View
    {
        return view('coupons.index', $this->coupons->workspace($request->user(), $request->query()));
    }

    public function show(Request $request, int $coupon): View
    {
        return view('coupons.show', [
            'coupon' => $this->coupons->one($request->user(), $coupon),
            'catalog' => $this->coupons->catalog(),
            'states' => $this->coupons->workspace($request->user())['states'],
            'districts' => $this->coupons->workspace($request->user())['districts'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $row = $this->coupons->upsert($request->user(), $this->payload($request, true));

        return redirect()->route('coupons.show', $row['id'])->with('status', 'Coupon saved. Discounts are always computed on the server.');
    }

    public function update(Request $request, int $coupon): RedirectResponse
    {
        $this->coupons->upsert($request->user(), $this->payload($request, false) + [
            'active' => $request->boolean('active'),
        ], $coupon);

        return back()->with('status', 'Coupon updated.');
    }

    public function preview(Request $request): RedirectResponse
    {
        $quoted = $this->coupons->preview($request->user(), $request->all());

        return back()->with('status', 'Server quote: ₹'.number_format($quoted['discountPaise'] / 100, 2).' off. Payable ₹'.number_format($quoted['payablePaise'] / 100, 2).'.');
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
            'discount_paise' => ['prohibited'],
        ]);
        $data['active'] = $request->boolean('active');
        $data['product'] = $data['product'] ?: null;

        return $data;
    }
}
