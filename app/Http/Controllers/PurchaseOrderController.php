<?php

namespace App\Http\Controllers;

use App\Http\Resources\PurchaseOrderResource;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @group Purchasing
 * @description APIs for managing purchase orders to suppliers.
 */
class PurchaseOrderController extends Controller
{
    /**
     * List Purchase Orders
     * @description Get a list of purchase orders.
     * @queryParam branch_id string Filter by Branch.
     */
    public function index(Request $request)
    {
        try {
            $query = PurchaseOrder::with(['supplier', 'items.ingredient']);
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            return PurchaseOrderResource::collection($query->get());
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Purchase Order
     * @description Create a new purchase order.
     * @bodyParam supplier_id string required Supplier UUID.
     * @bodyParam branch_id string required Branch UUID.
     * @bodyParam order_date date required Order Date.
     * @bodyParam items object[] required List of ingredients.
     * @bodyParam items[].ingredient_id string required Ingredient UUID.
     * @bodyParam items[].quantity int required Quantity.
     * @bodyParam items[].unit_price number required Unit Price.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => 'required|uuid|exists:suppliers,id',
                'branch_id' => 'required|uuid|exists:branches,id',
                'order_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.ingredient_id' => 'required|uuid|exists:ingredients,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();
            try {
                $po = PurchaseOrder::create([
                    'supplier_id' => $validated['supplier_id'],
                    'branch_id' => $validated['branch_id'],
                    'order_date' => $validated['order_date'],
                    'status' => 'pending',
                    'total_amount' => 0 // calculated below
                ]);

                $total = 0;
                foreach ($validated['items'] as $item) {
                    $subtotal = $item['quantity'] * $item['unit_price'];
                    $po->items()->create([
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $subtotal
                    ]);
                    $total += $subtotal;
                }

                $po->total_amount = $total;
                $po->save();

                DB::commit();
                return new PurchaseOrderResource($po->load(['supplier', 'items.ingredient']));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Purchase Order
     * @description Get purchase order details.
     */
    public function show($id)
    {
        try {
            $po = PurchaseOrder::with(['supplier', 'items.ingredient'])->findOrFail($id);
            return new PurchaseOrderResource($po);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Purchase Order
     * @description Update a pending purchase order. Replaces all items.
     * @bodyParam items object[] required List of ingredients.
     */
    public function update(Request $request, $id)
    {
        try {
            $po = PurchaseOrder::findOrFail($id);

            if ($po->status !== 'pending') {
                return response()->json(['error' => 'Cannot update non-pending order'], 400);
            }

            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.ingredient_id' => 'required|uuid|exists:ingredients,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();
            try {
                // Delete old items
                $po->items()->delete();

                $total = 0;
                foreach ($validated['items'] as $item) {
                    $subtotal = $item['quantity'] * $item['unit_price'];
                    $po->items()->create([
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $subtotal
                    ]);
                    $total += $subtotal;
                }

                $po->total_amount = $total;
                $po->save();

                DB::commit();
                return new PurchaseOrderResource($po->load(['supplier', 'items.ingredient']));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Purchase Order
     * @description Delete a pending purchase order.
     */
    public function destroy($id)
    {
        try {
            $po = PurchaseOrder::findOrFail($id);
            if ($po->status !== 'pending') {
                return response()->json(['error' => 'Cannot delete non-pending order'], 400);
            }
            $po->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve Purchase Order
     * @description Approve a pending purchase order.
     */
    public function approve($id)
    {
        try {
            $po = PurchaseOrder::findOrFail($id);
            if ($po->status !== 'pending') {
                return response()->json(['error' => 'Cannot approve non-pending order'], 400);
            }
            $po->status = 'approved';
            $po->save();
            return new PurchaseOrderResource($po);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Receive Purchase Order
     * @description Mark order as received and update stock.
     */
    public function receive($id)
    {
        try {
            $po = PurchaseOrder::with('items')->findOrFail($id);

            if ($po->status === 'received') {
                return response()->json(['error' => 'Order already received'], 400);
            }

            DB::beginTransaction();
            try {
                // Update Stock per Branch
                foreach ($po->items as $item) {
                    // Update BranchStock
                    $stock = BranchStock::where('branch_id', $po->branch_id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->first();
                    
                    if ($stock) {
                        $stock->quantity += $item->quantity;
                        $stock->save();
                    } else {
                        BranchStock::create([
                            'branch_id' => $po->branch_id,
                            'ingredient_id' => $item->ingredient_id,
                            'quantity' => $item->quantity
                        ]);
                    }

                    // Optional: Update cost_per_unit logic could go here
                }

                $po->status = 'received';
                $po->save();

                DB::commit();
                return new PurchaseOrderResource($po->load(['items', 'supplier']));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
