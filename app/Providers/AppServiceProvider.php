<?php

namespace App\Providers;

use App\Models\Deployment;
use App\Models\EnvironmentVariable;
use App\Models\Server;
use App\Observers\DeploymentObserver;
use App\Observers\EnvironmentVariableObserver;
use App\Observers\ServerObserver;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\ApiUsage\ApiUsageRecorder;
use App\Services\Http\ExternalHttpClient;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApiUsageContext::class);
        $this->app->singleton(ApiUsageRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Deployment::observe(DeploymentObserver::class);
        EnvironmentVariable::observe(EnvironmentVariableObserver::class);
        Server::observe(ServerObserver::class);

        Event::listen(RequestSending::class, function (RequestSending $event): void {
            app(ApiUsageRecorder::class)->requestStarted($event->request);
        });
        Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
            app(ApiUsageRecorder::class)->requestFinished($event->request, $event->response->status());
        });
        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event): void {
            app(ApiUsageRecorder::class)->requestFinished($event->request, null);
        });

        if (! app(ExternalHttpClient::class)->shouldVerifySsl()) {
            Log::warning('Outbound SSL certificate verification is disabled for this local/testing environment.');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(200)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? null,
                    ], 429, $headers);
                });
        });
    }
}
