<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected $google2fa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->google2fa = new Google2FA();
    }

    public function test_user_can_setup_2fa()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
                        ->postJson('/api/2fa/setup');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'secret',
                    'qr_code_url',
                    'message'
                ]);

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_user_cannot_setup_2fa_if_already_enabled()
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encrypt('test-secret')
        ]);

        $response = $this->actingAs($user)
                        ->postJson('/api/2fa/setup');

        $response->assertStatus(422)
                ->assertJson(['error' => '2FA is already enabled']);
    }

    public function test_user_can_verify_and_enable_2fa()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        $user->update(['two_factor_secret' => Crypt::encrypt($secret)]);

        $validCode = $this->google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)
                        ->postJson('/api/2fa/verify', [
                            'code' => $validCode
                        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => '2FA enabled successfully']);

        $this->assertTrue($user->fresh()->two_factor_enabled);
        $this->assertNotNull($user->fresh()->two_factor_enabled_at);
    }

    public function test_user_cannot_verify_with_invalid_code()
    {
        $user = User::factory()->create();
        $secret = $this->google2fa->generateSecretKey();
        $user->update(['two_factor_secret' => Crypt::encrypt($secret)]);

        $response = $this->actingAs($user)
                        ->postJson('/api/2fa/verify', [
                            'code' => '123456'
                        ]);

        $response->assertStatus(422)
                ->assertJson(['error' => 'Invalid verification code']);

        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_user_can_disable_2fa()
    {
        $secret = $this->google2fa->generateSecretKey();
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encrypt($secret),
            'two_factor_enabled_at' => now()
        ]);

        $validCode = $this->google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)
                        ->postJson('/api/2fa/disable', [
                            'code' => $validCode
                        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => '2FA disabled successfully']);

        $user = $user->fresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_enabled_at);
    }

    public function test_user_can_check_2fa_status()
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_enabled_at' => now()
        ]);

        $response = $this->actingAs($user)
                        ->getJson('/api/2fa/status');

        $response->assertStatus(200)
                ->assertJson([
                    'enabled' => true
                ])
                ->assertJsonStructure([
                    'enabled',
                    'enabled_at'
                ]);
    }
}