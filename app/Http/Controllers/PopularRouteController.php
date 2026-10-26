<?php

namespace App\Http\Controllers;

use App\Platform\OperatorRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PopularRouteController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard($request);
        $routes = Schema::hasTable('popular_routes')
            ? DB::table('popular_routes')->orderBy('sort')->orderByDesc('id')->limit(100)->get()
            : collect();

        return view('routes.popular', [
            'routes' => $routes,
            'ready' => Schema::hasTable('popular_routes'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        abort_unless(Schema::hasTable('popular_routes'), 503, 'Popular routes table is not ready.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'from_title' => ['required', 'string', 'max:120'],
            'from_address' => ['nullable', 'string', 'max:180'],
            'from_lat' => ['required', 'numeric'],
            'from_lng' => ['required', 'numeric'],
            'to_title' => ['required', 'string', 'max:120'],
            'to_address' => ['nullable', 'string', 'max:180'],
            'to_lat' => ['required', 'numeric'],
            'to_lng' => ['required', 'numeric'],
            'distance_km' => ['nullable', 'integer', 'min:0'],
            'from_fare' => ['nullable', 'integer', 'min:0'],
            'service' => ['required', 'in:all,one_way,round_trip'],
            'state' => ['nullable', 'string', 'max:80'],
        ]);
        $data['distance_km'] = (int) ($data['distance_km'] ?? 0);
        $data['from_fare'] = (int) ($data['from_fare'] ?? 0);
        $data['active'] = true;
        $data['sort'] = 0;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        DB::table('popular_routes')->insert($data);

        return back()->with('status', 'Popular route saved. The apps show it on One Way and Round Trip.');
    }

    public function destroy(Request $request, int $route): RedirectResponse
    {
        $this->guard($request);
        if (Schema::hasTable('popular_routes')) {
            DB::table('popular_routes')->where('id', $route)->delete();
        }

        return back()->with('status', 'Popular route removed.');
    }

    private function guard(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 403);
        $role = (string) ($user->role ?? '');
        $allowed = $user->isPrivilegedOperator()
            || in_array($role, [OperatorRole::STATE_HEAD, OperatorRole::SUPER_ADMIN, 'SUPER_ADMIN', 'ADMIN'], true);
        abort_unless($allowed, 403);
    }
}
