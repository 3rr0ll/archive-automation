<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Server;
use App\Services\Cache\ApplicationCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ApplicationCache $cache,
    ) {}

    /**
     * Show the application dashboard.
     */
    public function index(Request $request): View
    {
        $queueDeployments = Deployment::with(['user:id,name,email', 'server'])
            ->withCount('subdomainFolders')
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('created_at')
            ->get();

        $failedDeployments = Deployment::with(['user:id,name,email', 'server'])
            ->withCount('subdomainFolders')
            ->where('status', 'failed')
            ->latest('created_at')
            ->limit(30)
            ->get();

        $initialCompleted = Deployment::with(['user:id,name,email', 'server'])
            ->withCount('subdomainFolders')
            ->where('status', 'completed')
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(9);

        $servers = $this->cache->rememberLocalServers(function () {
            return Server::where('is_active', true)
                ->orderBy('name')
                ->get();
        });

        return view('pages.dashboard', [
            'initialDeployments' => $initialCompleted->items(),
            'initialTotal' => $initialCompleted->total(),
            'initialQueue' => $queueDeployments,
            'initialFailed' => $failedDeployments,
            'servers' => $servers,
        ]);
    }
}
