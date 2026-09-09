<?php

namespace App\Http\Controllers;

use App\Platform\AdPolicy;
use App\Services\AdvertisingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdvertisingController extends Controller
{
    public function __construct(private readonly AdvertisingService $ads) {}

    public function index(Request $request): View
    {
        return view('ads.index', $this->ads->workspace($request->user(), $request->query()));
    }

    public function show(Request $request, int $ad): View
    {
        return view('ads.show', [
            'campaign' => $this->ads->one($request->user(), $ad),
            'catalog' => $this->ads->catalog(),
            'states' => $this->ads->states(),
            'districts' => $this->ads->districts(),
            'canReview' => $request->user()->isPrivilegedOperator(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $row = $this->ads->create($request->user(), $this->campaignPayload($request, true));

        return redirect()->route('ads.show', $row['id'])->with('status', 'Campaign submitted as pending. Admin approval is required before it can run.');
    }

    public function update(Request $request, int $ad): RedirectResponse
    {
        $this->ads->update($request->user(), $ad, $this->campaignPayload($request, false));

        return back()->with('status', 'Campaign saved.');
    }

    public function banner(Request $request, int $ad): RedirectResponse
    {
        $request->validate([
            'banner' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);
        $this->ads->uploadBanner($request->user(), $ad, $request->file('banner'));

        return back()->with('status', 'Banner uploaded.');
    }

    public function bannerFile(Request $request, int $ad): Response
    {
        $file = $this->ads->bannerFile($request->user(), $ad);

        return response()->file($file['path'], ['Content-Type' => $file['mime']]);
    }

    public function review(Request $request, int $ad): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $row = $this->ads->review($request->user(), $ad, $data);

        return back()->with('status', 'Campaign '.$row['status'].'.');
    }

    public function pause(Request $request, int $ad): RedirectResponse
    {
        $this->ads->pause($request->user(), $ad);

        return back()->with('status', 'Campaign paused.');
    }

    public function resume(Request $request, int $ad): RedirectResponse
    {
        $this->ads->resume($request->user(), $ad);

        return back()->with('status', 'Campaign resumed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignPayload(Request $request, bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'nullable', 'string', 'min:2', 'max:160'],
            'business_name' => [$creating ? 'required' : 'nullable', 'string', 'min:2', 'max:160'],
            'business_info' => ['nullable', 'string', 'max:2000'],
            'category' => [$creating ? 'required' : 'nullable', 'in:'.implode(',', array_keys(AdPolicy::CATEGORIES))],
            'campaign_type' => [$creating ? 'required' : 'nullable', 'in:'.implode(',', array_keys(AdPolicy::TYPES))],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'target_city' => ['nullable', 'string', 'max:80'],
            'starts_on' => [$creating ? 'required' : 'nullable', 'date'],
            'ends_on' => [$creating ? 'required' : 'nullable', 'date'],
            'budget_rupees' => [$creating ? 'required' : 'nullable', 'integer', 'min:1'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'status' => ['prohibited'],
        ];

        return $request->validate($rules);
    }
}
