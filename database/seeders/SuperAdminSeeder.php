<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = config('bootstrap-admin');
        $this->validateCredentials($credentials);
        $role = Role::query()->where('name', 'super-admin')->firstOrFail();

        DB::transaction(function () use ($credentials, $role): void {
            $user = User::query()->updateOrCreate(
                ['email' => Str::lower(trim($credentials['email']))],
                [
                    'name' => trim($credentials['name']),
                    'password' => Hash::make($credentials['password']),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }

    private function validateCredentials(array $credentials): void
    {
        $missing = collect(['name', 'email', 'password'])->filter(fn ($key) => blank($credentials[$key] ?? null));
        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('Seeder super admin memerlukan SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, dan SUPER_ADMIN_PASSWORD pada environment.');
        }

        validator($credentials, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();
    }
}
