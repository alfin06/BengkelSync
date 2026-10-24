<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\Inventory\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suppliers. Adding and editing happen in a dialog on the list page.
 */
class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $suppliers) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $user = $request->user();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $this->suppliers
                ->search($filters['search'] ?? null)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Supplier $supplier) => SupplierResource::make($supplier)->resolve($request)),
            'filters' => ['search' => $filters['search'] ?? ''],
            'can' => [
                'create' => $user?->can('create', Supplier::class) ?? false,
                'update' => $user?->can('update', new Supplier) ?? false,
                'delete' => $user?->can('delete', new Supplier) ?? false,
            ],
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = $this->suppliers->create($request->validated());

        return back()->with('success', __('suppliers.created', ['name' => $supplier->name]));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->suppliers->update($supplier, $request->validated());

        return back()->with('success', __('suppliers.updated', ['name' => $supplier->name]));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('delete', $supplier);

        $this->suppliers->delete($supplier);

        return back()->with('success', __('suppliers.deleted', ['name' => $supplier->name]));
    }
}
