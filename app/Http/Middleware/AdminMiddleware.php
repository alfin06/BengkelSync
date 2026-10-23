<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // STRICTLY Admin only
        if (Auth::check() && Auth::user()->isAdmin()) {
            return $next($request);
        }

        return redirect()->route('dashboard')->with('error', 'Akses Ditolak: Halaman ini khusus untuk Owner/Admin.');
    }
}