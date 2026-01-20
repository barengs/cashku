<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class StockAdjustmentTest extends TestCase
{
    // Removing RefreshDatabase to avoid conflicts with Tenancy in SQLite
    // use RefreshDatabase;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Cleanup leftover tenant database using the manager
        $dummyTenant = new \App\Models\Tenant();
        $dummyTenant->id = 'test_tenant';
        
        try {
            if (class_exists(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class)) {
                app(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class)->deleteDatabase($dummyTenant);
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        // Migrate central
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations',
            '--realpath' => true,
            '--drop-views' => true,
        ]);

        // Create Tenant
        $this->tenant = \App\Models\Tenant::create(['id' => 'test_tenant']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);

        // Initialize Tenancy
        tenancy()->initialize($this->tenant);
        
        // Migrate Tenant
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

    public function test_can_create_draft_adjustment()
    {
        $this->authenticate();
        $branch = \App\Models\Branch::create(['name' => 'Main Branch']);
        
        $payload = [
            'branch_id' => $branch->id,
            'adjustment_date' => now()->format('Y-m-d'),
            'note' => 'Monthly Stock Opname'
        ];

        $response = $this->postJson('http://test.localhost/api/stock-adjustments', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'draft',
                'note' => 'Monthly Stock Opname',
                'branch_id' => $branch->id
            ]);
            
        $this->assertDatabaseHas('stock_adjustments', [
            'note' => 'Monthly Stock Opname',
            'status' => 'draft'
        ]);
    }

    public function test_can_update_adjustment_with_items_and_calculate_difference()
    {
        $this->authenticate();
        $branch = \App\Models\Branch::create(['name' => 'Main Branch']);
        $ingredient = Ingredient::create(['name' => 'Flour', 'unit' => 'kg']);
        
        \App\Models\BranchStock::create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 10
        ]);

        $adjustment = StockAdjustment::create([
            'branch_id' => $branch->id,
            'adjustment_date' => now(),
            'status' => 'draft'
        ]);

        $payload = [
            'adjustment_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'actual_stock' => 8 // Difference should be -2
                ]
            ]
        ];

        $response = $this->putJson("http://test.localhost/api/stock-adjustments/{$adjustment->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('stock_adjustment_items', [
            'stock_adjustment_id' => $adjustment->id,
            'ingredient_id' => $ingredient->id,
            'system_stock' => 10,
            'actual_stock' => 8,
            'difference' => -2
        ]);
    }

    public function test_finalize_adjustment_updates_inventory_stock()
    {
        $this->authenticate();
        $branch = \App\Models\Branch::create(['name' => 'Main Branch']);
        $ingredient = Ingredient::create(['name' => 'Sugar', 'unit' => 'kg']);
        
        \App\Models\BranchStock::create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50
        ]);

        $adjustment = StockAdjustment::create([
            'branch_id' => $branch->id,
            'adjustment_date' => now(),
            'status' => 'draft'
        ]);

        $adjustment->items()->create([
            'ingredient_id' => $ingredient->id,
            'system_stock' => 50,
            'actual_stock' => 45, // Found less
            'difference' => -5
        ]);

        $response = $this->postJson("http://test.localhost/api/stock-adjustments/{$adjustment->id}/finalize");

        $response->assertStatus(200)
            ->assertJson(['status' => 'completed']);

        // Check Ingredient Stock refreshed
        $this->assertEquals(45, \App\Models\BranchStock::where('branch_id', $branch->id)->first()->quantity);
    }

    public function test_cannot_edit_completed_adjustment()
    {
        $this->authenticate();
        $branch = \App\Models\Branch::create(['name' => 'Main Branch']);
        $adjustment = StockAdjustment::create([
            'branch_id' => $branch->id,
            'adjustment_date' => now(),
            'status' => 'completed'
        ]);

        $response = $this->putJson("http://test.localhost/api/stock-adjustments/{$adjustment->id}", [
            'note' => 'Trying to edit'
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Cannot update a completed adjustment']);
    }
}
