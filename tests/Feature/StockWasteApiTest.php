<?php

namespace Tests\Feature;

use App\Models\StockWaste;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StockWasteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_stock_wastes()
    {
        StockWaste::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/stock-wastes');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_stock_waste()
    {
        $branch = Branch::factory()->create();
        $ingredient = Ingredient::factory()->create();
        
        $data = [
            'branch_id' => $branch->id,
            'note' => 'Spoiled',
            'waste_date' => now()->toDateString(),
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 5,
                    'reason' => 'Expired'
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/stock-wastes', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.note', 'Spoiled');
            
        $this->assertDatabaseHas('stock_wastes', ['note' => 'Spoiled']);
    }

    public function test_can_show_stock_waste()
    {
        $stockWaste = StockWaste::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/stock-wastes/{$stockWaste->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $stockWaste->id);
    }
}
