<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Tests\TestCase;

class ProductTest extends TestCase
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

    public function test_can_manage_product_categories()
    {
        $this->authenticate();
        
        // Create
        $response = $this->postJson('http://test.localhost/api/product-categories', [
            'name' => 'Beverages',
            'description' => 'Drinks'
        ]);
        $response->assertStatus(201)->assertJson(['name' => 'Beverages']);
        
        // List
        $this->getJson('http://test.localhost/api/product-categories')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Beverages']);
    }

    public function test_can_create_product_with_recipe_and_calculate_cogs()
    {
        $this->authenticate();
        $category = ProductCategory::create(['name' => 'Food']);
        $ing1 = Ingredient::create(['name' => 'Bun', 'unit' => 'pcs', 'cost_per_unit' => 2000]);
        $ing2 = Ingredient::create(['name' => 'Patty', 'unit' => 'pcs', 'cost_per_unit' => 5000]);

        $payload = [
            'category_id' => $category->id,
            'name' => 'Burger',
            'price' => 15000,
            'recipes' => [
                ['ingredient_id' => $ing1->id, 'quantity' => 1], // Cost: 2000
                ['ingredient_id' => $ing2->id, 'quantity' => 1]  // Cost: 5000
            ]
        ];

        $response = $this->postJson('http://test.localhost/api/products', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Burger',
                'cogs' => 7000 // 2000 + 5000
            ]);

        $this->assertDatabaseHas('products', ['name' => 'Burger']);
        $this->assertDatabaseHas('product_recipes', ['ingredient_id' => $ing1->id]);
    }

    public function test_updating_product_recipe_updates_database()
    {
        $this->authenticate();
        $product = Product::create(['name' => 'Coffee', 'price' => 10000]);
        $ing = Ingredient::create(['name' => 'Beans', 'unit' => 'gr']);

        // Update to add recipe
        $response = $this->putJson("http://test.localhost/api/products/{$product->id}", [
            'name' => 'Black Coffee', // Name change
            'recipes' => [
                ['ingredient_id' => $ing->id, 'quantity' => 20]
            ]
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('products', ['name' => 'Black Coffee']);
        $this->assertDatabaseHas('product_recipes', [
            'product_id' => $product->id,
            'ingredient_id' => $ing->id,
            'quantity' => 20
        ]);
    }
}
