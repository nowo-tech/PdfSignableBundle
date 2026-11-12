<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Submission;

use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the {@see PdfRequestContext} of an HTTP request (the HTTP edge of the events).
 *
 * @internal
 */
final class PdfRequestContextFactory
{
    public static function fromRequest(Request $request): PdfRequestContext
    {
        return new PdfRequestContext(
            $request->getMethod(),
            $request->getPathInfo(),
            $request->getLocale(),
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
        );
    }
}
