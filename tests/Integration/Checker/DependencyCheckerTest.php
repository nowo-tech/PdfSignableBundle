<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Checker;

use Nowo\PdfSignableBundle\Checker\DependencyChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class DependencyCheckerTest extends TestCase
{
    public function testCheckReturnsFailuresAndWarningsKeys(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(false);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $checker = new DependencyChecker($params, $kernel);
        $result  = $checker->check();

        self::assertArrayHasKey('failures', $result);
        self::assertArrayHasKey('warnings', $result);
        self::assertIsArray($result['failures']);
        self::assertIsArray($result['warnings']);
    }

    public function testCheckWhenAcroFormDisabledDoesNotAddAcroFormFailures(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(false);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $checker = new DependencyChecker($params, $kernel);
        $result  = $checker->check();

        self::assertIsArray($result['failures']);
        foreach ($result['failures'] as $msg) {
            self::assertStringNotContainsString('AcroForm script', $msg);
        }
    }

    public function testCheckWhenAcroFormEnabledAndScriptMissingAddsFailure(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(true);
        $params->method('get')->willReturnMap([
            ['nowo_pdf_signable.acroform.enabled', true],
            ['nowo_pdf_signable.acroform.apply_script', '/nonexistent/apply_script.py'],
            ['nowo_pdf_signable.acroform.process_script', ''],
            ['nowo_pdf_signable.acroform.fields_extractor_script', ''],
            ['nowo_pdf_signable.acroform.apply_script_command', 'python3'],
            ['nowo_pdf_signable.acroform.process_script_command', 'python3'],
        ]);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $checker = new DependencyChecker($params, $kernel);
        $result  = $checker->check();

        self::assertNotEmpty($result['failures']);
        $found = false;
        foreach ($result['failures'] as $msg) {
            if (str_contains($msg, 'apply_script') && str_contains($msg, 'not found')) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected failure message about apply_script not found');
    }

    /**
     * @group integration
     */
    public function testCheckWhenBundlePublicDirMissingAddsWarning(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(false);
        $kernel     = $this->createMock(KernelInterface::class);
        $projectDir = sys_get_temp_dir() . '/pdfsignable-test-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($projectDir, 0o755, true));
        $kernel->method('getProjectDir')->willReturn($projectDir);
        try {
            $checker = new DependencyChecker($params, $kernel);
            $result  = $checker->check();
            self::assertNotEmpty($result['warnings']);
            $found = false;
            foreach ($result['warnings'] as $msg) {
                if (str_contains($msg, 'Bundle public dir not found') || str_contains($msg, 'assets:install')) {
                    $found = true;
                    break;
                }
            }
            self::assertTrue($found, 'Expected warning about bundle public dir or assets:install');
        } finally {
            rmdir($projectDir);
        }
    }

    /**
     * @group integration
     */
    public function testCheckRequiredExtensionsInFailureMessageWhenMissing(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(false);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $checker = new DependencyChecker($params, $kernel);
        $result  = $checker->check();

        self::assertArrayHasKey('failures', $result);
        if ($result['failures'] !== []) {
            $extMsg = null;
            foreach ($result['failures'] as $msg) {
                if (str_contains($msg, 'Missing required PHP extensions')) {
                    $extMsg = $msg;
                    break;
                }
            }
            self::assertNotNull($extMsg);
            self::assertStringContainsString('json', $extMsg);
        }
    }

    public function testCheckWhenAcroFormEnabledButAllScriptPathsEmptySkipsScriptChecks(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(true);
        $params->method('get')->willReturnMap([
            ['nowo_pdf_signable.acroform.enabled', true],
            ['nowo_pdf_signable.acroform.apply_script', ''],
            ['nowo_pdf_signable.acroform.process_script', ''],
            ['nowo_pdf_signable.acroform.fields_extractor_script', ''],
            ['nowo_pdf_signable.acroform.apply_script_command', 'python3'],
            ['nowo_pdf_signable.acroform.process_script_command', 'python3'],
        ]);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $checker = new DependencyChecker($params, $kernel);
        $result  = $checker->check();

        self::assertArrayHasKey('failures', $result);
        foreach ($result['failures'] as $msg) {
            self::assertStringNotContainsString('command not found in PATH', $msg);
        }
    }

    public function testCheckWhenAcroFormEnabledScriptExistsButCommandNotFoundAddsFailure(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pdfsign_');
        self::assertNotFalse($tmpFile);
        try {
            $params = $this->createMock(ParameterBagInterface::class);
            $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(true);
            $params->method('get')->willReturnMap([
                ['nowo_pdf_signable.acroform.enabled', true],
                ['nowo_pdf_signable.acroform.apply_script', $tmpFile],
                ['nowo_pdf_signable.acroform.process_script', ''],
                ['nowo_pdf_signable.acroform.fields_extractor_script', ''],
                ['nowo_pdf_signable.acroform.apply_script_command', 'nonexistent_python_cmd_xyz_123'],
                ['nowo_pdf_signable.acroform.process_script_command', 'python3'],
            ]);
            $kernel = $this->createMock(KernelInterface::class);
            $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

            $checker = new DependencyChecker($params, $kernel);
            $result  = $checker->check();

            $found = false;
            foreach ($result['failures'] as $msg) {
                if (str_contains($msg, 'command not found in PATH') && str_contains($msg, 'apply_script')) {
                    $found = true;
                    break;
                }
            }
            self::assertTrue($found, 'Expected failure about command not found for apply_script');
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * @group integration
     */
    public function testCheckWhenBundlePublicExistsButJsDirMissingAddsAssetWarnings(): void
    {
        $projectDir = sys_get_temp_dir() . '/pdfsignable-test-' . bin2hex(random_bytes(4));
        $publicDir  = $projectDir . '/public';
        $bundleDir  = $publicDir . '/bundles/nowopdfsignable';
        self::assertTrue(mkdir($bundleDir, 0o755, true));

        try {
            $params = $this->createMock(ParameterBagInterface::class);
            $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(false);
            $kernel = $this->createMock(KernelInterface::class);
            $kernel->method('getProjectDir')->willReturn($projectDir);

            $checker = new DependencyChecker($params, $kernel);
            $result  = $checker->check();

            self::assertNotEmpty($result['warnings']);
            $workerMsg = null;
            $mainMsg   = null;
            foreach ($result['warnings'] as $msg) {
                if (str_contains($msg, 'pdf.worker.min')) {
                    $workerMsg = $msg;
                }
                if (str_contains($msg, 'pdf-signable.js')) {
                    $mainMsg = $msg;
                }
            }
            self::assertNotNull($workerMsg, 'Expected warning about worker');
            self::assertNotNull($mainMsg, 'Expected warning about pdf-signable.js');
        } finally {
            self::rmdirRecursive($projectDir);
        }
    }

    /**
     * @group integration
     */
    public function testCheckWhenBundleAssetsExistDoesNotAddAssetWarnings(): void
    {
        $projectDir = sys_get_temp_dir() . '/pdfsignable-test-' . bin2hex(random_bytes(4));
        $jsDir      = $projectDir . '/public/bundles/nowopdfsignable/js';
        self::assertTrue(mkdir($jsDir, 0o755, true));
        self::assertNotFalse(file_put_contents($jsDir . '/pdf.worker.min.mjs', 'worker'));
        self::assertNotFalse(file_put_contents($jsDir . '/pdf-signable.js', 'main'));

        try {
            $params = $this->createMock(ParameterBagInterface::class);
            $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(false);
            $kernel = $this->createMock(KernelInterface::class);
            $kernel->method('getProjectDir')->willReturn($projectDir);

            $checker = new DependencyChecker($params, $kernel);
            $result  = $checker->check();

            foreach ($result['warnings'] as $msg) {
                self::assertStringNotContainsString('pdf.worker.min', $msg);
                self::assertStringNotContainsString('pdf-signable.js', $msg);
            }
        } finally {
            self::rmdirRecursive($projectDir);
        }
    }

    /**
     * @param non-empty-string $dir
     */
    private static function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::rmdirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testCheckAddsFailureWhenPhpVersionBelowMinimum(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(false);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $checker = new DependencyChecker($params, $kernel, null, '8.0.0');
        $result  = $checker->check();

        self::assertNotEmpty($result['failures']);
        self::assertTrue((bool) array_filter($result['failures'], static fn (string $msg): bool => str_contains($msg, 'PHP version must be >=')));
    }

    public function testCheckAddsFailureWhenRequiredExtensionMissingViaInjectedChecker(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(false);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $checker = new DependencyChecker(
            $params,
            $kernel,
            static fn (string $ext): bool => $ext !== 'json',
        );
        $result = $checker->check();

        self::assertTrue((bool) array_filter($result['failures'], static fn (string $msg): bool => str_contains($msg, 'Missing required PHP extensions') && str_contains($msg, 'json')));
    }

    public function testCheckAddsPypdfWarningWhenInjectedCheckerReturnsFalse(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pdfsign_');
        self::assertNotFalse($tmpFile);
        try {
            $params = $this->createMock(ParameterBagInterface::class);
            $params->method('has')->with('nowo_pdf_signable.acroform.enabled')->willReturn(true);
            $params->method('get')->willReturnMap([
                ['nowo_pdf_signable.acroform.enabled', true],
                ['nowo_pdf_signable.acroform.apply_script', $tmpFile],
                ['nowo_pdf_signable.acroform.process_script', ''],
                ['nowo_pdf_signable.acroform.fields_extractor_script', ''],
                ['nowo_pdf_signable.acroform.apply_script_command', 'python3'],
                ['nowo_pdf_signable.acroform.process_script_command', 'python3'],
            ]);
            $kernel = $this->createMock(KernelInterface::class);
            $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

            $checker = new DependencyChecker(
                $params,
                $kernel,
                null,
                null,
                static fn (string $pythonCommand): bool => false,
            );
            $result = $checker->check();

            self::assertTrue((bool) array_filter($result['warnings'], static fn (string $msg): bool => str_contains($msg, 'pypdf module not installed')));
        } finally {
            @unlink($tmpFile);
        }
    }
}
