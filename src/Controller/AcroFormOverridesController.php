<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Controller;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldPatch;
use Nowo\PdfSignableBundle\AcroForm\AcroFormOverrides;
use Nowo\PdfSignableBundle\AcroForm\Exception\AcroFormEditorException;
use Nowo\PdfSignableBundle\AcroForm\PdfAcroFormEditorInterface;
use Nowo\PdfSignableBundle\AcroForm\PythonProcessEnv;
use Nowo\PdfSignableBundle\AcroForm\Storage\AcroFormOverridesStorageInterface;
use Nowo\PdfSignableBundle\Event\AcroFormApplyRequestEvent;
use Nowo\PdfSignableBundle\Event\AcroFormModifiedPdfProcessedEvent;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * AcroForm overrides and PDF apply/process REST controller.
 *
 * Exposes:
 * - GET  /acroform/overrides         – Return stored overrides for a document_key
 * - POST /acroform/overrides         – Save overrides for a document_key
 * - DELETE /acroform/overrides       – Remove stored overrides for a document_key
 * - POST /acroform/overrides/load    – Load overrides and optionally fields (from body or Python extractor)
 * - POST /acroform/fields/extract    – Extract AcroForm field descriptors from a PDF (Python script)
 * - POST /acroform/apply             – Apply patches to a PDF and return the modified PDF (event or editor)
 * - POST /acroform/process          – Run process script on modified PDF and dispatch event
 *
 * All routes return 404 when acroform.enabled is false.
 * Apply returns 501 (Not Implemented) when allow_pdf_modify is false or no listener/editor sets the modified PDF.
 *
 * @internal Part of the bundle API
 */
#[AsController]
final class AcroFormOverridesController extends AbstractController
{
    /** Maximum allowed length for document_key. */
    private const DOCUMENT_KEY_MAX_LENGTH = 256;

    /**
     * @param bool                              $enabled               Whether the AcroForm editor endpoints are enabled
     * @param AcroFormOverridesStorageInterface $storage               Storage for overrides (session or custom service)
     * @param bool                              $allowPdfModify        Whether POST /acroform/apply is allowed
     * @param PdfAcroFormEditorInterface|null   $editor                Optional PHP-based editor to apply patches
     * @param EventDispatcherInterface          $eventDispatcher       Dispatcher for ACROFORM_APPLY_REQUEST and ACROFORM_MODIFIED_PDF_PROCESSED
     * @param TranslatorInterface               $translator            Used for error messages
     * @param list<string>                      $proxyUrlAllowlist     URL allowlist for pdf_url (when fetching PDFs)
     * @param int                               $maxPdfSize            Max PDF size in bytes for apply/process
     * @param int                               $maxPatches            Max number of patches per apply request
     * @param string|null                       $fieldsExtractorScript Path to Python script to extract AcroForm fields
     * @param string|null                       $processScript         Path to Python script to process modified PDF
     * @param string                            $processScriptCommand  Executable to run process_script (e.g. python3)
     * @param bool                              $debug                 When true, allow validate_only in apply (dry-run)
     * @param LoggerInterface|null              $logger                Optional logger for apply debug (when debug=true)
     */
    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.acroform.enabled')]
        private readonly bool $enabled,
        private readonly AcroFormOverridesStorageInterface $storage,
        #[Autowire(param: 'nowo_pdf_signable.acroform.allow_pdf_modify')]
        private readonly bool $allowPdfModify,
        private readonly ?PdfAcroFormEditorInterface $editor = null,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'nowo_pdf_signable.proxy_url_allowlist')]
        private readonly array $proxyUrlAllowlist,
        #[Autowire(param: 'nowo_pdf_signable.acroform.max_pdf_size')]
        private readonly int $maxPdfSize,
        #[Autowire(param: 'nowo_pdf_signable.acroform.max_patches')]
        private readonly int $maxPatches,
        #[Autowire(param: 'nowo_pdf_signable.acroform.fields_extractor_script')]
        private readonly ?string $fieldsExtractorScript = null,
        #[Autowire(param: 'nowo_pdf_signable.acroform.process_script')]
        private readonly ?string $processScript = null,
        #[Autowire(param: 'nowo_pdf_signable.acroform.process_script_command')]
        private readonly string $processScriptCommand = 'python3',
        #[Autowire(param: 'nowo_pdf_signable.debug')]
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Returns stored overrides for the given document_key.
     *
     * document_key can be passed as query parameter or (for consistency) in the request body.
     *
     * @param Request $request Request containing document_key (query or body)
     *
     * @return Response JSON with overrides and document_key, or 400 if document_key missing/invalid, 404 if not found
     */
    #[Route('/acroform/overrides', name: 'nowo_pdf_signable_acroform_overrides', methods: ['GET'])]
    public function getOverrides(Request $request): Response
    {
        if (!$this->enabled) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $documentKey = $this->resolveDocumentKey($request, null);
        if (null === $documentKey) {
            return new JsonResponse(['error' => 'document_key required'], Response::HTTP_BAD_REQUEST);
        }
        $overrides = $this->storage->get($documentKey);
        if (null === $overrides) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($overrides->toArray());
    }

    /**
     * Load overrides for a document_key and optionally resolve field list.
     *
     * Body: document_key (required), optional pdf_url or pdf_content, optional fields array.
     * - If "fields" is sent (e.g. from PDF.js in the browser), it is used and response includes them.
     * - Otherwise, if pdf_url or pdf_content is provided and fields_extractor_script is set,
     *   the Python script is run to extract AcroForm fields; response includes "fields" and merged overrides.
     * - If extraction fails, response includes fields_extractor_error (message only; overrides still returned).
     *
     * @param Request $request Request body must contain document_key; may contain pdf_url, pdf_content, or fields
     *
     * @return Response JSON with overrides, document_key, and optionally fields and fields_extractor_error
     */
    #[Route('/acroform/overrides/load', name: 'nowo_pdf_signable_acroform_overrides_load', methods: ['POST'])]
    public function loadOverrides(Request $request): Response
    {
        if (!$this->enabled) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $data = $request->toArray();
        $documentKey = $data['document_key'] ?? '';
        $documentKey = \is_string($documentKey) ? trim($documentKey) : '';
        if ('' === $documentKey || !$this->isValidDocumentKey($documentKey)) {
            return new JsonResponse(['error' => 'document_key required in body'], Response::HTTP_BAD_REQUEST);
        }

        $overrides = $this->storage->get($documentKey);
        $overridesData = null !== $overrides ? $overrides->toArray()['overrides'] ?? [] : [];
        $out = ['overrides' => $overridesData, 'document_key' => $documentKey];

        // 1) Use "fields" from request body if the frontend sent them (e.g. from PDF.js in the browser).
        $requestFields = $data['fields'] ?? null;
        $extractedFields = \is_array($requestFields) ? $requestFields : null;

        // 2) Otherwise try to extract from PDF via the Python script (when pdf_url/pdf_content and script are available).
        if (null === $extractedFields || 0 === \count($extractedFields)) {
            $pdfContents = $this->resolvePdfContentsFromRequest($request);
            if (null !== $pdfContents
                && null !== $this->fieldsExtractorScript
                && '' !== trim($this->fieldsExtractorScript)
                && \strlen($pdfContents) <= $this->maxPdfSize
            ) {
                $scriptPath = trim($this->fieldsExtractorScript);
                if (!is_file($scriptPath)) {
                    $out['fields_extractor_error'] = 'Fields extractor script not found (path: '.$scriptPath.'). Fields have been sent from the browser if available.';
                } else {
                    $tmpFile = null;
                    try {
                        $tmpFile = tempnam(sys_get_temp_dir(), 'pdfsignable_');
                        if (false !== $tmpFile && false !== file_put_contents($tmpFile, $pdfContents)) {
                            $process = new Process(['python3', $scriptPath, $tmpFile], null, PythonProcessEnv::build());
                            $process->setTimeout(60);
                            $process->run();
                            if ($process->isSuccessful()) {
                                $decoded = json_decode($process->getOutput(), true);
                                if (\is_array($decoded) && \count($decoded) > 0) {
                                    $extractedFields = $decoded;
                                }
                            } else {
                                $stderr = $process->getErrorOutput();
                                $exitCode = $process->getExitCode();
                                $hint = ('' !== trim($stderr)) ? trim($stderr) : ('exit code '.$exitCode);
                                $out['fields_extractor_error'] = 'Fields extractor (Python) could not run. Ensure python3 and pypdf are installed on the server. Detail: '.$hint;
                            }
                        }
                    } catch (\Throwable $e) {
                        $out['fields_extractor_error'] = 'Error running the fields extractor (e.g. python3 is not installed or not in PATH): '.$e->getMessage();
                    } finally {
                        if (null !== $tmpFile && is_file($tmpFile)) {
                            @unlink($tmpFile);
                        }
                    }
                }
            }
        }

        if (null !== $extractedFields && \count($extractedFields) > 0) {
            $out['fields'] = $extractedFields;
        }

        // Merge extracted field info into overrides so each key has the full field object (rect, width, height, fontSize, etc.)
        if (null !== $extractedFields && \count($extractedFields) > 0) {
            $byId = [];
            foreach ($extractedFields as $f) {
                $id = isset($f['id']) ? (string) $f['id'] : '';
                if ('' !== $id) {
                    $byId[$id] = $f;
                }
            }
            $fullOverrides = [];
            foreach ($overridesData as $id => $stored) {
                $base = $byId[$id] ?? [];
                $fullOverrides[$id] = \is_array($stored) ? array_merge($base, $stored) : $base;
            }
            // Include all extracted fields as keys with at least full info (user can add overrides later)
            foreach ($byId as $id => $fieldData) {
                if (!isset($fullOverrides[$id])) {
                    $fullOverrides[$id] = $fieldData;
                }
            }
            $out['overrides'] = $fullOverrides;
        }

        return new JsonResponse($out);
    }

    /**
     * Saves overrides for the given document_key.
     *
     * Body: document_key (required), overrides (object keyed by field id), optional fields array.
     * Overrides are stored per document_key; existing value is replaced.
     *
     * @param Request $request Request body with document_key, overrides, and optionally fields
     *
     * @return Response JSON with saved overrides and document_key, or 400 if document_key missing/invalid
     */
    #[Route('/acroform/overrides', name: 'nowo_pdf_signable_acroform_overrides_save', methods: ['POST'])]
    public function saveOverrides(Request $request): Response
    {
        if (!$this->enabled) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $data = $request->toArray();
        $documentKey = $data['document_key'] ?? $request->query->get('document_key') ?? '';
        $documentKey = \is_string($documentKey) ? trim($documentKey) : '';
        if ('' === $documentKey || !$this->isValidDocumentKey($documentKey)) {
            return new JsonResponse(['error' => 'Invalid or missing document_key'], Response::HTTP_BAD_REQUEST);
        }
        $overridesData = $data['overrides'] ?? [];
        if (!\is_array($overridesData)) {
            $overridesData = [];
        }
        $fields = $data['fields'] ?? null;
        if (null !== $fields && !\is_array($fields)) {
            $fields = null;
        }
        $overrides = new AcroFormOverrides($overridesData, $documentKey, $fields);
        $this->storage->set($documentKey, $overrides);

        return new JsonResponse($overrides->toArray(), Response::HTTP_OK);
    }

    /**
     * Extracts AcroForm field descriptors from a PDF via the configured Python extractor script.
     *
     * Body: pdf_url (must pass allowlist and SSRF checks) or pdf_content (base64).
     * The script is invoked with a single argument (path to a temporary PDF file); stdout must be JSON array.
     *
     * @param Request $request Request body with pdf_url or pdf_content
     *
     * @return Response JSON with "fields" array of field descriptors, or 400/404/503 on error
     */
    #[Route('/acroform/fields/extract', name: 'nowo_pdf_signable_acroform_fields_extract', methods: ['POST'])]
    public function extractFields(Request $request): Response
    {
        if (!$this->enabled || null === $this->fieldsExtractorScript || '' === trim($this->fieldsExtractorScript)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $scriptPath = trim($this->fieldsExtractorScript);
        if (!is_file($scriptPath)) {
            return new JsonResponse(['error' => 'Fields extractor script not found'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $pdfContents = $this->resolvePdfContentsFromRequest($request);
        if (null === $pdfContents) {
            return new JsonResponse(['error' => 'Provide pdf_url or pdf_content'], Response::HTTP_BAD_REQUEST);
        }
        if (\strlen($pdfContents) > $this->maxPdfSize) {
            return new JsonResponse(['error' => 'PDF too large'], Response::HTTP_BAD_REQUEST);
        }

        $tmpFile = null;
        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'pdfsignable_');
            if (false === $tmpFile) {
                return new JsonResponse(['error' => 'Failed to create temp file'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            if (false === file_put_contents($tmpFile, $pdfContents)) {
                return new JsonResponse(['error' => 'Failed to write temp PDF'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $process = new Process(['python3', $scriptPath, $tmpFile], null, PythonProcessEnv::build());
            $process->setTimeout(60);
            $process->run();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
            if (!$process->isSuccessful()) {
                return new JsonResponse([
                    'error' => 'Fields extractor failed',
                    'detail' => $stderr ?: $process->getExitCodeText(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } finally {
            if (null !== $tmpFile && is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }

        $decoded = json_decode($stdout, true);
        if (!\is_array($decoded)) {
            return new JsonResponse(['error' => 'Invalid extractor output', 'detail' => $stdout], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['fields' => $decoded]);
    }

    /**
     * Removes stored overrides for the given document_key.
     *
     * document_key can be passed as query parameter or in the request body.
     * No error is returned if no overrides existed for that key.
     *
     * @param Request $request Request containing document_key (query or body)
     *
     * @return Response 204 No Content on success, or 400 if document_key missing/invalid
     */
    #[Route('/acroform/overrides', name: 'nowo_pdf_signable_acroform_overrides_remove', methods: ['DELETE'])]
    public function removeOverrides(Request $request): Response
    {
        if (!$this->enabled) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $documentKey = $this->resolveDocumentKey($request, null);
        if (null === $documentKey) {
            return new JsonResponse(['error' => 'document_key required'], Response::HTTP_BAD_REQUEST);
        }
        $this->storage->remove($documentKey);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Applies AcroForm patches to a PDF and returns the modified PDF.
     *
     * Body: patches (array of patch objects with fieldId, optional fieldName, rect, defaultValue, etc.),
     * and pdf_url (allowlisted) or pdf_content (base64). When debug is true, validate_only can be set
     * to receive a JSON validation result instead of the PDF (dry-run).
     *
     * Dispatches ACROFORM_APPLY_REQUEST. If a listener (e.g. Python apply script) sets the modified PDF
     * on the event, that PDF is returned. Otherwise the configured editor service is used. If neither
     * provides a PDF, returns 501 Not Implemented.
     *
     * @param Request $request Request body with patches and pdf_url or pdf_content; optional validate_only when debug
     *
     * @return Response application/pdf with modified PDF, or JSON (validation/error), or 501 with plain text
     */
    #[Route('/acroform/apply', name: 'nowo_pdf_signable_acroform_apply', methods: ['POST'])]
    public function apply(Request $request): Response
    {
        if (!$this->enabled || !$this->allowPdfModify) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $data = $request->toArray();
        $patchesData = $data['patches'] ?? [];
        if (!\is_array($patchesData)) {
            return new JsonResponse(['error' => 'patches must be an array'], Response::HTTP_BAD_REQUEST);
        }
        if (\count($patchesData) > $this->maxPatches) {
            return new JsonResponse(['error' => 'Too many patches'], Response::HTTP_BAD_REQUEST);
        }
        $patches = [];
        foreach ($patchesData as $i => $p) {
            if (!\is_array($p)) {
                continue;
            }
            try {
                $patches[] = AcroFormFieldPatch::fromArray($p);
            } catch (\InvalidArgumentException $e) {
                return new JsonResponse(['error' => "Patch {$i}: ".$e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }

        $pdfContents = null;
        if (isset($data['pdf_url']) && \is_string($data['pdf_url'])) {
            $url = trim($data['pdf_url']);
            if ('' === $url || !filter_var($url, FILTER_VALIDATE_URL)) {
                return new JsonResponse(['error' => $this->translator->trans('proxy.invalid_url', [], 'nowo_pdf_signable')], Response::HTTP_BAD_REQUEST);
            }
            if ([] !== $this->proxyUrlAllowlist && !$this->isUrlAllowedByAllowlist($url)) {
                return new JsonResponse(['error' => $this->translator->trans('proxy.url_not_allowed', [], 'nowo_pdf_signable')], Response::HTTP_FORBIDDEN);
            }
            if ($this->isUrlBlockedForSsrf($url)) {
                return new JsonResponse(['error' => $this->translator->trans('proxy.url_not_allowed', [], 'nowo_pdf_signable')], Response::HTTP_FORBIDDEN);
            }
            try {
                $client = HttpClient::create();
                $response = $client->request('GET', $url, [
                    'timeout' => 30,
                    'max_redirects' => 5,
                    'headers' => ['Accept' => 'application/pdf,*/*'],
                ]);
                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                    throw new \RuntimeException('Upstream error');
                }
                $pdfContents = $response->getContent();
            } catch (ExceptionInterface|\Throwable) {
                return new Response(
                    $this->translator->trans('proxy.error_load', [], 'nowo_pdf_signable'),
                    Response::HTTP_BAD_GATEWAY
                );
            }
        } elseif (isset($data['pdf_content']) && \is_string($data['pdf_content'])) {
            $decoded = base64_decode($data['pdf_content'], true);
            if (false === $decoded) {
                return new JsonResponse(['error' => 'Invalid base64 pdf_content'], Response::HTTP_BAD_REQUEST);
            }
            if (\strlen($decoded) > $this->maxPdfSize) {
                return new JsonResponse(['error' => 'PDF too large'], Response::HTTP_BAD_REQUEST);
            }
            $pdfContents = $decoded;
        }
        if (null === $pdfContents) {
            return new JsonResponse(['error' => 'Provide pdf_url or pdf_content'], Response::HTTP_BAD_REQUEST);
        }

        $validateOnly = $this->debug && !empty($data['validate_only']);
        if ($this->debug && null !== $this->logger) {
            $this->logger->info('AcroForm apply request', [
                'has_pdf_content' => isset($data['pdf_content']),
                'has_pdf_url' => isset($data['pdf_url']),
                'pdf_bytes' => \strlen($pdfContents),
                'patches_count' => \count($patches),
                'validate_only' => $validateOnly,
            ]);
        }

        $event = new AcroFormApplyRequestEvent($pdfContents, $patches, $validateOnly);
        $this->eventDispatcher->dispatch($event, PdfSignableEvents::ACROFORM_APPLY_REQUEST);

        if (null !== $event->getValidationResult()) {
            if ($this->debug && null !== $this->logger) {
                $this->logger->info('AcroForm apply response: validation_result (JSON)');
            }

            return new JsonResponse($event->getValidationResult(), Response::HTTP_OK);
        }
        if (null !== $event->getError()) {
            $payload = ['error' => $event->getError()->getMessage()];
            if (null !== $event->getErrorDetail()) {
                $payload['detail'] = $event->getErrorDetail();
            }
            if ($this->debug && null !== $this->logger) {
                $this->logger->warning('AcroForm apply response: error', ['error' => $payload['error'], 'detail' => $payload['detail'] ?? null]);
            }

            return new JsonResponse($payload, Response::HTTP_BAD_REQUEST);
        }
        if (null !== $event->getModifiedPdf()) {
            $modified = $event->getModifiedPdf();
            if ($this->debug && null !== $this->logger) {
                $this->logger->info('AcroForm apply response: modified PDF', ['pdf_output_bytes' => \strlen($modified)]);
            }

            return new Response($modified, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="document.pdf"',
            ]);
        }
        if (null !== $this->editor) {
            try {
                $modified = $this->editor->applyPatches($pdfContents, $patches);
                if ($validateOnly) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Apply would succeed',
                        'patches_count' => \count($patches),
                    ], Response::HTTP_OK);
                }

                return new Response($modified, Response::HTTP_OK, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="document.pdf"',
                ]);
            } catch (AcroFormEditorException $e) {
                if ($validateOnly) {
                    return new JsonResponse(['success' => false, 'error' => $e->getMessage()], Response::HTTP_OK);
                }

                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($this->debug && null !== $this->logger) {
            $this->logger->warning('AcroForm apply response: 501 No editor configured and event did not set modified PDF');
        }

        return new Response('No editor configured', Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Runs the configured process script on the modified PDF and dispatches an event for the app to save it.
     *
     * Body: pdf_content (base64, required), document_key (optional, passed to script as --document-key).
     * The script is invoked with --input <temp PDF path> and --output <temp path>; it must write the
     * processed PDF to the output path. Then ACROFORM_MODIFIED_PDF_PROCESSED is dispatched with the result.
     *
     * @param Request $request Request body with pdf_content (base64), optional document_key
     *
     * @return Response 200 JSON { success: true, document_key?: string }, or application/pdf if Accept header requests it
     */
    #[Route('/acroform/process', name: 'nowo_pdf_signable_acroform_process', methods: ['POST'])]
    public function process(Request $request): Response
    {
        if (!$this->enabled || null === $this->processScript || '' === trim($this->processScript)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $script = trim($this->processScript);
        if (!is_file($script) || !is_readable($script)) {
            return new JsonResponse(['error' => 'Process script not configured or not readable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $data = $request->toArray();
        $pdfContentB64 = $data['pdf_content'] ?? '';
        if (!\is_string($pdfContentB64)) {
            return new JsonResponse(['error' => 'pdf_content (base64) is required'], Response::HTTP_BAD_REQUEST);
        }
        $decoded = base64_decode($pdfContentB64, true);
        if (false === $decoded || \strlen($decoded) > $this->maxPdfSize) {
            return new JsonResponse(['error' => 'Invalid or too large pdf_content'], Response::HTTP_BAD_REQUEST);
        }

        $documentKey = isset($data['document_key']) && \is_string($data['document_key']) ? trim($data['document_key']) : null;
        if ('' === $documentKey) {
            $documentKey = null;
        }

        $tmpInput = tempnam(sys_get_temp_dir(), 'pdf_process_in_');
        $tmpOutput = tempnam(sys_get_temp_dir(), 'pdf_process_out_');
        if (false === $tmpInput || false === $tmpOutput) {
            return new JsonResponse(['error' => 'Failed to create temp files'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            if (false === file_put_contents($tmpInput, $decoded)) {
                throw new \RuntimeException('Failed to write temp PDF');
            }

            $procArgs = [$this->processScriptCommand, $script, '--input', $tmpInput, '--output', $tmpOutput];
            if (null !== $documentKey) {
                $procArgs[] = '--document-key';
                $procArgs[] = $documentKey;
            }
            $proc = new Process($procArgs, null, PythonProcessEnv::build());
            $proc->setTimeout(120);
            $proc->run();

            if (!$proc->isSuccessful()) {
                $err = $proc->getErrorOutput()."\n".$proc->getOutput();
                $isPythonNotFound = str_contains(strtolower($err), 'not found') && str_contains(strtolower($err), 'python');

                return new JsonResponse([
                    'error' => $isPythonNotFound
                        ? 'Process script failed: Python 3 is not installed or not in PATH. Install python3 on the server or set process_script_command to the full path of your Python executable.'
                        : 'Process script failed',
                    'detail' => $err,
                ], Response::HTTP_BAD_REQUEST);
            }

            $processedPdf = is_file($tmpOutput) ? file_get_contents($tmpOutput) : '';
            if ('' === $processedPdf) {
                return new JsonResponse(['error' => 'Process script produced no output file'], Response::HTTP_BAD_REQUEST);
            }

            $this->eventDispatcher->dispatch(
                new AcroFormModifiedPdfProcessedEvent($processedPdf, $documentKey, $request),
                PdfSignableEvents::ACROFORM_MODIFIED_PDF_PROCESSED
            );

            $accept = $request->headers->get('Accept', '');
            if (str_contains($accept, 'application/pdf')) {
                return new Response($processedPdf, Response::HTTP_OK, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="processed.pdf"',
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'document_key' => $documentKey,
            ], Response::HTTP_OK);
        } finally {
            @unlink($tmpInput);
            @unlink($tmpOutput);
        }
    }

    /**
     * Resolves raw PDF bytes from the request body.
     *
     * Reads request JSON: pdf_url (fetched via HTTP if allowed by allowlist and not blocked for SSRF)
     * or pdf_content (base64-decoded). Uses the same allowlist and SSRF rules as the apply endpoint.
     *
     * @param Request $request Request whose body may contain pdf_url or pdf_content
     *
     * @return string|null PDF binary content, or null if missing, invalid, or URL not allowed
     */
    private function resolvePdfContentsFromRequest(Request $request): ?string
    {
        $data = $request->toArray();
        if (isset($data['pdf_url']) && \is_string($data['pdf_url'])) {
            $url = trim($data['pdf_url']);
            if ('' === $url || !filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }
            if ([] !== $this->proxyUrlAllowlist && !$this->isUrlAllowedByAllowlist($url)) {
                return null;
            }
            if ($this->isUrlBlockedForSsrf($url)) {
                return null;
            }
            try {
                $client = HttpClient::create();
                $response = $client->request('GET', $url, [
                    'timeout' => 30,
                    'max_redirects' => 5,
                    'headers' => ['Accept' => 'application/pdf,*/*'],
                ]);
                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                    return null;
                }

                return $response->getContent();
            } catch (ExceptionInterface|\Throwable) {
                return null;
            }
        }
        if (isset($data['pdf_content']) && \is_string($data['pdf_content'])) {
            $decoded = base64_decode($data['pdf_content'], true);

            return false !== $decoded ? $decoded : null;
        }

        return null;
    }

    /**
     * Resolves and validates document_key from the request.
     *
     * Prefer body['document_key'] when body is provided; otherwise query or request parameter.
     *
     * @param Request                   $request Request to read document_key from
     * @param array<string, mixed>|null $body    Optional pre-parsed body (e.g. from $request->toArray())
     *
     * @return string|null The document key if present and valid, null otherwise
     */
    private function resolveDocumentKey(Request $request, ?array $body): ?string
    {
        $key = (null !== $body && \array_key_exists('document_key', $body))
            ? $body['document_key']
            : ($request->query->get('document_key') ?? $request->request->get('document_key'));
        if (null === $key || '' === trim((string) $key)) {
            return null;
        }
        $key = trim((string) $key);

        return $this->isValidDocumentKey($key) ? $key : null;
    }

    /**
     * Validates document_key format and length.
     *
     * Allowed: non-empty, length <= DOCUMENT_KEY_MAX_LENGTH, characters [a-zA-Z0-9_.-].
     *
     * @param string $documentKey The document key to validate
     *
     * @return bool True if the key is valid
     */
    private function isValidDocumentKey(string $documentKey): bool
    {
        if ('' === $documentKey || \strlen($documentKey) > self::DOCUMENT_KEY_MAX_LENGTH) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9_.\-]+$/', $documentKey);
    }

    /**
     * Checks whether the URL targets a private or local host (SSRF mitigation).
     *
     * Blocks: localhost, ::1, 127.0.0.0/8, 10.0.0.0/8, 192.168.0.0/16, 169.254.0.0/16, and IPv6 link-local (fe80::).
     *
     * @param string $url Full URL (e.g. https://example.com/doc.pdf)
     *
     * @return bool True if the URL should be blocked
     */
    private function isUrlBlockedForSsrf(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (null === $host || '' === $host) {
            return true;
        }
        $hostLower = strtolower($host);
        if ('localhost' === $hostLower || '::1' === $hostLower) {
            return true;
        }
        $ip = $host;
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $resolved = gethostbyname($host);
            if ($resolved === $host) {
                return false;
            }
            $ip = $resolved;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if (false === $long) {
                return true;
            }
            $u = (float) sprintf('%u', $long);

            return ($u >= 2130706432 && $u <= 2147483647)
                || ($u >= 167772160 && $u <= 184549375)
                || ($u >= 3232235520 && $u <= 3232301055)
                || ($u >= 2851995648 && $u <= 2852061183);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return str_starts_with($ip, '::1') || str_starts_with($ip, 'fe80:');
        }

        return false;
    }

    /**
     * Checks whether the URL is allowed by the configured proxy_url_allowlist.
     *
     * Each allowlist entry is either a substring (URL must contain it) or a regex if prefixed with '#'.
     *
     * @param string $url Full URL to check
     *
     * @return bool True if the URL matches at least one allowlist entry
     */
    private function isUrlAllowedByAllowlist(string $url): bool
    {
        foreach ($this->proxyUrlAllowlist as $pattern) {
            if ('' === $pattern) {
                continue;
            }
            if (str_starts_with($pattern, '#')) {
                if (1 === @preg_match($pattern, $url)) {
                    return true;
                }
                continue;
            }
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
