<?php

namespace App\Http\Controllers\Purchasing;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\Purchasing\PurchaseService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Hutang Supplier": what the branch still owes, oldest due date first.
 */
class SupplierDebtController extends Controller
{
    public function __invoke(Request $request, PurchaseService $purchases): Response
    {
        Gate::authorize('viewDebts', Purchase::class);

        $open = Purchase::query()->whereIn('status', [PurchaseStatus::Unpaid, PurchaseStatus::Partial]);
        $outstanding = (int) $open->clone()->sum('total') - (int) $open->clone()->sum('amount_paid');

        return Inertia::render('Purchases/Debts', [
            'debts' => $purchases->debts()
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Purchase $purchase) => PurchaseResource::make($purchase)->resolve($request)),
            'outstandingLabel' => Money::format($outstanding),
        ]);
    }
}
