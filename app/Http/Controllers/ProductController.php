<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'recipes.ingredient']);
        
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|uuid|exists:product_categories,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|string', // URL for now
            'is_active' => 'boolean',
            'recipes' => 'array',
            'recipes.*.ingredient_id' => 'required|uuid|exists:ingredients,id',
            'recipes.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::create($request->except('recipes'));

            if (isset($validated['recipes'])) {
                foreach ($validated['recipes'] as $recipe) {
                    $product->recipes()->create([
                        'ingredient_id' => $recipe['ingredient_id'],
                        'quantity' => $recipe['quantity']
                    ]);
                }
            }

            DB::commit();
            return response()->json($product->load(['category', 'recipes.ingredient']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $product = Product::with(['category', 'recipes.ingredient'])->findOrFail($id);
        // Include cogs in response
        $product->append('cogs');
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'nullable|uuid|exists:product_categories,id',
            'name' => 'string',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
            'recipes' => 'array',
            'recipes.*.ingredient_id' => 'required_with:recipes|uuid|exists:ingredients,id',
            'recipes.*.quantity' => 'required_with:recipes|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $product->update($request->except('recipes'));

            if (isset($validated['recipes'])) {
                $product->recipes()->delete();
                foreach ($validated['recipes'] as $recipe) {
                    $product->recipes()->create([
                        'ingredient_id' => $recipe['ingredient_id'],
                        'quantity' => $recipe['quantity']
                    ]);
                }
            }

            DB::commit();
            
            $product->refresh();
            $product->append('cogs');
            
            return response()->json($product->load(['category', 'recipes.ingredient']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(null, 204);
    }
}
