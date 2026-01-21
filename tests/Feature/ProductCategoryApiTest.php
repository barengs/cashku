<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_categories()
    {
        ProductCategory::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/product-categories');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_category()
    {
        $data = [
            'name' => 'Beverages',
            'description' => 'Drinks',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/product-categories', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Beverages');
            
        $this->assertDatabaseHas('product_categories', ['name' => 'Beverages']);
    }

    public function test_can_show_category()
    {
        $category = ProductCategory::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/product-categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_can_update_category()
    {
        $category = ProductCategory::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/product-categories/{$category->id}", [
                'name' => 'Updated Category',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Category');

        $this->assertDatabaseHas('product_categories', ['id' => $category->id, 'name' => 'Updated Category']);
    }

    public function test_can_delete_category()
    {
        $category = ProductCategory::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/product-categories/{$category->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }
}
