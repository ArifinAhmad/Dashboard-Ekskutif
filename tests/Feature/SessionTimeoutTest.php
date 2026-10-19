<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_exposes_thirty_minute_idle_timeout(): void
    {
        config(['session.lifetime' => 30]);
        $user = User::factory()->create();
        $permission = Permission::query()->create(['name' => 'dashboard.view', 'label' => 'Dashboard']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-session-lifetime="30"', false)
            ->assertSee('data-session-logout', false);
    }
}
