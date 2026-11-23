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

class ReportPaginationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_ten_report_rows_are_rendered_per_section(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create(['name' => 'service.view', 'label' => 'Pelayanan']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $rows = collect(range(1, 55))->map(fn ($number) => (object) ['label' => "Data {$number}", 'total' => $number]);

        $this->mock(ReadOnlySimrs::class, fn (MockInterface $mock) => $mock->shouldReceive('healthy')->once()->andReturnTrue());
        $this->mock(ReportManager::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('has')->once()->andReturnFalse();
            $mock->shouldReceive('get')->once()->andReturn([
                'summary' => [],
                'sections' => [['title' => 'Data', 'columns' => ['label' => 'Label', 'total' => 'Total'], 'formats' => ['total' => 'number'], 'rows' => $rows]],
            ]);
        });

        $response = $this->actingAs($user)->get('/laporan/pelayanan/rawat-jalan?from=2026-09-23&to=2026-09-23');

        $response->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertSee('10</strong> dari 55 data ditampilkan', false)
            ->assertSee('Data 55')
            ->assertSee('Data 46')
            ->assertDontSee('Data 45')
            ->assertSee('Tampilkan 10 berikutnya')
            ->assertSee('data-load-more-url="/laporan/pelayanan/rawat-jalan/data"', false)
            ->assertSee('href="http://localhost/laporan/pelayanan/rawat-jalan?', false);
    }

    public function test_report_rows_can_be_sorted_ascending_when_requested(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create(['name' => 'service.view', 'label' => 'Pelayanan']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $rows = collect(range(1, 55))->map(fn ($number) => (object) ['label' => "Data {$number}", 'total' => $number]);

        $this->mock(ReadOnlySimrs::class, fn (MockInterface $mock) => $mock->shouldReceive('healthy')->once()->andReturnTrue());
        $this->mock(ReportManager::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('has')->once()->andReturnFalse();
            $mock->shouldReceive('get')->once()->andReturn([
                'summary' => [],
                'sections' => [['title' => 'Data', 'columns' => ['label' => 'Label', 'total' => 'Total'], 'formats' => ['total' => 'number'], 'rows' => $rows]],
            ]);
        });

        $response = $this->actingAs($user)->get('/laporan/pelayanan/rawat-jalan?from=2026-09-23&to=2026-09-23&section_sort[0]=total&section_direction[0]=asc');

        $response->assertOk()->assertSee('Data 1')->assertSee('Data 10')->assertDontSee('Data 11');
    }

    public function test_stock_report_defaults_to_highest_available_stock(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create(['name' => 'service.view', 'label' => 'Pelayanan']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $rows = collect(range(1, 15))->map(fn ($number) => (object) ['nama_brng' => "Obat {$number}", 'stok' => $number]);

        $this->mock(ReadOnlySimrs::class, fn (MockInterface $mock) => $mock->shouldReceive('healthy')->once()->andReturnTrue());
        $this->mock(ReportManager::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('has')->once()->andReturnFalse();
            $mock->shouldReceive('get')->once()->andReturn([
                'summary' => [],
                'sections' => [['title' => 'Stok Obat', 'columns' => ['nama_brng' => 'Nama Barang', 'stok' => 'Stok'], 'formats' => ['stok' => 'number'], 'rows' => $rows]],
            ]);
        });

        $response = $this->actingAs($user)->get('/laporan/pelayanan/rawat-jalan?from=2026-09-23&to=2026-09-23');

        $response->assertOk()->assertSee('Obat 15')->assertSee('Obat 6')->assertDontSee('Obat 5');
    }

    public function test_load_more_endpoint_returns_the_next_ten_rows_without_page_reload(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create(['name' => 'service.view', 'label' => 'Pelayanan']);
        $role = Role::query()->create(['name' => 'direksi', 'label' => 'Direksi']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $rows = collect(range(1, 55))->map(fn ($number) => (object) ['label' => "Data {$number}", 'total' => $number]);

        $this->mock(ReadOnlySimrs::class, fn (MockInterface $mock) => $mock->shouldReceive('healthy')->zeroOrMoreTimes()->andReturnTrue());
        $this->mock(ReportManager::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('has')->once()->andReturnTrue();
            $mock->shouldReceive('get')->once()->andReturn([
                'summary' => [],
                'sections' => [['title' => 'Data', 'columns' => ['label' => 'Label', 'total' => 'Total'], 'formats' => ['total' => 'number'], 'rows' => $rows]],
            ]);
        });

        $response = $this->actingAs($user)->getJson('/laporan/pelayanan/rawat-jalan/data?from=2026-09-23&to=2026-09-23&section=0&page=2');

        $response->assertOk()->assertHeaderContains('Cache-Control', 'no-store')->assertJsonPath('page', 2)->assertJsonPath('shown', 20)->assertJsonPath('total', 55);
        $html = $response->json('html');
        $this->assertStringContainsString('Data 45', $html);
        $this->assertStringContainsString('Data 36', $html);
        $this->assertStringNotContainsString('Data 35', $html);
        $this->assertSame(10, substr_count($html, '<tr>'));
    }
}
