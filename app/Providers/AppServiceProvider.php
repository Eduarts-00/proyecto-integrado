<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

        try {
            if (Schema::hasTable('usuarios') && (! Schema::hasColumn('usuarios', 'acceso_rapido') || ! Schema::hasColumn('usuarios', 'pin_acceso'))) {
                Schema::table('usuarios', function (Blueprint $table) {
                    if (! Schema::hasColumn('usuarios', 'acceso_rapido')) {
                        $table->boolean('acceso_rapido')->default(false)->after('pin_qf');
                    }
                    if (! Schema::hasColumn('usuarios', 'pin_acceso')) {
                        $table->string('pin_acceso')->nullable()->after('acceso_rapido');
                    }
                });

                DB::table('usuarios')->whereNull('pin_acceso')->update([
                    'acceso_rapido' => true,
                    'pin_acceso' => Hash::make('1234'),
                ]);
            }
        } catch (\Throwable) {
            // Ignorar de forma segura si la base de datos aún no está conectada
        }
    }
}
