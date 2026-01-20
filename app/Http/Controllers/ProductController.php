<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @group Product Management
 * @description APIs for managing products and their recipes.
 */
class ProductController extends Controller
{
    /**
     * List Products
     * @description Get a list of products.
     * @queryParam category_id string Filter by Category ID.
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with(['category', 'recipes.ingredient']);
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            // Append COGS
            $products = $query->get()->each->append('cogs');
            return ProductResource::collection($products);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Product
     * @description Create a new product.
     * @bodyParam category_id string required Category UUID.
     * @bodyParam name string required Product Name.
     * @bodyParam description string optional Description.
     * @bodyParam price number required Price.
     * @bodyParam image file optional Product Image.
     * @bodyParam is_active boolean optional status.
     * @bodyParam recipes object[] optional List of ingredients.
     * @bodyParam recipes[].ingredient_id string required Ingredient UUID.
     * @bodyParam recipes[].quantity number required Amount used.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => 'required|uuid|exists:product_categories,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'image' => 'nullable|image|max:2048',
                'is_active' => 'boolean',
                'recipes' => 'nullable|array',
                'recipes.*.ingredient_id' => 'required|uuid|exists:ingredients,id',
                'recipes.*.quantity' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();
            try {
                // Upload Image
                $imagePath = null;
                if ($request->hasFile('image')) {
                    $imagePath = $request->file('image')->store('product-images', 'public');
                }

                $product = Product::create([
                    'category_id' => $validated['category_id'],
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'price' => $validated['price'],
                    'image' => $imagePath,
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                // Save Recipes
                if (!empty($validated['recipes'])) {
                    foreach ($validated['recipes'] as $recipe) {
                        $product->recipes()->create([
                            'ingredient_id' => $recipe['ingredient_id'],
                            'quantity' => $recipe['quantity']
                        ]);
                    }
                }

                DB::commit();
                return new ProductResource($product->load('recipes')->append('cogs'));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Product
     * @description Get product details.
     */
    public function show($id)
    {
        try {
            $product = Product::with(['category', 'recipes.ingredient'])->findOrFail($id);
            return new ProductResource($product->append('cogs'));
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Product
     * @description Update product details.
     * @bodyParam image file optional New Image.
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);
            
            $validated = $request->validate([
                'category_id' => 'uuid|exists:product_categories,id',
                'name' => 'string|max:255',
                'description' => 'nullable|string',
                'price' => 'numeric|min:0',
                'image' => 'nullable|image|max:2048',
                'is_active' => 'boolean',
                'recipes' => 'nullable|array',
            ]);

            DB::beginTransaction();
            try {
                if ($request->hasFile('image')) {
                    $imagePath = $request->file('image')->store('product-images', 'public');
                    $product->image = $imagePath;
                }

                $product->fill($request->only(['category_id', 'name', 'description', 'price', 'is_active']));
                $product->save();

                // Sync Recipes if provided
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
                return new ProductResource($product->load('recipes')->append('cogs'));
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Product
     * @description Delete a product.
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
