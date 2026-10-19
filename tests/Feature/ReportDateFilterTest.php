<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Simrs\ReadOnlySimrs;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ReportDateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_defaults_to_the_current_day(): void
    {
        CarbonImmutable::setTestNow('2026-09-23 15:00:00');
        $user = User::factory()->create();
        $permission = Permission::query()->create(['name' => 'service.view', 'label' => 'Pelayanan']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $this->mock(ReadOnlySimrs::class, function (MockInterface $mock): void {
            $mock->shouldReceive('healthy')->once()->andReturnFalse();
        });

        $this->actingAs($user)
            ->get('/laporan/pelayanan/rawat-jalan')
            ->assertOk()
            ->assertViewHas('from', fn (CarbonImmutable $date) => $date->toDateString() === '2026-09-23')
            ->assertViewHas('to', fn (CarbonImmutable $date) => $date->toDateString() === '2026-09-23');

        CarbonImmutable::setTestNow();
    }
}
