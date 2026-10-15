<?php

namespace Tests\Unit;

use App\Services\Sftp\SftpArchiveService;
use App\Services\Sftp\SftpConnectionFactory;
use Mockery;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Tests\TestCase;

class SftpArchiveServiceTest extends TestCase
{
    public function test_it_uploads_a_local_zip_before_extracting_and_deletes_the_remote_copy(): void
    {
        config()->set('services.sftp.allowed_roots', ['/home/runcloud']);
        $localArchive = tempnam(sys_get_temp_dir(), 'archive-');
        $this->assertIsString($localArchive);
        file_put_contents($localArchive, 'zip contents');

        try {
            $client = Mockery::mock(SFTP::class);
            $client->shouldReceive('is_dir')
                ->once()
                ->with('/home/runcloud/webapps/example')
                ->andReturnTrue();
            $client->shouldReceive('put')
                ->once()
                ->with(
                    '/home/runcloud/webapps/example/archive-1.zip',
                    $localArchive,
                    SFTP::SOURCE_LOCAL_FILE,
                )
                ->andReturnTrue();
            $client->shouldReceive('is_file')
                ->once()
                ->with('/home/runcloud/webapps/example/archive-1.zip')
                ->andReturnTrue();
            $client->shouldReceive('is_dir')
                ->once()
                ->with('/home/runcloud/webapps/example/public_html')
                ->andReturnTrue();
            $client->shouldReceive('exec')->once()->andReturn("index.php\n");
            $client->shouldReceive('getExitStatus')->once()->andReturn(0);
            $client->shouldReceive('is_file')->twice()->andReturnFalse();
            $client->shouldReceive('exec')->once()->andReturn('');
            $client->shouldReceive('getExitStatus')->once()->andReturn(0);
            $client->shouldReceive('delete')
                ->once()
                ->with('/home/runcloud/webapps/example/archive-1.zip')
                ->andReturnTrue();

            $factory = Mockery::mock(SftpConnectionFactory::class);
            $factory->shouldReceive('connect')->once()->with('192.0.2.10')->andReturn($client);

            $result = (new SftpArchiveService($factory))->uploadAndExtract(
                '192.0.2.10',
                $localArchive,
                '/home/runcloud/webapps/example/archive-1.zip',
                '/home/runcloud/webapps/example/public_html',
            );

            $this->assertTrue($result['archive_uploaded']);
            $this->assertTrue($result['archive_deleted']);
        } finally {
            @unlink($localArchive);
        }
    }

    public function test_it_extracts_a_valid_archive_and_optionally_deletes_it(): void
    {
        config()->set('services.sftp.allowed_roots', ['/home/runcloud']);

        $client = Mockery::mock(SFTP::class);
        $client->shouldReceive('is_file')->once()->with("/home/runcloud/site's archive.zip")->andReturnTrue();
        $client->shouldReceive('is_dir')->once()->with('/home/runcloud/webapps/example/public_html')->andReturnFalse();
        $client->shouldReceive('mkdir')->once()->with('/home/runcloud/webapps/example/public_html', -1, true)->andReturnTrue();
        $client->shouldReceive('exec')
            ->once()
            ->with("unzip -Z1 '/home/runcloud/site'\"'\"'s archive.zip'")
            ->andReturn("index.html\nassets/app.css\n");
        $client->shouldReceive('getExitStatus')->once()->andReturn(0);
        $client->shouldReceive('is_file')
            ->once()
            ->with('/home/runcloud/webapps/example/public_html/index.html')
            ->andReturnTrue();
        $client->shouldReceive('delete')
            ->once()
            ->with('/home/runcloud/webapps/example/public_html/index.html')
            ->andReturnTrue();
        $client->shouldReceive('is_file')
            ->once()
            ->with('/home/runcloud/webapps/example/public_html/index.htm')
            ->andReturnFalse();
        $client->shouldReceive('exec')
            ->once()
            ->with("unzip -oq '/home/runcloud/site'\"'\"'s archive.zip' -d '/home/runcloud/webapps/example/public_html'")
            ->andReturn('');
        $client->shouldReceive('getExitStatus')->once()->andReturn(0);
        $client->shouldReceive('delete')->once()->with("/home/runcloud/site's archive.zip")->andReturnTrue();

        $factory = Mockery::mock(SftpConnectionFactory::class);
        $factory->shouldReceive('connect')->once()->with('192.0.2.10')->andReturn($client);

        $result = (new SftpArchiveService($factory))->extract(
            '192.0.2.10',
            "/home/runcloud/site's archive.zip",
            '/home/runcloud/webapps/example/public_html',
            true,
        );

        $this->assertSame(2, $result['entries_extracted']);
        $this->assertTrue($result['archive_deleted']);
        $this->assertSame(
            ['/home/runcloud/webapps/example/public_html/index.html'],
            $result['deleted_index_files'],
        );
    }

    public function test_it_rejects_zip_slip_entries_before_extraction(): void
    {
        config()->set('services.sftp.allowed_roots', ['/home/runcloud']);

        $client = Mockery::mock(SFTP::class);
        $client->shouldReceive('is_file')->once()->andReturnTrue();
        $client->shouldReceive('is_dir')->once()->andReturnTrue();
        $client->shouldReceive('exec')->once()->andReturn("index.html\n../outside.php\n");
        $client->shouldReceive('getExitStatus')->once()->andReturn(0);

        $factory = Mockery::mock(SftpConnectionFactory::class);
        $factory->shouldReceive('connect')->once()->andReturn($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The ZIP contains an unsafe file path.');

        (new SftpArchiveService($factory))->extract(
            '192.0.2.10',
            '/home/runcloud/archive.zip',
            '/home/runcloud/webapps/example/public_html',
        );
    }

    public function test_it_can_preserve_existing_index_files_when_requested(): void
    {
        config()->set('services.sftp.allowed_roots', ['/home/runcloud']);

        $client = Mockery::mock(SFTP::class);
        $client->shouldReceive('is_file')->once()->with('/home/runcloud/archive.zip')->andReturnTrue();
        $client->shouldReceive('is_dir')->once()->andReturnTrue();
        $client->shouldReceive('exec')->once()->andReturn("index.php\n");
        $client->shouldReceive('getExitStatus')->once()->andReturn(0);
        $client->shouldReceive('exec')->once()->andReturn('');
        $client->shouldReceive('getExitStatus')->once()->andReturn(0);
        $client->shouldNotReceive('delete');

        $factory = Mockery::mock(SftpConnectionFactory::class);
        $factory->shouldReceive('connect')->once()->andReturn($client);

        $result = (new SftpArchiveService($factory))->extract(
            '192.0.2.10',
            '/home/runcloud/archive.zip',
            '/home/runcloud/webapps/example/public_html',
            false,
            false,
        );

        $this->assertSame([], $result['deleted_index_files']);
    }

    public function test_it_rejects_paths_outside_the_configured_remote_roots(): void
    {
        config()->set('services.sftp.allowed_roots', ['/home/runcloud']);
        $factory = Mockery::mock(SftpConnectionFactory::class);
        $factory->shouldNotReceive('connect');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The remote path is outside the configured SFTP roots.');

        (new SftpArchiveService($factory))->extract(
            '192.0.2.10',
            '/tmp/archive.zip',
            '/home/runcloud/webapps/example/public_html',
        );
    }
}
