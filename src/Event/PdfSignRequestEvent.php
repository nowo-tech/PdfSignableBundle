<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when the app requests a digital (PKI/PAdES) signature.
 *
 * The bundle does not perform PKI signing. Your listener subscribes to this event,
 * calls your signing service or HSM with the coordinates and PDF, and sets the
 * result (e.g. signed PDF binary or storage path) or a Response (e.g. redirect to
 * certificate picker). Optionally set a Response to short-circuit the default flow.
 *
 * @see PdfSignableEvents::PDF_SIGN_REQUEST
 */
final class PdfSignRequestEvent extends Event
{
    /** If set by a listener, return this instead of the default flow. */
    private ?Response $response = null;

    /**
     * @param SignatureCoordinatesModel $coordinates The coordinates model (PDF URL, boxes)
     * @param Request                   $request    The current request
     * @param array<string, mixed>      $options    Optional: signing_profile, box_indices, etc.
     */
    public function __construct(
        private readonly SignatureCoordinatesModel $coordinates,
        private readonly Request $request,
        private array $options = [],
    ) {
    }

    public function getCoordinates(): SignatureCoordinatesModel
    {
        return $this->coordinates;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set a response to return (e.g. redirect or JSON) and stop propagation.
     */
    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
