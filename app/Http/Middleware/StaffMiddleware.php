<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class StaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow Admin, Finance, and Staff to pass through for functional routes
        if (Auth::check() && in_array(Auth::user()->role, ['admin', 'finance', 'staff'])) {
            return $next($request);
        }

        // Unauthenticated or unknown role
        return redirect('/');
    }
}