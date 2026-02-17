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
}
