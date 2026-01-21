<?php

namespace App\Http\Controllers;

use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Exception;

/**
 * @group Marketing
 * @description APIs for managing promotions.
 */
class PromotionController extends Controller
{
    /**
     * List Promotions
     * @description Get a list of promotions.
     */
    public function index()
    {
        try {
            $promotions = Promotion::all();
            return PromotionResource::collection($promotions);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Promotion
     * @description Create a new promotion.
     * @bodyParam name string required Promotion Name.
     * @bodyParam type string required Type (fixed, percentage).
     * @bodyParam value number required Discount Value.
     * @bodyParam start_date date required Start Date.
     * @bodyParam end_date date required End Date.
     * @bodyParam is_active boolean optional Status.
     */
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

    /**
     * Show Promotion
     * @description Get promotion details.
     */
    public function show($id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            return new PromotionResource($promotion);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Promotion
     * @description Update promotion details.
     * @bodyParam name string optional Name.
     * @bodyParam type string optional Type.
     * @bodyParam value number optional Value.
     * @bodyParam start_date date optional Start.
     * @bodyParam end_date date optional End.
     * @bodyParam is_active boolean optional Status.
     */
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

    /**
     * Delete Promotion
     * @description Delete a promotion.
     */
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
