<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
/**
 * @group Tenant Management
 * @description APIs for managing tenants.
 */
class TenantController extends Controller
{
    /**
     * Create Tenant
     * @description Create a new tenant.
     * @bodyParam id string required Tenant ID (Unique).
     * @bodyParam domain string required Domain name (Unique).
     * @bodyParam name string required Tenant/Company Name.
     * @bodyParam email string required Contact Email.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|unique:tenants|max:255',
            'domain' => 'required|string|unique:domains,domain|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        $tenant = Tenant::create([
            'id' => $validated['id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $tenant->domains()->create([
            'domain' => $validated['domain'],
        ]);

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant' => $tenant,
        ], 201);
    }
}
