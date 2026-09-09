<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletLedgerService $wallets) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['wallets' => $this->wallets->wallets($request->user(), $request->query())]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->wallets->wallet($request->user(), $id));
    }

    public function ledger(Request $request): JsonResponse
    {
        return response()->json(['ledger' => $this->wallets->ledger($request->user(), $request->query())]);
    }

    public function policy(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('payments.view') || $request->user()?->can('wallet.view'), 403);

        return response()->json($this->wallets->policy());
    }

    public function updatePolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'on_base_fare' => ['nullable', 'boolean'],
            'on_gst' => ['nullable', 'boolean'],
            'on_toll' => ['nullable', 'boolean'],
            'on_parking' => ['nullable', 'boolean'],
            'on_waiting' => ['nullable', 'boolean'],
            'on_discount' => ['nullable', 'boolean'],
            'on_other' => ['nullable', 'boolean'],
            'on_complete' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->wallets->updatePolicy($request->user(), $data));
    }

    public function post(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_type' => ['required', 'in:'.implode(',', WalletLedgerService::ACCOUNTS)],
            'owner_user_id' => ['required', 'integer'],
            'direction' => ['required', 'in:CREDIT,DEBIT'],
            'amount_paise' => ['required', 'integer', 'min:1'],
            'commission_paise' => ['nullable', 'integer', 'min:0'],
            'booking_id' => ['nullable', 'integer'],
            'kind' => ['nullable', 'string', 'max:24'],
            'payment_ref' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->wallets->post($request->user(), $data), 201);
    }

    public function settle(Request $request, int $id): JsonResponse
    {
        return response()->json($this->wallets->settleBooking($request->user(), $id));
    }
}
