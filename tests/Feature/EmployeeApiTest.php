<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Branch;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Spatie permission requires guard initialization sometimes in tests
        $this->app['cache']->forget('spatie.permission.cache');

        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_can_list_employees()
    {
        User::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/employees');

        // user + 3 employees = 4
        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_can_create_employee()
    {
        $branch = Branch::factory()->create();
        Role::create(['name' => 'staff', 'guard_name' => 'api']);
        
        $data = [
            'name' => 'New Employee',
            'email' => 'employee@example.com',
            'password' => 'password',
            'branch_id' => $branch->id,
            'role' => 'staff',
        ];
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/employees', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Employee');
            
        $this->assertDatabaseHas('users', ['email' => 'employee@example.com']);
    }

    public function test_can_show_employee()
    {
        $employee = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $employee->id);
    }

    public function test_can_delete_employee()
    {
        $employee = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
    }
}
