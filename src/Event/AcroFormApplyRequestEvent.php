<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldPatch;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when POST /pdf-signable/acroform/apply is called.
 *
 * Listeners can set the modified PDF or an error; the controller returns that
 * or falls back to the configured PdfAcroFormEditorInterface.
 * When validateOnly is true (debug), listeners may set a validation result (JSON)
 * instead of the modified PDF; the controller returns that as JSON.
 */
final class AcroFormApplyRequestEvent extends Event
{
    /** @var string|null Modified PDF bytes (set by listener) */
    private ?string $modifiedPdf = null;

    /** @var \Throwable|null Error to return to client (set by listener) */
    private ?\Throwable $error = null;

    /** @var string|null Optional detail (stderr, stdout, logs) for error response */
    private ?string $errorDetail = null;

    /** @var array<string, mixed>|null Validation result when validateOnly (e.g. dry-run); controller returns as JSON */
    private ?array $validationResult = null;

    /**
     * @param string                $pdfContents Original PDF bytes
     * @param list<AcroFormFieldPatch> $patches   Patches to apply
     * @param bool                  $validateOnly When true, listener may run dry-run and set validationResult instead of modifiedPdf
     */
    public function __construct(
        private readonly string $pdfContents,
        private readonly array $patches,
        private readonly bool $validateOnly = false,
    ) {
    }

    /**
     * Returns the original PDF bytes (before patches are applied).
     */
    public function getPdfContents(): string
    {
        return $this->pdfContents;
    }

    /**
     * @return list<AcroFormFieldPatch>
     */
    public function getPatches(): array
    {
        return $this->patches;
    }

    /**
     * Sets the modified PDF bytes (listeners call this to provide the result).
     */
    public function setModifiedPdf(string $pdf): void
    {
        $this->modifiedPdf = $pdf;
    }

    /**
     * Returns the modified PDF bytes set by a listener, or null.
     */
    public function getModifiedPdf(): ?string
    {
        return $this->modifiedPdf;
    }

    /**
     * Sets an error to return to the client (e.g. script failed).
     */
    public function setError(\Throwable $e): void
    {
        $this->error = $e;
    }

    /**
     * Returns the error set by a listener, or null.
     */
    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    /**
     * Sets optional detail (stderr, stdout, logs) for the error response.
     */
    public function setErrorDetail(string $detail): void
    {
        $this->errorDetail = $detail;
    }

    /**
     * Returns optional detail for the error response, or null.
     */
    public function getErrorDetail(): ?string
    {
        return $this->errorDetail;
    }

    /**
     * When true, listener should run apply in dry-run and set validationResult (only in debug).
     */
    public function isValidateOnly(): bool
    {
        return $this->validateOnly;
    }

    /**
     * Sets the validation result (dry-run). Controller returns this as JSON when validateOnly was true.
     *
     * @param array<string, mixed> $result e.g. ['success' => true, 'message' => '...', 'patches_count' => N]
     */
    public function setValidationResult(array $result): void
    {
        $this->validationResult = $result;
    }

    /**
     * Returns the validation result set by a listener (dry-run), or null.
     *
     * @return array<string, mixed>|null
     */
    public function getValidationResult(): ?array
    {
        return $this->validationResult;
    }

    /**
     * True if a listener set a modified PDF, an error, or a validation result.
     */
    public function hasResponse(): bool
    {
        return null !== $this->modifiedPdf || null !== $this->error || null !== $this->validationResult;
    }
}
