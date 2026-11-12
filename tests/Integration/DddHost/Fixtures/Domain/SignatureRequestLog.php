<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Domain;

/**
 * A DDD host's own domain service: plain PHP, no framework type.
 */
final class SignatureRequestLog
{
    /** @var list<array{document: string|null, signers: list<string>, pages: list<int>, consent: bool, ip: string|null, auditIp: mixed, locale: string}> */
    public array $entries = [];

    /**
     * @param list<string> $signers
     * @param list<int> $pages
     */
    public function record(?string $document, array $signers, array $pages, bool $consent, ?string $ip, mixed $auditIp, string $locale): void
    {
        $this->entries[] = ['document' => $document, 'signers' => $signers, 'pages' => $pages, 'consent' => $consent, 'ip' => $ip, 'auditIp' => $auditIp, 'locale' => $locale];
    }
}
