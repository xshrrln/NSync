<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $tenant;
    public $temporaryPassword;

    public function __construct(Tenant $tenant, $temporaryPassword = null)
    {
        $this->tenant = $tenant;
        $this->temporaryPassword = $temporaryPassword ?? 'Check your signup password or use password reset';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your NSync Workspace is Ready! 🎉',
        );
    }

    public function content(): Content
    {
$loginUrl = 'http://' . $this->tenant->domain . ':8000/login';
$workspaceUrl = 'http://' . $this->tenant->domain . ':8000';
        
        return new Content(
            view: 'emails.tenant-approved',
            with: [
                'tenant' => $this->tenant,
                'login_url' => $loginUrl,
                'workspace_url' => $workspaceUrl,
                'username' => $this->tenant->tenant_admin_email,
                'password' => $this->temporaryPassword,
                'theme' => is_array($this->tenant->theme) ? $this->tenant->theme : json_decode($this->tenant->getRawOriginal('theme') ?? '{}', true) ?? ['primary' => '#16A34A', 'secondary' => '#10B981'],
            ]
        );
    }
}

