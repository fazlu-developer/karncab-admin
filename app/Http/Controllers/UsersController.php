<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformUser;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('users.view'), 403);

        $query = PlatformUser::query()->orderByDesc('id');
        TerritoryScope::applyUsers($query, $request->user());
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return view('users.index', [
            'users' => $query->paginate(12)->withQueryString(),
            'filters' => $request->only(['q', 'role', 'status']),
            'roles' => OperatorRole::all(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('users.create'), 403);

        return view('users.form', [
            'user' => new PlatformUser(['status' => 'ACTIVE', 'role' => 'CUSTOMER']),
            'roles' => OperatorRole::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('users.create'), 403);
        $data = $this->validated($request, true);
        $data['phone'] = $data['phone'] ?: null;
        $data['password_hash'] = Hash::make($data['password']);
        unset($data['password']);
        $actor = $request->user();
        if ($actor && ! $actor->isPrivilegedOperator()) {
            $data['state_id'] = $actor->state_id;
            $data['district_id'] = $actor->district_id;
        }
        $user = PlatformUser::query()->create($data);

        return redirect()->route('users.show', $user)->with('status', 'User created.');
    }

    public function show(Request $request, PlatformUser $platformUser): View
    {
        abort_unless($request->user()?->can('users.view'), 403);
        $this->assertVisible($request, $platformUser);
        $platformUser->load('driver');

        return view('users.show', ['user' => $platformUser]);
    }

    public function edit(Request $request, PlatformUser $platformUser): View
    {
        abort_unless($request->user()?->can('users.edit'), 403);
        $this->assertVisible($request, $platformUser);

        return view('users.form', [
            'user' => $platformUser,
            'roles' => OperatorRole::all(),
        ]);
    }

    public function update(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        abort_unless($request->user()?->can('users.edit'), 403);
        $this->assertVisible($request, $platformUser);
        $data = $this->validated($request, false, $platformUser->id);
        $data['phone'] = $data['phone'] ?: null;
        if (! empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
        }
        unset($data['password']);
        $platformUser->update($data);

        return redirect()->route('users.show', $platformUser)->with('status', 'User updated.');
    }

    public function destroy(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        abort_unless($request->user()?->can('users.delete'), 403);
        $this->assertVisible($request, $platformUser);
        $platformUser->driver?->delete();
        $platformUser->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }

    private function assertVisible(Request $request, PlatformUser $platformUser): void
    {
        $visible = PlatformUser::query()->whereKey($platformUser->id);
        TerritoryScope::applyUsers($visible, $request->user());
        abort_unless($visible->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique(PlatformUser::class, 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique(PlatformUser::class, 'phone')->ignore($id)],
            'role' => ['required', Rule::in(OperatorRole::all())],
            'status' => ['required', Rule::in(['ACTIVE', 'PENDING', 'SUSPENDED'])],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8'],
        ]);
    }
}
