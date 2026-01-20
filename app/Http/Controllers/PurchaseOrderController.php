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
        $po = PurchaseOrder::with(['supplier', 'items.ingredient'])->findOrFail($id);
        return response()->json($po);
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);
        
        if ($po->status === 'received') {
             return response()->json(['error' => 'Cannot update a received order'], 400);
        }

        $validated = $request->validate([
            'supplier_id' => 'uuid|exists:suppliers,id',
            'order_date' => 'date',
            'status' => 'in:pending,approved,cancelled', // Cannot set to received here
        ]);

        $po->update($validated);
        return response()->json($po);
    }

    public function receive($id)
    {
        $po = PurchaseOrder::with('items')->findOrFail($id);

        if ($po->status === 'received') {
            return response()->json(['error' => 'Order already received'], 400);
        }

        DB::beginTransaction();
        try {
            // Update Stock
            foreach ($po->items as $item) {
                $ingredient = Ingredient::find($item->ingredient_id);
                if ($ingredient) {
                    $ingredient->current_stock += $item->quantity;
                    // Optional: Update cost_per_unit logic could go here
                    $ingredient->save();
                }
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
