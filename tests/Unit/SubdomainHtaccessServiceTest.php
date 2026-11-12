<?php

namespace Tests\Unit;

use App\Services\Deployment\SubdomainHtaccessService;
use App\Services\Sftp\SftpFileService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SubdomainHtaccessServiceTest extends TestCase
{
    public function test_it_escapes_dynamic_domains_and_all_tlds(): void
    {
        $service = new SubdomainHtaccessService(Mockery::mock(SftpFileService::class));

        $this->assertSame('example\.com', $service->escapeDomainForRegex('example.com'));
        $this->assertSame('portal\.domain\.co\.uk', $service->escapeDomainForRegex('portal.domain.co.uk'));
        $this->assertSame('my-site\.com\.ph', $service->escapeDomainForRegex('https://my-site.com.ph/path'));
        $this->assertSame('app\.net', $service->escapeDomainForRegex('http://app.net:8080/'));
    }

    public function test_it_sanitizes_folder_names_and_skips_invalid(): void
    {
        $service = new SubdomainHtaccessService(Mockery::mock(SftpFileService::class));

        $result = $service->sanitizeFolders([
            'blog',
            'shop-sub',
            'valid-name-123',
            'INVALID FOLDER!',
            '../traversal',
            '---',
            '',
        ]);

        $this->assertSame(['blog', 'shop', 'valid-name-123'], $result['subdomains']);
        $this->assertCount(3, $result['warnings']);
    }

    public function test_it_builds_expected_routing_rules_block(): void
    {
        $service = new SubdomainHtaccessService(Mockery::mock(SftpFileService::class));

        $block = $service->buildRoutingBlock('example.com', ['blog', 'shop']);

        $expected = implode("\n", [
            '# BEGIN Subdomain Routing',
            '# Route subdomain to folder: blog',
            'RewriteCond %{HTTP_HOST} ^blog\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/blog-sub(/|$)',
            'RewriteRule ^(.*)$ /blog-sub/$1 [END]',
            '',
            '# Route subdomain to folder: shop',
            'RewriteCond %{HTTP_HOST} ^shop\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/shop-sub(/|$)',
            'RewriteRule ^(.*)$ /shop-sub/$1 [END]',
            '# END Subdomain Routing',
        ]);

        $this->assertSame($expected, $block);
    }

    public function test_it_inserts_rules_immediately_after_first_closing_ifmodule_tag(): void
    {
        $service = new SubdomainHtaccessService(Mockery::mock(SftpFileService::class));

        $existing = implode("\n", [
            '<IfModule mod_rewrite.c>',
            '  RewriteEngine On',
            '  # existing rules...',
            '</IfModule>',
            '',
            '# other subsequent rules',
        ]);

        $result = $service->injectRules($existing, 'example.com', ['blog', 'shop']);

        $this->assertTrue($result['has_if_module']);
        $this->assertNull($result['notice']);

        $expected = implode("\n", [
            '<IfModule mod_rewrite.c>',
            '  RewriteEngine On',
            '  # existing rules...',
            '</IfModule>',
            '',
            '# BEGIN Subdomain Routing',
            '# Route subdomain to folder: blog',
            'RewriteCond %{HTTP_HOST} ^blog\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/blog-sub(/|$)',
            'RewriteRule ^(.*)$ /blog-sub/$1 [END]',
            '',
            '# Route subdomain to folder: shop',
            'RewriteCond %{HTTP_HOST} ^shop\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/shop-sub(/|$)',
            'RewriteRule ^(.*)$ /shop-sub/$1 [END]',
            '# END Subdomain Routing',
            '',
            '# other subsequent rules',
        ]);

        $this->assertSame($expected, $result['content']);
    }

    public function test_it_appends_to_end_of_file_when_no_ifmodule_exists_and_sets_notice(): void
    {
        $service = new SubdomainHtaccessService(Mockery::mock(SftpFileService::class));

        $existing = "Options -Indexes\nDirectoryIndex index.php";

        $result = $service->injectRules($existing, 'example.com', ['blog']);

        $this->assertFalse($result['has_if_module']);
        $this->assertStringContainsString('No </IfModule> tag found in .htaccess', $result['notice']);

        $expected = implode("\n", [
            'Options -Indexes',
            'DirectoryIndex index.php',
            '',
            '# BEGIN Subdomain Routing',
            '# Route subdomain to folder: blog',
            'RewriteCond %{HTTP_HOST} ^blog\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/blog-sub(/|$)',
            'RewriteRule ^(.*)$ /blog-sub/$1 [END]',
            '# END Subdomain Routing',
            '',
        ]);

        $this->assertSame($expected, $result['content']);
    }

    public function test_it_is_idempotent_and_updates_without_duplicates(): void
    {
        $service = new SubdomainHtaccessService(Mockery::mock(SftpFileService::class));

        $initial = implode("\n", [
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            '</IfModule>',
        ]);

        // First apply with blog
        $first = $service->injectRules($initial, 'example.com', ['blog']);
        // Second apply with blog and shop
        $second = $service->injectRules($first['content'], 'example.com', ['blog', 'shop']);

        $this->assertSame(1, substr_count($second['content'], '# BEGIN Subdomain Routing'));
        $this->assertSame(1, substr_count($second['content'], '# END Subdomain Routing'));
        $this->assertSame(1, substr_count($second['content'], '# Route subdomain to folder: blog'));
        $this->assertSame(1, substr_count($second['content'], '# Route subdomain to folder: shop'));
    }

    public function test_unchecking_subfolder_removes_its_block(): void
    {
        $service = new SubdomainHtaccessService(Mockery::mock(SftpFileService::class));

        $withTwoFolders = implode("\n", [
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            '</IfModule>',
            '',
            '# BEGIN Subdomain Routing',
            '# Route subdomain to folder: blog',
            'RewriteCond %{HTTP_HOST} ^blog\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/blog-sub(/|$)',
            'RewriteRule ^(.*)$ /blog-sub/$1 [END]',
            '',
            '# Route subdomain to folder: shop',
            'RewriteCond %{HTTP_HOST} ^shop\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/shop-sub(/|$)',
            'RewriteRule ^(.*)$ /shop-sub/$1 [END]',
            '# END Subdomain Routing',
        ]);

        // Now only shop is checked (blog is unchecked)
        $updated = $service->injectRules($withTwoFolders, 'example.com', ['shop']);

        $this->assertStringNotContainsString('folder: blog', $updated['content']);
        $this->assertStringContainsString('folder: shop', $updated['content']);
        $this->assertSame(1, substr_count($updated['content'], '# BEGIN Subdomain Routing'));

        // If both are unchecked, the entire managed block is removed
        $removedAll = $service->injectRules($updated['content'], 'example.com', []);
        $this->assertStringNotContainsString('# BEGIN Subdomain Routing', $removedAll['content']);
        $this->assertStringNotContainsString('# END Subdomain Routing', $removedAll['content']);
    }

    public function test_it_preserves_crlf_line_endings(): void
    {
        $service = new SubdomainHtaccessService(Mockery::mock(SftpFileService::class));

        $crlfContent = "<IfModule mod_rewrite.c>\r\nRewriteEngine On\r\n</IfModule>\r\n";

        $result = $service->injectRules($crlfContent, 'example.com', ['blog']);

        $this->assertStringContainsString("\r\n", $result['content']);
        $this->assertStringNotContainsString("<IfModule mod_rewrite.c>\n", $result['content']);
    }

    public function test_apply_creates_backup_and_preserves_permissions(): void
    {
        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.1', '/home/runcloud/webapps/app/public_html/.htaccess')
            ->once()
            ->andReturnTrue();

        $existingHtaccess = "<IfModule mod_rewrite.c>\nRewriteEngine On\n</IfModule>\n";
        $sftp->shouldReceive('readFile')
            ->with('192.0.2.1', '/home/runcloud/webapps/app/public_html/.htaccess')
            ->once()
            ->andReturn($existingHtaccess);

        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.1', Mockery::pattern('#/\.htaccess\.bak-\d{8}-\d{6}#'))
            ->once()
            ->andReturnFalse();

        $sftp->shouldReceive('getFilePermissions')
            ->with('192.0.2.1', '/home/runcloud/webapps/app/public_html/.htaccess')
            ->once()
            ->andReturn(0644);

        // Backup written first
        $sftp->shouldReceive('writeFile')
            ->once()
            ->with(
                '192.0.2.1',
                Mockery::pattern('#/\.htaccess\.bak-\d{8}-\d{6}#'),
                $existingHtaccess,
                0644,
            );

        // Updated file written next
        $sftp->shouldReceive('writeFile')
            ->once()
            ->with(
                '192.0.2.1',
                '/home/runcloud/webapps/app/public_html/.htaccess',
                Mockery::pattern('/# BEGIN Subdomain Routing/'),
                0644,
            );

        $service = new SubdomainHtaccessService($sftp);

        $result = $service->apply('192.0.2.1', '/home/runcloud/webapps/app/public_html/.htaccess', 'example.com', ['blog', 'shop']);

        $this->assertTrue($result['success']);
        $this->assertSame(['blog', 'shop'], $result['added']);
        $this->assertSame([], $result['removed']);
        $this->assertStringStartsWith('.htaccess.bak-', $result['backup_file']);
        $this->assertStringContainsString('Added: blog, shop', $result['message']);
    }

    public function test_apply_throws_clear_error_when_file_is_missing(): void
    {
        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.1', '/home/runcloud/webapps/app/public_html/.htaccess')
            ->once()
            ->andReturnFalse();

        $service = new SubdomainHtaccessService($sftp);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The .htaccess file does not exist on the server');

        $service->apply('192.0.2.1', '/home/runcloud/webapps/app/public_html/.htaccess', 'example.com', ['blog']);
    }
}
