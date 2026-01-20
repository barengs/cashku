<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Branch;
use Tests\TestCase;

class TableTest extends TestCase
{
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $dummyTenant = new \App\Models\Tenant();
        $dummyTenant->id = 'test_tenant';
        
        try {
            if (class_exists(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class)) {
                app(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class)->deleteDatabase($dummyTenant);
            }
        } catch (\Throwable $e) {}

        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations',
            '--realpath' => true,
            '--drop-views' => true,
        ]);

        $this->tenant = \App\Models\Tenant::create(['id' => 'test_tenant']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        tenancy()->initialize($this->tenant);
        
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => 'tenant', 
            '--path' => 'database/migrations/tenant',
            '--realpath' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->tenant) {
            $this->tenant->delete();
        }
        parent::tearDown();
    }

    protected function authenticate()
    {
        $user = User::factory()->create();
        $this->withToken(\PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($user));
        return $user;
    }

    public function test_can_create_table()
    {
        $this->authenticate();
        $branch = Branch::create(['name' => 'Main Branch']);

        $response = $this->postJson('http://test.localhost/api/tables', [
            'branch_id' => $branch->id,
            'number' => 'T01',
            'capacity' => 4,
            'status' => 'available'
        ]);

        $response->assertStatus(201)
            ->assertJson(['data' => ['number' => 'T01', 'capacity' => 4]]);
            
        $this->assertDatabaseHas('tables', ['number' => 'T01']);
    }

    public function test_cannot_create_duplicate_table_number_in_same_branch()
    {
        $this->authenticate();
        $branch = Branch::create(['name' => 'Main Branch']);
        \App\Models\Table::create([
            'branch_id' => $branch->id,
            'number' => 'T01',
            'capacity' => 2
        ]);

        $response = $this->postJson('http://test.localhost/api/tables', [
            'branch_id' => $branch->id,
            'number' => 'T01',
            'capacity' => 4
        ]);

        $response->assertStatus(422); // We now check for duplicates and return 422
    }

    public function test_can_update_table_status()
    {
        $this->authenticate();
        $branch = Branch::create(['name' => 'Main Branch']);
        $table = \App\Models\Table::create([
            'branch_id' => $branch->id,
            'number' => 'T02',
            'capacity' => 2
        ]);

        $response = $this->putJson("http://test.localhost/api/tables/{$table->id}", [
            'status' => 'occupied'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('occupied', $table->fresh()->status);
    }
}
