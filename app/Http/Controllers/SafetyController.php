<?php

namespace App\Http\Controllers;

use App\Platform\SafetyPolicy;
use App\Services\SafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SafetyController extends Controller
{
    public function __construct(private readonly SafetyService $safety) {}

    public function index(Request $request): View
    {
        return view('safety.index', $this->safety->workspace($request->user(), $request->query()));
    }

    public function show(Request $request, int $incident): View
    {
        abort_unless($request->user()?->can('safety.view'), 403);

        return view('safety.show', [
            'incident' => $this->safety->oneIncident($request->user(), $incident),
            'catalog' => $this->safety->catalog(),
        ]);
    }

    public function review(Request $request, int $incident): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', SafetyPolicy::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->safety->review($request->user(), $incident, $data);

        return back()->with('status', 'Incident updated.');
    }

    public function share(string $token): View
    {
        return view('safety.share', ['share' => $this->safety->publicShare($token)]);
    }
}
