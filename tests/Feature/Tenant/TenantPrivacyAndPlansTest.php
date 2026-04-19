<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use Tests\TestCase;

class TenantPrivacyAndPlansTest extends TestCase
{
    public function test_generates_deterministic_obfuscated_database_name(): void
    {
        $name = 'Acme Finance Workspace';
        $dbOne = Tenant::generateDatabaseName($name, 42);
        $dbTwo = Tenant::generateDatabaseName($name, 42);
        $dbOther = Tenant::generateDatabaseName($name, 43);

        $this->assertSame($dbOne, $dbTwo);
        $this->assertNotSame($dbOne, $dbOther);
        $this->assertStringStartsWith('nsync_t42_', $dbOne);
        $this->assertLessThanOrEqual(64, strlen($dbOne));
        $this->assertStringNotContainsString('acme', strtolower($dbOne));
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $dbOne);
    }

    public function test_bulk_invites_are_plan_feature_driven(): void
    {
        $plans = (array) config('plans', []);

        $this->assertContains('bulk-invites', $plans['pro']['features'] ?? []);
        $this->assertNotContains('bulk-invites', $plans['free']['features'] ?? []);
    }
}

