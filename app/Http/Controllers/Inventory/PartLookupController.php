<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Part search for forms (purchase lines, later work orders). The last purchase cost is
 * only included for users allowed to see costs.
 */
class PartLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Part::class);

        $validated = $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']]);
        $term = trim($validated['q']);
        $canSeeCost = $request->user()?->can('viewCost', Part::class) ?? false;

        $parts = Part::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($parts->map(fn (Part $part) => [
            'id' => $part->id,
            'name' => $part->name,
            'sku' => $part->sku,
            'unit_label' => $part->unit->label(),
            'allows_decimals' => $part->unit->allowsDecimals(),
            'cost_price' => $canSeeCost ? $part->cost_price : null,
        ]));
    }
}
