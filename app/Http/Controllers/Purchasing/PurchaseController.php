<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ReasonRequest;
use App\Http\Requests\Purchasing\StorePurchaseRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\CashAccount;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PaymentAccountRules;
use App\Services\Purchasing\PurchaseService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly PaymentAccountRules $accounts,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Purchase::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(PurchaseService::STATUSES)],
        ]);
        $status = $filters['status'] ?? 'all';

        return Inertia::render('Purchases/Index', [
            'purchases' => $this->purchases
                ->search($filters['search'] ?? null, $status)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Purchase $purchase) => PurchaseResource::make($purchase)->resolve($request)),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
            'can' => [
                'create' => $request->user()?->can('create', Purchase::class) ?? false,
                'viewDebts' => $request->user()?->can('viewDebts', Purchase::class) ?? false,
            ],
        ]);
    }

    public function create(Request $request, TenantContext $context): Response
    {
        Gate::authorize('create', Purchase::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Purchases/Create', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'payAccounts' => $this->payAccounts($user, onReceipt: true),
            'canPayAnyAmount' => $user->can('supplier_payment.create'),
            'cashLimit' => $context->requireBranch()->cash_purchase_limit,
            'today' => $context->today(),
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        /** @var Supplier $supplier */
        $supplier = $request->supplier();
        $payAmount = $request->integer('pay_amount');
        $account = $request->payAccount();

        $purchase = $this->purchases->create(
            $supplier,
            $request->only(['supplier_ref', 'purchase_date', 'due_date', 'notes']),
            $request->lines(),
            $payAmount > 0 && $account !== null
                ? ['amount' => $payAmount, 'account' => $account, 'reference' => $request->input('pay_reference')]
                : null,
        );

        return redirect()->route('purchases.show', $purchase)->with('success', __('purchases.created', ['number' => $purchase->number]));
    }

    public function show(Request $request, Purchase $purchase, TenantContext $context): Response
    {
        Gate::authorize('view', $purchase);

        /** @var User $user */
        $user = $request->user();
        $purchase->load(['supplier', 'lines.part', 'payments.account', 'payments.payer']);

        return Inertia::render('Purchases/Show', [
            'purchase' => PurchaseResource::make($purchase)->resolve($request),
            'payAccounts' => $user->can('pay', $purchase) ? $this->payAccounts($user, onReceipt: false) : [],
            'today' => $context->today(),
            'can' => [
                'update' => $user->can('update', $purchase),
                // A purchase with payments is voided by voiding the payments first.
                'void' => $user->can('void', $purchase) && $purchase->amount_paid === 0,
                'pay' => $user->can('pay', $purchase),
            ],
        ]);
    }

    public function edit(Request $request, Purchase $purchase): Response
    {
        Gate::authorize('update', $purchase);

        $purchase->load(['supplier', 'lines.part']);

        return Inertia::render('Purchases/Edit', [
            'purchase' => PurchaseResource::make($purchase)->resolve($request),
        ]);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->purchases->revise(
            $purchase,
            $request->only(['supplier_ref', 'due_date', 'notes']),
            $request->lines(),
            $request->string('reason')->toString(),
        );

        return redirect()->route('purchases.show', $purchase)->with('success', __('purchases.revised', ['number' => $purchase->number]));
    }

    public function void(ReasonRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->purchases->void($purchase, $request->string('reason')->toString());

        return back()->with('success', __('purchases.voided', ['number' => $purchase->number]));
    }

    /**
     * @return list<array{id: int, name: string, is_physical: bool}>
     */
    private function payAccounts(User $user, bool $onReceipt): array
    {
        return $this->accounts->accountsFor($user, $onReceipt)
            ->map(fn (CashAccount $account) => ['id' => $account->id, 'name' => $account->name, 'is_physical' => $account->type->isPhysical()])
            ->values()
            ->all();
    }
}
