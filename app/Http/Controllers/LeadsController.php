<?php

namespace App\Http\Controllers;

use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadsController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request): View
    {
        return view('leads.index', $this->leads->workspace($request->user(), $request->query()));
    }

    public function show(Request $request, int $lead): View
    {
        return view('leads.show', [
            'lead' => $this->leads->one($request->user(), $lead),
            'catalog' => ['statuses' => LeadService::STATUSES],
        ]);
    }

    public function update(Request $request, int $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', LeadService::STATUSES)],
        ]);
        $this->leads->updateStatus($request->user(), $lead, $data);

        return back()->with('status', 'Lead marked '.$data['status'].'.');
    }
}
