<?php

namespace App\Http\Controllers\Mechanics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mechanics\StoreMechanicRequest;
use App\Http\Requests\Mechanics\UpdateMechanicRequest;
use App\Http\Resources\MechanicResource;
use App\Models\Mechanic;
use App\Services\Mechanics\MechanicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mechanics of the active branch. Adding and editing happen in a dialog.
 */
class MechanicController extends Controller
{
    public function __construct(private readonly MechanicService $mechanics) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Mechanic::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(MechanicService::STATUSES)],
        ]);
        $status = $filters['status'] ?? 'active';

        return Inertia::render('Mechanics/Index', [
            'mechanics' => $this->mechanics
                ->search($filters['search'] ?? null, $status)
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Mechanic $mechanic) => MechanicResource::make($mechanic)->resolve($request)),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
            'can' => $this->abilities($request),
        ]);
    }

    public function show(Request $request, Mechanic $mechanic): Response
    {
        Gate::authorize('view', $mechanic);

        return Inertia::render('Mechanics/Show', [
            'mechanic' => MechanicResource::make($mechanic)->resolve($request),
            'can' => $this->abilities($request),
        ]);
    }

    public function store(StoreMechanicRequest $request): RedirectResponse
    {
        $mechanic = $this->mechanics->create($request->mechanicData(), $request->ktp());

        return back()->with('success', __('mechanics.created', ['name' => $mechanic->name]));
    }

    public function update(UpdateMechanicRequest $request, Mechanic $mechanic): RedirectResponse
    {
        $this->mechanics->update($mechanic, $request->mechanicData(), $request->ktp(), $request->boolean('remove_ktp'));

        return back()->with('success', __('mechanics.updated', ['name' => $mechanic->name]));
    }

    public function destroy(Mechanic $mechanic): RedirectResponse
    {
        Gate::authorize('delete', $mechanic);

        $this->mechanics->delete($mechanic);

        return redirect()->route('mechanics.index')->with('success', __('mechanics.deleted', ['name' => $mechanic->name]));
    }

    /**
     * @return array{create: bool, update: bool, delete: bool, identity: bool}
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user?->can('create', Mechanic::class) ?? false,
            'update' => $user?->can('update', new Mechanic) ?? false,
            'delete' => $user?->can('delete', new Mechanic) ?? false,
            'identity' => $user?->can('viewIdentity', Mechanic::class) ?? false,
        ];
    }
}
