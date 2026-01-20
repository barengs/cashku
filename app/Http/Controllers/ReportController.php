<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\BranchStock;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        try {
            $query = Order::where('status', 'completed');
            
            $this->applyFilters($query, $request);

            $totalRevenue = $query->sum('total_amount');
            $totalOrders = $query->count();
            $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
            
            // Group by Date
            $salesByDate = Order::where('status', 'completed')
                ->when($request->branch_id, function($q) use ($request) {
                    $q->where('branch_id', $request->branch_id);
                })
                ->when($request->start_date, function($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                })
                ->when($request->end_date, function($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                })
                ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'average_order_value' => round($avgOrderValue, 2),
                'sales_by_date' => $salesByDate
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function profit(Request $request)
    {
        try {
            // 1. Calculate Revenue
            $orderQuery = Order::with('items.product.recipes.ingredient')->where('status', 'completed');
            $this->applyFilters($orderQuery, $request);
            
            $orders = $orderQuery->get();
            $totalRevenue = $orders->sum('total_amount');

            // 2. Calculate COGS (Cost of Goods Sold)
            $totalCOGS = 0;
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    // Calculate item COGS based on current recipe costs
                    // Note: ideally we should snapshot cost at time of sale, but using current ingredient cost is standard for simple POS
                    $productCOGS = 0;
                    if ($item->product && $item->product->recipes) {
                        foreach ($item->product->recipes as $recipe) {
                             $cost = $recipe->ingredient->cost_per_unit ?? 0;
                             $productCOGS += ($cost * $recipe->quantity);
                        }
                    }
                    $totalCOGS += ($productCOGS * $item->quantity);
                }
            }
            
            $grossProfit = $totalRevenue - $totalCOGS;
            $margin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

            return response()->json([
                'revenue' => $totalRevenue,
                'cogs' => $totalCOGS,
                'gross_profit' => $grossProfit,
                'margin_percentage' => round($margin, 2)
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function inventory(Request $request)
    {
        try {
            $stocks = BranchStock::with('ingredient');
            if($request->branch_id) {
                $stocks->where('branch_id', $request->branch_id);
            }
            
            $data = $stocks->get()->map(function($stock) {
                $unitCost = $stock->ingredient->cost_per_unit ?? 0;
                return [
                    'branch_id' => $stock->branch_id,
                    'ingredient_name' => $stock->ingredient->name,
                    'quantity' => $stock->quantity,
                    'value' => $stock->quantity * $unitCost
                ];
            });

            $totalValue = $data->sum('value');

            return response()->json([
                'total_stock_value' => $totalValue,
                'stocks' => $data
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function cashFlow(Request $request)
    {
        try {
            // Inflow: Sales
            $salesQuery = Order::where('status', 'completed');
            $this->applyFilters($salesQuery, $request);
            $inflow = $salesQuery->sum('total_amount');

            // Outflow: Expenses
            $expenseQuery = Expense::query();
            if ($request->branch_id) $expenseQuery->where('branch_id', $request->branch_id);
            if ($request->start_date) $expenseQuery->where('date', '>=', $request->start_date);
            if ($request->end_date) $expenseQuery->where('date', '<=', $request->end_date);
            $expenses = $expenseQuery->sum('amount');

            // Outflow: Purchase Orders (assuming paid)
            $poQuery = PurchaseOrder::where('status', 'received'); // Only count received as paid for now
            if ($request->branch_id) $poQuery->where('branch_id', $request->branch_id);
            if ($request->start_date) $poQuery->where('order_date', '>=', $request->start_date);
            if ($request->end_date) $poQuery->where('order_date', '<=', $request->end_date);
            $purchases = $poQuery->sum('total_amount');

            $totalOutflow = $expenses + $purchases;

            return response()->json([
                'inflow' => $inflow,
                'outflow' => $totalOutflow,
                'net_cash_flow' => $inflow - $totalOutflow,
                'breakdown' => [
                    'sales' => $inflow,
                    'expenses' => $expenses,
                    'purchases' => $purchases
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function applyFilters($query, $request)
    {
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
    }
}
