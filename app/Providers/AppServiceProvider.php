<?php

namespace App\Providers;

use App\Models\Deployment;
use App\Models\EnvironmentVariable;
use App\Models\Server;
use App\Observers\DeploymentObserver;
use App\Observers\EnvironmentVariableObserver;
use App\Observers\ServerObserver;
use App\Services\Http\ExternalHttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Deployment::observe(DeploymentObserver::class);
        EnvironmentVariable::observe(EnvironmentVariableObserver::class);
        Server::observe(ServerObserver::class);

        if (! app(ExternalHttpClient::class)->shouldVerifySsl()) {
            Log::warning('Outbound SSL certificate verification is disabled for this local/testing environment.');
        }
    }
}
