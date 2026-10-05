<?php

namespace App\Services\Cloudflare;

use Exception;

/**
 * Unified Cloudflare facade/coordinator.
 * Delegates worker operations to CloudflareWorkerService,
 * DNS operations to CloudflareDnsService, and client operations to CloudflareClient.
 */
class CloudflareService
{
    public const WORKER_WWW_MAIN = CloudflareWorkerService::WORKER_WWW_MAIN;
    public const WORKER_WWW_REDIRECT = CloudflareWorkerService::WORKER_WWW_REDIRECT;
    public const WORKER_ROOT_MAIN = CloudflareWorkerService::WORKER_ROOT_MAIN;
    public const WORKER_ROOT_REDIRECT = CloudflareWorkerService::WORKER_ROOT_REDIRECT;

    public function __construct(
        protected CloudflareWorkerService $workerService,
        protected CloudflareDnsService $dnsService,
        protected CloudflareClient $client
    ) {}

    // --- Worker Operations ---

    public function changeWorkers(string $domain, ?string $version = null): array
    {
        return $this->workerService->changeWorkers($domain, $version);
    }

    public function getWorkerRoutes(string $zoneId): array
    {
        return $this->workerService->getWorkerRoutes($zoneId);
    }

    public function createWorkerRoute(string $zoneId, string $pattern, string $script): array
    {
        return $this->workerService->createWorkerRoute($zoneId, $pattern, $script);
    }

    public function updateWorkerRoute(string $zoneId, string $routeId, string $pattern, string $script): array
    {
        return $this->workerService->updateWorkerRoute($zoneId, $routeId, $pattern, $script);
    }

    public function deleteWorkerRoute(string $zoneId, string $routeId): bool
    {
        return $this->workerService->deleteWorkerRoute($zoneId, $routeId);
    }

    // --- DNS Operations ---

    public function setupRunCloudDns(string $domain, string $serverIp, bool $proxied = true): array
    {
        return $this->dnsService->setupRunCloudDns($domain, $serverIp, $proxied);
    }

    public function getDnsRecords(string $domain): array
    {
        return $this->dnsService->getDnsRecords($domain);
    }

    public function deleteAllDnsRecords(string $domain): array
    {
        return $this->dnsService->deleteAllDnsRecords($domain);
    }

    public function addDnsRecord(string $domain, string $type, string $name, string $content, bool $proxied = true, int $ttl = 1): array
    {
        return $this->dnsService->addDnsRecord($domain, $type, $name, $content, $proxied, $ttl);
    }

    public function listDnsRecords(string $zoneId): array
    {
        return $this->dnsService->listDnsRecords($zoneId);
    }

    public function addARecord(string $zoneId, string $name, string $ip, bool $proxied = true): array
    {
        return $this->dnsService->addARecord($zoneId, $name, $ip, $proxied);
    }

    public function upsertARecord(string $zoneId, string $name, string $ip, bool $proxied = true): array
    {
        return $this->dnsService->upsertARecord($zoneId, $name, $ip, $proxied);
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): void
    {
        $this->dnsService->deleteDnsRecord($zoneId, $recordId);
    }

    public function configureDns(string $domain, array $records): bool
    {
        return $this->dnsService->configureDns($domain, $records);
    }

    // --- Zone & Client Operations ---

    public function getZoneId(string $domain): string
    {
        return $this->client->getZoneId($domain);
    }

    public function findZoneByDomain(string $domain): ?array
    {
        return $this->client->findZoneByDomain($domain);
    }

    public function createZone(string $domain): array
    {
        return $this->client->createZone($domain);
    }

    public function getZone(string $zoneId): array
    {
        return $this->client->getZone($zoneId);
    }

    public function normalizeDomain(string $domain): string
    {
        return $this->client->normalizeDomain($domain);
    }

    public function getRootDomain(string $domain): string
    {
        return $this->client->getRootDomain($domain);
    }
}
