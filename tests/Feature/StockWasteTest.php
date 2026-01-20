<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\User;
use Tests\TestCase;

class StockWasteTest extends TestCase
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
    }

    public function test_can_create_waste_record_and_deduct_stock()
    {
        $this->authenticate();
        $branch = Branch::create(['name' => 'Main Branch']);
        $ingredient = Ingredient::create(['name' => 'Milk', 'unit' => 'ltr']);
        
        BranchStock::create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50
        ]);

        $payload = [
            'branch_id' => $branch->id,
            'waste_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 5,
                    'reason' => 'Expired'
                ]
            ]
        ];

        $response = $this->postJson('http://test.localhost/api/stock-wastes', $payload);

        $response->assertStatus(201);

        $this->assertEquals(45, BranchStock::where('branch_id', $branch->id)->first()->quantity);
        
        $this->assertDatabaseHas('stock_waste_items', [
            'ingredient_id' => $ingredient->id,
            'quantity' => 5,
            'reason' => 'Expired'
        ]);
    }
}
