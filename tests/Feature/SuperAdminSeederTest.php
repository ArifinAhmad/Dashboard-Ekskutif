<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_an_active_super_admin_idempotently(): void
    {
        config(['bootstrap-admin' => [
            'name' => 'Administrator Sistem',
            'email' => 'admin@example.test',
            'password' => 'Secure!Admin2026',
        ]]);

        $this->seed(RbacSeeder::class);
        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $user = User::query()->where('email', 'admin@example.test')->sole();
        $this->assertSame('Administrator Sistem', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Secure!Admin2026', $user->password));
        $this->assertTrue($user->roles()->where('name', 'super-admin')->exists());
        $this->assertSame(1, User::query()->where('email', 'admin@example.test')->count());
    }

    public function test_seeder_rejects_missing_bootstrap_credentials(): void
    {
        config(['bootstrap-admin' => ['name' => null, 'email' => null, 'password' => null]]);
        $this->seed(RbacSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->seed(SuperAdminSeeder::class);
    }
}
