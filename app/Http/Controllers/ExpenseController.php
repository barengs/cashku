<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Expense::query();
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            }
            return ExpenseResource::collection($query->latest()->get());
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|uuid|exists:branches,id',
                'name' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'category' => 'required|string',
                'date' => 'required|date',
                'note' => 'nullable|string'
            ]);

            $expense = Expense::create([
                ...$validated,
                'user_id' => Auth::id()
            ]);

            return new ExpenseResource($expense);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $expense = Expense::with('branch')->findOrFail($id);
            return new ExpenseResource($expense);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $expense = Expense::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'string|max:255',
                'amount' => 'numeric|min:0',
                'category' => 'string',
                'date' => 'date',
                'note' => 'nullable|string'
            ]);

            $expense->update($validated);
            return new ExpenseResource($expense);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $expense = Expense::findOrFail($id);
            $expense->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
