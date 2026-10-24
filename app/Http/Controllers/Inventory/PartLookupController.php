<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartBranchStock;
use App\Support\Quantity;
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

        // Stock of the active branch (branch-scoped), so work orders can show what is left.
        $stock = PartBranchStock::query()->whereIn('part_id', $parts->pluck('id'))->get()->keyBy('part_id');

        return response()->json($parts->map(function (Part $part) use ($canSeeCost, $stock) {
            $available = $stock->get($part->id)?->qtyHundredths() ?? 0;

            return [
                'id' => $part->id,
                'name' => $part->name,
                'sku' => $part->sku,
                'unit_label' => $part->unit->label(),
                'allows_decimals' => $part->unit->allowsDecimals(),
                'cost_price' => $canSeeCost ? $part->cost_price : null,
                'sell_price' => $part->sell_price,
                'available' => $available,
                'available_label' => Quantity::format($available),
            ];
        }));
    }
}
