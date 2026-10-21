<?php

namespace Laravel\Sail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class PhpSecurityMinimumTest extends TestCase
{
    public function test_rejects_releases_before_the_php2shell_fixes(): void
    {
        $path = __DIR__.'/../../runtimes/8.x/check-php-security.php';
        $this->assertFileExists($path);
        $check = require $path;

        foreach (['7.4.33', '8.0.30', '8.1.34', '8.2.34', '8.3.35', '8.4.26', '8.5.11', '8.4.27RC1', '8.6.0RC3', 'invalid'] as $version) {
            try {
                $check($version);
                $this->fail('Accepted unverified PHP '.$version);
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('CVE-2026-109618', $exception->getMessage());
                $this->assertStringContainsString('CVE-2026-109619', $exception->getMessage());
            }
        }
    }

    public function test_accepts_fixed_releases_and_later_patches_on_each_supported_branch(): void
    {
        $path = __DIR__.'/../../runtimes/8.x/check-php-security.php';
        $this->assertFileExists($path);
        $check = require $path;

        foreach (['8.2.35', '8.2.36', '8.3.36', '8.3.37', '8.4.27', '8.4.28', '8.5.12', '8.5.13'] as $version) {
            $this->assertNull($check($version), $version);
        }
    }
}
