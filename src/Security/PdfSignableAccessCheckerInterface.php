<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Security;

/**
 * Decides who may use the AcroForm endpoints (load / save / remove overrides, extract fields, apply, process) and the
 * PDF proxy. Replace it with `nowo_pdf_signable.security.access_checker` (service id).
 */
interface PdfSignableAccessCheckerInterface
{
    public function canAccess(): bool;
}
