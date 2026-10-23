<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The home page every member of the tenant lands on after login, so it only needs an
     * authenticated tenant member. Each widget added in Phase 2 checks its own permission
     * (e.g. dashboard.view_financials) on the server.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard');
    }
}
