<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Branch;
use Tests\TestCase;

class ExpenseTest extends TestCase
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

    public function test_can_create_expense()
    {
        $this->authenticate();
        $branch = Branch::create(['name' => 'Main Branch']);

        $response = $this->postJson('http://test.localhost/api/expenses', [
            'branch_id' => $branch->id,
            'name' => 'Electricity Bill',
            'amount' => 500000,
            'date' => now()->format('Y-m-d'),
            'category' => 'utilities'
        ]);

        $response->assertStatus(201)
            ->assertJson(['data' => ['name' => 'Electricity Bill', 'amount' => 500000]]);
            
        $this->assertDatabaseHas('expenses', ['name' => 'Electricity Bill']);
    }

    public function test_can_filter_expenses_by_date()
    {
        $this->authenticate();
        $branch = Branch::create(['name' => 'Main Branch']);
        \App\Models\Expense::create([
            'branch_id' => $branch->id,
            'name' => 'Old Exp',
            'amount' => 100,
            'date' => '2023-01-01'
        ]);
        \App\Models\Expense::create([
            'branch_id' => $branch->id,
            'name' => 'New Exp',
            'amount' => 100,
            'date' => '2023-02-01'
        ]);

        $response = $this->getJson('http://test.localhost/api/expenses?start_date=2023-02-01&end_date=2023-02-28');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'New Exp']);
    }
}
