<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\AcroForm;

use Nowo\PdfSignableBundle\AcroForm\PythonProcessEnv;
use PHPUnit\Framework\TestCase;

final class PythonProcessEnvTest extends TestCase
{
    public function testBuildReturnsArray(): void
    {
        $env = PythonProcessEnv::build();

        self::assertIsArray($env);
    }

    public function testBuildUnsetsPythonEnvVars(): void
    {
        $env = PythonProcessEnv::build([
            'PYTHONPATH'       => '/tmp/custom',
            'VIRTUAL_ENV'      => '/venv',
            'PYTHONHOME'       => '/home',
            'PYTHONUSERBASE'   => '/user',
            'PYTHONNOUSERSITE' => '1',
            'PATH'             => '/custom/bin',
            'KEEP'             => 'yes',
        ]);

        self::assertArrayNotHasKey('PYTHONPATH', $env);
        self::assertArrayNotHasKey('VIRTUAL_ENV', $env);
        self::assertArrayNotHasKey('PYTHONHOME', $env);
        self::assertArrayNotHasKey('PYTHONUSERBASE', $env);
        self::assertArrayNotHasKey('PYTHONNOUSERSITE', $env);
        self::assertSame('yes', $env['KEEP']);
    }

    public function testBuildIncludesPath(): void
    {
        $env = PythonProcessEnv::build(['FOO' => 'bar']);

        self::assertArrayHasKey('PATH', $env);
        self::assertStringStartsWith('/usr/local/bin:/usr/bin:/bin', $env['PATH']);
    }

    public function testBuildAppendsExistingPath(): void
    {
        $env = PythonProcessEnv::build(['PATH' => '/usr/local/sbin:/custom/bin']);

        self::assertStringContainsString('/usr/local/bin:/usr/bin:/bin', $env['PATH']);
        self::assertStringContainsString('/usr/local/sbin:/custom/bin', $env['PATH']);
    }

    public function testBuildFiltersFalseValues(): void
    {
        $env = PythonProcessEnv::build(['OK' => '1', 'BAD' => false]);

        foreach ($env as $value) {
            self::assertNotFalse($value);
        }
        self::assertArrayNotHasKey('BAD', $env);
    }

    public function testBuildWhenPathIsUnsetStillPrependsSystemPath(): void
    {
        $env = PythonProcessEnv::build(['KEEP' => '1']);
        self::assertArrayHasKey('PATH', $env);
        self::assertStringStartsWith('/usr/local/bin:/usr/bin:/bin', $env['PATH']);
    }

    public function testBuildReturnsEmptyArrayWhenSourceEnvIsNotArray(): void
    {
        $env = PythonProcessEnv::build('not-an-array');
        self::assertSame([], $env);
    }
}
