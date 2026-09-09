<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StateHeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StateHeadController extends Controller
{
    public function __construct(private readonly StateHeadService $stateHeads) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->stateHeads->dashboard($request->user(), $request->query()));
    }

    public function show(Request $request, string $section): JsonResponse
    {
        return response()->json($this->stateHeads->section($section, $request->user(), $request->query()));
    }

    public function storeDistrictHead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'district_id' => ['required', 'integer'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        return response()->json($this->stateHeads->createDistrictHead($request->user(), $data, $request->query()), 201);
    }

    public function storeFranchise(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', 'in:DISTRICT_HEAD,EXCLUSIVE_FRANCHISE'],
            'trade_name' => ['required', 'string', 'max:160'],
            'district_id' => ['required', 'integer'],
            'owner_user_id' => ['nullable', 'integer'],
            'name' => ['required_without:owner_user_id', 'nullable', 'string', 'max:120'],
            'email' => ['required_without:owner_user_id', 'nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required_without:owner_user_id', 'nullable', 'string', 'min:8'],
            'fee_amount_paise' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->stateHeads->saveFranchise($request->user(), $data, $request->query()), 201);
    }

    public function notify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:500'],
            'audience' => ['required', 'in:customer,driver,ops'],
        ]);
        $count = $this->stateHeads->notify($request->user(), $data, $request->query());

        return response()->json(['ok' => true, 'recipients' => $count]);
    }
}
