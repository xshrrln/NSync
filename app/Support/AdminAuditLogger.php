<?php

namespace App\Support;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminAuditLogger
{
    public function log(
        ?User $user,
        string $action,
        ?string $description = null,
        ?Request $request = null,
        array $context = [],
        ?int $statusCode = null,
        ?string $subjectType = null,
        mixed $subjectId = null
    ): void {
        AdminAuditLog::query()->create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'action' => $action,
            'description' => $description,
            'method' => $request?->method(),
            'route_name' => $request?->route()?->getName(),
            'path' => $request?->path(),
            'host' => $request?->getHost(),
            'full_url' => $request?->fullUrl(),
            'referer' => $request?->headers->get('referer'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'status_code' => $statusCode,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'context' => $this->limitContext($context),
            'occurred_at' => now(),
        ]);
    }

    private function limitContext(array $context): array
    {
        return collect($context)
            ->map(function ($value) {
                if (is_string($value)) {
                    return mb_strimwidth($value, 0, 500, '...');
                }

                if (is_array($value)) {
                    return Arr::map($value, function ($nested) {
                        return is_string($nested)
                            ? mb_strimwidth($nested, 0, 200, '...')
                            : $nested;
                    });
                }

                return $value;
            })
            ->all();
    }
}
