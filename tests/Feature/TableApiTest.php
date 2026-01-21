<?php

namespace Tests\Feature;

use App\Models\Table;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TableApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_tables()
    {
        Table::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/tables');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_table()
    {
        $branch = Branch::factory()->create();
        
        $data = [
            'branch_id' => $branch->id,
            'number' => '10A',
            'capacity' => 4,
            'status' => 'available',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/tables', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.number', '10A');
            
        $this->assertDatabaseHas('tables', ['number' => '10A']);
    }

    public function test_can_show_table()
    {
        $table = Table::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/tables/{$table->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $table->id);
    }

    public function test_can_update_table()
    {
        $table = Table::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/tables/{$table->id}", [
                'number' => '10B',
                'capacity' => 6,
                'status' => 'occupied',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.number', '10B');

        $this->assertDatabaseHas('tables', ['id' => $table->id, 'number' => '10B']);
    }

    public function test_can_delete_table()
    {
        $table = Table::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/tables/{$table->id}");

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('tables', ['id' => $table->id]);
    }
}
