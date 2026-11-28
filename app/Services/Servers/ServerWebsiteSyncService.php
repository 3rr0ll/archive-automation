<?php

namespace App\Services\Servers;

use App\Enums\DeploymentStatus;
use App\Enums\DomainVersion;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use App\Services\RunCloud\RunCloudWebAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ServerWebsiteSyncService
{
    private const APPS_PER_REQUEST = 4;

    public function __construct(private RunCloudWebAppService $webApps) {}

    /** @return array{imported: int, existing: int, unavailable: int, next: ?array{page: int, offset: int}} */
    public function sync(Server $server, User $user, int $page, int $offset): array
    {
        $listing = $this->webApps->getWebsiteSyncPage($server->server_id, $page);
        $apps = array_slice($listing['apps'], $offset, self::APPS_PER_REQUEST);
        $counts = ['imported' => 0, 'existing' => 0, 'unavailable' => 0];
        $websites = [];

        foreach ($apps as $app) {
            $appId = (int) ($app['id'] ?? 0);
            $name = $app['name'] ?? null;
            if ($appId <= 0 || ! is_string($name) || blank($name)) {
                $counts['unavailable']++;

                continue;
            }

            if ($this->webAppExists($server, $appId)) {
                $counts['existing']++;

                continue;
            }

            $domains = $this->websiteDomains($server, $appId, $app);
            if ($domains === []) {
                $counts['unavailable']++;

                continue;
            }

            $websites[] = ['id' => $appId, 'name' => $name, 'domains' => $domains];
        }

        if ($websites !== []) {
            $imported = Cache::lock('runcloud:website-import', 30)->block(2, fn (): int => DB::transaction(
                fn (): int => $this->importWebsites($server, $user, $websites)
            ));
            $counts['imported'] += $imported;
            $counts['existing'] += count($websites) - $imported;
        }

        $nextOffset = $offset + count($apps);
        $next = $nextOffset < count($listing['apps'])
            ? ['page' => $page, 'offset' => $nextOffset]
            : ($listing['next_page'] === null ? null : ['page' => $listing['next_page'], 'offset' => 0]);

        return [...$counts, 'next' => $next];
    }

    /**
     * @param  array<string, mixed>  $app
     * @return list<string>
     */
    private function websiteDomains(Server $server, int $appId, array $app): array
    {
        $domains = collect($app['domains'] ?? []);
        $primary = $this->validHostname(data_get($app, 'primaryDomain.name'))
            ?? $this->validHostname($app['domainName'] ?? null);

        if ($domains->isEmpty() && $primary === null) {
            $domains = collect($this->webApps->getWebsiteSyncDomains($server->server_id, $appId));
        }

        return $domains
            ->filter(fn (mixed $domain): bool => is_array($domain))
            ->sortByDesc(fn (array $domain): bool => ($domain['type'] ?? null) === 'primary')
            ->pluck('name')
            ->prepend($primary)
            ->map($this->validHostname(...))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function validHostname(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $domain = strtolower(rtrim(trim($value), '.'));

        return str_contains($domain, '.') && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            ? $domain
            : null;
    }

    private function webAppExists(Server $server, int $appId): bool
    {
        return $server->deployments()->where('runcloud_web_app_id', $appId)->exists();
    }

    /** @param list<array{id: int, name: string, domains: list<string>}> $websites */
    private function importWebsites(Server $server, User $user, array $websites): int
    {
        $imported = 0;

        foreach ($websites as $website) {
            $domainVariants = collect($website['domains'])
                ->map(fn (string $domain): string => $this->webApps->normalizeDomain($domain))
                ->flatMap(fn (string $domain): array => [$domain, "{$domain}.", "www.{$domain}", "www.{$domain}."])
                ->unique()
                ->all();

            if ($this->webAppExists($server, $website['id'])
                || Deployment::query()->whereIn(DB::raw('LOWER(TRIM(domain))'), $domainVariants)->exists()) {
                continue;
            }

            $hostname = $website['domains'][0];
            $deployment = $server->deployments()->create([
                'user_id' => $user->getKey(),
                'domain' => $this->webApps->normalizeDomain($hostname),
                'version' => str_starts_with($hostname, 'www.') ? DomainVersion::Www : DomainVersion::NonWww,
                'runcloud_web_app_id' => $website['id'],
                'runcloud_web_app_name' => $website['name'],
                'archive_source' => 'runcloud',
                'status' => DeploymentStatus::Completed,
                'current_step' => 'runcloud_imported',
            ]);
            $deployment->logs()->create([
                'step_name' => 'runcloud_imported',
                'status' => 'success',
                'message' => "Imported {$website['name']} (web app ID {$website['id']}) from RunCloud server {$server->name}.",
            ]);
            $imported++;
        }

        return $imported;
    }
}
