<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Support\AiSupportResponder;
use Tests\TestCase;

class AiSupportResponderTest extends TestCase
{
    public function test_it_returns_escalation_reply_when_billing_message_is_sent_without_openai(): void
    {
        config([
            'services.support_ai.enabled' => false,
            'services.openai.key' => null,
        ]);

        $ticket = new SupportTicket([
            'subject' => 'Billing issue',
            'category' => 'billing',
            'priority' => 'normal',
        ]);

        $reply = app(AiSupportResponder::class)->replyFor($ticket, 'My payment was declined.');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('should be checked by the nsync support team directly', strtolower($reply));
    }

    public function test_it_returns_generic_fallback_reply_when_openai_is_disabled(): void
    {
        config([
            'services.support_ai.enabled' => false,
            'services.openai.key' => null,
        ]);

        $ticket = new SupportTicket([
            'subject' => 'Need help',
            'category' => 'general',
            'priority' => 'normal',
        ]);

        $reply = app(AiSupportResponder::class)->replyFor($ticket, 'The dashboard looks wrong after refresh.');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('dashboard', strtolower($reply));
        $this->assertStringContainsString('which page', strtolower($reply));
    }

    public function test_follow_up_message_in_billing_ticket_does_not_repeat_billing_escalation_when_message_is_general(): void
    {
        config([
            'services.support_ai.enabled' => false,
            'services.openai.key' => null,
        ]);

        $ticket = new SupportTicket([
            'subject' => 'Payment issue',
            'category' => 'billing',
            'priority' => 'normal',
        ]);

        $ticket->setRelation('messages', collect([
            new \App\Models\SupportTicketMessage(['author_type' => 'tenant', 'message' => 'Payment failed']),
            new \App\Models\SupportTicketMessage(['author_type' => 'ai', 'message' => 'Billing escalation']),
            new \App\Models\SupportTicketMessage(['author_type' => 'tenant', 'message' => 'what is nsync']),
        ]));

        $reply = app(AiSupportResponder::class)->replyFor($ticket, 'what is nsync');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('multi-tenant workspace app', strtolower($reply));
        $this->assertStringNotContainsString('support team directly', strtolower($reply));
    }
}
