<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_never_exposes_the_reset_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $known = $this->postJson('/api/v1/platform/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/api/v1/platform/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertOk()->assertJsonMissingPath('reset_url');
        $unknown->assertOk()->assertJsonMissingPath('reset_url');
        $this->assertSame($known->json('message'), $unknown->json('message'));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/platform/login', [
                'email' => 'a@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/platform/login', [
            'email' => 'a@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_login_returns_a_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/v1/platform/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->assertJsonStructure(['data' => ['user', 'token']]);
    }
}
