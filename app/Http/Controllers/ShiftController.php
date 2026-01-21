<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * @group Shift Management
 * @description APIs for managing start/end of shifts.
 */
class ShiftController extends Controller
{
    /**
     * List Shifts
     * @description Get a list of shifts.
     * @queryParam branch_id string Filter by Branch.
     * @queryParam user_id string Filter by User.
     * @queryParam status string Filter by status (open, closed).
     */
    public function index(Request $request)
    {
        try {
            $query = Shift::with(['branch', 'user']);
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->has('status')) { // open or closed
                $status = $request->status;
                if ($status === 'open') {
                    $query->whereNull('end_time');
                } else if ($status === 'closed') {
                    $query->whereNotNull('end_time');
                }
            }
            
            return ShiftResource::collection($query->orderBy('start_time', 'desc')->get());
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Open Shift
     * @description Start a new shift.
     * @bodyParam branch_id string required Branch UUID.
     * @bodyParam starting_cash number required Initial cash amount.
     */
    public function open(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'starting_cash' => 'required|numeric|min:0'
            ]);

            // Check if user has open shift
            $openShift = Shift::where('user_id', Auth::id())
                ->whereNull('end_time')
                ->first();
            
            if ($openShift) {
                return response()->json(['message' => 'You already have an open shift'], 400);
            }

            $shift = Shift::create([
                'branch_id' => $validated['branch_id'],
                'user_id' => Auth::id(),
                'start_time' => Carbon::now(),
                'starting_cash' => $validated['starting_cash'],
                'status' => 'open'
            ]);

            return new ShiftResource($shift);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Close Shift
     * @description End the current shift.
     * @bodyParam actual_cash number required Counted cash at end of shift.
     * @bodyParam note string optional Notes.
     */
    public function close(Request $request, $id)
    {
        try {
            $shift = Shift::where('user_id', Auth::id())->whereNull('end_time')->findOrFail($id);
            
            $validated = $request->validate([
                'actual_cash' => 'required|numeric|min:0',
                'note' => 'nullable|string'
            ]);

            // Calculate expected cash
            // Start Cash + Total Cash Payments
            $totalSales = Order::where('branch_id', $shift->branch_id) // Assuming shift is per branch
                ->where('created_at', '>=', $shift->start_time)
                ->where('payment_status', 'paid')
                ->whereHas('payments', function($q) {
                     $q->where('payment_method', 'cash');
                })
                ->get()
                ->sum(function($order) {
                    return $order->payments->where('payment_method', 'cash')->sum('amount');
                });

            $expectedCash = $shift->starting_cash + $totalSales;
            
            // Note: Migration has 'ending_cash' (system calc) and 'actual_cash' (user input)
            // 'difference' column does NOT exist in migration provided above (2026_01_20_120001_create_shifts_table.php)
            // It has: starting_cash, ending_cash, actual_cash. No difference column.
            
            $shift->update([
                'end_time' => Carbon::now(),
                'actual_cash' => $validated['actual_cash'],
                'ending_cash' => $expectedCash,
                'status' => 'closed',
                 // 'note' doesn't exist in migration either! Migration 2026_01_20_120001_create_shifts_table.php does NOT show note column.
                 // It shows: id, branch_id, user_id, start_time, end_time, starting_cash, ending_cash, actual_cash, status, timestamps.
                 // So I must remove 'note' and 'difference' assignment to avoid SQL error.
            ]);

            return new ShiftResource($shift);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Shift
     * @description Get shift details.
     */
    public function show($id)
    {
        try {
            $shift = Shift::with(['branch', 'user'])->findOrFail($id);
            return new ShiftResource($shift);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
