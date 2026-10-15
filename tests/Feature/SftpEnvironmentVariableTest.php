<?php

namespace Tests\Feature;

use App\Models\EnvironmentVariable;
use App\Services\Sftp\SftpConnectionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SftpEnvironmentVariableTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_sftp_password_overrides_the_env_configuration(): void
    {
        config()->set('services.sftp.private_key', null);
        config()->set('services.sftp.private_key_path', null);
        config()->set('services.sftp.password', 'env-password');

        EnvironmentVariable::create([
            'key' => 'SFTP_PASSWORD',
            'value' => 'database-password',
            'type' => 'secret',
            'environments' => 'Production',
        ]);

        $method = new ReflectionMethod(SftpConnectionFactory::class, 'credential');

        $this->assertSame(
            'database-password',
            $method->invoke(app(SftpConnectionFactory::class)),
        );
    }

    public function test_env_sftp_password_is_used_when_no_database_override_exists(): void
    {
        config()->set('services.sftp.private_key', null);
        config()->set('services.sftp.private_key_path', null);
        config()->set('services.sftp.password', 'env-password');

        $method = new ReflectionMethod(SftpConnectionFactory::class, 'credential');

        $this->assertSame(
            'env-password',
            $method->invoke(app(SftpConnectionFactory::class)),
        );
    }
}
