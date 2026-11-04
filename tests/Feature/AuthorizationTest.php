<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertForbidden();

        $permission = Permission::query()->create(['name' => 'dashboard.view', 'label' => 'Dashboard']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_security_headers_are_attached(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' https://static.cloudflareinsights.com; connect-src 'self' https://cloudflareinsights.com; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    }

    public function test_super_admin_has_every_permission_without_permission_pivot(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'super-admin', 'label' => 'Super Admin']);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('permission.yang.baru.dibuat'));
    }
}
