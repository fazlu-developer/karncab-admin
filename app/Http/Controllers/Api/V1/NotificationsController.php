<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\NotificationPolicy;
use App\Services\NotificationService;
use App\Services\PlatformOpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly PlatformOpsService $ops,
    ) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->notifications->catalog());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['notifications' => $this->notifications->inbox($request->user())]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        return response()->json(['deliveries' => $this->notifications->deliveries($request->user(), $request->query())]);
    }

    public function announce(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:500'],
            'audience' => ['required', 'in:customer,driver,ops'],
        ]);
        $count = $this->ops->announce($data, $request->user());

        return response()->json(['ok' => true, 'recipients' => $count]);
    }

    public function dispatch(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'event' => ['required', 'in:'.implode(',', array_keys(NotificationPolicy::EVENTS))],
            'phone' => ['nullable', 'string', 'max:20'],
            'vars' => ['nullable', 'array'],
        ]);

        return response()->json($this->notifications->dispatch(
            (int) $data['user_id'],
            $data['event'],
            $data['vars'] ?? [],
            ['phone' => $data['phone'] ?? null],
        ), 201);
    }
}
