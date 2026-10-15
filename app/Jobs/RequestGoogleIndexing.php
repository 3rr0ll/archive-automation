<?php

namespace App\Jobs;

use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Requests Google indexing for every URL in a domain's sitemap.
 *
 * Respects the Indexing API daily quota: processes up to the configured
 * batch size, then re-dispatches itself (next day on quota exhaustion)
 * until every URL has been submitted. Submitted URLs are remembered so
 * retries and re-runs do not resubmit them.
 */
class RequestGoogleIndexing implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [60, 600];

    public function __construct(
        public string $domain,
        public string $sitemapUrl,
        public int $rounds = 0,
    ) {}

    public function handle(GoogleSearchConsoleService $searchConsole): void
    {
        $dailyQuota = (int) config('services.google_search_console.indexing_daily_quota', 200);
        $maxRounds = (int) config('services.google_search_console.indexing_max_rounds', 60);
        $cacheKey = 'gsc_indexed_urls:'.$this->domain;

        $urls = $searchConsole->fetchSitemapUrls($this->sitemapUrl);
        if ($urls === []) {
            $urls = ["https://{$this->domain}/"];
        }

        /** @var array<string, true> $submitted */
        $submitted = Cache::get($cacheKey, []);
        $pending = array_values(array_filter($urls, fn (string $url): bool => ! isset($submitted[$url])));

        $sentThisRun = 0;
        $quotaExhausted = false;

        foreach ($pending as $url) {
            if ($sentThisRun >= $dailyQuota) {
                $quotaExhausted = true;
                break;
            }

            $status = $searchConsole->publishUrl($url);

            if ($status === 429) {
                $quotaExhausted = true;
                break;
            }

            $sentThisRun++;

            if ($status >= 200 && $status < 300) {
                $submitted[$url] = true;
                Cache::put($cacheKey, $submitted, now()->addDays(30));
            }
        }

        $remaining = count($urls) - count(array_intersect_key(array_flip($urls), $submitted));

        Log::info('Google indexing batch finished.', [
            'domain' => $this->domain,
            'sent' => $sentThisRun,
            'remaining' => $remaining,
            'round' => $this->rounds,
        ]);

        if ($remaining > 0 && $this->rounds < $maxRounds) {
            $delay = $quotaExhausted ? now()->addDay() : now()->addHour();
            self::dispatch($this->domain, $this->sitemapUrl, $this->rounds + 1)->delay($delay);
        }
    }
}
