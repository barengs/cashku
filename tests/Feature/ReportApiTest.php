<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_sales_report_returns_correct_data()
    {
        $branch = Branch::factory()->create();
        
        // Create 2 completed orders for this branch
        Order::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'completed',
            'total_amount' => 100000,
            'created_at' => now()
        ]);
        
        Order::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'completed',
            'total_amount' => 50000,
            'created_at' => now()
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/reports/sales?branch_id={$branch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('total_revenue', 150000)
            ->assertJsonPath('total_orders', 2);
    }

    public function test_inventory_report_returns_correct_value()
    {
        $branch = Branch::factory()->create();
        $ingredient = Ingredient::factory()->create(['cost_per_unit' => 1000]);
        
        BranchStock::create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/reports/inventory?branch_id={$branch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('total_stock_value', 50000); // 50 * 1000
    }

    public function test_cash_flow_report_returns_correct_data()
    {
        $branch = Branch::factory()->create();
        
        // Inflow: 100000 Sales
        Order::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'completed',
            'total_amount' => 100000
        ]);

        // Outflow: 20000 Expenses
        Expense::factory()->create([
            'branch_id' => $branch->id,
            'amount' => 20000
        ]);

        // Outflow: 30000 Purchases
        PurchaseOrder::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'received',
            'total_amount' => 30000
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/reports/cash-flow?branch_id={$branch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('inflow', 100000)
            ->assertJsonPath('outflow', 50000) // 20000 + 30000
            ->assertJsonPath('net_cash_flow', 50000); // 100000 - 50000
    }
}
