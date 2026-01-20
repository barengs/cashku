<?php

namespace App\Http\Controllers;

use App\Http\Resources\IngredientResource;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Exception;

/**
 * @group Inventory Management
 * @description APIs for managing ingredients.
 */
class IngredientController extends Controller
{
    /**
     * List Ingredients
     * @description Get a list of ingredients and their stocks.
     */
    public function index()
    {
        try {
            $ingredients = Ingredient::with(['stocks.branch'])->get();
            return IngredientResource::collection($ingredients);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Ingredient
     * @description Create a new ingredient.
     * @bodyParam name string required Ingredient Name.
     * @bodyParam unit string required Unit (kg, liter, pcs).
     * @bodyParam cost_per_unit number required Cost per unit.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'unit' => 'required|string|max:50',
                'cost_per_unit' => 'required|numeric|min:0'
            ]);

            $ingredient = Ingredient::create($validated);
            return new IngredientResource($ingredient->load('stocks'));
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Ingredient
     * @description Get ingredient details.
     */
    public function show($id)
    {
        try {
            $ingredient = Ingredient::with(['stocks.branch'])->findOrFail($id);
            return new IngredientResource($ingredient);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Ingredient
     * @description Update ingredient details.
     * @bodyParam name string optional Name.
     * @bodyParam unit string optional Unit.
     * @bodyParam cost_per_unit number optional Cost.
     */
    public function update(Request $request, $id)
    {
        try {
            $ingredient = Ingredient::findOrFail($id);
            $validated = $request->validate([
                'name' => 'string|max:255',
                'unit' => 'string|max:50',
                'cost_per_unit' => 'numeric|min:0'
            ]);

            $ingredient->update($validated);
            return new IngredientResource($ingredient->load('stocks'));
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Ingredient
     * @description Delete an ingredient.
     */
    public function destroy($id)
    {
        try {
            $ingredient = Ingredient::findOrFail($id);
            $ingredient->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
