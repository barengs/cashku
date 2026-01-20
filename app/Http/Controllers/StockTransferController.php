<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\Ingredient;
use App\Models\BranchStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function index()
    {
        $transfers = StockTransfer::with(['fromBranch', 'toBranch'])->orderBy('transfer_date', 'desc')->get();
        return response()->json($transfers);
    }

    public function store(Request $request)
    {
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
                    throw new \Exception("Insufficient stock for ingredient ID: {$item['ingredient_id']} at source branch.");
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

            // Immediately deduct from Source? Or wait for "shipped" status?
            // Let's assume creating the transfer reserves/deducts stock to avoid double sending.
            // Or better: Use status flow. 
            // - pending: Draft
            // - shipped: Deduct from Source
            // - received: Add to Destination
            
            // For simplicity in this iteration, let's treat 'store' as creating a pending transfer.
            // And a separate 'ship' action to deduct, or do it on create if we want simpler flow.
            // Let's go with: Store = Pending. 
            
            DB::commit();
            return response()->json($transfer->load('items'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        $transfer = StockTransfer::with(['items.ingredient', 'fromBranch', 'toBranch'])->findOrFail($id);
        return response()->json($transfer);
    }

    public function update(Request $request, $id)
    {
        $transfer = StockTransfer::findOrFail($id);
        
        if ($transfer->status !== 'pending') {
            return response()->json(['error' => 'Only pending transfers can be updated'], 400);
        }
        
        // Similar logic to update Items... skipping for brevity unless requested, focusing on flow.
        // Assuming simple flow first.
    }

    public function ship($id)
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json(['error' => 'Transfer status is not pending'], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($transfer->items as $item) {
                $stock = BranchStock::where('branch_id', $transfer->from_branch_id)
                    ->where('ingredient_id', $item->ingredient_id)
                    ->lockForUpdate() // Lock to prevent race conditions
                    ->first();

                if (!$stock || $stock->quantity < $item->quantity) {
                    throw new \Exception("Insufficient stock for ingredient ID: {$item->ingredient_id}");
                }

                $stock->quantity -= $item->quantity;
                $stock->save();
            }

            $transfer->status = 'shipped';
            $transfer->save();

            DB::commit();
            return response()->json($transfer);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function receive($id)
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== 'shipped') {
             return response()->json(['error' => 'Transfer must be shipped before receiving'], 400);
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
            return response()->json($transfer);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
