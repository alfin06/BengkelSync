<?php

namespace App\Http\Controllers\Customers;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\Customers\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(CustomerService::SORTS)],
        ]);

        $customers = $this->customers
            ->search($filters['search'] ?? null, $filters['sort'] ?? 'name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer) => CustomerResource::make($customer)->resolve());

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $filters['search'] ?? '', 'sort' => $filters['sort'] ?? 'name'],
            'can' => ['create' => $request->user()?->can('create', Customer::class) ?? false],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('Customers/Create', ['types' => $this->typeOptions()]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->create($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('customers.created'));
    }

    public function show(Request $request, Customer $customer): Response
    {
        Gate::authorize('view', $customer);

        $customer->load(['vehicles' => fn ($vehicles) => $vehicles->orderBy('plate_number')]);
        $user = $request->user();

        return Inertia::render('Customers/Show', [
            'customer' => CustomerResource::make($customer)->resolve(),
            'can' => [
                'update' => $user?->can('update', $customer) ?? false,
                'delete' => $user?->can('delete', $customer) ?? false,
                'createVehicle' => $user?->can('create', Vehicle::class) ?? false,
            ],
        ]);
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('update', $customer);

        return Inertia::render('Customers/Edit', [
            'customer' => CustomerResource::make($customer)->resolve(),
            'types' => $this->typeOptions(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('customers.updated'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $this->customers->delete($customer);

        return redirect()->route('customers.index')->with('success', __('customers.deleted'));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (CustomerType $type) => ['value' => $type->value, 'label' => $type->label()],
            CustomerType::cases(),
        );
    }
}
