<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\AcroForm;

use Nowo\PdfSignableBundle\AcroForm\Exception\AcroFormEditorException;

/**
 * Contract for applying AcroForm patches to a PDF and returning modified PDF bytes.
 *
 * Implement in the app or via optional SetaPDF-based service.
 */
interface PdfAcroFormEditorInterface
{
    /**
     * Apply patches to AcroForm fields and return the modified PDF.
     *
     * @param list<AcroFormFieldPatch> $patches
     *
     * @throws AcroFormEditorException when the PDF has no form or a patch cannot be applied
     */
    public function applyPatches(string $pdfContents, array $patches): string;
}
