<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @group Inventory Management
 * @description APIs for stock adjustments (corrections).
 */
class StockAdjustmentController extends Controller
{
    /**
     * List Adjustments
     * @description Get a list of stock adjustments.
     */
    public function index()
    {
        try {
            $adjustments = StockAdjustment::orderBy('adjustment_date', 'desc')->get();
            return StockAdjustmentResource::collection($adjustments);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Adjustment Draft
     * @description Create a new stock adjustment draft.
     * @bodyParam branch_id string required Branch UUID.
     * @bodyParam adjustment_date date required Date.
     * @bodyParam note string optional Note.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|uuid|exists:branches,id',
                'adjustment_date' => 'required|date',
                'note' => 'nullable|string',
            ]);

            $adjustment = StockAdjustment::create([
                'branch_id' => $validated['branch_id'],
                'adjustment_date' => $validated['adjustment_date'],
                'note' => $validated['note'] ?? null,
                'status' => 'draft',
            ]);

            return new StockAdjustmentResource($adjustment);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Adjustment
     * @description Get adjustment details.
     */
    public function show($id)
    {
        try {
            $adjustment = StockAdjustment::with('items.ingredient', 'branch')->findOrFail($id);
            return new StockAdjustmentResource($adjustment);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Adjustment Draft
     * @description Update an adjustment draft and its items.
     * @bodyParam adjustment_date date optional Date.
     * @bodyParam note string optional Note.
     * @bodyParam items object[] optional List of items.
     * @bodyParam items[].ingredient_id string required Ingredient UUID.
     * @bodyParam items[].actual_stock number required Actual physical stock.
     */
    public function update(Request $request, $id)
    {
        try {
            $adjustment = StockAdjustment::findOrFail($id);

            if ($adjustment->status === 'completed') {
                return response()->json(['message' => 'Cannot update a completed adjustment'], 400);
            }

            $validated = $request->validate([
                'adjustment_date' => 'date',
                'note' => 'nullable|string',
                'items' => 'array',
                'items.*.ingredient_id' => 'required_with:items|uuid|exists:ingredients,id',
                'items.*.actual_stock' => 'required_with:items|numeric|min:0',
            ]);

            DB::beginTransaction();
            try {
                $adjustment->update($request->only(['adjustment_date', 'note']));

                if (isset($validated['items'])) {
                    // Delete existing items to resync
                    $adjustment->items()->delete();

                    foreach ($validated['items'] as $item) {
                        $ingredient = Ingredient::find($item['ingredient_id']);
                        // Capture current system stock at this branch
                        $systemStock = $ingredient->stockForBranch($adjustment->branch_id);
                        $actualStock = $item['actual_stock'];
                        $difference = $actualStock - $systemStock;

                        $adjustment->items()->create([
                            'ingredient_id' => $item['ingredient_id'],
                            'system_stock' => $systemStock,
                            'actual_stock' => $actualStock,
                            'difference' => $difference,
                        ]);
                    }
                }

                DB::commit();
                return new StockAdjustmentResource($adjustment->load('items'));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Finalize Adjustment
     * @description Commit the adjustment and update actual stock levels.
     */
    public function finalize($id)
    {
        try {
            $adjustment = StockAdjustment::with('items')->findOrFail($id);

            if ($adjustment->status === 'completed') {
                return response()->json(['message' => 'Adjustment already finalized'], 400);
            }

            if ($adjustment->items->isEmpty()) {
                 return response()->json(['message' => 'Cannot finalize an adjustment with no items'], 400);
            }

            DB::beginTransaction();
            try {
                foreach ($adjustment->items as $item) {
                    // Update or create Branch Stock entry setting quantity to Actual Stock from count
                    $branchStock = \App\Models\BranchStock::firstOrNew([
                        'branch_id' => $adjustment->branch_id,
                        'ingredient_id' => $item->ingredient_id,
                    ]);
                    
                    $branchStock->quantity = $item->actual_stock;
                    $branchStock->save();
                }

                $adjustment->status = 'completed';
                $adjustment->save();

                DB::commit();
                return new StockAdjustmentResource($adjustment);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
