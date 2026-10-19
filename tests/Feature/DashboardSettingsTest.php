<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_admin_can_update_dashboard_refresh_and_scroll_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/administrasi/pengaturan-dashboard', [
            'refresh_minutes' => 15,
            'scroll_speed' => 120,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('app_settings', ['key' => 'dashboard_refresh_seconds', 'value' => '900']);
        $this->assertDatabaseHas('app_settings', ['key' => 'dashboard_scroll_speed', 'value' => '120']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.dashboard_settings_updated', 'user_id' => $admin->id]);
    }

    public function test_dashboard_settings_validate_safe_ranges(): void
    {
        $this->actingAs($this->admin())->put('/administrasi/pengaturan-dashboard', [
            'refresh_minutes' => 0,
            'scroll_speed' => 500,
        ])->assertSessionHasErrors(['refresh_minutes', 'scroll_speed']);
    }

    public function test_regular_user_cannot_open_dashboard_settings(): void
    {
        $this->actingAs(User::factory()->create())->get('/administrasi/pengaturan-dashboard')->assertForbidden();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super-admin')->sole());

        return $user;
    }
}
