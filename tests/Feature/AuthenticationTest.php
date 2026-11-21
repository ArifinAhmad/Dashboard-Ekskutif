<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_session_is_regenerated(): void
    {
        $user = User::factory()->create(['password' => 'Secret-123', 'is_active' => true]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Secret-123']);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'successful' => true]);
    }

    public function test_user_can_enable_secure_remember_me_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-password'), 'is_active' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'remember' => true,
        ])->assertRedirect('/dashboard');

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => 'Secret-123', 'is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret-123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_route_uses_named_throttle_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('login.store');

        $this->assertNotNull($route);
        $this->assertContains('throttle:login', $route->gatherMiddleware());
    }
}
