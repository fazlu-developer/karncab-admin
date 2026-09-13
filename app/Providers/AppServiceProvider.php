<?php

namespace App\Providers;

use App\Events\OperatorRegistered;
use App\Listeners\HandleOperatorRegistered;
use App\Platform\PlatformPermission;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
            if (! is_object($user) || ! isset($user->role)) {
                return null;
            }
            if (PlatformPermission::allows((string) $user->role, $ability)) {
                return true;
            }

            return null;
        });
    }
}
