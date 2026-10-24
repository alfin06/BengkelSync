<?php

namespace App\Providers;

use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One context per request/job; flushed between requests.
        $this->app->scoped(TenantContext::class);

        // `php artisan serve` (and so `composer dev`) only passes allowlisted environment
        // variables to the PHP server. Without TEMP/TMP, PHP on Windows falls back to
        // C:\Windows\Temp, which is not writable, and every file upload fails.
        ServeCommand::$passthroughVariables = [...ServeCommand::$passthroughVariables, 'TEMP', 'TMP'];
    }

    public function boot(): void
    {
        // Lazy loading, silently discarded attributes and missing attributes all throw
        // outside production, so N+1 queries and typos surface during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Role is not tenant-scoped (spatie caches roles globally), so {role} in a URL is
        // looked up within the active tenant: another shop's role is a 404.
        Route::bind('role', fn (string $value) => Role::query()
            ->forTenant(app(TenantContext::class)->requireTenant())
            ->whereKey((int) $value)
            ->firstOrFail());
    }
}
