<?php

namespace App\Providers;

use App\Events\OperatorRegistered;
use App\Listeners\HandleOperatorRegistered;
use App\Models\User;
use App\Platform\GeoCatalog;
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

            return null;
        });

        View::composer('*', function ($view) {
            $user = auth()->user();
            $view->with('kcGeo', GeoCatalog::payload($user instanceof User ? $user : null));
        });
    }
}
