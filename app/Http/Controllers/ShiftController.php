<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = Shift::with('user');
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        // Show open shifts first
        return response()->json($query->orderBy('status', 'desc')->orderBy('start_time', 'desc')->get());
    }

    public function open(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:branches,id',
            'starting_cash' => 'required|numeric|min:0',
        ]);

        // Check if user already has an open shift?
        $existingShift = Shift::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();

        if ($existingShift) {
             return response()->json(['error' => 'You already have an open shift'], 400);
        }

        $shift = Shift::create([
            'branch_id' => $validated['branch_id'],
            'user_id' => Auth::id(), // Use authenticated user
            'start_time' => now(),
            'starting_cash' => $validated['starting_cash'],
            'status' => 'open'
        ]);

        return response()->json($shift, 201);
    }

    public function close(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);
        
        if ($shift->status !== 'open') {
            return response()->json(['error' => 'Shift is already closed'], 400);
        }
        
        $validated = $request->validate([
            'actual_cash' => 'required|numeric|min:0',
        ]);

        // Calculate expected ending cash
        // Logic: Starting Cash + Total Payments (Cash) in this shift
        // For now, simple calc
        $totalCashSales = $shift->orders()
            ->whereHas('payments', function($q) {
                $q->where('payment_method', 'cash');
            })
            ->withSum(['payments' => function($q) {
                $q->where('payment_method', 'cash');
            }], 'amount')
            ->get()
            ->sum('payments_sum_amount');

        $shift->end_time = now();
        $shift->ending_cash = $shift->starting_cash + $totalCashSales;
        $shift->actual_cash = $validated['actual_cash'];
        $shift->status = 'closed';
        $shift->save();

        return response()->json($shift);
    }

    public function show($id)
    {
        return response()->json(Shift::with(['user', 'orders'])->findOrFail($id));
    }
}
