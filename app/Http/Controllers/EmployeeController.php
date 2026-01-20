<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'branch', 'profile'])->get();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'role' => 'required|string|exists:roles,name',
            // Profile fields
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string',
            'nik' => 'nullable|string',
            'photo' => 'nullable|image|max:2048', // Max 2MB
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos', 'public');
        }

        $user->profile()->create([
            'phone_number' => $validated['phone_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'nik' => $validated['nik'] ?? null,
            'photo' => $photoPath,
        ]);

        return response()->json($user->load('roles', 'branch', 'profile'), 201);
    }

    public function show($id)
    {
        $user = User::with(['roles', 'branch', 'profile'])->findOrFail($id);
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => ['email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'role' => 'nullable|string|exists:roles,name',
            // Profile fields
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string',
            'nik' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
        ]);

        $user->fill([
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'branch_id' => $validated['branch_id'] ?? $user->branch_id,
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if (!empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }
        
        $photoPath = $user->profile?->photo;
        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($photoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($photoPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('photos', 'public');
        }

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone_number' => $validated['phone_number'] ?? $user->profile?->phone_number,
                'address' => $validated['address'] ?? $user->profile?->address,
                'birth_date' => $validated['birth_date'] ?? $user->profile?->birth_date,
                'gender' => $validated['gender'] ?? $user->profile?->gender,
                'nik' => $validated['nik'] ?? $user->profile?->nik,
                'photo' => $photoPath,
            ]
        );

        return response()->json($user->load('roles', 'branch', 'profile'));
    }

    public function destroy($id)
    {
        User::destroy($id);
        return response()->json(null, 204);
    }
}
