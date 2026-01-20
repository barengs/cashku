<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\BranchStock;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['items.product', 'table', 'shift.user']);
        
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:branches,id',
            'shift_id' => 'nullable|uuid|exists:shifts,id',
            'table_id' => 'nullable|uuid|exists:tables,id',
            'customer_name' => 'nullable|string',
            'type' => 'in:dine_in,takeaway',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            if (isset($validated['table_id']) && $validated['type'] === 'dine_in') {
                $table = Table::find($validated['table_id']);
                if ($table->status === 'occupied') {
                     // throw new \Exception("Table is already occupied");
                     // Allow multiple orders per table? usually yes.
                }
                $table->status = 'occupied';
                $table->save();
            }

            $order = Order::create([
                'branch_id' => $validated['branch_id'],
                'shift_id' => $validated['shift_id'] ?? null,
                'table_id' => $validated['table_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'type' => $validated['type'] ?? 'dine_in',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_amount' => 0 // calculated below
            ]);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $subtotal = $product->price * $item['quantity'];
                
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                    'note' => $item['note'] ?? null
                ]);
                $total += $subtotal;
            }

            $order->total_amount = $total;
            $order->save();

            DB::commit();
            return response()->json($order->load('items'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Order::with(['items.product', 'payments'])->findOrFail($id));
    }

    public function pay(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        
        if ($order->payment_status === 'paid') {
             return response()->json(['error' => 'Order already paid'], 400);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Record Payment
            $order->payments()->create([
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'payment_date' => now()
            ]);

            // Check if fully paid
            $totalPaid = $order->payments()->sum('amount') + $validated['amount']; // + current logic? NO, create saves it.
            // Re-query payments after create
            $totalPaid = $order->payments()->sum('amount');
            
            if ($totalPaid >= $order->total_amount) {
                $order->payment_status = 'paid';
                $order->status = 'completed';
                $order->save();

                // Clear Table if occupied
                if ($order->table_id) {
                    $table = Table::find($order->table_id);
                    $table->status = 'available';
                    $table->save();
                }

                // DEDUCT STOCK
                $this->deductStock($order);
            }

            DB::commit();
            return response()->json($order->load('payments'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function deductStock(Order $order)
    {
        foreach ($order->items as $orderItem) {
            $product = $orderItem->product;
            foreach ($product->recipes as $recipe) {
                // Determine usage: Recipe Qty * Order Item Qty
                $totalUsage = $recipe->quantity * $orderItem->quantity;

                // Find Branch Stock
                $stock = BranchStock::where('branch_id', $order->branch_id)
                    ->where('ingredient_id', $recipe->ingredient_id)
                    ->first();

                if ($stock) {
                    $stock->quantity -= $totalUsage;
                    $stock->save();
                } else {
                    // Create negative stock if tracking allowing over-draft
                    BranchStock::create([
                        'branch_id' => $order->branch_id,
                        'ingredient_id' => $recipe->ingredient_id,
                        'quantity' => -$totalUsage
                    ]);
                }
            }
        }
    }
}
