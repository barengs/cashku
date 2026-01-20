<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\BranchStock;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @group Order Management
 * @description APIs for handling orders, payments, and cancellations.
 */
class OrderController extends Controller
{
    /**
     * List Orders
     * @description Get a list of orders with optional filters.
     * @queryParam branch_id string Filter by Branch ID.
     * @queryParam status string Filter by status (pending, completed, cancelled).
     * @queryParam payment_status string Filter by payment status (unpaid, partial, paid).
     * @queryParam start_date string Filter by creation date range start (YYYY-MM-DD).
     * @queryParam end_date string Filter by creation date range end (YYYY-MM-DD).
     */
    public function index(Request $request)
    {
        try {
            $query = Order::with(['items.product', 'payments']);
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }
            // Date Filter
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }

            return OrderResource::collection($query->latest()->get());
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Order
     * @description Create a new order (Dine In, Take Away, Delivery).
     * @bodyParam branch_id string required Branch UUID.
     * @bodyParam shift_id string optional Shift UUID.
     * @bodyParam table_id string optional Table UUID (required for dine_in).
     * @bodyParam customer_name string optional Customer Name.
     * @bodyParam type string required Order type: dine_in, take_away, delivery.
     * @bodyParam items object[] required List of order items.
     * @bodyParam items[].product_id string required Product UUID.
     * @bodyParam items[].quantity int required Quantity.
     * @bodyParam items[].notes string optional Notes for the item.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|uuid|exists:branches,id',
                'shift_id' => 'nullable|uuid|exists:shifts,id',
                'table_id' => 'nullable|uuid|exists:tables,id',
                'customer_name' => 'nullable|string',
                'type' => 'required|in:dine_in,take_away,delivery',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|uuid|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.notes' => 'nullable|string'
            ]);

            DB::beginTransaction();
            try {
                // Update Table Status if Dine In
                if (!empty($validated['table_id']) && $validated['type'] === 'dine_in') {
                     \App\Models\Table::where('id', $validated['table_id'])->update(['status' => 'occupied']);
                }

                $order = Order::create([
                    'branch_id' => $validated['branch_id'],
                    'shift_id' => $validated['shift_id'] ?? null,
                    'table_id' => $validated['table_id'] ?? null,
                    'customer_name' => $validated['customer_name'] ?? 'Guest',
                    'type' => $validated['type'],
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'total_amount' => 0 // Calculated below
                ]);

                $totalAmount = 0;

                foreach ($validated['items'] as $itemData) {
                    $product = Product::find($itemData['product_id']);
                    $subtotal = $product->price * $itemData['quantity'];
                    
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $product->price,
                        'subtotal' => $subtotal,
                        'notes' => $itemData['notes'] ?? null
                    ]);

                    $totalAmount += $subtotal;
                }

                $order->total_amount = $totalAmount;
                $order->save();

                DB::commit();
                return new OrderResource($order->load(['items.product']));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Order
     * @description Get details of a specific order.
     */
    public function show($id)
    {
        try {
            $order = Order::with(['items.product', 'payments'])->findOrFail($id);
            return new OrderResource($order);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Process Payment
     * @description Pay for an order. Can be partial or full payment.
     * @bodyParam payment_method string required Payment method: cash, qris, debit, credit.
     * @bodyParam amount number required Payment amount.
     */
    public function pay(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);
            
            if ($order->payment_status === 'paid') {
                return response()->json(['message' => 'Order is already paid'], 400);
            }

            $validated = $request->validate([
                'payment_method' => 'required|in:cash,qris,debit,credit',
                'amount' => 'required|numeric|min:0'
            ]);

            DB::beginTransaction();
            try {
                // Record Payment
                $payment = $order->payments()->create([
                    'payment_method' => $validated['payment_method'],
                    'amount' => $validated['amount'],
                    'payment_date' => now()
                ]);

                // Check if fully paid
                $totalPaid = $order->payments()->sum('amount');
                if ($totalPaid >= $order->total_amount) {
                    $order->payment_status = 'paid';
                    $order->status = 'completed'; 
                    $order->save();

                    // MENGURANGI STOK
                    foreach ($order->items as $item) {
                        $product = $item->product; 
                        foreach ($product->recipes as $recipe) {
                            $totalIngredientUsed = $item->quantity * $recipe->quantity;

                            $stock = BranchStock::where('branch_id', $order->branch_id)
                                ->where('ingredient_id', $recipe->ingredient_id)
                                ->first();

                            if ($stock) {
                                $stock->quantity -= $totalIngredientUsed;
                                $stock->save();
                            } else {
                                // Create negative stock entry if missing
                                BranchStock::create([
                                    'branch_id' => $order->branch_id,
                                    'ingredient_id' => $recipe->ingredient_id,
                                    'quantity' => -$totalIngredientUsed
                                ]);
                            }
                        }
                    }

                    // Clear Table if occupied
                    if ($order->table_id) {
                        \App\Models\Table::where('id', $order->table_id)->update(['status' => 'available']);
                    }

                } else {
                    $order->payment_status = 'partial';
                    $order->save();
                }

                DB::commit();
                return new OrderResource($order->load(['items.product', 'payments']));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel Order
     * @description Cancel an order. Cannot cancel if already completed.
     */
    public function cancel($id)
    {
        try {
            $order = Order::findOrFail($id);
            if ($order->status === 'completed' || $order->status === 'cancelled') {
                 return response()->json(['error' => 'Cannot cancel completed or already cancelled order'], 400);
            }

            $order->status = 'cancelled';
            $order->save();
            return new OrderResource($order);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
