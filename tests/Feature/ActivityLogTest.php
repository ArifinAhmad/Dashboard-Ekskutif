<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemErrorLogger;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_admin_can_filter_and_view_activity_and_error_logs(): void
    {
        $admin = $this->admin();
        AuditLog::query()->create(['user_id' => $admin->id, 'event' => 'dashboard.view', 'module' => 'dashboard', 'successful' => true]);
        AuditLog::query()->create(['user_id' => $admin->id, 'event' => 'system.error', 'module' => 'system', 'context' => ['fingerprint' => 'abc123'], 'successful' => false]);

        $this->actingAs($admin)->get('/administrasi/log-aktivitas?type=error')
            ->assertOk()
            ->assertSee('system.error')
            ->assertDontSee('dashboard.view')
            ->assertSee('log-detail-', false);
    }

    public function test_error_logger_records_sanitized_server_errors_only(): void
    {
        $request = Request::create('/dashboard', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'Test Agent']);
        app(SystemErrorLogger::class)->record(new RuntimeException('Failure token=secret-123 user@example.test'), $request);
        app(SystemErrorLogger::class)->record(new NotFoundHttpException('Not found'), $request);

        $this->assertDatabaseCount('audit_logs', 1);
        $log = AuditLog::query()->sole();
        $this->assertSame('system.error', $log->event);
        $this->assertStringContainsString('token=[redacted]', $log->context['message']);
        $this->assertStringContainsString('[email]', $log->context['message']);
        $this->assertArrayHasKey('fingerprint', $log->context);
    }

    public function test_regular_user_cannot_view_activity_logs(): void
    {
        $this->actingAs(User::factory()->create())->get('/administrasi/log-aktivitas')->assertForbidden();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super-admin')->sole());

        return $user;
    }
}
