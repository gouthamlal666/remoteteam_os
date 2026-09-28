<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Support\Settings;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Re-run on every Livewire request too, so deactivated users are cut off
        // immediately and presence stays fresh while a page is open.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);

        // Always generate https:// links in production.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Company name from System settings (emails, page titles).
        config(['app.name' => rescue(fn () => Settings::companyName(), config('app.name'), false)]);
    }
}
