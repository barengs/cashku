<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_promotions()
    {
        Promotion::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promotions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_promotion()
    {
        $data = [
            'name' => 'Discount 10%',
            'type' => 'percentage',
            'value' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/promotions', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Discount 10%');
            
        $this->assertDatabaseHas('promotions', ['name' => 'Discount 10%']);
    }

    public function test_can_show_promotion()
    {
        $promotion = Promotion::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/promotions/{$promotion->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $promotion->id);
    }

    public function test_can_update_promotion()
    {
        $promotion = Promotion::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/promotions/{$promotion->id}", [
                'name' => 'Updated Promotion',
                'value' => 20
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Promotion');

        $this->assertDatabaseHas('promotions', ['id' => $promotion->id, 'name' => 'Updated Promotion']);
    }

    public function test_can_delete_promotion()
    {
        $promotion = Promotion::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/promotions/{$promotion->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    }
}
