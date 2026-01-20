<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $orders = PurchaseOrder::with('supplier')->orderBy('order_date', 'desc')->get();
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:branches,id',
            'supplier_id' => 'required|uuid|exists:suppliers,id',
            'order_date' => 'required|date',
            'status' => 'in:pending,approved,received,cancelled',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|uuid|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            $po = PurchaseOrder::create([
                'branch_id' => $validated['branch_id'],
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'status' => $validated['status'] ?? 'pending',
                'total_amount' => $totalAmount,
            ]);

            foreach ($validated['items'] as $item) {
                $po->items()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            DB::commit();
            return response()->json($po->load('items'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $po = PurchaseOrder::with(['supplier', 'items.ingredient', 'branch'])->findOrFail($id);
        return response()->json($po);
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);
        
        if ($po->status === 'received') {
             return response()->json(['error' => 'Cannot update a received order'], 400);
        }

        $validated = $request->validate([
            'branch_id' => 'uuid|exists:branches,id',
            'supplier_id' => 'uuid|exists:suppliers,id',
            'order_date' => 'date',
            'status' => 'in:pending,approved,cancelled',
            'items' => 'array|min:1',
            'items.*.ingredient_id' => 'required_with:items|uuid|exists:ingredients,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Update PO basic details
            $po->update(collect($validated)->except('items')->toArray());

            if (isset($validated['items'])) {
                // Delete existing items
                $po->items()->delete();

                // Re-create items and calculate total
                $totalAmount = 0;
                foreach ($validated['items'] as $item) {
                    $subtotal = $item['quantity'] * $item['unit_price'];
                    $totalAmount += $subtotal;

                    $po->items()->create([
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $subtotal,
                    ]);
                }

                // Update total amount on PO
                $po->total_amount = $totalAmount;
                $po->save();
            }

            DB::commit();
            return response()->json($po->load('items'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function receive($id)
    {
        $po = PurchaseOrder::with('items')->findOrFail($id);

        if ($po->status === 'received') {
            return response()->json(['error' => 'Order already received'], 400);
        }

        DB::beginTransaction();
        try {
            // Update Stock in BranchStock
            foreach ($po->items as $item) {
                $branchStock = \App\Models\BranchStock::firstOrNew([
                    'branch_id' => $po->branch_id,
                    'ingredient_id' => $item->ingredient_id,
                ]);
                
                $branchStock->quantity = ($branchStock->quantity ?? 0) + $item->quantity;
                $branchStock->save();
            }

            $po->status = 'received';
            $po->save();

            DB::commit();
            return response()->json($po->load('items'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
