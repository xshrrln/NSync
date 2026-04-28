<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AdminSupportTicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
        private readonly SupportTicketMessage $message,
        private readonly string $eventType = 'message_sent',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tenantName = $this->ticket->tenant?->name ?? 'Unknown workspace';
        $requesterName = $this->ticket->requester_name ?: ($this->message->author_name ?: 'Tenant user');
        $isNewTicket = $this->eventType === 'ticket_created';

        return [
            'key' => 'support-ticket-' . $this->eventType . '-' . $this->ticket->id . '-' . $this->message->id,
            'type' => 'support-ticket',
            'title' => $isNewTicket ? 'New support ticket' : 'New tenant support reply',
            'message' => sprintf(
                '%s from %s: %s - %s',
                $requesterName,
                $tenantName,
                $this->ticket->subject,
                Str::limit($this->message->message, 100)
            ),
            'support_ticket_id' => $this->ticket->id,
            'support_ticket_message_id' => $this->message->id,
            'tenant_id' => $this->ticket->tenant_id,
            'tenant_name' => $tenantName,
            'requester_name' => $requesterName,
            'event_type' => $this->eventType,
            'url' => route('admin.support.index'),
            'action_label' => 'Open Support',
        ];
    }

    public function toMail(object $notifiable)
    {
        $tenantName = $this->ticket->tenant?->name ?? 'Unknown workspace';
        $requesterName = $this->ticket->requester_name ?: ($this->message->author_name ?: 'Tenant user');
        $isNewTicket = $this->eventType === 'ticket_created';

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($isNewTicket ? 'New Support Ticket' : 'New Tenant Support Reply')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($isNewTicket ? 'A new support ticket has been submitted.' : 'A tenant has replied to a support ticket.')
            ->line('**Tenant:** ' . $tenantName)
            ->line('**Requester:** ' . $requesterName)
            ->line('**Subject:** ' . $this->ticket->subject)
            ->line('**Message:** ' . Str::limit($this->message->message, 200))
            ->action('View Ticket', route('admin.support.index'))
            ->salutation('Regards, NSync Support System');
    }
}
