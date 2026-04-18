<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantSuspended extends Mailable
{
    use Queueable, SerializesModels;

    public $tenant;
    public $reason;

    public function __construct(Tenant $tenant, string $reason = 'Administrative action')
    {
        $this->tenant = $tenant;
        $this->reason = $reason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your NSync Workspace Has Been Suspended 🚨',
        );
    }

    public function content(): Content
    {
        $loginUrl = 'http://' . $this->tenant->domain . ':8000/login';

        return new Content(
            view: 'emails.tenant-suspended',
            with: [
                'tenant' => $this->tenant,
                'reason' => $this->reason,
                'login_url' => $loginUrl,
            ]
        );
    }
}

