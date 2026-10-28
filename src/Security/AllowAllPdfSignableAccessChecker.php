<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Security;

/**
 * Used only with `security.allow_unauthenticated: true` (demos, local development): everybody may use the endpoints.
 */
final class AllowAllPdfSignableAccessChecker implements PdfSignableAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }
}
