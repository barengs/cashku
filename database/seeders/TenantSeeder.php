<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;

class TenantSeeder extends Seeder
{
    public function run()
    {
        $tenant = Tenant::firstOrCreate(['id' => 'barengs']);
        $tenant->domains()->firstOrCreate(['domain' => 'barengs.localhost']);

        // Run the Tenant-specific seeder for this tenant
        $tenant->run(function () {
            $this->call(TenantDatabaseSeeder::class);
        });
    }
}
