<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Exception;

/**
 * @group Role Management
 * @description APIs for managing roles and permissions.
 */
class RoleController extends Controller
{
    /**
     * List Roles
     * @description Get a list of roles with their permissions.
     */
    public function index()
    {
        try {
            $roles = Role::with('permissions')->get();
            return RoleResource::collection($roles);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
