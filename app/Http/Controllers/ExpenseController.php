<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * @group Finance
 * @description APIs for managing expenses.
 */
class ExpenseController extends Controller
{
    /**
     * List Expenses
     * @description Get a list of expenses.
     * @queryParam branch_id string Filter by Branch.
     * @queryParam date string Filter by date.
     */
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

    /**
     * Create Expense
     * @description Record a new expense.
     * @bodyParam branch_id string required Branch UUID.
     * @bodyParam name string required Expense Name.
     * @bodyParam amount number required Amount.
     * @bodyParam category string required Category.
     * @bodyParam date date required Expense Date.
     * @bodyParam note string optional Note.
     */
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

    /**
     * Show Expense
     * @description Get expense details.
     */
    public function show($id)
    {
        try {
            $expense = Expense::with('branch')->findOrFail($id);
            return new ExpenseResource($expense);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Expense
     * @description Update expense details.
     * @bodyParam name string optional Name.
     * @bodyParam amount number optional Amount.
     * @bodyParam category string optional Category.
     * @bodyParam date date optional Date.
     * @bodyParam note string optional Note.
     */
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

    /**
     * Delete Expense
     * @description Delete an expense.
     */
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
