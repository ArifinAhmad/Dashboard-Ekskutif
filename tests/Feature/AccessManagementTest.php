<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_super_admin_can_open_access_management_page(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/administrasi/akses')
            ->assertOk()
            ->assertSee('Pengguna dan role')
            ->assertSee('Daftar role')
            ->assertSee('Tambah Role Baru')
            ->assertSee('data-modal-open="create-role-modal"', false)
            ->assertSee('Tambah Pengguna Baru')
            ->assertSee('data-modal-open="create-user-modal"', false)
            ->assertSee('icon-button edit', false)
            ->assertSee('Action');
    }

    public function test_regular_user_cannot_open_access_management_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/administrasi/akses')->assertForbidden();
    }

    public function test_super_admin_can_create_user_with_role_and_hashed_password(): void
    {
        $admin = $this->superAdmin();
        $role = Role::query()->where('name', 'pelayanan')->sole();

        $this->actingAs($admin)->post('/administrasi/pengguna', [
            'name' => 'Manajer Pelayanan',
            'email' => 'pelayanan@example.test',
            'password' => 'Strong-Password-123!',
            'password_confirmation' => 'Strong-Password-123!',
            'roles' => [$role->id],
            'is_active' => 1,
        ])->assertRedirect();

        $user = User::query()->where('email', 'pelayanan@example.test')->sole();
        $this->assertTrue(Hash::check('Strong-Password-123!', $user->password));
        $this->assertTrue($user->roles()->whereKey($role->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.user_created', 'user_id' => $admin->id]);
    }

    public function test_super_admin_can_create_role_with_permissions(): void
    {
        $admin = $this->superAdmin();
        $permissions = Permission::query()->whereIn('name', ['dashboard.view', 'pharmacy.view'])->pluck('id')->all();

        $this->actingAs($admin)->post('/administrasi/role', [
            'name' => 'Kepala Farmasi',
            'label' => 'Kepala Farmasi',
            'permissions' => $permissions,
        ])->assertRedirect();

        $role = Role::query()->where('name', 'kepala-farmasi')->sole();
        $this->assertSame(2, $role->permissions()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.role_created', 'user_id' => $admin->id]);
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put("/administrasi/pengguna/{$admin->id}/akses", [
            'roles' => [$admin->roles()->firstOrFail()->id],
        ])->assertUnprocessable();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_super_admin_can_edit_role_from_modal_action(): void
    {
        $admin = $this->superAdmin();
        $role = Role::query()->where('name', 'pelayanan')->sole();
        $permissions = Permission::query()->whereIn('name', ['dashboard.view', 'service.view'])->pluck('id')->all();

        $this->actingAs($admin)->put("/administrasi/role/{$role->id}", [
            'label' => 'Manajer Pelayanan',
            'permissions' => $permissions,
        ])->assertRedirect();

        $this->assertSame('Manajer Pelayanan', $role->fresh()->label);
        $this->assertSame(2, $role->permissions()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.role_updated', 'user_id' => $admin->id]);
    }

    public function test_super_admin_can_delete_unused_custom_role(): void
    {
        $admin = $this->superAdmin();
        $role = Role::query()->create(['name' => 'role-sementara', 'label' => 'Role Sementara']);
        $role->permissions()->attach(Permission::query()->firstOrFail());

        $this->actingAs($admin)->delete("/administrasi/role/{$role->id}")->assertRedirect();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.role_deleted', 'user_id' => $admin->id]);
    }

    public function test_role_in_use_and_super_admin_role_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $role = Role::query()->where('name', 'pelayanan')->sole();
        User::factory()->create()->roles()->attach($role);

        $this->actingAs($admin)->delete("/administrasi/role/{$role->id}")->assertUnprocessable();
        $this->actingAs($admin)->delete('/administrasi/role/'.Role::query()->where('name', 'super-admin')->value('id'))->assertUnprocessable();
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_super_admin_can_delete_regular_user(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $target->roles()->attach(Role::query()->where('name', 'pelayanan')->sole());

        $this->actingAs($admin)->delete("/administrasi/pengguna/{$target->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.user_deleted', 'user_id' => $admin->id]);
    }

    public function test_own_account_and_super_admin_user_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $otherAdmin = $this->superAdmin();

        $this->actingAs($admin)->delete("/administrasi/pengguna/{$admin->id}")->assertUnprocessable();
        $this->actingAs($admin)->delete("/administrasi/pengguna/{$otherAdmin->id}")->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::query()->where('name', 'super-admin')->sole());

        return $user;
    }
}
