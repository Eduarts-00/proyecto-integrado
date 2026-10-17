<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('Can_Manage_Stock', fn (User $user): bool => $user->hasPermission('Can_Manage_Stock'));
        Gate::define('Can_Sell_OTC', fn (User $user): bool => $user->hasPermission('Can_Sell_OTC'));
        Gate::define('Can_Authorize_Prescription', fn (User $user): bool => $user->hasPermission('Can_Authorize_Prescription'));
        Gate::define('Can_Audit_Logs', fn (User $user): bool => $user->hasPermission('Can_Audit_Logs'));
        Gate::define('Can_Manage_Users', fn (User $user): bool => $user->hasPermission('Can_Manage_Users'));
    }
}
