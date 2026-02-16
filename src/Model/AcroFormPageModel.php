<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Model;

/**
 * Minimal model for an AcroForm page: PDF URL and optional document key.
 *
 * Used as data_class for AcroFormEditorType. The AcroForm editor panel
 * (load/save overrides, apply PDF) is stateless wrt form submit; only
 * pdfUrl (and optionally documentKey) are submitted with the form.
 */
class AcroFormPageModel
{
    private ?string $pdfUrl = null;

    private ?string $documentKey = null;

    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    public function setPdfUrl(?string $pdfUrl): self
    {
        $this->pdfUrl = $pdfUrl;
        return $this;
    }

    public function getDocumentKey(): ?string
    {
        return $this->documentKey;
    }

    public function setDocumentKey(?string $documentKey): self
    {
        $this->documentKey = $documentKey;
        return $this;
    }
}
