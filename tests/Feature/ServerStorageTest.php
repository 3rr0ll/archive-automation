<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Services\Sftp\SftpConnectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ServerStorageTest extends TestCase
{
    use RefreshDatabase;

    private const STORAGE = [
        'total' => 107374182400,
        'used' => 80530636800,
        'available' => 26843545600,
        'percent' => 75,
    ];

    public function test_servers_page_renders_without_connecting_to_ssh(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        $this->mock(SftpConnectionFactory::class)->shouldNotReceive('connectSsh');

        $this->actingAs($admin)
            ->get(route('servers'))
            ->assertOk()
            ->assertViewIs('pages.servers')
            ->assertViewHas('servers', function (Collection $servers) use ($server): bool {
                return $servers->count() === 1
                    && $servers->first()->is($server)
                    && ! array_key_exists('storage', $servers->first()->getAttributes());
            });
    }

    public function test_storage_binds_by_server_id_and_caches_success_for_ten_minutes(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create(['id' => 1, 'server_id' => 344845]);
        $this->mockStorageCommand($server, json_encode(self::STORAGE), connections: 2);
        $this->actingAs($admin);
        $url = route('servers.storage', ['server' => $server->server_id]);

        $this->getJson($url)->assertOk()->assertExactJson(self::STORAGE);

        $this->travel(599)->seconds();
        $this->getJson($url)->assertOk()->assertExactJson(self::STORAGE);

        $this->travel(2)->seconds();
        $this->getJson($url)->assertOk()->assertExactJson(self::STORAGE);
    }

    public function test_connection_errors_are_safe_and_cached_for_one_minute(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        $this->mock(SftpConnectionFactory::class)
            ->shouldReceive('connectSsh')
            ->twice()
            ->with($server->ip_address)
            ->andThrow(new RuntimeException('Private SSH credentials and connection details.'));
        $this->actingAs($admin);
        $url = route('servers.storage', ['server' => $server->server_id]);

        $this->getJson($url)->assertOk()->assertExactJson(['error' => 'Unable to connect']);

        $this->travel(59)->seconds();
        $this->getJson($url)->assertOk()->assertExactJson(['error' => 'Unable to connect']);

        $this->travel(2)->seconds();
        $this->getJson($url)->assertOk()->assertExactJson(['error' => 'Unable to connect']);
    }

    public function test_cached_errors_do_not_expose_previous_exception_messages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        Cache::put("server-storage:{$server->server_id}", ['error' => 'Private SSH credentials.'], 60);
        $this->mock(SftpConnectionFactory::class)->shouldNotReceive('connectSsh');

        $this->actingAs($admin)
            ->getJson(route('servers.storage', ['server' => $server->server_id]))
            ->assertOk()
            ->assertExactJson(['error' => 'Unable to connect']);
    }

    /**
     * @param  array{total: int, used: int, available: int, percent: int}|array{error: string}  $cachedStorage
     */
    #[DataProvider('cachedStorage')]
    public function test_refresh_reloads_only_the_requested_server(array $cachedStorage): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        $otherServer = Server::factory()->create();
        Cache::put("server-storage:{$server->server_id}", $cachedStorage, 600);
        Cache::put("server-storage:{$otherServer->server_id}", self::STORAGE, 600);
        $this->mockStorageCommand($server, json_encode(self::STORAGE));

        $this->actingAs($admin)
            ->getJson(route('servers.storage', ['server' => $server->server_id, 'refresh' => 1]))
            ->assertOk()
            ->assertExactJson(self::STORAGE);

        $this->assertSame(self::STORAGE, Cache::get("server-storage:{$otherServer->server_id}"));
    }

    /**
     * @return array<string, array{array{total: int, used: int, available: int, percent: int}|array{error: string}}>
     */
    public static function cachedStorage(): array
    {
        return [
            'previous success' => [[...self::STORAGE, 'percent' => 80]],
            'previous error' => [['error' => 'Unable to connect']],
        ];
    }

    #[DataProvider('invalidStorageOutputs')]
    public function test_invalid_command_output_returns_a_safe_cached_error(string|false $output): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        $this->mockStorageCommand($server, $output);
        $this->actingAs($admin);
        $url = route('servers.storage', ['server' => $server->server_id]);

        $this->getJson($url)->assertOk()->assertExactJson(['error' => 'Unable to connect']);
        $this->getJson($url)->assertOk()->assertExactJson(['error' => 'Unable to connect']);
    }

    /**
     * @return array<string, array{string|false}>
     */
    public static function invalidStorageOutputs(): array
    {
        return [
            'failed command' => [false],
            'malformed JSON' => ['SSH error containing private details'],
            'non-object JSON' => ['null'],
            'missing fields' => ['{"total":100,"used":75,"available":25}'],
            'invalid fields' => ['{"total":100,"used":75,"available":25,"percent":"unavailable"}'],
            'negative fields' => ['{"total":100,"used":75,"available":-25,"percent":75}'],
        ];
    }

    public function test_timed_out_command_returns_a_safe_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        $this->mockStorageCommand($server, json_encode(self::STORAGE), timedOut: true);

        $this->actingAs($admin)
            ->getJson(route('servers.storage', ['server' => $server->server_id]))
            ->assertOk()
            ->assertExactJson(['error' => 'Unable to connect']);
    }

    public function test_command_exception_disconnects_and_returns_a_safe_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();
        $ssh = Mockery::mock(SSH2::class);
        $ssh->shouldReceive('setTimeout')->once()->with(10);
        $ssh->shouldReceive('exec')->once()->andThrow(new RuntimeException('Private SSH details.'));
        $ssh->shouldReceive('disconnect')->once();
        $this->mock(SftpConnectionFactory::class)
            ->shouldReceive('connectSsh')->once()->with($server->ip_address)->andReturn($ssh);

        $this->actingAs($admin)
            ->getJson(route('servers.storage', ['server' => $server->server_id]))
            ->assertOk()
            ->assertExactJson(['error' => 'Unable to connect']);
    }

    public function test_storage_returns_403_for_guests_without_connecting_to_ssh(): void
    {
        $server = Server::factory()->create();
        $this->mock(SftpConnectionFactory::class)->shouldNotReceive('connectSsh');

        $this->getJson(route('servers.storage', ['server' => $server->server_id]))->assertForbidden();
    }

    public function test_storage_returns_403_for_regular_users_without_connecting_to_ssh(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $server = Server::factory()->create();
        $this->mock(SftpConnectionFactory::class)->shouldNotReceive('connectSsh');

        $this->actingAs($user)
            ->getJson(route('servers.storage', ['server' => $server->server_id]))
            ->assertForbidden();
    }

    public function test_storage_returns_404_for_database_id_instead_of_server_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create(['id' => 1, 'server_id' => 344845]);
        $this->mock(SftpConnectionFactory::class)->shouldNotReceive('connectSsh');

        $this->actingAs($admin)
            ->getJson(route('servers.storage', ['server' => $server->id]))
            ->assertNotFound();
    }

    private function mockStorageCommand(Server $server, string|false $output, int $connections = 1, bool $timedOut = false): void
    {
        $ssh = Mockery::mock(SSH2::class);
        $ssh->shouldReceive('setTimeout')->times($connections)->with(10);
        $ssh->shouldReceive('exec')
            ->times($connections)
            ->with(Mockery::on(fn (string $command): bool => str_contains($command, 'df -B1 --output=size,used,avail,pcent /') && str_contains($command, 'awk')))
            ->andReturn($output);
        $ssh->shouldReceive('isTimeout')->andReturn($timedOut);
        $ssh->shouldReceive('disconnect')->times($connections);
        $this->mock(SftpConnectionFactory::class)
            ->shouldReceive('connectSsh')
            ->times($connections)
            ->with($server->ip_address)
            ->andReturnUsing(function () use ($ssh): SSH2 {
                $this->assertSame(10, config('services.sftp.timeout'));

                return $ssh;
            });
    }
}
