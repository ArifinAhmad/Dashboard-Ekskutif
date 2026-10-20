<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Simrs\ReadOnlySimrs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class DashboardAutoRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_ten_minute_auto_refresh_interval(): void
    {
        config(['simrs.dashboard_refresh_seconds' => 600, 'simrs.cache_store' => 'array']);
        Cache::store('array')->flush();
        $user = User::factory()->create();
        $permission = Permission::query()->create(['name' => 'dashboard.view', 'label' => 'Dashboard']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $this->mock(ReadOnlySimrs::class, fn (MockInterface $mock) => $mock->shouldReceive('healthy')->zeroOrMoreTimes()->andReturnFalse());

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-dashboard-refresh="600"', false)
            ->assertSee('data-refresh-countdown', false)
            ->assertSee('data-auto-scroll-toggle', false)
            ->assertSee('data-auto-scroll-status', false)
            ->assertSee('10:00');
    }
}
