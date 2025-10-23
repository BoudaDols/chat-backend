<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'user' => ['id', 'name', 'email'],
                    'token'
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe'
        ]);
    }

    public function test_user_cannot_register_with_duplicate_email()
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'user' => ['id', 'name', 'email'],
                    'token'
                ]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
                ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_rate_limiting_on_login()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123')
        ]);

        // Make 6 failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'john@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // 7th attempt should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }
}