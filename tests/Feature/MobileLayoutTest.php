<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_contains_mobile_app_navigation(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create(['name' => 'dashboard.view', 'label' => 'Dashboard']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('viewport-fit=cover', false)
            ->assertSee('mobile-bottom-nav', false)
            ->assertSee('data-bottom-menu', false)
            ->assertSee('data-bottom-theme', false)
            ->assertSee('data-bottom-logout', false)
            ->assertSee('mobile-drawer-head', false)
            ->assertSee('dashboard-sidebar', false)
            ->assertSee('Sembunyikan sidebar');
    }
}
