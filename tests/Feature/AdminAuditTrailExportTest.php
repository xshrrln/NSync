<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuditTrailExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_download_audit_trail_as_pdf(): void
    {
        $role = Role::firstOrCreate(['name' => 'Platform Administrator', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        AdminAuditLog::query()->create([
            'action' => 'Viewed Dashboard',
            'description' => 'Opened Dashboard.',
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => 'nsync.localhost'])
            ->get(route('admin.audit-trail.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
