<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class StockTransferTest extends TestCase
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

    public function test_can_create_pending_transfer()
    {
        $this->authenticate();
        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);
        $ingredient = Ingredient::create(['name' => 'Coffee', 'unit' => 'kg']);
        
        // Seed stock at Branch A
        BranchStock::create([
            'branch_id' => $branchA->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100
        ]);

        $payload = [
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'transfer_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 20
                ]
            ]
        ];

        $response = $this->postJson('http://test.localhost/api/stock-transfers', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'pending',
                'from_branch_id' => $branchA->id,
                'to_branch_id' => $branchB->id
            ]);
    }

    public function test_cannot_create_transfer_if_insufficient_stock()
    {
        $this->authenticate();
        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);
        $ingredient = Ingredient::create(['name' => 'Coffee', 'unit' => 'kg']);
        
        BranchStock::create([
            'branch_id' => $branchA->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 10 // Only 10
        ]);

        $payload = [
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'transfer_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 20 // Trying to send 20
                ]
            ]
        ];

        $response = $this->postJson('http://test.localhost/api/stock-transfers', $payload);
        $response->assertStatus(400);
    }

    public function test_shipping_transfer_deducts_source_stock()
    {
        $this->authenticate();
        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);
        $ingredient = Ingredient::create(['name' => 'Coffee', 'unit' => 'kg']);
        
        BranchStock::create([
            'branch_id' => $branchA->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100
        ]);

        $transfer = StockTransfer::create([
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'transfer_date' => now(),
            'status' => 'pending'
        ]);

        $transfer->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 20
        ]);

        $response = $this->postJson("http://test.localhost/api/stock-transfers/{$transfer->id}/ship");

        $response->assertStatus(200)
            ->assertJson(['status' => 'shipped']);

        $this->assertEquals(80, BranchStock::where('branch_id', $branchA->id)->first()->quantity);
    }

    public function test_receiving_transfer_increments_destination_stock()
    {
        $this->authenticate();
        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);
        $ingredient = Ingredient::create(['name' => 'Coffee', 'unit' => 'kg']);
        
        $transfer = StockTransfer::create([
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'transfer_date' => now(),
            'status' => 'shipped'
        ]);

        $transfer->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 20
        ]);

        $response = $this->postJson("http://test.localhost/api/stock-transfers/{$transfer->id}/receive");

        $response->assertStatus(200)
            ->assertJson(['status' => 'received']);

        $this->assertEquals(20, BranchStock::where('branch_id', $branchB->id)->first()->quantity);
    }
}
