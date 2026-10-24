<?php

namespace App\Http\Controllers;

use App\Services\HeadInfoService;
use App\Support\NamePicker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TerritoryContactsController extends Controller
{
    public function __construct(private readonly HeadInfoService $heads) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);

        return view('territory-contacts.index', [
            'rows' => $this->heads->rows(),
            'stateHeads' => NamePicker::users($request->user(), 'STATE_HEAD'),
            'districtHeads' => NamePicker::users($request->user(), 'DISTRICT_HEAD'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->heads->save($request->user(), $this->validated($request), $request->file('photo'));

        return back()->with('status', 'Territory contact saved. Customers and drivers in that area will see it.');
    }

    public function update(Request $request, int $contact): RedirectResponse
    {
        $this->heads->save($request->user(), $this->validated($request), $request->file('photo'), $contact);

        return back()->with('status', 'Territory contact updated.');
    }

    public function destroy(Request $request, int $contact): RedirectResponse
    {
        $this->heads->destroy($request->user(), $contact);

        return back()->with('status', 'Territory contact removed. Apps fall back to KarnaRide support.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'integer'],
            'support_phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:180'],
            'connect_phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'place_name' => ['nullable', 'string', 'max:160'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'remove_photo' => ['nullable'],
        ]);
    }
}
