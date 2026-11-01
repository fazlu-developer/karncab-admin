<?php

namespace App\Http\Controllers;

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
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:160'],
            'link' => ['nullable', 'string', 'max:500'],
            'banner' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'publish' => ['nullable'],
            'budget_rupees' => ['nullable', 'numeric', 'min:0'],
        ]);
        $publish = $request->boolean('publish');
        $live = collect($this->ads->workspace($request->user())['campaigns'])
            ->where('status', 'published')
            ->count();
        if ($publish && $live >= 1 && (float) ($data['budget_rupees'] ?? 0) <= 0) {
            return back()->withInput()->withErrors([
                'budget_rupees' => 'A home banner is already published. Set a budget in rupees before adding another.',
            ]);
        }
        $row = $this->ads->create($request->user(), [
            'title' => trim((string) ($data['name'] ?? '')),
            'cta_url' => trim((string) ($data['link'] ?? '')),
            'placement' => 'home',
            'publish' => $publish,
            'budget_rupees' => $data['budget_rupees'] ?? null,
        ]);
        try {
            $this->ads->uploadBanner($request->user(), $row['id'], $request->file('banner'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('ads.index')->withErrors([
                'banner' => $e->getMessage() ?: 'Could not upload this banner. Use JPEG, PNG, WebP, or GIF under 4 MB.',
            ]);
        }

        return redirect()->route('ads.index')->with('status', $publish ? 'Banner published. The website home rotates published banners.' : 'Banner saved and kept off the home page.');
    }

    public function update(Request $request, int $ad): RedirectResponse
    {
        $payload = $this->campaignPayload($request, false);
        $payload['remove_banner'] = $request->boolean('remove_banner');
        $this->ads->update($request->user(), $ad, $payload);
        if ($request->hasFile('banner')) {
            try {
                $this->ads->uploadBanner($request->user(), $ad, $request->file('banner'));
            } catch (\Throwable $e) {
                report($e);

                return back()->withErrors(['banner' => $e->getMessage() ?: 'Could not upload this banner. Use JPEG, PNG, WebP, or GIF under 4 MB.']);
            }
        }

        return back()->with('status', 'Campaign saved.');
    }

    public function banner(Request $request, int $ad): RedirectResponse
    {
        $request->validate([
            'banner' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
        ]);
        try {
            $this->ads->uploadBanner($request->user(), $ad, $request->file('banner'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['banner' => $e->getMessage() ?: 'Could not upload this banner. Use JPEG, PNG, WebP, or GIF under 4 MB.']);
        }

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

    public function destroy(Request $request, int $ad): RedirectResponse
    {
        $this->ads->destroy($request->user(), $ad);

        return redirect()->route('ads.index')->with('status', 'Banner deleted. It is off the customer home.');
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignPayload(Request $request, bool $creating): array
    {
        $rules = [
            'title' => ['nullable', 'string', 'max:160'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'banner' => [$creating ? 'required_without:image_url' : 'nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'remove_banner' => ['nullable'],
        ];
        $data = $request->validate($rules);
        $data['placement'] = 'home';

        return $data;
    }
}
