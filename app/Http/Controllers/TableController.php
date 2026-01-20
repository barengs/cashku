<?php

namespace App\Http\Controllers;

use App\Http\Resources\TableResource;
use App\Models\Table;
use Illuminate\Http\Request;
use Exception;

class TableController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Table::query();
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            return TableResource::collection($query->get());
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'number' => 'required|string',
                'capacity' => 'integer|min:1',
                'status' => 'in:available,occupied,reserved'
            ]);
            
            // Check uniqueness
            $exists = Table::where('branch_id', $validated['branch_id'])
                ->where('number', $validated['number'])
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'Table number already exists in this branch'], 422);
            }

            $table = Table::create($validated);
            return new TableResource($table);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $table = Table::with('branch')->findOrFail($id);
            return new TableResource($table);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $table = Table::findOrFail($id);
            $validated = $request->validate([
                'number' => 'string',
                'capacity' => 'integer|min:1',
                'status' => 'in:available,occupied,reserved'
            ]);
            
            $table->update($validated);
            return new TableResource($table);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $table = Table::findOrFail($id);
            $table->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
