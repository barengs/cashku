<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Exception;

class ShiftController extends Controller
{
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

    public function open(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'start_cash' => 'required|numeric|min:0'
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
                'start_cash' => $validated['start_cash']
            ]);

            return new ShiftResource($shift);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function close(Request $request, $id)
    {
        try {
            $shift = Shift::where('user_id', Auth::id())->whereNull('end_time')->findOrFail($id);
            
            $validated = $request->validate([
                'actual_end_cash' => 'required|numeric|min:0',
                'note' => 'nullable|string'
            ]);

            // Calculate expected cash
            // Start Cash + Total Cash Payments
            $totalSales = Order::where('branch_id', $shift->branch_id) // Assuming shift is per branch
                ->where('created_at', '>=', $shift->start_time)
                ->where('payment_status', 'paid')
                ->with(['payments' => function($q) {
                    $q->where('payment_method', 'cash');
                }])
                ->get()
                ->sum(function($order) {
                    return $order->payments->sum('amount');
                });

            $expectedCash = $shift->start_cash + $totalSales;
            $difference = $validated['actual_end_cash'] - $expectedCash;

            $shift->update([
                'end_time' => Carbon::now(),
                'actual_end_cash' => $validated['actual_end_cash'],
                'expected_end_cash' => $expectedCash,
                'difference' => $difference,
                'note' => $validated['note'] ?? null
            ]);

            return new ShiftResource($shift);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

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
