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
        putenv('PYTHONPATH=/tmp/custom');
        putenv('VIRTUAL_ENV=/venv');

        try {
            $env = PythonProcessEnv::build();

            self::assertArrayNotHasKey('PYTHONPATH', $env);
            self::assertArrayNotHasKey('VIRTUAL_ENV', $env);
            self::assertArrayNotHasKey('PYTHONHOME', $env);
            self::assertArrayNotHasKey('PYTHONUSERBASE', $env);
            self::assertArrayNotHasKey('PYTHONNOUSERSITE', $env);
        } finally {
            putenv('PYTHONPATH');
            putenv('VIRTUAL_ENV');
        }
    }

    public function testBuildIncludesPath(): void
    {
        $env = PythonProcessEnv::build();

        self::assertArrayHasKey('PATH', $env);
        self::assertStringStartsWith('/usr/local/bin:/usr/bin:/bin', $env['PATH']);
    }

    public function testBuildAppendsExistingPath(): void
    {
        putenv('PATH=/usr/local/sbin:/custom/bin');

        try {
            $env = PythonProcessEnv::build();

            self::assertStringContainsString('/usr/local/bin:/usr/bin:/bin', $env['PATH']);
            self::assertStringContainsString('/usr/local/sbin:/custom/bin', $env['PATH']);
        } finally {
            putenv('PATH');
        }
    }

    public function testBuildFiltersFalseValues(): void
    {
        $env = PythonProcessEnv::build();

        foreach ($env as $value) {
            self::assertNotSame(false, $value);
        }
    }

    public function testBuildWhenPathIsUnsetStillPrependsSystemPath(): void
    {
        $originalPath = getenv('PATH');
        if (false !== $originalPath) {
            putenv('PATH');
        }
        try {
            $env = PythonProcessEnv::build();
            self::assertArrayHasKey('PATH', $env);
            self::assertStringStartsWith('/usr/local/bin:/usr/bin:/bin', $env['PATH']);
        } finally {
            if (false !== $originalPath) {
                putenv('PATH='.$originalPath);
            }
        }
    }
}
