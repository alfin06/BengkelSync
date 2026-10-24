<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public const PERIODS = ['today', 'week', 'month', 'custom'];

    /**
     * The home page every member of the tenant lands on after login. Each section checks
     * its own permission here, so nothing is calculated or sent for what a user may not see.
     */
    public function __invoke(Request $request, DashboardService $dashboard, TenantContext $context): Response
    {
        /** @var User $user */
        $user = $request->user();
        $can = fn (Permission $permission): bool => $user->can($permission->value);
        $overview = $can(Permission::DashboardView);
        $financials = $can(Permission::DashboardViewFinancials);

        [$period, $from, $to] = $financials ? $this->period($request, $context) : ['month', null, null];

        return Inertia::render('Dashboard', [
            'workshop' => $overview && $can(Permission::WorkOrderView) ? $dashboard->workshop() : null,
            'lowStock' => $overview && $can(Permission::StockView) ? $dashboard->lowStock() : null,
            'receivables' => $overview && $can(Permission::PaymentView) ? $dashboard->receivables() : null,
            'supplierDue' => $overview && $can(Permission::SupplierPaymentView) ? $dashboard->supplierDue() : null,
            'cash' => $overview && $can(Permission::CashView) ? $dashboard->cash($user) : null,
            'financials' => $financials && $from !== null && $to !== null ? [
                ...$dashboard->financials($user, $from, $to),
                'monthly' => $dashboard->monthlySales(),
                'top' => $dashboard->topItems($from, $to),
                'mechanics' => $dashboard->mechanicWorkload($from, $to),
            ] : null,
            'filters' => ['period' => $period, 'from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Today, this week (Monday–Sunday), this month, or a chosen range: in branch time.
     *
     * @return array{string, string, string}
     */
    private function period(Request $request, TenantContext $context): array
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $today = Carbon::parse($context->today());
        $period = $validated['period'] ?? 'month';

        return match ($period) {
            'today' => [$period, $today->toDateString(), $today->toDateString()],
            'week' => [$period, $today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            'custom' => [$period, (string) $validated['from'], (string) $validated['to']],
            default => ['month', $today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
        };
    }
}
