<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Exception;

class ProductCategoryController extends Controller
{
    public function index()
    {
        try {
            $categories = ProductCategory::orderBy('name')->get();
            return ProductCategoryResource::collection($categories);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

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

    public function show($id)
    {
        try {
            $category = ProductCategory::with('products')->findOrFail($id);
            return new ProductCategoryResource($category);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

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
