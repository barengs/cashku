<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ShiftApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_shifts()
    {
        Shift::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/shifts');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_open_shift()
    {
        $branch = Branch::factory()->create();
        
        $data = [
            'branch_id' => $branch->id,
            'starting_cash' => 100000,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/shifts/open', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'open');
            
        $this->assertDatabaseHas('shifts', ['starting_cash' => 100000, 'status' => 'open']);
    }

    public function test_can_show_shift()
    {
        $shift = Shift::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/shifts/{$shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $shift->id);
    }

    public function test_can_close_shift()
    {
        // Must be the same user
        $shift = Shift::factory()->create(['user_id' => $this->user->id, 'status' => 'open', 'branch_id' => Branch::factory()->create()->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/shifts/{$shift->id}/close", [
                'actual_cash' => 150000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.actual_cash', '150000.00');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'status' => 'closed', 'actual_cash' => 150000]);
    }
}
