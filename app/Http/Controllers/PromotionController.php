<?php

namespace App\Http\Controllers;

use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Exception;

class PromotionController extends Controller
{
    public function index()
    {
        try {
            $promotions = Promotion::all();
            return PromotionResource::collection($promotions);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:fixed,percentage',
                'value' => 'required|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'is_active' => 'boolean'
            ]);

            $promotion = Promotion::create($validated);
            return new PromotionResource($promotion);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            return new PromotionResource($promotion);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'string|max:255',
                'type' => 'in:fixed,percentage',
                'value' => 'numeric|min:0',
                'start_date' => 'date',
                'end_date' => 'date|after_or_equal:start_date',
                'is_active' => 'boolean'
            ]);

            $promotion->update($validated);
            return new PromotionResource($promotion);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            $promotion->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
