<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class PurchaseOrderTest extends TestCase
{
    // Removing RefreshDatabase to avoid conflicts with Tenancy connection switching in SQLite
    // use RefreshDatabase;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Cleanup leftover tenant database using the manager to ensure correct path
        $dummyTenant = new \App\Models\Tenant();
        $dummyTenant->id = 'test_tenant';
        
        try {
            if (class_exists(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class)) {
                app(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class)->deleteDatabase($dummyTenant);
            }
        } catch (\Throwable $e) {
            // Ignore if it fails
        }

        // Migrate central (users table for auth)
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
        // Create user in the tenant context or central context depending on your app
        // Based on routes/tenant.php having auth:api, it usually uses the tenant users table if independent, or central if shared.
        // Assuming tenant users table based on migrations: 0001_01_01_000000_create_users_table.php was in tenant folder? 
        // Wait, list_dir showed 0001...create_users_table.php in database/migrations/tenant. So users are inside tenant.
        
        $user = User::factory()->create();
        $this->withToken(\PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($user));
    }

    public function test_can_create_purchase_order()
    {
        $this->authenticate();
        $supplier = Supplier::create(['name' => 'Test Supplier', 'phone' => '123', 'address' => 'Test Address']);
        $ingredient = Ingredient::create(['name' => 'Test Ingredient', 'unit' => 'kg', 'current_stock' => 10]);

        $payload = [
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 'pending',
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 10,
                    'unit_price' => 5000
                ]
            ]
        ];

        // Send request with Tenant Domain
        $response = $this->postJson('http://test.localhost/api/purchase-orders', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'total_amount', 'items']);

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'total_amount' => 50000
        ]);
    }

    public function test_can_update_purchase_order_items_sync()
    {
        $this->authenticate();
        $supplier = Supplier::create(['name' => 'Supplier A', 'phone' => '123', 'address' => 'Addr']);
        $ingredient1 = Ingredient::create(['name' => 'Ing 1', 'unit' => 'kg', 'current_stock' => 0]);
        $ingredient2 = Ingredient::create(['name' => 'Ing 2', 'unit' => 'kg', 'current_stock' => 0]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now(),
            'status' => 'pending',
            'total_amount' => 10000
        ]);

        $po->items()->create([
            'ingredient_id' => $ingredient1->id,
            'quantity' => 2,
            'unit_price' => 5000,
            'subtotal' => 10000
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 'pending',
            'items' => [
                [
                    'ingredient_id' => $ingredient2->id,
                    'quantity' => 5,
                    'unit_price' => 2000 
                ]
            ]
        ];

        $response = $this->putJson("http://test.localhost/api/purchase-orders/{$po->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'ingredient_id' => $ingredient1->id
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 5
        ]);
        
        $this->assertEquals(10000, $po->fresh()->total_amount);
    }

    public function test_can_receive_purchase_order_and_update_stock()
    {
        $this->authenticate();
        $supplier = Supplier::create(['name' => 'Supplier A', 'phone' => '123', 'address' => 'Addr']);
        $ingredient = Ingredient::create(['name' => 'Ing 1', 'unit' => 'kg', 'current_stock' => 10]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now(),
            'status' => 'pending',
            'total_amount' => 50000
        ]);

        $po->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 20,
            'unit_price' => 2500,
            'subtotal' => 50000
        ]);

        $response = $this->postJson("http://test.localhost/api/purchase-orders/{$po->id}/receive");

        $response->assertStatus(200)
            ->assertJson(['status' => 'received']);

        $this->assertEquals(30, $ingredient->fresh()->current_stock);
    }

    public function test_cannot_update_received_order()
    {
        $this->authenticate();
        $supplier = Supplier::create(['name' => 'Supplier A', 'phone' => '123', 'address' => 'Addr']);
        
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now(),
            'status' => 'received',
            'total_amount' => 0
        ]);

        $response = $this->putJson("http://test.localhost/api/purchase-orders/{$po->id}", [
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 'pending'
        ]);

        $response->assertStatus(400)
             ->assertJson(['error' => 'Cannot update a received order']);
    }
}
