<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dispatched before the PDF proxy fetches an external URL.
 *
 * Subscribers can change the URL to fetch or set a custom Response
 * to skip the HTTP fetch (e.g. serve from cache or local file).
 */
final class PdfProxyRequestEvent extends Event
{
    private string $url;
    private ?Response $response = null;

    public function __construct(
        string $url,
        private readonly Request $request,
    ) {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Set a custom response to skip the proxy fetch. The proxy controller will return this response.
     */
    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function hasResponse(): bool
    {
        return $this->response !== null;
    }
}
