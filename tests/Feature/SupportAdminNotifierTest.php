<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AdminSupportTicketNotification;
use App\Notifications\AdminTenantMessageNotification;
use App\Support\SupportAdminNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportAdminNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_platform_administrators_when_tenant_message_is_sent(): void
    {
        Notification::fake();

        $role = Role::firstOrCreate(['name' => 'Platform Administrator', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $ticket = new SupportTicket([
            'id' => 123,
            'tenant_id' => 50,
            'subject' => 'Payment issue',
            'requester_name' => 'Alice Tenant',
        ]);
        $ticket->setRelation('tenant', new Tenant(['name' => 'Acme Workspace']));

        $message = new SupportTicketMessage([
            'id' => 456,
            'author_name' => 'Alice Tenant',
            'message' => 'Payment declined',
        ]);

        app(SupportAdminNotifier::class)->notifyTenantMessage($ticket, $message, 'ticket_created');

        Notification::assertSentTo($admin, AdminSupportTicketNotification::class);
    }

    public function test_it_notifies_platform_administrators_when_tenant_chat_message_is_sent(): void
    {
        Notification::fake();

        $role = Role::firstOrCreate(['name' => 'Platform Administrator', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $tenant = new Tenant([
            'id' => 50,
            'name' => 'Acme Workspace',
        ]);

        $message = new Message([
            'id' => 789,
            'room_id' => 50,
            'sender_id' => 1,
            'message' => 'Hello from tenant chat',
        ]);

        app(SupportAdminNotifier::class)->notifyTenantChatMessage($tenant, $message, 'Bob Tenant');

        Notification::assertSentTo($admin, AdminTenantMessageNotification::class);
    }

    public function test_it_finds_admins_even_when_tenant_global_scope_is_active(): void
    {
        Notification::fake();

        $role = Role::firstOrCreate(['name' => 'Platform Administrator', 'guard_name' => 'web']);
        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole($role);

        // Simulate a tenant-scoped request where the User global scope would normally
        // exclude admins that don't belong to the current tenant.
        app()->bind('currentTenant', fn () => new Tenant(['id' => 99, 'name' => 'Other']));

        $ticket = new SupportTicket([
            'id' => 123,
            'tenant_id' => 50,
            'subject' => 'Scope test',
            'requester_name' => 'Alice',
        ]);
        $ticket->setRelation('tenant', new Tenant(['name' => 'Test']));

        $message = new SupportTicketMessage([
            'id' => 456,
            'author_name' => 'Alice',
            'message' => 'Scope test message',
        ]);

        app(SupportAdminNotifier::class)->notifyTenantMessage($ticket, $message, 'ticket_created');

        Notification::assertSentTo($admin, AdminSupportTicketNotification::class);
    }
}
