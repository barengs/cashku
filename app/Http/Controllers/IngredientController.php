<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index()
    {
        return response()->json(Ingredient::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ingredients',
            'unit' => 'required|string|max:50',
            'cost_per_unit' => 'numeric|min:0',
            'minimum_stock' => 'integer|min:0',
            'current_stock' => 'integer|min:0',
        ]);

        $ingredient = Ingredient::create($validated);

        return response()->json($ingredient, 201);
    }

    public function show(Ingredient $ingredient)
    {
        return response()->json($ingredient);
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $validated = $request->validate([
            'name' => 'string|max:255|unique:ingredients,name,' . $ingredient->id,
            'unit' => 'string|max:50',
            'cost_per_unit' => 'numeric|min:0',
            'minimum_stock' => 'integer|min:0',
            'current_stock' => 'integer|min:0',
        ]);

        $ingredient->update($validated);

        return response()->json($ingredient);
    }

    public function destroy(Ingredient $ingredient)
    {
        $ingredient->delete();

        return response()->json(null, 204);
    }
}
