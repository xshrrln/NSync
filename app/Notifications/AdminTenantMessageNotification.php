<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AdminTenantMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Message $message,
        private readonly ?string $senderName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tenantName = $this->tenant->name ?? 'Unknown workspace';
        $sender = $this->senderName ?? 'Tenant User';

        return [
            'key' => 'tenant-chat-message-' . $this->tenant->id . '-' . $this->message->id,
            'type' => 'tenant-chat-message',
            'title' => 'New tenant chat message',
            'message' => sprintf(
                '%s from %s: %s',
                $sender,
                $tenantName,
                Str::limit($this->message->message, 100)
            ),
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $tenantName,
            'sender_name' => $sender,
            'message_id' => $this->message->id,
            'url' => route('admin.support.index'),
            'action_label' => 'Open Support',
        ];
    }
}

