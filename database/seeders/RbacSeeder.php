<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view' => 'Lihat dashboard', 'service.view' => 'Lihat pelayanan',
            'pharmacy.view' => 'Lihat farmasi', 'logistics.view' => 'Lihat logistik',
            'quality.view' => 'Lihat mutu dan biaya', 'cashier.view' => 'Lihat kasir',
            'finance.view' => 'Lihat keuangan', 'report.export' => 'Ekspor laporan',
            'admin.manage' => 'Kelola administrasi',
        ];
        foreach ($permissions as $name => $label) {
            Permission::query()->updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $roles = [
            'super-admin' => array_keys($permissions),
            'direksi' => array_values(array_filter(array_keys($permissions), fn ($item) => $item !== 'admin.manage')),
            'pelayanan' => ['dashboard.view', 'service.view', 'quality.view', 'report.export'],
            'farmasi' => ['dashboard.view', 'pharmacy.view', 'report.export'],
            'logistik' => ['dashboard.view', 'logistics.view', 'report.export'],
            'keuangan' => ['dashboard.view', 'cashier.view', 'finance.view', 'report.export'],
            'auditor' => ['dashboard.view', 'service.view', 'pharmacy.view', 'logistics.view', 'quality.view', 'cashier.view', 'finance.view'],
        ];
        foreach ($roles as $name => $grants) {
            $role = Role::query()->updateOrCreate(['name' => $name], ['label' => str($name)->replace('-', ' ')->title()]);
            $role->permissions()->sync(Permission::query()->whereIn('name', $grants)->pluck('id'));
        }
    }
}
