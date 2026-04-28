<?php

namespace Tests\Feature;

use App\Support\GitHubReleaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubReleaseServiceTest extends TestCase
{
    public function test_release_feed_survives_compare_timeout_when_releases_endpoint_succeeds(): void
    {
        Cache::flush();

        Http::fake([
            'https://api.github.com/repos/*/releases' => Http::response([
                [
                    'tag_name' => 'v1.0.3',
                    'name' => 'v1.0.3',
                    'body' => '',
                    'html_url' => 'https://github.com/xshrrln/NSync/releases/tag/v1.0.3',
                    'published_at' => '2026-04-21T10:00:00Z',
                    'draft' => false,
                    'prerelease' => false,
                ],
                [
                    'tag_name' => 'v1.0.2',
                    'name' => 'v1.0.2',
                    'body' => '',
                    'html_url' => 'https://github.com/xshrrln/NSync/releases/tag/v1.0.2',
                    'published_at' => '2026-04-20T10:00:00Z',
                    'draft' => false,
                    'prerelease' => false,
                ],
            ], 200),
            'https://api.github.com/repos/*/compare/*' => fn () => throw new \RuntimeException('compare timeout'),
        ]);

        $releases = app(GitHubReleaseService::class)->releases(8);

        $this->assertCount(2, $releases);
        $this->assertSame('v1.0.3', $releases[0]['tag_name']);
        $this->assertSame('Maintenance and reliability updates included in this release.', $releases[0]['body']);
    }
}
