<?php

namespace Tests\Feature;

use App\Models\StockTransfer;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\BranchStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StockTransferApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_stock_transfers()
    {
        StockTransfer::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/stock-transfers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_stock_transfer()
    {
        $source = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $ingredient = Ingredient::factory()->create();
        
        // Ensure source has stock
        BranchStock::create([
            'branch_id' => $source->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100
        ]);
        
        $data = [
            'from_branch_id' => $source->id,
            'to_branch_id' => $destination->id,
            'note' => 'Transfer',
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 10
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/stock-transfers', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.note', 'Transfer');
            
        $this->assertDatabaseHas('stock_transfers', ['note' => 'Transfer']);
    }

    public function test_can_show_stock_transfer()
    {
        $stockTransfer = StockTransfer::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/stock-transfers/{$stockTransfer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $stockTransfer->id);
    }

    public function test_can_ship_stock_transfer()
    {
        $source = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $ingredient = Ingredient::factory()->create();
        
        // Create Initial Stock
        BranchStock::create([
            'branch_id' => $source->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100
        ]);

        $stockTransfer = StockTransfer::factory()->create([
            'from_branch_id' => $source->id,
            'to_branch_id' => $destination->id,
            'status' => 'pending'
        ]);
        
        $stockTransfer->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 10
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/stock-transfers/{$stockTransfer->id}/ship");

        $response->assertStatus(200)
             ->assertJsonPath('data.status', 'shipped');

        $this->assertDatabaseHas('stock_transfers', ['id' => $stockTransfer->id, 'status' => 'shipped']);
    }
}
