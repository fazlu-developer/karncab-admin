<?php

namespace App\Providers;

use App\Events\OperatorRegistered;
use App\Support\IndiaCarbon;
use App\Listeners\HandleOperatorRegistered;
use App\Models\User;
use App\Platform\GeoCatalog;
use App\Platform\PlatformPermission;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Date::use(IndiaCarbon::class);

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Event::listen(OperatorRegistered::class, HandleOperatorRegistered::class);

        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }
            if ($user->hasPlatformAbility($ability)) {
                return true;
            }
            if (in_array($ability, PlatformPermission::builtInCatalog(), true) || str_contains($ability, '.')) {
                return false;
            }

            return null;
        });

        View::composer('*', function ($view) {
            $user = auth()->user();
            if ($user instanceof User) {
                \App\Services\OperatorTerritorySync::apply($user);
            }
            $view->with('kcGeo', GeoCatalog::payload($user instanceof User ? $user : null));
            $view->with('kcBrand', \App\Support\SiteBrand::payload());
        });
    }
}
