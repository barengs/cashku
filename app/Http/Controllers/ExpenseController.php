<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with('user');
        
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        return response()->json($query->orderBy('date', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:branches,id',
            'name' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'category' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $expense = Expense::create(array_merge($validated, [
            'user_id' => Auth::id()
        ]));

        return response()->json($expense, 201);
    }

    public function show($id)
    {
        return response()->json(Expense::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'string',
            'amount' => 'numeric|min:0',
            'date' => 'date',
            'category' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $expense->update($validated);
        return response()->json($expense);
    }

    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();
        return response()->json(null, 204);
    }
}
