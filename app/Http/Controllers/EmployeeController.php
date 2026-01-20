<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    /**
     * @group Employee Management
     * @description List all employees (users).
     */
    public function index()
    {
        try {
            $employees = User::with(['roles', 'branch', 'profile'])->get();
            return UserResource::collection($employees);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Employee Management
     * @description Create a new employee, assign role, and optionally create profile & photo.
     * @bodyParam name string required Name of the employee. Example: John Doe
     * @bodyParam email string required Email. Example: john@example.com
     * @bodyParam password string required Password. Example: secret
     * @bodyParam role string required Role name. Example: Kasir
     * @bodyParam branch_id string required Branch ID (UUID).
     * @bodyParam phone string Optional phone number.
     * @bodyParam address string Optional address.
     * @bodyParam photo file Optional profile photo.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'role' => 'required|exists:roles,name',
                'branch_id' => 'required|exists:branches,id',
                // Profile fields
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'photo' => 'nullable|image|max:2048'
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'branch_id' => $validated['branch_id']
            ]);

            $user->assignRole($validated['role']);

            // Handle Photo Upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('profile-photos', 'public');
            }

            // Create Profile
            $user->profile()->create([
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'photo' => $photoPath
            ]);

            return new UserResource($user->load(['roles', 'branch', 'profile']));
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Employee Management
     * @description Get employee details.
     */
    public function show($id)
    {
        try {
            $user = User::with(['roles', 'branch', 'profile'])->findOrFail($id);
            return new UserResource($user);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Employee Management
     * @description Update employee details.
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
                'password' => 'nullable|string|min:8',
                'role' => 'required|exists:roles,name',
                'branch_id' => 'required|exists:branches,id',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'photo' => 'nullable|image|max:2048'
            ]);

            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'branch_id' => $validated['branch_id']
            ];

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            $user->syncRoles([$validated['role']]);

            // Handle Photo Upload
            $photoPath = $user->profile?->photo;
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                    Storage::disk('public')->delete($photoPath);
                }
                $photoPath = $request->file('photo')->store('profile-photos', 'public');
            }

            // Update or Create Profile
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'photo' => $photoPath
                ]
            );

            return new UserResource($user->load(['roles', 'branch', 'profile']));
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        User::destroy($id);
        return response()->json(null, 204);
    }
}
