<?php

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language per user (SPEC section 1): the user's choice, then the tenant default,
 * then a guest's choice from the session, then the app default (Indonesian).
 */
final class SetLocale
{
    public const SESSION_KEY = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $candidates = [
            $user instanceof User ? $user->locale : null,
            $user instanceof User ? $user->tenant->default_locale : null,
            $request->session()->get(self::SESSION_KEY),
        ];

        $locale = collect($candidates)->first(fn (mixed $locale): bool => self::isSupported($locale))
            ?? config('app.locale');

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, config('app.supported_locales'), true);
    }
}
