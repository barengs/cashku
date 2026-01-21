<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_suppliers()
    {
        Supplier::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/suppliers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_supplier()
    {
        $data = [
            'name' => 'New Supplier',
            'contact_person' => 'John Doe',
            'phone' => '08123456789',
            'email' => 'supplier@example.com',
            'address' => '123 Supplier St',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/suppliers', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Supplier');
            
        $this->assertDatabaseHas('suppliers', ['name' => 'New Supplier']);
    }

    public function test_can_show_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $supplier->id);
    }

    public function test_can_update_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/suppliers/{$supplier->id}", [
                'name' => 'Updated Supplier',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Supplier');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Updated Supplier']);
    }

    public function test_can_delete_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
