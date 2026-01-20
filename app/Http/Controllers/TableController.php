<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $query = Table::query();
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        return response()->json($query->orderBy('number')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:branches,id',
            'number' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'status' => 'in:available,occupied,reserved',
        ]);

        $table = Table::create($validated);
        return response()->json($table, 201);
    }

    public function show($id)
    {
        return response()->json(Table::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $table = Table::findOrFail($id);
        
        $validated = $request->validate([
            'number' => 'string',
            'capacity' => 'integer|min:1',
            'status' => 'in:available,occupied,reserved',
        ]);

        $table->update($validated);
        return response()->json($table);
    }

    public function destroy($id)
    {
        $table = Table::findOrFail($id);
        $table->delete();
        return response()->json(null, 204);
    }
}
