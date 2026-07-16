<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/register/customer', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'role' => UserRole::Customer->value,
        ]);
    }

    public function test_vendor_can_register_and_creates_pending_vendor_record(): void
    {
        $response = $this->postJson('/api/register/vendor', [
            'name' => 'John Seller',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'store_name' => 'Johns Gadgets',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'vendor', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => UserRole::Vendor->value,
        ]);

        $this->assertDatabaseHas('vendors', [
            'store_name' => 'Johns Gadgets',
            'slug' => 'johns-gadgets',
            'status' => VendorStatus::Pending->value,
        ]);
    }

    public function test_vendor_registration_generates_unique_slug_on_collision(): void
    {
        $this->postJson('/api/register/vendor', [
            'name' => 'First Seller',
            'email' => 'first@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'store_name' => 'Cool Store',
        ])->assertStatus(201);

        $response = $this->postJson('/api/register/vendor', [
            'name' => 'Second Seller',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'store_name' => 'Cool Store',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('vendors', ['slug' => 'cool-store']);
        $this->assertDatabaseHas('vendors', ['slug' => 'cool-store-1']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.com']);

        $response = $this->postJson('/api/register/customer', [
            'name' => 'Someone',
            'email' => 'dupe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }
}