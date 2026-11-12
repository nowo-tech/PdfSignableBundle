<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Submission;

use function is_string;

/**
 * Framework-free view of the HTTP request behind a PdfSignable event (3.10, REQ-ARCH-001 rule 3): method, path, locale,
 * client IP and user agent, instead of the HttpFoundation `Request`.
 *
 * The client IP and the user agent are kept outside the object's properties, so a dump, `var_export()` or `(array)`
 * cast does not show them. It cannot be cloned or serialized.
 */
final readonly class PdfRequestContext
{
    /**
     * @param string $method HTTP method (upper case)
     * @param string $path path info, without the query string
     * @param string $locale request locale
     * @param string|null $clientIp client IP (as resolved with the host's trusted proxies), null when unknown
     * @param string|null $userAgent User-Agent header, null when absent
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $locale,
        ?string $clientIp = null,
        ?string $userAgent = null,
    ) {
        SensitiveValues::put($this, ['clientIp' => $clientIp, 'userAgent' => $userAgent]);
    }

    public function getClientIp(): ?string
    {
        $ip = SensitiveValues::get($this, 'clientIp');

        return is_string($ip) ? $ip : null;
    }

    public function getUserAgent(): ?string
    {
        $agent = SensitiveValues::get($this, 'userAgent');

        return is_string($agent) ? $agent : null;
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw SensitiveValues::refuseSerialization($this);
    }

    /** @param array<array-key, mixed> $data */
    public function __unserialize(array $data): void
    {
        throw SensitiveValues::refuseSerialization($this);
    }

    /**
     * Not cloneable: a copy would carry no request data (`clone` fails before this body runs).
     *
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }
}
