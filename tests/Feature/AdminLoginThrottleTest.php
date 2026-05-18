<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_login_throttle_allows_repeated_development_retries(): void
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
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

    public function test_dev_reset_admin_command_resets_local_admin_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@kiosk.test',
            'password' => Hash::make('old-password'),
            'role' => 'admin',
            'is_active' => false,
            'must_change_password' => true,
            'google2fa_enabled' => true,
        ]);

        Artisan::call('dev:reset-admin');

        $output = Artisan::output();
        $admin = User::where('email', 'admin@kiosk.test')->firstOrFail();

        $this->assertStringContainsString('Local admin ready', $output);
        $this->assertStringContainsString('Email: admin@kiosk.test', $output);
        $this->assertStringContainsString('Password: admin123', $output);
        $this->assertTrue(Hash::check('admin123', $admin->password));
        $this->assertTrue($admin->is_active);
        $this->assertFalse($admin->must_change_password);
        $this->assertFalse($admin->google2fa_enabled);
    }

    public function test_remember_me_sets_remember_token_and_local_session_flag(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@kiosk.test',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'remember_token' => null,
            'is_active' => true,
            'must_change_password' => false,
            'google2fa_enabled' => false,
        ]);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'admin@kiosk.test',
                'password' => 'admin123',
                'remember' => '1',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->refresh()->remember_token);
        $this->assertTrue(session('admin_remembered_login'));

        $this->get(route('admin.waitlist'))
            ->assertOk()
            ->assertSee('Waitlist Management');
    }

    public function test_local_dev_login_shortcut_authenticates_admin(): void
    {
        $this->post(route('login.dev'))
            ->assertRedirect(route('admin.dashboard'));

        $admin = User::where('email', 'admin@kiosk.test')->firstOrFail();

        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Hash::check('admin123', $admin->password));
        $this->assertTrue($admin->is_active);
        $this->assertFalse($admin->must_change_password);
    }
}
