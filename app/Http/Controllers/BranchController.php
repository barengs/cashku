<?php

namespace App\Http\Controllers;

use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Request;
use Exception;

class BranchController extends Controller
{
    /**
     * @group Branch Management
     * @description Manage multiple branches for the tenant.
     */
    public function index()
    {
        try {
            $branches = Branch::all();
            return BranchResource::collection($branches);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Branch Management
     * @description Create a new branch.
     * @bodyParam name string required The name of the branch. Example: Cabang Jakarta
     * @bodyParam address string The address of the branch. Example: Jl. Sudirman No. 1
     * @bodyParam phone string The phone number. Example: 08123456789
     * @bodyParam is_central boolean Whether this is the central branch/warehouse. Example: false
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'is_central' => 'boolean'
            ]);

            $branch = Branch::create($validated);
            return new BranchResource($branch);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Branch Management
     * @description Get a specific branch details.
     */
    public function show($id)
    {
        try {
            $branch = Branch::findOrFail($id);
            return new BranchResource($branch);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Branch Management
     * @description Update branch information.
     */
    public function update(Request $request, $id)
    {
        try {
            $branch = Branch::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'string|max:255',
                'address' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'is_central' => 'boolean'
            ]);

            $branch->update($validated);
            return new BranchResource($branch);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Branch Management
     * @description Delete a branch.
     */
    public function destroy($id)
    {
        try {
            $branch = Branch::findOrFail($id);
            $branch->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
