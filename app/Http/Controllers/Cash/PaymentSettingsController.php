<?php

namespace App\Http\Controllers\Cash;

use App\Enums\CashAccountType;
use App\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\CashAccountRequest;
use App\Http\Requests\Cash\PaymentMethodRequest;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Services\Cash\CashSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branch settings: "Akun kas & metode pembayaran" (cash plan, decision 3).
 */
class PaymentSettingsController extends Controller
{
    public function __construct(private readonly CashSetupService $setup) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', PaymentMethod::class);

        return Inertia::render('Settings/Payments', [
            'accounts' => CashAccount::query()->orderBy('sort')->orderBy('name')->get()->map(fn (CashAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'type_label' => $account->type->label(),
                'is_active' => $account->is_active,
                'sort' => $account->sort,
            ]),
            'methods' => PaymentMethod::query()->with('account')->orderBy('sort')->orderBy('name')->get()->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'type' => $method->type->value,
                'type_label' => $method->type->label(),
                'cash_account_id' => $method->cash_account_id,
                'account_name' => $method->account->name,
                'surcharge_percent' => rtrim(rtrim($method->surcharge_percent, '0'), '.'),
                'needs_reference' => $method->needs_reference,
                'is_active' => $method->is_active,
                'sort' => $method->sort,
            ]),
            'accountTypes' => array_map(fn (CashAccountType $type) => ['value' => $type->value, 'label' => $type->label()], CashAccountType::cases()),
            'methodTypes' => array_map(fn (PaymentMethodType $type) => ['value' => $type->value, 'label' => $type->label()], PaymentMethodType::cases()),
        ]);
    }

    public function storeAccount(CashAccountRequest $request): RedirectResponse
    {
        $account = $this->setup->createAccount($request->validated());

        return back()->with('success', __('cash.account_saved', ['name' => $account->name]));
    }

    public function updateAccount(CashAccountRequest $request, CashAccount $cashAccount): RedirectResponse
    {
        $this->setup->updateAccount($cashAccount, $request->validated());

        return back()->with('success', __('cash.account_saved', ['name' => $cashAccount->name]));
    }

    public function destroyAccount(CashAccount $cashAccount): RedirectResponse
    {
        Gate::authorize('delete', $cashAccount);

        $this->setup->deleteAccount($cashAccount);

        return back()->with('success', __('cash.account_deleted', ['name' => $cashAccount->name]));
    }

    public function storeMethod(PaymentMethodRequest $request): RedirectResponse
    {
        $method = $this->setup->createMethod($request->validated());

        return back()->with('success', __('cash.method_saved', ['name' => $method->name]));
    }

    public function updateMethod(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->setup->updateMethod($paymentMethod, $request->validated());

        return back()->with('success', __('cash.method_saved', ['name' => $paymentMethod->name]));
    }

    public function destroyMethod(PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize('delete', $paymentMethod);

        $this->setup->deleteMethod($paymentMethod);

        return back()->with('success', __('cash.method_deleted', ['name' => $paymentMethod->name]));
    }
}
