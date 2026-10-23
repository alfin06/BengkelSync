<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Tenancy\ActiveBranchResolver;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant and active branch for an authenticated request. Must run after `auth`.
 *
 * Deactivated users and users of suspended tenants are logged out here, so a
 * deactivation takes effect on their very next request.
 */
final class InitializeTenancy
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ActiveBranchResolver $branches,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if (! $user->canLogIn()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
        }

        $this->context->set($user->tenant);

        try {
            $branch = $this->branches->resolve($user, $request->session());

            abort_if($branch === null, 403, __('app.no_branch_assigned'));

            $this->context->setBranch($branch);

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
