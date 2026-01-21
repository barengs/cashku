<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class IngredientApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_ingredients()
    {
        Ingredient::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/ingredients');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_ingredient()
    {
        $data = [
            'name' => 'New Ingredient',
            'unit' => 'kg',
            'cost_per_unit' => 50.00,
            'minimum_stock' => 10,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/ingredients', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Ingredient');
            
        $this->assertDatabaseHas('ingredients', ['name' => 'New Ingredient']);
    }

    public function test_can_show_ingredient()
    {
        $ingredient = Ingredient::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/ingredients/{$ingredient->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $ingredient->id);
    }

    public function test_can_update_ingredient()
    {
        $ingredient = Ingredient::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/ingredients/{$ingredient->id}", [
                'name' => 'Updated Ingredient',
                'cost_per_unit' => 60.00,
                'minimum_stock' => 15, // Provide all fields if using update
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Ingredient');

        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'name' => 'Updated Ingredient']);
    }

    public function test_can_delete_ingredient()
    {
        $ingredient = Ingredient::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/ingredients/{$ingredient->id}");

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('ingredients', ['id' => $ingredient->id]);
    }
}
