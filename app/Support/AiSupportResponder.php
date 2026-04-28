<?php

namespace App\Support;

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiSupportResponder
{
    public function isEnabled(): bool
    {
        return (bool) config('services.support_ai.enabled') && filled(config('services.openai.key'));
    }

    public function assistantName(): string
    {
        return (string) config('services.support_ai.assistant_name', 'NSync Assistant');
    }

    public function replyFor(SupportTicket $ticket, string $message): ?string
    {
        if (! $this->isEnabled()) {
            return $this->fallbackReply($ticket, $message);
        }

        if ($this->shouldEscalate($ticket, $message)) {
            return $this->escalationReply($ticket);
        }

        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->timeout((int) config('services.openai.timeout', 12))
                ->post('https://api.openai.com/v1/responses', [
                    'model' => (string) config('services.openai.model', 'gpt-5.4-mini'),
                    'instructions' => $this->instructions(),
                    'input' => $this->inputFor($ticket, $message),
                    'max_output_tokens' => 300,
                    'temperature' => 0.2,
                    'store' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('Support AI response failed.', [
                    'ticket_id' => $ticket->id,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);

                return $this->fallbackReply($ticket, $message);
            }

            return $this->extractText($response->json()) ?: $this->fallbackReply($ticket, $message);
        } catch (\Throwable $e) {
            Log::warning('Support AI responder unavailable.', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackReply($ticket, $message);
        }
    }

    private function shouldEscalate(SupportTicket $ticket, string $message): bool
    {
        $contextParts = [$message];

        if ($this->isFirstTenantMessage($ticket)) {
            $contextParts[] = $ticket->category;
            $contextParts[] = $ticket->subject;
        }

        $text = Str::lower(implode(' ', $contextParts));

        return $ticket->priority === 'urgent'
            || Str::contains($text, [
                'billing',
                'payment',
                'paid',
                'invoice',
                'receipt',
                'subscription',
                'password',
                'login',
                'security',
                'hack',
                'breach',
                'delete account',
                'database',
                'data loss',
                'not activated',
                'plan inactive',
            ]);
    }

    private function escalationReply(SupportTicket $ticket): string
    {
        $category = str_replace('-', ' ', $ticket->category);

        return "Thanks for sending this. This looks like a {$category} concern that should be checked by the NSync support team directly, so I will leave this ticket open for admin review.\n\nIf you have screenshots, payment references, error messages, or steps to reproduce the issue, please add them here so the team can verify it faster.";
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
You are NSync Assistant, the first-response support assistant inside NSync.

NSync is a multi-tenant workspace app with dashboards, Kanban boards, tasks, team members, billing, reports, settings, support tickets, tenant plans, and version updates.

Reply with concise, practical help. Use a warm professional tone. Do not claim to be a human admin. Do not say GitHub or expose implementation details. Do not request passwords, tokens, API keys, database credentials, or private secrets. If the issue involves billing, payments, account access, security, suspected data loss, subscription activation, or admin-only changes, say the support team will review it and ask for safe context such as screenshots or error messages.

Keep replies under 120 words. Give clear next steps when possible.
PROMPT;
    }

    private function inputFor(SupportTicket $ticket, string $message): string
    {
        return implode("\n", [
            'Ticket subject: ' . $ticket->subject,
            'Category: ' . $ticket->category,
            'Priority: ' . $ticket->priority,
            'Tenant: ' . ($ticket->tenant?->name ?? 'Unknown workspace'),
            'User message:',
            $message,
        ]);
    }

    private function extractText(array $payload): ?string
    {
        $text = trim((string) data_get($payload, 'output_text', ''));

        if ($text === '') {
            $parts = collect(data_get($payload, 'output', []))
                ->flatMap(fn ($item) => data_get($item, 'content', []))
                ->map(function ($item) {
                    $value = data_get($item, 'text');

                    if (is_string($value)) {
                        return $value;
                    }

                    return data_get($item, 'text.value');
                })
                ->filter()
                ->all();

            $text = trim(implode("\n", $parts));
        }

        return $text !== '' ? Str::limit($text, 1200, '') : null;
    }

    private function fallbackReply(SupportTicket $ticket, string $message): string
    {
        if ($this->shouldEscalate($ticket, $message)) {
            return $this->escalationReply($ticket);
        }

        if ($response = $this->knowledgeReply($message)) {
            return $response;
        }

        $category = match ($ticket->category) {
            'technical' => 'Please share the exact steps, the page where it happened, and any error text or screenshot so the team can reproduce it.',
            'account' => 'Please confirm which account action you were trying to complete and include any visible error message or screenshot.',
            'feature-request' => 'Please describe the workflow you want to improve, what you expected to do, and why the current flow is not enough.',
            default => 'Please add the exact steps, what you expected, what happened instead, and any screenshot or error text you saw.',
        };

        return "Thanks for the update. I recorded your message and the support team can continue from this thread.\n\n{$category}";
    }

    private function knowledgeReply(string $message): ?string
    {
        $text = Str::lower(trim($message));

        if ($text === '') {
            return null;
        }

        if (Str::contains($text, ['what is nsync', "what's nsync", 'what is n sync', 'about nsync'])) {
            return 'NSync is a multi-tenant workspace app for teams. It includes dashboards, boards, tasks, team members, billing, reports, settings, support tickets, and release updates so each workspace can manage work in one place.';
        }

        if (Str::contains($text, ['hello', 'hi', 'hey'])) {
            return 'Hello. Share the issue you are seeing, what you expected to happen, and any error message or screenshot, and I will help route it correctly.';
        }

        if (Str::contains($text, ['dashboard', 'report', 'reports'])) {
            return 'The dashboard summarizes workspace activity, and Reports is used for exported summaries and audit-style views. If a page looks wrong, please share which page, what filter or action you used, and a screenshot if possible.';
        }

        if (Str::contains($text, ['board', 'kanban', 'task'])) {
            return 'NSync boards are used to organize work by stage. If something is wrong with a board or task, please share the board name, what action you took, and the exact result you saw.';
        }

        if (Str::contains($text, ['member', 'team', 'invite'])) {
            return 'NSync team management covers members, invites, and workspace roles. If you are having trouble adding or updating a member, please share the user email, the step you were taking, and any error shown.';
        }

        if (Str::contains($text, ['update', 'release', 'version'])) {
            return 'Release updates appear in the workspace update center. If you expected a newer version and do not see it, please share the version number you expected and what is currently displayed.';
        }

        return null;
    }

    private function isFirstTenantMessage(SupportTicket $ticket): bool
    {
        if ($ticket->relationLoaded('messages')) {
            return $ticket->messages
                ->where('author_type', 'tenant')
                ->count() <= 1;
        }

        if (! $ticket->exists || ! $ticket->getKey()) {
            return true;
        }

        return $ticket->messages()
            ->where('author_type', 'tenant')
            ->count() <= 1;
    }
}
