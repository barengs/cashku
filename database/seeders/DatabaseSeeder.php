<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Standard Roles for POS System
        $roles = [
            'Owner',
            'Admin Pusat',
            'Manajer Cabang',
            'Kasir',
            'Karyawan Dapur',
        ];

        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        // Example Owner User
        $user = User::firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Owner Cafe',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );

        $user->assignRole('Owner');
    }
}
