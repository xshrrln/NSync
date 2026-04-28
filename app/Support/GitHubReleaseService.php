<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class GitHubReleaseService
{
    private ?string $lastError = null;

    public function latest(): ?array
    {
        return $this->releases(1)[0] ?? null;
    }

    public function latestVersion(): string
    {
        return (string) ($this->latest()['tag_name'] ?? config('services.github.releases.fallback_version', 'v0.0.0'));
    }

    public function releases(int $limit = 10): array
    {
        $limit = max(1, min($limit, 30));

        return array_slice($this->allReleases(), 0, $limit);
    }

    public function repositoryUrl(): string
    {
        return 'https://github.com/' . $this->repository();
    }

    public function feedStatus(): array
    {
        $releases = $this->allReleases();

        return [
            'has_releases' => $releases !== [],
            'last_error' => $this->lastError,
        ];
    }

    private function allReleases(): array
    {
        $repository = $this->repository();
        $cacheKey = 'github-releases:' . Str::slug($repository);
        $fallbackCacheKey = $cacheKey . ':last-success';
        $ttl = now()->addMinutes((int) config('services.github.releases.cache_minutes', 10));

        return Cache::remember($cacheKey, $ttl, function () use ($repository, $fallbackCacheKey) {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(array_filter([
                        'X-GitHub-Api-Version' => '2022-11-28',
                        'Authorization' => $this->token() ? 'Bearer ' . $this->token() : null,
                    ]))
                    ->timeout((int) config('services.github.releases.timeout', 10))
                    ->get("https://api.github.com/repos/{$repository}/releases");

                if (! $response->successful()) {
                    $this->lastError = 'GitHub releases request failed with status ' . $response->status() . '.';

                    return $this->fallbackReleasesFromCli($repository, $fallbackCacheKey, $limit = 10);
                }

                $releases = collect($response->json())
                    ->filter(fn ($release) => empty($release['draft']))
                    ->filter(fn ($release) => filled($release['tag_name'] ?? null))
                    ->values();

                return $releases
                    ->map(function ($release, int $index) use ($repository, $releases) {
                        $tagName = (string) ($release['tag_name'] ?? '');
                        $previousTag = (string) ($releases->get($index + 1)['tag_name'] ?? '');
                        $body = $this->tenantSafeBody((string) ($release['body'] ?? ''));

                        if ($body === '') {
                            $body = $this->generatedChangelog($repository, $previousTag, $tagName);
                        }

                        return [
                            'tag_name' => $tagName,
                            'name' => (string) ($release['name'] ?? $tagName ?: 'Release'),
                            'body' => $body,
                            'html_url' => (string) ($release['html_url'] ?? $this->repositoryUrl()),
                            'published_at' => $this->parseDate($release['published_at'] ?? null),
                            'published_on' => $this->formatDate($release['published_at'] ?? null),
                            'is_prerelease' => (bool) ($release['prerelease'] ?? false),
                        ];
                    })
                    ->values()
                    ->all();

                Cache::forever($fallbackCacheKey, $payload);
                $this->lastError = null;

                return $payload;
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
                \Log::warning('Failed to fetch GitHub releases: ' . $e->getMessage());

                return $this->fallbackReleasesFromCli($repository, $fallbackCacheKey, $limit = 10);
            }
        });
    }

    private function repository(): string
    {
        return trim((string) config('services.github.releases.repository', 'xshrrln/NSync'), '/');
    }

    private function token(): ?string
    {
        $token = trim((string) config('services.github.releases.token', ''));

        return $token !== '' ? $token : null;
    }

    private function formatDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        return Carbon::parse($date)->format('M d, Y');
    }

    private function parseDate(?string $date): ?Carbon
    {
        return $date ? Carbon::parse($date) : null;
    }

    private function tenantSafeBody(string $body): string
    {
        $lines = preg_split('/\R/', trim($body)) ?: [];

        return collect($lines)
            ->map(fn (string $line) => trim($line))
            ->reject(fn (string $line) => $line === '')
            ->reject(fn (string $line) => str_contains(Str::lower($line), 'github.com'))
            ->reject(fn (string $line) => str_contains(Str::lower($line), 'full changelog'))
            ->map(fn (string $line) => preg_replace('/https?:\/\/\S+/i', '', $line) ?? '')
            ->map(fn (string $line) => str_ireplace('github', 'release system', $line))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->implode("\n");
    }

    private function generatedChangelog(string $repository, string $previousTag, string $tagName): string
    {
        if ($previousTag === '' || $tagName === '') {
            return 'Initial product release.';
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(array_filter([
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'Authorization' => $this->token() ? 'Bearer ' . $this->token() : null,
                ]))
                ->timeout((int) config('services.github.releases.timeout', 12))
                ->get("https://api.github.com/repos/{$repository}/compare/{$previousTag}...{$tagName}");

            if (! $response->successful()) {
                return 'Maintenance and reliability updates included in this release.';
            }

            $changes = collect($response->json('commits', []))
                ->map(fn ($commit) => (string) data_get($commit, 'commit.message', ''))
                ->map(fn (string $message) => trim(Str::before($message, "\n")))
                ->map(fn (string $message) => $this->tenantSafeCommitMessage($message))
                ->filter()
                ->unique()
                ->take(8)
                ->values();

            if ($changes->isEmpty()) {
                return 'Maintenance and reliability updates included in this release.';
            }

            return $changes
                ->map(fn (string $change) => '- ' . $change)
                ->implode("\n");
        } catch (\Throwable $e) {
            \Log::warning('Failed to generate GitHub release changelog: ' . $e->getMessage(), [
                'repository' => $repository,
                'from' => $previousTag,
                'to' => $tagName,
            ]);

            return 'Maintenance and reliability updates included in this release.';
        }
    }

    private function tenantSafeCommitMessage(string $message): string
    {
        $message = trim($message);
        $lower = Str::lower($message);

        if ($message === ''
            || str_contains($lower, 'github.com')
            || str_contains($lower, 'full changelog')
            || str_starts_with($lower, 'merge ')
            || str_starts_with($lower, 'release ')
        ) {
            return '';
        }

        $message = preg_replace('/https?:\/\/\S+/i', '', $message) ?? '';
        $message = preg_replace('/^\w+(?:\([^)]+\))?!?:\s*/', '', $message) ?? $message;
        $message = str_ireplace('github', 'release system', $message);
        $message = trim($message, " \t\n\r\0\x0B-:.");

        if ($message === '') {
            return '';
        }

        return Str::ucfirst($message) . '.';
    }

    private function fallbackReleasesFromCli(string $repository, string $fallbackCacheKey, int $limit = 10): array
    {
        try {
            $process = new Process([
                'gh',
                'release',
                'list',
                '--repo',
                $repository,
                '--limit',
                (string) max(1, min($limit, 30)),
                '--json',
                'tagName,name,publishedAt,isPrerelease',
            ]);
            $process->setTimeout((int) config('services.github.releases.timeout', 12));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'gh release list failed.');
            }

            $payload = collect(json_decode($process->getOutput(), true) ?: [])
                ->filter(fn ($release) => filled($release['tagName'] ?? null))
                ->map(function (array $release) use ($repository) {
                    $tagName = (string) ($release['tagName'] ?? '');

                    return [
                        'tag_name' => $tagName,
                        'name' => (string) ($release['name'] ?? $tagName ?: 'Release'),
                        'body' => 'Release notes are available in GitHub for this version.',
                        'html_url' => 'https://github.com/' . trim($repository, '/') . '/releases/tag/' . $tagName,
                        'published_at' => $this->parseDate($release['publishedAt'] ?? null),
                        'published_on' => $this->formatDate($release['publishedAt'] ?? null),
                        'is_prerelease' => (bool) ($release['isPrerelease'] ?? false),
                    ];
                })
                ->values()
                ->all();

            if ($payload !== []) {
                Cache::forever($fallbackCacheKey, $payload);
                $this->lastError = null;
                return $payload;
            }
        } catch (\Throwable $e) {
            $this->lastError = $this->lastError ?: $e->getMessage();
            \Log::warning('Failed to fetch GitHub releases via gh CLI: ' . $e->getMessage());
        }

        return Cache::get($fallbackCacheKey, []);
    }
}
