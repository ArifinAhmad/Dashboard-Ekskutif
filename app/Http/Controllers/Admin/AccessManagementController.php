<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccessManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.access.index', [
            'users' => User::query()->with('roles.permissions')->orderBy('name')->get(),
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('label')->get(),
            'permissions' => Permission::query()->orderBy('label')->get(),
        ]);
    }

    public function storeUser(StoreUserRequest $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeSuperAdminAssignment($request, $data['roles']);
        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            $user->roles()->sync($data['roles']);

            return $user;
        });
        $audit->record($request, 'admin.user_created', 'administration', ['target_user_id' => $user->id, 'role_ids' => $data['roles']]);

        return back()->with('status', "Pengguna {$user->email} berhasil dibuat.");
    }

    public function updateUser(UpdateUserAccessRequest $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeSuperAdminAssignment($request, $data['roles']);
        $active = (bool) ($data['is_active'] ?? false);
        abort_if($request->user()->is($user) && ! $active, 422, 'Anda tidak dapat menonaktifkan akun sendiri.');

        $superAdminRoleId = Role::query()->where('name', 'super-admin')->value('id');
        if ($user->isSuperAdmin() && ! in_array($superAdminRoleId, $data['roles'], true)) {
            abort(422, 'Role super-admin tidak dapat dilepas melalui halaman ini.');
        }

        DB::transaction(function () use ($user, $active, $data) {
            $user->update(['is_active' => $active]);
            $user->roles()->sync($data['roles']);
        });
        $audit->record($request, 'admin.user_access_updated', 'administration', ['target_user_id' => $user->id, 'role_ids' => $data['roles'], 'is_active' => $active]);

        return back()->with('status', "Akses {$user->email} berhasil diperbarui.");
    }

    public function destroyUser(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('admin.manage'), 403);
        abort_if($request->user()->is($user), 422, 'Anda tidak dapat menghapus akun sendiri.');
        abort_if($user->isSuperAdmin(), 422, 'Akun super-admin dikelola sistem dan tidak dapat dihapus.');

        $userData = ['target_user_id' => $user->id, 'email' => $user->email];
        $audit->record($request, 'admin.user_deleted', 'administration', $userData);
        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->roles()->detach();
            $user->delete();
        });

        return back()->with('status', "Pengguna {$userData['email']} berhasil dihapus.");
    }

    public function storeRole(StoreRoleRequest $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $role = DB::transaction(function () use ($data) {
            $role = Role::query()->create(['name' => $data['name'], 'label' => $data['label']]);
            $role->permissions()->sync($data['permissions']);

            return $role;
        });
        $audit->record($request, 'admin.role_created', 'administration', ['role_id' => $role->id, 'permission_ids' => $data['permissions']]);

        return back()->with('status', "Role {$role->label} berhasil dibuat.");
    }

    public function updateRole(UpdateRoleRequest $request, Role $role, AuditService $audit): RedirectResponse
    {
        abort_if($role->name === 'super-admin', 422, 'Role super-admin dikelola sistem dan tidak dapat diubah.');
        $data = $request->validated();
        DB::transaction(function () use ($role, $data) {
            $role->update(['label' => $data['label']]);
            $role->permissions()->sync($data['permissions']);
        });
        $audit->record($request, 'admin.role_updated', 'administration', ['role_id' => $role->id, 'permission_ids' => $data['permissions']]);

        return back()->with('status', "Role {$role->label} berhasil diperbarui.");
    }

    public function destroyRole(Request $request, Role $role, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('admin.manage'), 403);
        abort_if($role->name === 'super-admin', 422, 'Role super-admin dikelola sistem dan tidak dapat dihapus.');
        abort_if($role->users()->exists(), 422, 'Role masih digunakan oleh pengguna dan tidak dapat dihapus.');

        $roleData = ['role_id' => $role->id, 'name' => $role->name, 'label' => $role->label];
        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });
        $audit->record($request, 'admin.role_deleted', 'administration', $roleData);

        return back()->with('status', "Role {$roleData['label']} berhasil dihapus.");
    }

    private function authorizeSuperAdminAssignment(Request $request, array $roleIds): void
    {
        $assignsSuperAdmin = Role::query()->whereIn('id', $roleIds)->where('name', 'super-admin')->exists();
        abort_if($assignsSuperAdmin && ! $request->user()->isSuperAdmin(), 403, 'Hanya super-admin yang dapat memberikan role super-admin.');
    }
}
