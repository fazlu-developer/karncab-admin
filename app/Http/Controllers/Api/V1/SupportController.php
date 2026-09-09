<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\SupportPolicy;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SupportController extends Controller
{
    public function __construct(private readonly SupportService $tickets) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->tickets->catalog());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['tickets' => $this->tickets->list($request->user(), $request->query())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(SupportPolicy::CATEGORIES))],
            'priority' => ['nullable', 'in:'.implode(',', SupportPolicy::PRIORITIES)],
            'kind' => ['nullable', 'in:'.implode(',', SupportPolicy::KINDS)],
            'booking_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->tickets->create($request->user(), $data), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->tickets->one($request->user(), $id));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        return response()->json($this->tickets->assign($request->user(), $id, $request->integer('agent_id') ?: null));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', SupportPolicy::STATUSES)],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($this->tickets->transition($request->user(), $id, $data));
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        $staff = $request->user()->can('safety.edit');

        return response()->json($this->tickets->reply($request->user(), $id, $data['body'], $staff));
    }

    public function attach(Request $request, int $id): JsonResponse
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => ['required', 'file', 'max:2048']]);

            return response()->json($this->tickets->attach($request->user(), $id, $request->file('file')), 201);
        }
        $data = $request->validate([
            'fileBase64' => ['required', 'string'],
            'mime' => ['required', 'string'],
            'fileName' => ['nullable', 'string', 'max:160'],
        ]);

        return response()->json($this->tickets->attachBase64($request->user(), $id, $data), 201);
    }

    public function file(Request $request, int $id, int $attachmentId): Response
    {
        $file = $this->tickets->attachmentFile($request->user(), $id, $attachmentId);

        return response($file['bytes'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['name'].'"',
        ]);
    }
}
