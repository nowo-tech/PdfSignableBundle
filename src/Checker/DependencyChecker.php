<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Checker;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

use function extension_loaded;
use function is_string;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const PHP_VERSION;

/**
 * Runs dependency checks for the PdfSignable bundle (PHP, extensions, AcroForm scripts, assets).
 * Used by the console command and by the debug listener when rendering form pages.
 */
final class DependencyChecker implements DependencyCheckerInterface
{
    private const MIN_PHP_VERSION = '8.1.0';

    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = ['json', 'mbstring', 'ctype', 'xml', 'fileinfo'];

    /** @var list<string> */
    private const OPTIONAL_EXTENSIONS = ['yaml'];

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * Runs all checks. Returns failures (required missing) and warnings (optional missing).
     *
     * @return array{failures: list<string>, warnings: list<string>}
     */
    public function check(): array
    {
        $failures = [];
        $warnings = [];

        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            $failures[] = sprintf('PHP version must be >= %s (current: %s)', self::MIN_PHP_VERSION, PHP_VERSION);
        }

        $missingRequired = [];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            if (!extension_loaded($ext)) {
                $missingRequired[] = $ext;
            }
        }
        if ($missingRequired !== []) {
            $failures[] = 'Missing required PHP extensions: ' . implode(', ', $missingRequired);
        }

        $missingOptional = [];
        foreach (self::OPTIONAL_EXTENSIONS as $ext) {
            if (!extension_loaded($ext)) {
                $missingOptional[] = $ext;
            }
        }
        if ($missingOptional !== []) {
            $warnings[] = 'Optional PHP extensions not loaded (recommended): ' . implode(', ', $missingOptional);
        }

        $this->checkAcroFormScripts($failures, $warnings);
        $this->checkBundleAssets($failures, $warnings);

        return ['failures' => $failures, 'warnings' => $warnings];
    }

    /**
     * @param list<string> $failures
     * @param list<string> $warnings
     */
    private function checkAcroFormScripts(array &$failures, array &$warnings): void
    {
        if (!$this->params->has('nowo_pdf_signable.acroform.enabled') || !$this->params->get('nowo_pdf_signable.acroform.enabled')) {
            return;
        }

        $applyScript     = $this->params->get('nowo_pdf_signable.acroform.apply_script');
        $processScript   = $this->params->get('nowo_pdf_signable.acroform.process_script');
        $extractorScript = $this->params->get('nowo_pdf_signable.acroform.fields_extractor_script');
        $applyCommand    = $this->params->get('nowo_pdf_signable.acroform.apply_script_command');
        $processCommand  = $this->params->get('nowo_pdf_signable.acroform.process_script_command');

        $scripts = [
            'apply_script'            => is_string($applyScript) ? trim($applyScript) : null,
            'process_script'          => is_string($processScript) ? trim($processScript) : null,
            'fields_extractor_script' => is_string($extractorScript) ? trim($extractorScript) : null,
        ];

        $commands = [
            'apply'   => is_string($applyCommand) ? trim($applyCommand) : 'python3',
            'process' => is_string($processCommand) ? trim($processCommand) : 'python3',
        ];

        foreach ($scripts as $name => $path) {
            if ($path === '' || $path === null) {
                continue;
            }
            if (!is_file($path) || !is_readable($path)) {
                $failures[] = "AcroForm script configured but not found or not readable: {$name} = {$path}";
                continue;
            }
            $cmd      = ($name === 'apply_script') ? $commands['apply'] : (($name === 'process_script') ? $commands['process'] : 'python3');
            $finder   = new ExecutableFinder();
            $resolved = $finder->find($cmd);
            if ($resolved === null) {
                $failures[] = "AcroForm script command not found in PATH: {$cmd} (used for {$name})";
            }
        }

        $anyScriptSet = ($scripts['apply_script'] ?? '') !== '' || ($scripts['process_script'] ?? '') !== '' || ($scripts['fields_extractor_script'] ?? '') !== '';
        if ($anyScriptSet) {
            $this->checkPypdfAvailable($warnings, $commands['apply']);
        }
    }

    /**
     * @param list<string> $warnings
     */
    private function checkPypdfAvailable(array &$warnings, string $pythonCommand): void
    {
        $finder   = new ExecutableFinder();
        $resolved = $finder->find($pythonCommand);
        if ($resolved === null) {
            return;
        }
        $proc = new Process([$resolved, '-c', 'import pypdf; print("ok")']);
        $proc->setTimeout(5);
        $proc->run();
        if (!$proc->isSuccessful()) {
            $warnings[] = 'Python pypdf module not installed. Install with: ' . $pythonCommand . ' -m pip install pypdf (required for AcroForm apply/extract scripts)';
        }
    }

    /**
     * @param list<string> $failures
     * @param list<string> $warnings
     */
    private function checkBundleAssets(array &$failures, array &$warnings): void
    {
        $projectDir   = $this->kernel->getProjectDir();
        $publicDir    = $projectDir . DIRECTORY_SEPARATOR . 'public';
        $bundlePublic = $publicDir . DIRECTORY_SEPARATOR . 'bundles' . DIRECTORY_SEPARATOR . 'nowopdfsignable';
        $jsDir        = $bundlePublic . DIRECTORY_SEPARATOR . 'js';

        if (!is_dir($bundlePublic)) {
            $warnings[] = 'Bundle public dir not found. Run: php bin/console assets:install — Expected: ' . $bundlePublic;

            return;
        }

        $foundWorker = false;
        foreach (['pdf.worker.min.js', 'pdf.worker.min.mjs'] as $workerFile) {
            $path = $jsDir . DIRECTORY_SEPARATOR . $workerFile;
            if (is_file($path) && is_readable($path)) {
                $foundWorker = true;
                break;
            }
        }
        if (!$foundWorker) {
            $warnings[] = 'Bundle asset missing. Run: php bin/console assets:install — Expected: ' . $jsDir . DIRECTORY_SEPARATOR . 'pdf.worker.min.js or pdf.worker.min.mjs';
        }
        $mainPath = $jsDir . DIRECTORY_SEPARATOR . 'pdf-signable.js';
        if (!is_file($mainPath) || !is_readable($mainPath)) {
            $warnings[] = "Bundle asset missing. Run: php bin/console assets:install — Expected: {$mainPath}";
        }
    }
}
