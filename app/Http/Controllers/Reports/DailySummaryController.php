<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\DailySummaryRequest;
use App\Models\User;
use App\Services\Reports\DailySummaryService;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Rekap harian": one day of the active branch, on screen and printed on A4.
 */
class DailySummaryController extends Controller
{
    public function __construct(private readonly DailySummaryService $summary) {}

    public function show(DailySummaryRequest $request, TenantContext $context): Response
    {
        return Inertia::render('Reports/Daily', [
            'summary' => $this->summary->for($this->user($request), $request->day()),
            'today' => $context->today(),
        ]);
    }

    public function print(DailySummaryRequest $request, TenantContext $context): Response
    {
        $branch = $context->requireBranch();

        return Inertia::render('Reports/DailyPrint', [
            'summary' => $this->summary->for($this->user($request), $request->day()),
            'shop' => [
                'name' => $context->requireTenant()->name,
                'branch' => $branch->name,
                'logo_url' => $branch->logoUrl(),
            ],
            'printedBy' => $this->user($request)->name,
        ]);
    }

    private function user(DailySummaryRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
