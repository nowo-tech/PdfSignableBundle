<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\Submission\PdfRequestContext;
use Nowo\PdfSignableBundle\Submission\PdfRequestContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the PDF proxy successfully fetches the document.
 *
 * Subscribers can modify the Response (e.g. add headers, log, or replace content).
 *
 * 3.10 (REQ-ARCH-001 rule 3): {@see getRequestContext()}, {@see getContent()}, {@see setContent()} and
 * {@see setHeader()} do the same without HttpFoundation types.
 */
final class PdfProxyResponseEvent extends Event
{
    /**
     * @param string $url The URL that was fetched
     * @param Request $request The HTTP request to the proxy endpoint
     * @param Response $response The PDF response (can be modified by listeners)
     */
    public function __construct(
        private readonly string $url,
        private readonly Request $request,
        private Response $response,
    ) {
    }

    /**
     * Returns the URL that was fetched.
     *
     * @return string The fetched URL
     */
    public function getUrl(): string
    {
        return $this->url;
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
     * Returns the response (PDF content). Listeners may replace it.
     *
     * @return Response The PDF response (may have been modified by listeners)
     *
     * @deprecated since 3.10, use {@see getContent()} / {@see getStatusCode()}; removed in 4.0
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Replaces the response (e.g. add headers or transform content).
     *
     * @param Response $response The new response to return
     *
     * @deprecated since 3.10, use {@see setContent()} / {@see setHeader()}; removed in 4.0
     */
    public function setResponse(Response $response): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->response = $response;
    }

    /**
     * Method, path, locale, client IP and user agent of the proxy request (3.10).
     */
    public function getRequestContext(): PdfRequestContext
    {
        return PdfRequestContextFactory::fromRequest($this->request);
    }

    /** The PDF bytes that will be sent (3.10). */
    public function getContent(): string
    {
        return (string) $this->response->getContent();
    }

    /** Replaces the PDF bytes that will be sent (3.10). */
    public function setContent(string $content): void
    {
        $this->response->setContent($content);
    }

    /** The response status code (3.10). */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /** Sets (replaces) a response header (3.10). */
    public function setHeader(string $name, string $value): void
    {
        $this->response->headers->set($name, $value);
    }
}
