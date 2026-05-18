<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_login_throttle_allows_repeated_development_retries(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->from(route('login'))
                ->post(route('login'), [
                    'email' => 'missing-admin@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors([
                    'email' => 'These credentials do not match our records.',
                ]);
        }
    }

    public function test_auth_clear_lockouts_command_clears_admin_login_limiter_key(): void
    {
        $key = md5('admin-login'.'127.0.0.1');

        RateLimiter::hit($key, 60);
        $this->assertSame(1, RateLimiter::attempts($key));

        Artisan::call('auth:clear-lockouts', ['ip' => '127.0.0.1']);

        $this->assertSame(0, RateLimiter::attempts($key));
    }
}
