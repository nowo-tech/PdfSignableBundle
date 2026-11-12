<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\Submission\PdfRequestContext;
use Nowo\PdfSignableBundle\Submission\PdfRequestContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before the PDF proxy fetches an external URL.
 *
 * Subscribers can change the URL to fetch or set a custom Response
 * to skip the HTTP fetch (e.g. serve from cache or local file).
 *
 * 3.10 (REQ-ARCH-001 rule 3): {@see getRequestContext()} and {@see respondWith()} replace the HttpFoundation request
 * and response for listeners that keep framework types out of their code.
 */
final class PdfProxyRequestEvent extends Event
{
    /** @var Response|null Custom response to return instead of fetching (set by listeners). */
    private ?Response $response = null;

    /**
     * @param string $url The URL the proxy is about to fetch
     * @param Request $request The HTTP request to the proxy endpoint
     */
    public function __construct(private string $url, private readonly Request $request)
    {
    }

    /**
     * Returns the URL to fetch (may have been modified by a listener).
     *
     * @return string URL to fetch
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
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->url = $url;
    }

    /**
     * Method, path, locale, client IP and user agent of the proxy request (3.10).
     */
    public function getRequestContext(): PdfRequestContext
    {
        return PdfRequestContextFactory::fromRequest($this->request);
    }

    /**
     * Skips the fetch and answers with this status, body and headers instead (3.10): the framework-free form of
     * {@see setResponse()}. A later call (or {@see setResponse()}) replaces it.
     *
     * @param array<string, string> $headers
     */
    public function respondWith(int $statusCode, string $body = '', array $headers = []): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->response = new Response($body, $statusCode, $headers);
    }

    /**
     * Returns the HTTP request to the proxy endpoint.
     *
     * @return Request The request to the proxy route
     *
     * @deprecated since 3.10, use {@see getRequestContext()}; removed in 4.0
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Sets a custom response to skip the proxy fetch. The proxy controller will return this response.
     *
     * @param Response|null $response The response to return, or null to perform the fetch
     *
     * @deprecated since 3.10, use {@see respondWith()}; removed in 4.0
     */
    public function setResponse(?Response $response): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->response = $response;
    }

    /**
     * Returns the custom response set by a listener, or null if the proxy should fetch the URL.
     *
     * @return Response|null Custom response or null to perform the fetch
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * Returns whether a listener set a custom response (skip fetch).
     *
     * @return bool True if a custom response was set
     */
    public function hasResponse(): bool
    {
        return $this->response instanceof Response;
    }
}
