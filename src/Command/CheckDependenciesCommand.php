<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Command;

use Nowo\PdfSignableBundle\Checker\DependencyCheckerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Verifies that PHP, extensions, optional tools (Python) and bundle assets are ready for PdfSignable.
 *
 * Run after composer install and when enabling AcroForm scripts:
 *   php bin/console nowo_pdf_signable:check-dependencies
 */
#[AsCommand(
    name: 'nowo_pdf_signable:check-dependencies',
    description: 'Check that all required and optional dependencies for PdfSignable bundle are installed',
)]
final class CheckDependenciesCommand extends Command
{
    public function __construct(
        private readonly DependencyCheckerInterface $checker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with error code if any optional check fails')
            ->setHelp(
                <<<'HELP'
                This command checks:

                  • PHP version (>= 8.1)
                  • Required PHP extensions (json, mbstring, ctype, xml, fileinfo)
                  • Optional PHP extensions (yaml, for faster config parsing)
                  • AcroForm Python scripts: if configured, verifies the script path exists and
                    the script command (e.g. python3) is available; optionally checks pypdf.
                  • Bundle assets: pdf-signable.js and pdf.worker.min.js in public/bundles/nowopdfsignable/

                Run after <info>composer install</info> and <info>php bin/console assets:install</info>.
                If you use AcroForm with Python scripts, ensure <info>python3</info> and <info>pypdf</info> are installed.
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $strict = (bool) $input->getOption('strict');

        $io->title('PdfSignable bundle — dependency check');

        $result   = $this->checker->check();
        $failures = $result['failures'];
        $warnings = $result['warnings'];

        foreach ($warnings as $w) {
            $io->warning($w);
        }
        foreach ($failures as $f) {
            $io->error($f);
        }

        if ($failures !== []) {
            return Command::FAILURE;
        }
        if ($strict && $warnings !== []) {
            $io->error('Strict mode: optional checks failed.');

            return Command::FAILURE;
        }

        $io->success('All required dependencies are installed.');

        return Command::SUCCESS;
    }
}
