<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Membuat pengguna aplikasi dan menetapkan role tanpa password default';

    public function handle(): int
    {
        $roles = Role::query()->orderBy('name')->pluck('name')->all();
        if ($roles === []) {
            $this->error('Role belum tersedia. Jalankan RbacSeeder terlebih dahulu.');

            return self::FAILURE;
        }

        $input = [
            'name' => $this->ask('Nama lengkap'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Password (minimal 12 karakter)'),
            'role' => $this->choice('Role', $roles),
        ];
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'in:'.implode(',', $roles)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('name', $input['role'])->sole());
        $this->info("Pengguna {$user->email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
