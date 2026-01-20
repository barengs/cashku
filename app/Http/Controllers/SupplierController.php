<?php

namespace App\Http\Controllers;

use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Exception;

/**
 * @group Inventory Management
 * @description Manage suppliers.
 */
class SupplierController extends Controller
{
    /**
     * List Suppliers
     * @description Get a list of suppliers.
     */
    public function index()
    {
        try {
            $suppliers = Supplier::all();
            return SupplierResource::collection($suppliers);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Supplier
     * @description Create a new supplier.
     * @bodyParam name string required Supplier Name.
     * @bodyParam phone string optional Phone.
     * @bodyParam email string optional Email.
     * @bodyParam address string optional Address.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
            ]);

            $supplier = Supplier::create($validated);
            return new SupplierResource($supplier);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Supplier
     * @description Get supplier details.
     */
    public function show($id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            return new SupplierResource($supplier);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Supplier
     * @description Update supplier details.
     * @bodyParam name string optional Supplier Name.
     * @bodyParam phone string optional Phone.
     * @bodyParam email string optional Email.
     * @bodyParam address string optional Address.
     */
    public function update(Request $request, $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $validated = $request->validate([
                'name' => 'string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
            ]);

            $supplier->update($validated);
            return new SupplierResource($supplier);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Supplier
     * @description Delete a supplier.
     */
    public function destroy($id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $supplier->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
