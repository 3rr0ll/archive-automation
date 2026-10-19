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
        $deployments = Deployment::with(['user:id,name,email', 'server'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->get();

        $servers = $this->cache->rememberLocalServers(function () {
            return Server::where('is_active', true)
                ->orderBy('name')
                ->get();
        });

        return view('pages.dashboard', compact('deployments', 'servers'));
    }
}
