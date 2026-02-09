<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

/**
 * Dispatched when the signature coordinates form is submitted and valid.
 *
 * Subscribers can persist the model, send coordinates to a signing service,
 * or attach data for a custom response.
 */
final class SignatureCoordinatesSubmittedEvent extends Event
{
    public function __construct(
        private readonly SignatureCoordinatesModel $coordinates,
        private readonly Request $request,
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
}
