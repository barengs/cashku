<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Models\Ingredient;
use App\Models\BranchStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @group Inventory Management
 * @description APIs for transferring stock between branches.
 */
class StockTransferController extends Controller
{
    /**
     * List Transfers
     * @description Get a list of stock transfers.
     */
    public function index()
    {
        try {
            $transfers = StockTransfer::with(['fromBranch', 'toBranch'])->orderBy('transfer_date', 'desc')->get();
            return StockTransferResource::collection($transfers);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Transfer
     * @description Create a new stock transfer (Pending status).
     * @bodyParam from_branch_id string required Source Branch.
     * @bodyParam to_branch_id string required Destination Branch.
     * @bodyParam transfer_date date required Date.
     * @bodyParam items object[] required Items.
     * @bodyParam items[].ingredient_id string required Ingredient UUID.
     * @bodyParam items[].quantity number required Quantity to transfer.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'from_branch_id' => 'required|uuid|exists:branches,id',
                'to_branch_id' => 'required|uuid|exists:branches,id|different:from_branch_id',
                'transfer_date' => 'required|date',
                'note' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.ingredient_id' => 'required|uuid|exists:ingredients,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
            ]);

            DB::beginTransaction();
            try {
                // Check stock availability at source branch
                foreach ($validated['items'] as $item) {
                    $stock = BranchStock::where('branch_id', $validated['from_branch_id'])
                        ->where('ingredient_id', $item['ingredient_id'])
                        ->first();

                    if (!$stock || $stock->quantity < $item['quantity']) {
                        throw new Exception("Insufficient stock for ingredient ID: {$item['ingredient_id']} at source branch.");
                    }
                }

                $transfer = StockTransfer::create([
                    'from_branch_id' => $validated['from_branch_id'],
                    'to_branch_id' => $validated['to_branch_id'],
                    'transfer_date' => $validated['transfer_date'],
                    'note' => $validated['note'] ?? null,
                    'status' => 'pending',
                ]);

                foreach ($validated['items'] as $item) {
                    $transfer->items()->create([
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
                
                DB::commit();
                return new StockTransferResource($transfer->load('items'));
            } catch (Exception $e) {
                DB::rollBack();
                // If insufficient stock, we want 400 not 500 probably? 
                // But the requirement says try-catch all -> 500 or just try-catch?
                // Usually logic errors like this should be 400.
                // I will keep re-throwing or handle it.
                // If I re-throw, the outer catch catches it and returns 500.
                // Let's return 400 here specifically for logic errors if possible, OR just throw and let it be 500 with message.
                // The previous code returned 400.
                // I'll return response()->json(['message' => ...], 400) inside this inner catch,
                // BUT the outer catch will wrap it? No, if I return response, it's returned.
                return response()->json(['message' => $e->getMessage()], 400);
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Transfer
     * @description Get transfer details.
     */
    public function show($id)
    {
        try {
            $transfer = StockTransfer::with(['items.ingredient', 'fromBranch', 'toBranch'])->findOrFail($id);
            return new StockTransferResource($transfer);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Transfer
     * @description Update transfer details (Not Implemented).
     */
    public function update(Request $request, $id)
    {
        try {
            $transfer = StockTransfer::findOrFail($id);
            
            if ($transfer->status !== 'pending') {
                return response()->json(['message' => 'Only pending transfers can be updated'], 400);
            }
            
            // Logic not implemented in original file, just stub
            return response()->json(['message' => 'Update not implemented'], 501);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Ship Transfer
     * @description Mark transfer as shipped and deduct source stock.
     */
    public function ship($id)
    {
        try {
            $transfer = StockTransfer::with('items')->findOrFail($id);

            if ($transfer->status !== 'pending') {
                return response()->json(['message' => 'Transfer status is not pending'], 400);
            }

            DB::beginTransaction();
            try {
                foreach ($transfer->items as $item) {
                    $stock = BranchStock::where('branch_id', $transfer->from_branch_id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->lockForUpdate() // Lock to prevent race conditions
                        ->first();

                    if (!$stock || $stock->quantity < $item->quantity) {
                        throw new Exception("Insufficient stock for ingredient ID: {$item->ingredient_id}");
                    }

                    $stock->quantity -= $item->quantity;
                    $stock->save();
                }

                $transfer->status = 'shipped';
                $transfer->save();

                DB::commit();
                return new StockTransferResource($transfer);
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 400);
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Receive Transfer
     * @description Mark transfer as received and add to destination stock.
     */
    public function receive($id)
    {
        try {
            $transfer = StockTransfer::with('items')->findOrFail($id);

            if ($transfer->status !== 'shipped') {
                 return response()->json(['message' => 'Transfer must be shipped before receiving'], 400);
            }

            DB::beginTransaction();
            try {
                foreach ($transfer->items as $item) {
                    $stock = BranchStock::firstOrNew([
                        'branch_id' => $transfer->to_branch_id,
                        'ingredient_id' => $item->ingredient_id
                    ]);

                    $stock->quantity = ($stock->quantity ?? 0) + $item->quantity;
                    $stock->save();
                }

                $transfer->status = 'received';
                $transfer->save();

                DB::commit();
                return new StockTransferResource($transfer);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
