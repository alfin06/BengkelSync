<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreServiceItemRequest;
use App\Http\Requests\Catalog\UpdateServiceItemRequest;
use App\Http\Resources\ServiceItemResource;
use App\Models\ServiceItem;
use App\Services\Catalog\ServiceItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The service catalog. Adding and editing happen in a dialog on the list page.
 */
class ServiceItemController extends Controller
{
    public function __construct(private readonly ServiceItemService $items) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ServiceItem::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(ServiceItemService::STATUSES)],
        ]);
        $status = $filters['status'] ?? 'active';
        $user = $request->user();

        return Inertia::render('ServiceItems/Index', [
            'items' => $this->items
                ->search($filters['search'] ?? null, $status)
                ->paginate(30)
                ->withQueryString()
                ->through(fn (ServiceItem $item) => ServiceItemResource::make($item)->resolve()),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
            'categories' => $this->items->categories(),
            'can' => [
                'create' => $user?->can('create', ServiceItem::class) ?? false,
                'update' => $user?->can('update', new ServiceItem) ?? false,
                'delete' => $user?->can('delete', new ServiceItem) ?? false,
            ],
        ]);
    }

    public function store(StoreServiceItemRequest $request): RedirectResponse
    {
        $item = $this->items->create($request->validated());

        return back()->with('success', __('service_items.created', ['name' => $item->name]));
    }

    public function update(UpdateServiceItemRequest $request, ServiceItem $serviceItem): RedirectResponse
    {
        $this->items->update($serviceItem, $request->validated());

        return back()->with('success', __('service_items.updated', ['name' => $serviceItem->name]));
    }

    public function destroy(ServiceItem $serviceItem): RedirectResponse
    {
        Gate::authorize('delete', $serviceItem);

        $this->items->delete($serviceItem);

        return back()->with('success', __('service_items.deleted', ['name' => $serviceItem->name]));
    }
}
