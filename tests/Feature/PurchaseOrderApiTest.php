<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_purchase_orders()
    {
        PurchaseOrder::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/purchase-orders');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_purchase_order()
    {
        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $ingredient = Ingredient::factory()->create();
        
        $data = [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 10,
                    'unit_price' => 5000
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/purchase-orders', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
            
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier->id]);
    }

    public function test_can_show_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/purchase-orders/{$purchaseOrder->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $purchaseOrder->id);
    }

    public function test_can_approve_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => 'pending']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/purchase-orders/{$purchaseOrder->id}/approve");

        $response->assertStatus(200)
             ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => 'approved']);
    }
}
