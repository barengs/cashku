<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // call the tenant seeder to create the tenant
        $this->call(CoreDataSeeder::class);
    }
}
