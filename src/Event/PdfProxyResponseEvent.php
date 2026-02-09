<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dispatched after the PDF proxy successfully fetches the document.
 *
 * Subscribers can modify the Response (e.g. add headers, log, or replace content).
 */
final class PdfProxyResponseEvent extends Event
{
    public function __construct(
        private readonly string $url,
        private readonly Request $request,
        private Response $response,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
