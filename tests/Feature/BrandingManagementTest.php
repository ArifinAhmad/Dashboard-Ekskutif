<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_upload_and_view_application_logo(): void
    {
        Storage::fake('local');
        $user = $this->userWithRole('super-admin');

        $this->actingAs($user)
            ->put('/administrasi/branding', [
                'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
                'name' => 'RS Mutiara Sukma',
                'address' => 'Jl. Kesehatan No. 1',
                'phone' => '0370-123456',
                'email' => 'info@rs.test',
                'website' => 'https://rs.test',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $path = AppSetting::query()->whereKey('application_logo')->value('value');
        $exportPath = AppSetting::query()->whereKey('application_logo_export')->value('value');
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertExists($exportPath);
        $this->assertLessThanOrEqual(320, getimagesizefromstring(Storage::disk('local')->get($path))[0]);
        $this->assertLessThanOrEqual(180, getimagesizefromstring(Storage::disk('local')->get($exportPath))[0]);
        $this->assertDatabaseHas('app_settings', ['key' => 'hospital_name', 'value' => 'RS Mutiara Sukma']);
        $this->assertDatabaseHas('app_settings', ['key' => 'hospital_address', 'value' => 'Jl. Kesehatan No. 1']);

        $this->get('/branding/logo')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_non_super_admin_cannot_manage_branding(): void
    {
        $user = $this->userWithRole('administrator');

        $this->actingAs($user)->get('/administrasi/branding')->assertForbidden();
        $this->actingAs($user)
            ->put('/administrasi/branding', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertForbidden();
    }

    public function test_logo_validation_rejects_non_image_upload(): void
    {
        Storage::fake('local');
        $user = $this->userWithRole('super-admin');

        $this->actingAs($user)
            ->put('/administrasi/branding', ['logo' => UploadedFile::fake()->create('logo.txt', 10, 'text/plain')])
            ->assertSessionHasErrors('logo');
    }

    public function test_super_admin_can_update_hospital_information_without_reuploading_logo(): void
    {
        $user = $this->userWithRole('super-admin');

        $this->actingAs($user)->put('/administrasi/branding', [
            'name' => 'RSUD Nusa Tenggara Barat',
            'address' => 'Mataram, NTB',
            'phone' => '0370-000000',
            'email' => 'info@rsud.test',
            'website' => 'https://rsud.test',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('app_settings', ['key' => 'hospital_name', 'value' => 'RSUD Nusa Tenggara Barat']);
    }

    public function test_login_and_layout_use_dashboard_title_with_hospital_name(): void
    {
        AppSetting::query()->create(['key' => 'hospital_name', 'value' => 'RS Harapan Sehat']);
        cache()->forget('branding.logo.metadata');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('RS Harapan Sehat')
            ->assertDontSee('E-Eksekutif');
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => $roleName, 'label' => ucfirst($roleName)]);
        $user->roles()->attach($role);

        return $user;
    }
}
