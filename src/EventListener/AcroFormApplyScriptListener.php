<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\EventListener;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldPatch;
use Nowo\PdfSignableBundle\AcroForm\PythonProcessEnv;
use Nowo\PdfSignableBundle\Event\AcroFormApplyRequestEvent;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Process\Process;

/**
 * Runs the configured Python apply script when ACROFORM_APPLY_REQUEST is dispatched.
 *
 * Only runs if no other listener has set a response on the event and apply_script is configured.
 * Flow:
 * 1. Writes event PDF contents and patches (as JSON) to two temporary files.
 * 2. Invokes: <apply_script_command> <script> --pdf <path> --patches <path> [--dry-run if validate_only].
 * 3. Script must read those files and write the modified PDF to stdout (binary). Stdout is captured
 *    and set on the event as the modified PDF; temp files are removed.
 * 4. On validate_only (dry-run), script must output JSON to stdout instead of PDF.
 *
 * On failure, sets error and error detail on the event and logs stderr/stdout.
 *
 * @internal Part of the bundle API
 */
#[AsEventListener(event: PdfSignableEvents::ACROFORM_APPLY_REQUEST, priority: -100)]
final class AcroFormApplyScriptListener
{
    /**
     * @param string|null $applyScript        Absolute path to the Python script (from acroform.apply_script)
     * @param string      $applyScriptCommand Executable to run the script (e.g. python3 or /usr/bin/python3)
     * @param LoggerInterface|null $logger   Optional logger for apply flow (script run, success, stderr)
     */
    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.acroform.apply_script')]
        private readonly ?string $applyScript,
        #[Autowire(param: 'nowo_pdf_signable.acroform.apply_script_command')]
        private readonly string $applyScriptCommand,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Runs the apply script with temp PDF and patches; sets modified PDF or error on the event.
     *
     * Does nothing if the event already has a response (validation result, error, or modified PDF),
     * or if apply_script is not set. Otherwise writes temp files, runs the process, and sets
     * setModifiedPdf(output), setValidationResult(...), or setError(...) / setErrorDetail(...).
     *
     * @param AcroFormApplyRequestEvent $event Event carrying PDF contents, patches, and validate_only flag
     */
    public function __invoke(AcroFormApplyRequestEvent $event): void
    {
        if ($event->hasResponse() || null === $this->applyScript || '' === trim($this->applyScript)) {
            return;
        }

        $script = trim($this->applyScript);
        if (!is_file($script) || !is_readable($script)) {
            $event->setError(new \RuntimeException('Apply script not found or not readable: '.$script));

            return;
        }

        $pdfContents = $event->getPdfContents();
        $patches = $event->getPatches();
        $patchesArray = array_map(static fn (AcroFormFieldPatch $p) => $p->toArray(), $patches);

        $this->logger?->info('AcroForm apply: running script', [
            'pdf_bytes' => \strlen($pdfContents),
            'patches_count' => \count($patchesArray),
            'validate_only' => $event->isValidateOnly(),
        ]);

        $tmpPdf = tempnam(sys_get_temp_dir(), 'pdf_apply_');
        $tmpPatches = tempnam(sys_get_temp_dir(), 'patches_');
        if (false === $tmpPdf || false === $tmpPatches) {
            $event->setError(new \RuntimeException('Failed to create temp files'));

            return;
        }

        try {
            if (false === file_put_contents($tmpPdf, $pdfContents)) {
                throw new \RuntimeException('Failed to write temp PDF');
            }
            if (false === file_put_contents($tmpPatches, json_encode($patchesArray, JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('Failed to write temp patches');
            }

            $procArgs = [
                $this->applyScriptCommand,
                $script,
                '--pdf',
                $tmpPdf,
                '--patches',
                $tmpPatches,
            ];
            if ($event->isValidateOnly()) {
                $procArgs[] = '--dry-run';
            }
            $proc = new Process($procArgs, null, PythonProcessEnv::build());
            $proc->setTimeout(60);
            $proc->run();

            if (!$proc->isSuccessful()) {
                $stderr = $proc->getErrorOutput();
                $stdout = $proc->getOutput();
                $err = trim($stderr."\n".$stdout);
                $event->setErrorDetail($err);

                $this->logger?->error('AcroForm apply script failed', [
                    'exit_code' => $proc->getExitCode(),
                    'stderr' => $stderr,
                    'stdout' => $stdout,
                ]);

                if (str_contains(strtolower($err), 'not found') && str_contains(strtolower($err), 'python')) {
                    $event->setError(new \RuntimeException(
                        'Apply script failed: Python 3 is not installed or not in PATH. Install python3 on the server or configure a PHP-based editor (PdfAcroFormEditorInterface). You can set apply_script_command to the full path of your Python executable if needed.'
                    ));
                } else {
                    $event->setError(new \RuntimeException('Apply script failed: '.($err ?: 'unknown error')));
                }

                return;
            }

            $output = $proc->getOutput();

            if ($event->isValidateOnly()) {
                $decoded = json_decode($output, true);
                if (\is_array($decoded)) {
                    $event->setValidationResult($decoded);
                } else {
                    $event->setErrorDetail(trim($proc->getErrorOutput()."\n".$output));
                    $event->setError(new \RuntimeException('Dry-run script did not return valid JSON'));
                }

                return;
            }

            if ('' === $output) {
                $detail = trim($proc->getErrorOutput()."\n".$output);
                $event->setErrorDetail($detail);
                $this->logger?->error('AcroForm apply script produced no output', [
                    'stderr' => $proc->getErrorOutput(),
                    'stdout' => $output,
                ]);
                $event->setError(new \RuntimeException('Apply script produced no output'));

                return;
            }

            // Script must output raw PDF only (no debug text); otherwise response would be invalid
            if (!str_starts_with($output, '%PDF')) {
                $event->setErrorDetail(trim($proc->getErrorOutput()."\n".substr($output, 0, 500)));
                $this->logger?->error('AcroForm apply script stdout did not start with %PDF', [
                    'stdout_preview' => substr($output, 0, 200),
                ]);
                $event->setError(new \RuntimeException('Apply script did not return a valid PDF (stdout must be binary PDF only)'));

                return;
            }

            $stderr = $proc->getErrorOutput();
            $this->logger?->info('AcroForm apply: script succeeded', [
                'pdf_output_bytes' => \strlen($output),
                'script_stderr' => $stderr !== '' ? trim($stderr) : null,
            ]);

            $event->setModifiedPdf($output);
        } finally {
            @unlink($tmpPdf);
            @unlink($tmpPatches);
        }
    }
}
