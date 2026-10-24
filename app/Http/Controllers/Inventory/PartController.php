<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\PartUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StorePartRequest;
use App\Http\Requests\Inventory\UpdatePartRequest;
use App\Http\Resources\PartResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Part;
use App\Models\StockMovement;
use App\Services\Inventory\PartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartController extends Controller
{
    public function __construct(private readonly PartService $parts) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Part::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(PartService::STATUSES)],
        ]);
        $status = $filters['status'] ?? 'active';

        return Inertia::render('Parts/Index', [
            'parts' => $this->parts
                ->search($filters['search'] ?? null, $status)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Part $part) => PartResource::make($part)->resolve($request)),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
            'lowStockCount' => $this->parts->lowStockCount(),
            'can' => ['create' => $request->user()?->can('create', Part::class) ?? false],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Part::class);

        return Inertia::render('Parts/Create', [
            ...$this->formOptions(),
            'can' => [
                'adjustStock' => $request->user()?->can('adjustStock', Part::class) ?? false,
                'viewCost' => $request->user()?->can('viewCost', Part::class) ?? false,
            ],
        ]);
    }

    public function store(StorePartRequest $request): RedirectResponse
    {
        $part = $this->parts->create($request->partData(), $request->openingStock(), $request->minStock(), $request->location());

        return redirect()->route('parts.show', $part)->with('success', __('parts.created', ['name' => $part->name]));
    }

    public function show(Request $request, Part $part): Response
    {
        Gate::authorize('view', $part);

        $part->load('stock');
        $user = $request->user();

        return Inertia::render('Parts/Show', [
            'part' => PartResource::make($part)->resolve($request),
            'movements' => $part->movements()
                ->with('creator')
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (StockMovement $movement) => StockMovementResource::make($movement)->resolve($request)),
            'can' => [
                'update' => $user?->can('update', $part) ?? false,
                'delete' => $user?->can('delete', $part) ?? false,
                'adjustStock' => $user?->can('adjustStock', $part) ?? false,
                'configureStock' => $user?->can('configureStock', $part) ?? false,
            ],
        ]);
    }

    public function edit(Request $request, Part $part): Response
    {
        Gate::authorize('update', $part);

        return Inertia::render('Parts/Edit', [
            'part' => PartResource::make($part)->resolve($request),
            ...$this->formOptions(),
            'can' => ['viewCost' => $request->user()?->can('viewCost', $part) ?? false],
        ]);
    }

    public function update(UpdatePartRequest $request, Part $part): RedirectResponse
    {
        $this->parts->update($part, $request->partData());

        return redirect()->route('parts.show', $part)->with('success', __('parts.updated', ['name' => $part->name]));
    }

    public function destroy(Part $part): RedirectResponse
    {
        Gate::authorize('delete', $part);

        $this->parts->delete($part);

        return redirect()->route('parts.index')->with('success', __('parts.deleted', ['name' => $part->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'units' => array_map(fn (PartUnit $unit) => [
                'value' => $unit->value,
                'label' => $unit->label(),
                'allows_decimals' => $unit->allowsDecimals(),
            ], PartUnit::cases()),
            'categories' => Part::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'brands' => Part::query()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
        ];
    }
}
