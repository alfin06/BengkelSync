<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props. Everything that depends on the tenant is a closure: this middleware
     * runs before the route's `tenancy` middleware, and closures are only evaluated when
     * the page is rendered.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $context = app(TenantContext::class);

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'locale' => fn () => app()->getLocale(),
            'auth' => fn () => [
                'user' => $this->userProps($request->user()),
                'permissions' => $context->hasTenant() && $request->user() instanceof User
                    ? $request->user()->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'tenant' => fn () => $context->tenant()?->only(['id', 'name']),
            'branch' => fn () => $context->branch()?->only(['id', 'name', 'code']),
            'branches' => fn () => $context->hasTenant() && $request->user() instanceof User
                ? $request->user()->accessibleBranches()->orderBy('name')->get(['id', 'name', 'code'])
                : [],
            'notifications' => fn () => [
                'unread' => $request->user() instanceof User ? $request->user()->unreadNotifications()->count() : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userProps(mixed $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
        ];
    }
}
