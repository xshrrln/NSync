<?php

namespace App\Mail;

use App\Models\PendingInvite;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeamInvite extends Mailable
{
    use Queueable, SerializesModels;

    public $invite;
    public $inviter;
    public $tenant;
    public $acceptUrl;
    public $loginUrl;

    public function __construct(PendingInvite $invite, User $inviter, Tenant $tenant)
    {
        $this->invite = $invite;
        $this->inviter = $inviter;
        $this->tenant = $tenant;

        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: 8000;
        $portSegment = ($port && (int) $port !== 80 && (int) $port !== 443) ? ':' . $port : '';
        $tenantDomain = trim((string) ($tenant->domain ?? ''));
        $fallbackHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $host = $tenantDomain !== ''
            ? (str_contains($tenantDomain, '.') ? $tenantDomain : "{$tenantDomain}.localhost")
            : $fallbackHost;

        $this->acceptUrl = "http://{$host}{$portSegment}/team/invite/accept/{$invite->token}";
        $this->loginUrl = "http://{$host}{$portSegment}/login";
    }

    public function build()
    {
        return $this->subject("You've been invited to join {$this->tenant->name}")
                    ->view('emails.team-invite');
    }
}
