<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
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
    }
}
