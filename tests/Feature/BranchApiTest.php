<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BranchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_branches()
    {
        Branch::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/branches');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_branch()
    {
        $data = [
            'name' => 'New Branch',
            'address' => '123 Main St',
            'phone' => '08123456789',
            'is_central' => false,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/branches', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Branch');
            
        $this->assertDatabaseHas('branches', ['name' => 'New Branch']);
    }

    public function test_can_show_branch()
    {
        $branch = Branch::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/branches/{$branch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $branch->id);
    }

    public function test_can_update_branch()
    {
        $branch = Branch::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/branches/{$branch->id}", [
                'name' => 'Updated Branch',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Branch');

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Updated Branch']);
    }

    public function test_can_delete_branch()
    {
        $branch = Branch::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/branches/{$branch->id}");

        $response->assertStatus(204);
        
        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
    }
}
