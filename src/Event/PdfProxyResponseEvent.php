<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the PDF proxy successfully fetches the document.
 *
 * Subscribers can modify the Response (e.g. add headers, log, or replace content).
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
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Returns the response (PDF content). Listeners may replace it.
     *
     * @return Response The PDF response (may have been modified by listeners)
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Replaces the response (e.g. add headers or transform content).
     *
     * @param Response $response The new response to return
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
