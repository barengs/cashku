<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Exception;

/**
 * @group Product Management
 * @description APIs for managing product categories.
 */
class ProductCategoryController extends Controller
{
    /**
     * List Categories
     * @description Get a list of product categories.
     */
    public function index()
    {
        try {
            $categories = ProductCategory::orderBy('name')->get();
            return ProductCategoryResource::collection($categories);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Category
     * @description Create a new product category.
     * @bodyParam name string required Category Name.
     * @bodyParam description string optional Description.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string'
            ]);

            $category = ProductCategory::create($validated);
            return new ProductCategoryResource($category);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Category
     * @description Get category details.
     */
    public function show($id)
    {
        try {
            $category = ProductCategory::with('products')->findOrFail($id);
            return new ProductCategoryResource($category);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Category
     * @description Update category details.
     * @bodyParam name string optional Category Name.
     * @bodyParam description string optional Description.
     */
    public function update(Request $request, $id)
    {
        try {
            $category = ProductCategory::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'string|max:255',
                'description' => 'nullable|string'
            ]);

            $category->update($validated);
            return new ProductCategoryResource($category);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Category
     * @description Delete a product category.
     */
    public function destroy($id)
    {
        try {
            $category = ProductCategory::findOrFail($id);
            $category->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
