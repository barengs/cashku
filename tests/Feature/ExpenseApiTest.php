<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_expenses()
    {
        Expense::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/expenses');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_expense()
    {
        $branch = Branch::factory()->create();
        
        $data = [
            'branch_id' => $branch->id,
            'name' => 'Electricity Bill',
            'category' => 'Utilities',
            'amount' => 100.00,
            'note' => 'Electricity bill',
            'date' => now()->toDateString(),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/expenses', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Electricity Bill');
            
        $this->assertDatabaseHas('expenses', ['name' => 'Electricity Bill']);
    }

    public function test_can_show_expense()
    {
        $expense = Expense::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $expense->id);
    }

    public function test_can_update_expense()
    {
        $expense = Expense::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/expenses/{$expense->id}", [
                'name' => 'Updated Name',
                'category' => 'Updated Category',
                'amount' => 150.00,
                'date' => now()->toDateString(), // Date is often required
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'name' => 'Updated Name']);
    }

    public function test_can_delete_expense()
    {
        $expense = Expense::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/expenses/{$expense->id}");

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }
}
