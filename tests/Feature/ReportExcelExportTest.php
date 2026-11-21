<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Simrs\ReadOnlySimrs;
use App\Services\Simrs\ReportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_export_returns_an_excel_workbook(): void
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
                'summary' => [],
                'sections' => [[
                    'title' => 'Kunjungan',
                    'columns' => ['label' => 'Kategori', 'total' => 'Total'],
                    'formats' => ['total' => 'number'],
                    'rows' => collect([
                        (object) ['label' => "\t=HYPERLINK(\"https://example.test\")", 'total' => 12],
                    ]),
                ]],
            ]);
        });

        $response = $this->actingAs($user)
            ->get('/laporan/pelayanan/rawat-jalan/ekspor?from=2026-09-23&to=2026-09-23')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename=pelayanan-rawat-jalan-2026-09-23-2026-09-23.xlsx');

        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);
        $this->assertGreaterThan(1000, strlen($content));

        $path = tempnam(sys_get_temp_dir(), 'report-xlsx-');
        file_put_contents($path, $content);
        $workbook = IOFactory::load($path);
        $sheet = $workbook->getActiveSheet();
        $this->assertSame(config('app.name'), $sheet->getCell('B1')->getValue());
        $this->assertSame('PELAYANAN - RAWAT JALAN', $sheet->getCell('A4')->getValue());
        $this->assertSame('No', $sheet->getCell('A6')->getValue());
        $this->assertSame("'\t=HYPERLINK(\"https://example.test\")", $sheet->getCell('B7')->getValue());
        $this->assertSame('FF237A50', $sheet->getStyle('A4')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FF16593B', $sheet->getStyle('A6')->getFill()->getStartColor()->getARGB());
        $workbook->disconnectWorksheets();
        unlink($path);
    }
}
