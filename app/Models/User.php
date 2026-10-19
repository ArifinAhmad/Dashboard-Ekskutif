<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private ?array $permissionLookup = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissionLookup()['super-admin'] || isset($this->permissionLookup()['permissions'][$permission]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->permissionLookup()['super-admin'];
    }

    private function permissionLookup(): array
    {
        if ($this->permissionLookup !== null) {
            return $this->permissionLookup;
        }

        $usesLoadedRoles = $this->relationLoaded('roles') && $this->roles->every->relationLoaded('permissions');
        $roles = $usesLoadedRoles
            ? $this->roles
            : $this->roles()->with('permissions')->get();

        $lookup = [
            'super-admin' => $roles->contains('name', 'super-admin'),
            'permissions' => $roles->flatMap(fn (Role $role) => $role->permissions)->pluck('name')->flip()->all(),
        ];

        if ($usesLoadedRoles || $roles->isNotEmpty()) {
            $this->permissionLookup = $lookup;
        }

        return $lookup;
    }
}
