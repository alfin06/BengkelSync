<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\ConfigureStockRequest;
use App\Models\Part;
use App\Services\Inventory\StockService;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;

/**
 * Stock of a part in the active branch: correction after counting, and threshold/location.
 */
class PartStockController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function adjust(AdjustStockRequest $request, Part $part): RedirectResponse
    {
        $movement = $this->stock->adjustTo($part, $request->counted(), $request->string('reason')->toString());

        return back()->with('success', $movement === null
            ? __('stock.unchanged')
            : __('stock.adjusted', ['qty' => Quantity::format($request->counted()), 'unit' => $part->unit->label()]));
    }

    public function configure(ConfigureStockRequest $request, Part $part): RedirectResponse
    {
        $this->stock->configure($part, $request->minStock(), $request->validated('location'));

        return back()->with('success', __('stock.configured'));
    }
}
