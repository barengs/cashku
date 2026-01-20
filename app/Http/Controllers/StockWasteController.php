<?php

namespace App\Http\Controllers;

use App\Models\StockWaste;
use App\Models\BranchStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockWasteController extends Controller
{
    public function index()
    {
        $wastes = StockWaste::with('branch')->orderBy('waste_date', 'desc')->get();
        return response()->json($wastes);
    }

    public function store(Request $request)
    {
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
                
                // Assuming we allow waste to go mostly always, but logic suggests we can only waste what we have.
                // But sometimes you find waste without knowing system stock. 
                // Let's enforce stock must exist to be wasted? Or just deduct?
                // Let's treat like sale -> deduct.
                
                $currentQty = $stock ? $stock->quantity : 0;
                $newQty = $currentQty - $item['quantity'];
                
                // Update or create (if negative stock allowed? usually no)
                // If stock record doesn't exist, we create with negative? Or fail?
                // Proper inventory usually prevents negative. 
                if ($newQty < 0) {
                     // throw new \Exception("Insufficient stock to waste ingredient ID: {$item['ingredient_id']}");
                     // For simplicity, let's allow negative in case of data sync issues? No, safe to block.
                     // But user might need to fix stock first.
                     // Let's CREATE/UPDATE.
                }

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
            return response()->json($waste->load('items'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        $waste = StockWaste::with(['items.ingredient', 'branch'])->findOrFail($id);
        return response()->json($waste);
    }
}
