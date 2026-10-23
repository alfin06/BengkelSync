<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;
use App\Models\User;

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
        // Define exact capabilities
        Gate::define('view_financial_reports', function (User $user) {
            return in_array($user->role, ['admin', 'finance']);
        });

        Gate::define('edit_invoices', function (User $user) {
            return in_array($user->role, ['admin', 'finance']);
        });

        Gate::define('manage_stock', function (User $user) {
            return in_array($user->role, ['admin', 'finance', 'staff']);
        });

        Gate::define('manage_users', function (User $user) {
            return $user->role === 'admin';
        });
    }
}
