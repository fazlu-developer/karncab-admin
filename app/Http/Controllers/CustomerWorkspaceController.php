<?php

namespace App\Http\Controllers;

use App\Platform\SupportPolicy;
use App\Services\CustomerExperienceService;
use App\Services\SafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerWorkspaceController extends Controller
{
    public function __construct(
        private readonly CustomerExperienceService $me,
        private readonly SafetyService $safety,
    ) {}

    public function dashboard(Request $request): View
    {
        return view('customer.dashboard', $this->me->dashboard($request->user()));
    }

    public function section(Request $request, string $section): View
    {
        return view('customer.section', $this->me->section($request->user(), $section));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'last_address' => ['nullable', 'string', 'max:255'],
        ]);
        $this->me->updateProfile($request->user(), $data);

        return back()->with('status', 'Profile saved.');
    }

    public function updateEmergency(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'emergency_name' => ['nullable', 'string', 'max:120'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
        ]);
        $this->me->updateEmergency($request->user(), $data);

        return back()->with('status', 'Emergency contact saved.');
    }

    public function storeFamily(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'relation' => ['nullable', 'string', 'max:40'],
        ]);
        $this->me->addFamily($request->user(), $data);

        return back()->with('status', 'Family member added. They can be the passenger on a booking.');
    }

    public function destroyFamily(Request $request, int $member): RedirectResponse
    {
        $this->me->removeFamily($request->user(), $member);

        return back()->with('status', 'Family member removed.');
    }

    public function storePlace(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);
        $this->me->addPlace($request->user(), $data);

        return back()->with('status', 'Location saved.');
    }

    public function destroyPlace(Request $request, int $place): RedirectResponse
    {
        $this->me->removePlace($request->user(), $place);

        return back()->with('status', 'Location removed.');
    }

    public function storeComplaint(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(SupportPolicy::CATEGORIES))],
            'priority' => ['nullable', 'in:'.implode(',', SupportPolicy::PRIORITIES)],
            'booking_id' => ['nullable', 'integer'],
            'file' => ['nullable', 'file', 'max:2048'],
        ]);
        if ($request->hasFile('file')) {
            $data['attachment'] = $request->file('file');
        }
        $this->me->addComplaint($request->user(), $data);

        return back()->with('status', 'Complaint opened.');
    }

    public function storeRating(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        $this->me->addRating($request->user(), $data);

        return back()->with('status', 'Rating saved.');
    }

    public function readNotification(Request $request, int $notification): RedirectResponse
    {
        $this->me->markNotificationRead($request->user(), $notification);

        return back()->with('status', 'Notification marked read.');
    }

    public function sos(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:police,emergency,karnacab,share'],
            'booking_id' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);
        $this->safety->sos($request->user(), $data);

        return back()->with('status', ($data['kind'] ?? '') === 'share' ? 'Trip share link refreshed.' : 'SOS logged. Call police or KarnaCab from the numbers shown.');
    }

    public function share(Request $request): RedirectResponse
    {
        $share = $this->safety->share($request->user(), $request->integer('booking_id') ?: null);

        return back()->with('status', 'Share link: '.$share['url']);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'relation' => ['nullable', 'string', 'max:40'],
        ]);
        $this->safety->saveEmergency($request->user(), $data);

        return back()->with('status', 'Emergency contact saved.');
    }

    public function destroyContact(Request $request, int $contact): RedirectResponse
    {
        $this->safety->removeContact($request->user(), $contact);

        return back()->with('status', 'Emergency contact removed.');
    }
}
