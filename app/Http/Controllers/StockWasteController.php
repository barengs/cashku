<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockWasteResource;
use App\Models\StockWaste;
use App\Models\BranchStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class StockWasteController extends Controller
{
    public function index()
    {
        try {
            $wastes = StockWaste::with('branch')->orderBy('waste_date', 'desc')->get();
            return StockWasteResource::collection($wastes);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|uuid|exists:branches,id',
                'waste_date' => 'required|date',
                'note' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.ingredient_id' => 'required|uuid|exists:ingredients,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.reason' => 'nullable|string',
            ]);

            DB::beginTransaction();
            try {
                $waste = StockWaste::create([
                    'branch_id' => $validated['branch_id'],
                    'waste_date' => $validated['waste_date'],
                    'note' => $validated['note'] ?? null,
                ]);

                foreach ($validated['items'] as $item) {
                    // Deduct stock immediately
                    $stock = BranchStock::where('branch_id', $validated['branch_id'])
                        ->where('ingredient_id', $item['ingredient_id'])
                        ->first();
                    
                    $currentQty = $stock ? $stock->quantity : 0;
                    $newQty = $currentQty - $item['quantity'];

                    if ($stock) {
                        $stock->quantity = $newQty;
                        $stock->save();
                    } else {
                        BranchStock::create([
                            'branch_id' => $validated['branch_id'],
                            'ingredient_id' => $item['ingredient_id'],
                            'quantity' => -$item['quantity']
                        ]);
                    }

                    $waste->items()->create([
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity' => $item['quantity'],
                        'reason' => $item['reason'] ?? null,
                    ]);
                }

                DB::commit();
                return new StockWasteResource($waste->load('items'));
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 400); // 400 for logic issues here? Or let main catch handle it.
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $waste = StockWaste::with(['items.ingredient', 'branch'])->findOrFail($id);
            return new StockWasteResource($waste);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
