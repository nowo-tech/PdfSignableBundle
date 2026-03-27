<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Command;

use Nowo\PdfSignableBundle\Checker\DependencyCheckerInterface;
use Nowo\PdfSignableBundle\Command\CheckDependenciesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckDependenciesCommandTest extends TestCase
{
    private function createCheckerStub(array $checkResult): DependencyCheckerInterface
    {
        $checker = $this->createMock(DependencyCheckerInterface::class);
        $checker->method('check')->willReturn($checkResult);

        return $checker;
    }

    public function testCommandNameAndDescription(): void
    {
        $checker = $this->createCheckerStub(['failures' => [], 'warnings' => []]);

        $command = new CheckDependenciesCommand($checker);
        self::assertSame('nowo_pdf_signable:check-dependencies', $command->getName());
        self::assertStringContainsString('dependencies', $command->getDescription());
    }

    public function testCommandSucceedsWhenNoFailures(): void
    {
        $checker = $this->createCheckerStub(['failures' => [], 'warnings' => []]);

        $command = new CheckDependenciesCommand($checker);
        $input   = new ArrayInput([]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        self::assertSame(0, $code);
        $text = $output->fetch();
        self::assertStringContainsString('All required dependencies are installed', $text);
    }

    public function testCommandFailsWhenThereAreFailures(): void
    {
        $checker = $this->createCheckerStub([
            'failures' => ['Missing required PHP extensions: foo'],
            'warnings' => [],
        ]);

        $command = new CheckDependenciesCommand($checker);
        $input   = new ArrayInput([]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        self::assertSame(1, $code);
        $text = $output->fetch();
        self::assertStringContainsString('Missing required PHP extensions: foo', $text);
    }

    public function testCommandStrictFailsOnWarnings(): void
    {
        $checker = $this->createCheckerStub([
            'failures' => [],
            'warnings' => ['Optional PHP extensions not loaded: yaml'],
        ]);

        $command = new CheckDependenciesCommand($checker);
        $input   = new ArrayInput(['--strict' => true]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        self::assertSame(1, $code);
        $text = $output->fetch();
        self::assertStringContainsString('Strict mode', $text);
    }

    public function testCommandSucceedsWithWarningsWhenNotStrict(): void
    {
        $checker = $this->createCheckerStub([
            'failures' => [],
            'warnings' => ['Bundle public dir not found. Run assets:install.'],
        ]);

        $command = new CheckDependenciesCommand($checker);
        $input   = new ArrayInput([]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        self::assertSame(0, $code);
        $text = $output->fetch();
        self::assertStringContainsString('Bundle public dir not found', $text);
        self::assertStringContainsString('All required dependencies are installed', $text);
    }
}
