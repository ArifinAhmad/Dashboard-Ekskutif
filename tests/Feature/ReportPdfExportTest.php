<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Simrs\ReadOnlySimrs;
use App\Services\Simrs\ReportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ReportPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_export_returns_a_branded_pdf(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $permissions = collect([
            ['name' => 'service.view', 'label' => 'Pelayanan'],
            ['name' => 'report.export', 'label' => 'Ekspor'],
        ])->map(fn ($permission) => Permission::query()->create($permission));
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        $this->mock(ReadOnlySimrs::class, fn (MockInterface $mock) => $mock->shouldReceive('healthy')->once()->andReturnTrue());
        $this->mock(ReportManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('has')->once()->andReturnFalse();
            $mock->shouldReceive('get')->once()->andReturn([
                'summary' => ['Total Kunjungan' => 12],
                'sections' => [[
                    'title' => 'Kunjungan',
                    'columns' => ['label' => 'Kategori', 'total' => 'Total'],
                    'formats' => ['total' => 'number'],
                    'rows' => collect([(object) ['label' => 'Rawat Jalan', 'total' => 12]]),
                ]],
            ]);
        });

        $response = $this->actingAs($user)
            ->get('/laporan/pelayanan/rawat-jalan/ekspor-pdf?from=2026-09-23&to=2026-09-23')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=pelayanan-rawat-jalan-2026-09-23-2026-09-23.pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
