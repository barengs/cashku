<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        $adjustments = StockAdjustment::orderBy('adjustment_date', 'desc')->get();
        return response()->json($adjustments);
    }

    public function store(Request $request)
    {
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

        return response()->json($adjustment, 201);
    }

    public function show($id)
    {
        $adjustment = StockAdjustment::with('items.ingredient', 'branch')->findOrFail($id);
        return response()->json($adjustment);
    }

    public function update(Request $request, $id)
    {
        $adjustment = StockAdjustment::findOrFail($id);

        if ($adjustment->status === 'completed') {
            return response()->json(['error' => 'Cannot update a completed adjustment'], 400);
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
            return response()->json($adjustment->load('items'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function finalize($id)
    {
        $adjustment = StockAdjustment::with('items')->findOrFail($id);

        if ($adjustment->status === 'completed') {
            return response()->json(['error' => 'Adjustment already finalized'], 400);
        }

        if ($adjustment->items->isEmpty()) {
             return response()->json(['error' => 'Cannot finalize an adjustment with no items'], 400);
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
            return response()->json($adjustment);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
