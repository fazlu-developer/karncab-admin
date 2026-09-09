<?php

namespace App\Http\Controllers;

use App\Platform\NotificationPolicy;
use App\Services\NotificationService;
use App\Services\PlatformOpsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationsController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly PlatformOpsService $ops,
    ) {}

    public function index(Request $request): View
    {
        return view('notifications.index', $this->notifications->workspace($request->user(), $request->query()));
    }

    public function announce(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:500'],
            'audience' => ['required', 'in:customer,driver,ops'],
        ]);
        $this->ops->announce($data, $request->user());

        return back()->with('status', 'Announcement queued through NotificationService.');
    }

    public function dispatch(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'event' => ['required', 'in:'.implode(',', array_keys(NotificationPolicy::EVENTS))],
            'ref' => ['nullable', 'string', 'max:80'],
        ]);
        $this->notifications->dispatch((int) $data['user_id'], $data['event'], [
            'ref' => $data['ref'] ?? (string) $data['user_id'],
            'status' => 'updated',
            'amount' => '0.00',
            'code' => 'TEST',
            'note' => 'Admin test',
            'direction' => 'credit',
        ]);

        return back()->with('status', 'Event '.$data['event'].' dispatched.');
    }
}
