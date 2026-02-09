<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when the signature coordinates form is submitted and valid.
 *
 * Subscribers can persist the model, send coordinates to a signing service,
 * or attach data for a custom response.
 */
final class SignatureCoordinatesSubmittedEvent extends Event
{
    /**
     * @param SignatureCoordinatesModel $coordinates The submitted coordinates (PDF URL, unit, origin, boxes)
     * @param Request                   $request     The HTTP request (POST with form data)
     */
    public function __construct(
        private readonly SignatureCoordinatesModel $coordinates,
        private readonly Request $request,
    ) {
    }

    /**
     * Returns the submitted signature coordinates model.
     */
    public function getCoordinates(): SignatureCoordinatesModel
    {
        return $this->coordinates;
    }

    /**
     * Returns the HTTP request that submitted the form.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
