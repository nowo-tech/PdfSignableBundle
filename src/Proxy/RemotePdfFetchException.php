<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Proxy;

use RuntimeException;
use Throwable;

/**
 * Raised by {@see RemotePdfFetcher} when a remote PDF cannot be fetched safely.
 *
 * @internal
 */
final class RemotePdfFetchException extends RuntimeException
{
    /** URL (or a redirect target) is not allowed by the allowlist or targets a non-public address. */
    public const REASON_NOT_ALLOWED = 'not_allowed';

    /** Response body exceeds the configured maximum size. */
    public const REASON_TOO_LARGE = 'too_large';

    /** Upstream error (transport failure, non-2xx status, too many redirects). */
    public const REASON_UPSTREAM = 'upstream';

    public function __construct(
        public readonly string $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
