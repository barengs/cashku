<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_sales_report_aggregation()
    {
        $this->authenticate();
        $branch = Branch::create(['name' => 'Main Branch']);

        // Create 2 Sales
        Order::create(['branch_id' => $branch->id, 'total_amount' => 50000, 'status' => 'completed']);
        Order::create(['branch_id' => $branch->id, 'total_amount' => 50000, 'status' => 'completed']);
        Order::create(['branch_id' => $branch->id, 'total_amount' => 10000, 'status' => 'pending']); // Should ignore

        $response = $this->getJson('http://test.localhost/api/reports/sales');

        $response->assertStatus(200)
            ->assertJson([
                'total_revenue' => 100000,
                'total_orders' => 2,
                'average_order_value' => 50000
            ]);
    }

    public function test_profit_report_calculation()
    {
        $this->authenticate();
        $branch = Branch::create(['name' => 'Main Branch']);
        $ing = Ingredient::create(['name' => 'Bean', 'unit' => 'gr', 'cost_per_unit' => 10]); // 10 per gr
        
        $product = Product::create(['name' => 'Coffee', 'price' => 20000]);
        $product->recipes()->create(['ingredient_id' => $ing->id, 'quantity' => 20]); // Cost = 20 * 10 = 200

        $order = Order::create(['branch_id' => $branch->id, 'total_amount' => 40000, 'status' => 'completed']);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 20000,
            'subtotal' => 40000
        ]);

        // Revenue = 40000
        // COGS = 2 items * 200 cost = 400
        // Profit = 39600

        $response = $this->getJson('http://test.localhost/api/reports/profit');

        $response->assertStatus(200)
            ->assertJson([
                'revenue' => 40000,
                'cogs' => 400,
                'gross_profit' => 39600
            ]);
    }
}
