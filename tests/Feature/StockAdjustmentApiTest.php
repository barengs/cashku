<?php

namespace Tests\Feature;

use App\Models\StockAdjustment;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StockAdjustmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_stock_adjustments()
    {
        StockAdjustment::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/stock-adjustments');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_stock_adjustment()
    {
        $branch = Branch::factory()->create();
        
        $data = [
            'branch_id' => $branch->id,
            'note' => 'Stock Correction',
            'adjustment_date' => now()->toDateString(),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/stock-adjustments', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.note', 'Stock Correction');
            
        $this->assertDatabaseHas('stock_adjustments', ['note' => 'Stock Correction']);
    }

    public function test_can_show_stock_adjustment()
    {
        $stockAdjustment = StockAdjustment::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/stock-adjustments/{$stockAdjustment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $stockAdjustment->id);
    }

    public function test_can_finalize_stock_adjustment()
    {
        $stockAdjustment = StockAdjustment::factory()->create(['status' => 'draft']); // use draft to allow finalize based on controller check
        $ingredient = Ingredient::factory()->create();
        
        // Add items to adjustment
        $stockAdjustment->items()->create([
            'ingredient_id' => $ingredient->id,
            'system_stock' => 10,
            'actual_stock' => 12,
            'difference' => 2
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/stock-adjustments/{$stockAdjustment->id}/finalize");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('stock_adjustments', ['id' => $stockAdjustment->id, 'status' => 'completed']);
    }
}
