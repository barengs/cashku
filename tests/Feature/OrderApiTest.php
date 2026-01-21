<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Table;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_orders()
    {
        Order::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_order()
    {
        $branch = Branch::factory()->create();
        $table = Table::factory()->create(['branch_id' => $branch->id, 'status' => 'available']);
        $product = Product::factory()->create();
        $shift = Shift::factory()->create(['branch_id' => $branch->id, 'user_id' => $this->user->id, 'status' => 'open']);
        
        $data = [
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'customer_name' => 'Rofi',
            'type' => 'dine_in',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'note' => 'No spicy'
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/orders', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_name', 'Rofi');
            
        $this->assertDatabaseHas('orders', ['customer_name' => 'Rofi']);
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'status' => 'occupied']);
    }

    public function test_can_show_order()
    {
        $order = Order::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_can_complete_order_payment()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $shift = Shift::factory()->create(['branch_id' => $branch->id, 'user_id' => $user->id, 'status' => 'open']);
        
        // Ensure user is logged in as the one who has open shift or handled by controller
        $token = auth('api')->login($user);

        $order = Order::factory()->create([
            'branch_id' => $branch->id, 
            'shift_id' => $shift->id,
            'status' => 'pending', 
            'total_amount' => 100000
        ]);

        $data = [
            'payment_method' => 'cash',
            'amount' => 100000,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/orders/{$order->id}/pay", $data);


        $response->assertStatus(200)
            ->assertJsonPath('data.payment_status', 'paid');
        
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
    }
}
