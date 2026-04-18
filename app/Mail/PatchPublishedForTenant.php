<?php

namespace App\Mail;

use App\Models\Patch;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PatchPublishedForTenant extends Mailable
{
    use Queueable, SerializesModels;

    public Patch $patch;
    public Tenant $tenant;
    public string $updateCenterUrl;

    public function __construct(Patch $patch, Tenant $tenant)
    {
        $this->patch = $patch;
        $this->tenant = $tenant;

        $port = parse_url((string) config('app.url'), PHP_URL_PORT) ?: 8000;
        $portSegment = ($port && (int) $port !== 80 && (int) $port !== 443) ? ':' . $port : '';
        $tenantDomain = trim((string) ($tenant->domain ?? ''));
        $fallbackHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $host = $tenantDomain !== ''
            ? (str_contains($tenantDomain, '.') ? $tenantDomain : "{$tenantDomain}.localhost")
            : $fallbackHost;

        $this->updateCenterUrl = "http://{$host}{$portSegment}/update-center";
    }

    public function build()
    {
        return $this->subject("New workspace update available: {$this->patch->title}")
            ->view('emails.patch-published');
    }
}

