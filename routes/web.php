<?php

use App\Http\Controllers\Api\DeploymentFileEditorController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GoogleSearchConsoleOAuthController;
use App\Models\Deployment;
use App\Models\EnvironmentVariable;
use App\Models\GoogleSearchConsoleAccount;
use App\Models\Server;
use App\Models\User;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use App\Services\Sftp\SftpConnectionFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Guest Page Routes (Loads HTML views)
Route::middleware('guest')->group(function (): void {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');
});

// Protected Page Routes (Requires Authentication)
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/environment-variables', function () {
        $variables = EnvironmentVariable::with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($env) {
                if ($env->type === 'secret') {
                    $env->value = null;
                } else {
                    $env->makeVisible('value');
                }

                return $env;
            });

        return view('pages.environment-variables', compact('variables'));
    })->name('environment-variables');
    Route::get('/google-search-console', function () {
        $accounts = GoogleSearchConsoleAccount::orderBy('created_at', 'desc')->get()->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'client_email' => $account->client_email,
                'owner_email' => $account->owner_email,
                'is_active' => (bool) $account->is_active,
                'has_credentials' => true,
                'created_at' => $account->created_at?->toIso8601String(),
                'updated_at' => $account->updated_at?->toIso8601String(),
            ];
        });
        $legacyFallback = app(GoogleSearchConsoleService::class)->hasLegacyFallback();

        return view('pages.google-search-console', compact('accounts', 'legacyFallback'));
    })->middleware('admin')->name('google-search-console');
    Route::get('/servers', function () {
        $servers = Server::orderBy('created_at', 'desc')->get();

        return view('pages.servers', compact('servers'));
    })->name('servers');
    Route::get('/users', function () {
        $users = User::orderBy('created_at', 'desc')->get();

        return view('pages.users', compact('users'));
    })->name('users');
    Route::get('/deployments/{deployment}/logs', function (Deployment $deployment) {
        $deployment->load(['server', 'user']);

        return view('pages.deployment-logs', compact('deployment'));
    })->name('deployments.logs.page');
    Route::get('/deployments/{deployment}/files', function (Deployment $deployment) {
        $deployment->load(['server', 'user']);

        return view('pages.deployment-files', compact('deployment'));
    })->name('deployments.files.page');
    Route::get('/deployments/{deployment}/files/download', [DeploymentFileEditorController::class, 'downloadZip'])->name('deployments.files.download');
    Route::get('/google/redirect', function (Request $request) {
        try {
            return redirect()->away(app(GoogleSearchConsoleService::class)
                ->createOAuthUrl((int) $request->user()->getKey()));
        } catch (Throwable $e) {
            return redirect()->route('google-search-console')->with('error', $e->getMessage());
        }
    })->middleware('admin')->name('google.redirect');
});

// Google OAuth callback (matches GOOGLE_REDIRECT_URI)
Route::get('/google/callback', [GoogleSearchConsoleOAuthController::class, 'handleCallback'])
    ->name('google.callback');

// Admin-Only Web Pages
Route::middleware('admin')->group(function (): void {
    Route::get('/environment-variables', function () {
        $variables = EnvironmentVariable::with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($env) {
                if ($env->type === 'secret') {
                    $env->value = null;
                } else {
                    $env->makeVisible('value');
                }

                return $env;
            });

        return view('pages.environment-variables', compact('variables'));
    })->name('environment-variables');

    Route::get('/servers', function () {
        $servers = Server::orderBy('created_at', 'desc')->get();

        // Disk usage for each server via SSH, cached so the page doesn't reconnect on every load.
        config(['services.sftp.timeout' => 10]);
        $factory = app(SftpConnectionFactory::class);
        $command = <<<'CMD'
df -B1 --output=size,used,avail,pcent / | tail -1 | awk '{gsub("%","",$4); printf "{\"total\":%s,\"used\":%s,\"available\":%s,\"percent\":%s}\n",$1,$2,$3,$4}'
CMD;

        $servers->each(function ($server) use ($factory, $command) {
            $server->storage = Cache::remember(
                "server-storage:{$server->server_id}",
                now()->addMinutes(10),
                function () use ($factory, $command, $server) {
                    try {
                        $ssh = $factory->connectSsh($server->ip_address);
                        $result = json_decode(trim($ssh->exec($command)), true);
                        $ssh->disconnect();

                        return is_array($result) ? $result : ['error' => 'Unexpected response'];
                    } catch (Throwable $e) {
                        return ['error' => $e->getMessage()];
                    }
                }
            );
        });

        return view('pages.servers', compact('servers'));
    })->name('servers');

    Route::get('/users', function () {
        $users = User::orderBy('created_at', 'desc')->get();

        return view('pages.users', compact('users'));
    })->name('users');

    Route::get('/bulk-delete', function () {
        return view('pages.bulk-delete');
    })->name('bulk-delete');
});
