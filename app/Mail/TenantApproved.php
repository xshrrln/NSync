<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        $fromAddress = (string) (config('mail.mailers.smtp.username') ?: config('mail.from.address'));
        $fromName = (string) config('mail.from.name', config('app.name', 'NSync'));

        return new Envelope(
            subject: 'Your NSync Workspace is Ready!',
            from: new Address($fromAddress, $fromName),
        );
    }

    public function content(): Content
    {
        $appUrl = (string) config('app.url', 'http://localhost:8000');
        $parts = parse_url($appUrl);
        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        $workspaceUrl = $scheme . '://' . $this->tenant->domain . $port;
        $loginUrl = $workspaceUrl . '/login';

        return new Content(
            view: 'emails.tenant-approved',
            with: [
                'tenant' => $this->tenant,
                'login_url' => $loginUrl,
                'workspace_url' => $workspaceUrl,
                'username' => $this->tenant->tenant_admin_email,
                'password' => $this->temporaryPassword,
                'theme' => is_array($this->tenant->theme)
                    ? $this->tenant->theme
                    : json_decode($this->tenant->getRawOriginal('theme') ?? '{}', true) ?? ['primary' => '#16A34A', 'secondary' => '#10B981'],
            ]
        );
    }
}
