<?php

namespace App\Http\Controllers;

use App\Http\Resources\TableResource;
use App\Models\Table;
use Illuminate\Http\Request;
use Exception;

/**
 * @group Table Management
 * @description APIs for managing restaurant tables.
 */
class TableController extends Controller
{
    /**
     * List Tables
     * @description Get a list of tables.
     * @queryParam branch_id string Filter by Branch ID.
     * @queryParam status string Filter by status (available, occupied, reserved).
     */
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

    /**
     * Create Table
     * @description Add a new table to a branch.
     * @bodyParam branch_id string required Branch UUID.
     * @bodyParam number string required Table number/name.
     * @bodyParam capacity int required Number of seats.
     * @bodyParam status string optional Initial status (default: available).
     */
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

    /**
     * Show Table
     * @description Get table details.
     */
    public function show($id)
    {
        try {
            $table = Table::with('branch')->findOrFail($id);
            return new TableResource($table);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Table
     * @description Update table details.
     * @bodyParam number string optional Table number/name.
     * @bodyParam capacity int optional Number of seats.
     * @bodyParam status string optional Status.
     */
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

    /**
     * Delete Table
     * @description Remove a table.
     */
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
