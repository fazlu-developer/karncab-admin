<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->payments->catalog());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['payments' => $this->payments->list($request->user(), $request->query())]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->payments->one($request->user(), $id));
    }

    public function invoices(Request $request): JsonResponse
    {
        return response()->json(['invoices' => $this->payments->invoices($request->user())]);
    }

    public function invoice(Request $request, int $id): JsonResponse
    {
        return response()->json($this->payments->invoice($request->user(), $id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string'],
            'booking_id' => ['required', 'integer'],
            'amount_paise' => ['nullable', 'integer', 'min:1'],
            'intent' => ['nullable', 'in:capture,advance,partial'],
            'note' => ['nullable', 'string', 'max:255'],
            'status' => ['prohibited'],
        ]);

        return response()->json($this->payments->initiate($request->user(), $data), 201);
    }

    public function confirmCash(Request $request, int $id): JsonResponse
    {
        return response()->json($this->payments->confirmCash($request->user(), $id));
    }

    public function fail(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->payments->markFailed($request->user(), $id, $data));
    }

    public function clientSuccess(): never
    {
        $this->payments->rejectClientCapture();
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount_paise' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->payments->requestRefund($request->user(), $id, $data), 201);
    }

    public function refundStep(Request $request, int $id, string $step): JsonResponse
    {
        abort_unless(in_array($step, ['approved', 'processing', 'completed'], true), 404);

        return response()->json($this->payments->advanceRefund($request->user(), $id, $step));
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string'],
            'paymentRef' => ['required', 'string'],
            'amountPaise' => ['required', 'integer', 'min:0'],
            'gatewayPaymentId' => ['nullable', 'string'],
            'gatewayEventId' => ['nullable', 'string'],
        ]);

        return response()->json($this->payments->handleWebhook(
            $provider,
            $request->header('X-Karnacab-Webhook-Signature'),
            $data,
        ));
    }
}
