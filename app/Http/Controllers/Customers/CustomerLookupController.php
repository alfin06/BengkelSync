<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customers\CustomerService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Small JSON endpoints for forms: the duplicate-phone warning and the customer picker.
 */
class CustomerLookupController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function phone(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'except' => ['nullable', 'integer'],
        ]);

        $matches = $this->customers->withPhone($validated['phone'], isset($validated['except']) ? (int) $validated['except'] : null);

        return response()->json($matches->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'url' => route('customers.show', $customer),
        ]));
    }

    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $results = $this->customers->search($validated['q'])->limit(10)->get();

        return response()->json($results->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone === null ? null : Phone::format($customer->phone),
        ]));
    }
}
