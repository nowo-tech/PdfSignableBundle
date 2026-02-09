<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

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

    /**
     * @param string  $url     The URL the proxy is about to fetch
     * @param Request $request The HTTP request to the proxy endpoint
     */
    public function __construct(
        string $url,
        private readonly Request $request,
    ) {
        $this->url = $url;
    }

    /**
     * Returns the URL to fetch (may have been modified by a listener).
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Sets the URL to fetch (e.g. after rewriting or validation).
     *
     * @param string $url The URL to fetch
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * Returns the HTTP request to the proxy endpoint.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Sets a custom response to skip the proxy fetch. The proxy controller will return this response.
     *
     * @param Response|null $response The response to return, or null to perform the fetch
     */
    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    /**
     * Returns the custom response set by a listener, or null if the proxy should fetch the URL.
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * Returns whether a listener set a custom response (skip fetch).
     */
    public function hasResponse(): bool
    {
        return null !== $this->response;
    }
}
