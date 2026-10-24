<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\CountCashRequest;
use App\Http\Requests\Cash\TransferRequest;
use App\Http\Resources\CashMovementResource;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Kas": balances of the active branch's accounts, transfers and cash counts.
 */
class CashController extends Controller
{
    public function __construct(
        private readonly CashService $cash,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CashAccount::class);

        /** @var User $user */
        $user = $request->user();
        $accounts = $this->visibleAccounts($user);
        $balances = $this->cash->balances($accounts);

        return Inertia::render('Cash/Index', [
            'accounts' => $accounts->map(fn (CashAccount $account) => [
                ...$this->accountProps($account),
                'balance' => $balances[$account->id],
                'balance_label' => Money::format($balances[$account->id]),
                'can' => [
                    'transfer' => $user->can('transfer', $account),
                    'adjust' => $user->can('adjust', $account),
                ],
            ])->values(),
            'hiddenFinancial' => ! $user->can('viewFinancial', CashAccount::class),
        ]);
    }

    public function show(Request $request, CashAccount $cashAccount): Response
    {
        Gate::authorize('view', $cashAccount);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $timezone = $this->context->requireBranch()->timezone;

        $movements = $cashAccount->movements()
            ->with('creator')
            // Dates are picked in the branch timezone; stored timestamps are UTC (rule 11).
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', Carbon::parse($from, $timezone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', Carbon::parse($to, $timezone)->endOfDay()->utc()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CashMovement $movement) => CashMovementResource::make($movement)->resolve($request));

        $balance = $this->cash->balance($cashAccount);

        return Inertia::render('Cash/Show', [
            'account' => [
                ...$this->accountProps($cashAccount),
                'balance' => $balance,
                'balance_label' => Money::format($balance),
            ],
            'movements' => $movements,
            'filters' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
        ]);
    }

    public function transfer(TransferRequest $request): RedirectResponse
    {
        /** @var CashAccount $from */
        $from = $request->from();
        /** @var CashAccount $to */
        $to = $request->to();
        $amount = $request->integer('amount');

        $this->cash->transfer($from, $to, $amount, $request->validated('note'));

        return back()->with('success', __('cash.transferred', [
            'amount' => Money::format($amount),
            'from' => $from->name,
            'to' => $to->name,
        ]));
    }

    public function count(CountCashRequest $request, CashAccount $cashAccount): RedirectResponse
    {
        $counted = $request->integer('counted_amount');
        $movement = $this->cash->countTo($cashAccount, $counted, $request->string('reason')->toString());

        return back()->with('success', $movement === null
            ? __('cash.count_unchanged')
            : __('cash.counted', ['account' => $cashAccount->name, 'amount' => Money::format($counted)]));
    }

    /**
     * Active accounts the user may see, in the branch's order.
     *
     * @return Collection<int, CashAccount>
     */
    private function visibleAccounts(User $user): Collection
    {
        return CashAccount::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->filter(fn (CashAccount $account) => $user->can('view', $account))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function accountProps(CashAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'is_physical' => $account->type->isPhysical(),
        ];
    }
}
