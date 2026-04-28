<?php

namespace App\Support;

use App\Models\Message;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AdminSupportTicketNotification;
use App\Notifications\AdminTenantMessageNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SupportAdminNotifier
{
    public function notifyTenantMessage(SupportTicket $ticket, SupportTicketMessage $message, string $eventType = 'message_sent'): void
    {
        try {
            $admins = User::withoutGlobalScopes()->role('Platform Administrator')->get();

            Log::info('Support notification attempt', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'event_type' => $eventType,
                'admin_count' => $admins->count(),
            ]);

            if ($admins->isEmpty()) {
                Log::warning('No platform administrators found for support notification');
                return;
            }

            Notification::send($admins, new AdminSupportTicketNotification($ticket->loadMissing('tenant'), $message, $eventType));
            
            Log::info('Support notification sent to admins', [
                'ticket_id' => $ticket->id,
                'admin_ids' => $admins->pluck('id')->toArray(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Support admin notification failed.', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function notifyTenantChatMessage(Tenant $tenant, Message $message, ?string $senderName = null): void
    {
        try {
            $admins = User::withoutGlobalScopes()->role('Platform Administrator')->get();

            Log::info('Chat notification attempt', [
                'tenant_id' => $tenant->id,
                'message_id' => $message->id,
                'admin_count' => $admins->count(),
            ]);

            if ($admins->isEmpty()) {
                Log::warning('No platform administrators found for chat notification');
                return;
            }

            Notification::send($admins, new AdminTenantMessageNotification($tenant, $message, $senderName));
            
            Log::info('Chat notification sent to admins', [
                'tenant_id' => $tenant->id,
                'message_id' => $message->id,
                'admin_ids' => $admins->pluck('id')->toArray(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chat admin notification failed.', [
                'tenant_id' => $tenant->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
