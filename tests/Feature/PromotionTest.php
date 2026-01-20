<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $dummyTenant = new \App\Models\Tenant();
        $dummyTenant->id = 'test_tenant';
        
        try {
            if (class_exists(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class)) {
                app(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class)->deleteDatabase($dummyTenant);
            }
        } catch (\Throwable $e) {}

        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations',
            '--realpath' => true,
            '--drop-views' => true,
        ]);

        $this->tenant = \App\Models\Tenant::create(['id' => 'test_tenant']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        tenancy()->initialize($this->tenant);
        
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => 'tenant', 
            '--path' => 'database/migrations/tenant',
            '--realpath' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->tenant) {
            $this->tenant->delete();
        }
        parent::tearDown();
    }

    protected function authenticate()
    {
        $user = User::factory()->create();
        $this->withToken(\PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($user));
    }

    public function test_can_create_promotion()
    {
        $this->authenticate();
        
        $payload = [
            'name' => 'Launch Discount',
            'type' => 'percentage',
            'value' => 10,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'is_active' => true
        ];

        $response = $this->postJson('http://test.localhost/api/promotions', $payload);

        $response->assertStatus(201)
            ->assertJson(['name' => 'Launch Discount']);
            
        $this->assertDatabaseHas('promotions', ['name' => 'Launch Discount']);
    }

    public function test_promotion_dates_validation()
    {
        $this->authenticate();
        
        $payload = [
            'name' => 'Invalid Discount',
            'type' => 'fixed_amount',
            'value' => 5000,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'), // End before start
        ];

        $response = $this->postJson('http://test.localhost/api/promotions', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
