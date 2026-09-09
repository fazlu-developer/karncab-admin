<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DistrictFranchiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FranchiseController extends Controller
{
    public function __construct(private readonly DistrictFranchiseService $franchises) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['franchises' => $this->franchises->list($request->user(), $request->query())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:DISTRICT_HEAD,EXCLUSIVE_FRANCHISE'],
            'trade_name' => ['required', 'string', 'max:160'],
            'district_id' => ['required', 'integer'],
            'owner_user_id' => ['nullable', 'integer'],
            'name' => ['required_without:owner_user_id', 'nullable', 'string', 'max:120'],
            'email' => ['required_without:owner_user_id', 'nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required_without:owner_user_id', 'nullable', 'string', 'min:8'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'fee_amount_paise' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->franchises->apply($request->user(), $data), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->franchises->one($request->user(), $id));
    }

    public function lifecycle(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->franchises->lifecycle($request->user(), $id, $data['status'], $data['reason'] ?? null));
    }

    public function kyc(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'kyc_status' => ['required', 'in:pending,submitted,verified,rejected'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->franchises->setKyc($request->user(), $id, $data['kyc_status'], $data['reason'] ?? null));
    }

    public function document(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:32'],
            'storage_key' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:180'],
            'mime' => ['nullable', 'string', 'max:80'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
            'checksum_sha256' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json($this->franchises->addDocument($request->user(), $id, $data));
    }

    public function agreement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['version' => ['nullable', 'string', 'max:32']]);

        return response()->json($this->franchises->signAgreement($request->user(), $id, $data['version'] ?? 'v1'));
    }

    public function fee(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'max:24'],
            'amount_paise' => ['required', 'integer', 'min:1'],
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->franchises->addFee($request->user(), $id, $data));
    }

    public function payFee(Request $request, int $id, int $feeId): JsonResponse
    {
        return response()->json($this->franchises->payFee($request->user(), $id, $feeId));
    }

    public function renewal(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
        ]);

        return response()->json($this->franchises->requestRenewal($request->user(), $id, $data));
    }

    public function decideRenewal(Request $request, int $id, int $renewalId): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);

        return response()->json($this->franchises->decideRenewal($request->user(), $id, $renewalId, $data['status']));
    }

    public function commission(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['commission_percent' => ['required', 'numeric', 'min:0', 'max:100']]);

        return response()->json($this->franchises->setCommission($request->user(), $id, (float) $data['commission_percent']));
    }

    public function territory(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['district_id' => ['required', 'integer']]);

        return response()->json($this->franchises->reassign($request->user(), $id, (int) $data['district_id']));
    }
}
