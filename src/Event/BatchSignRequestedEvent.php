<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when the user submits the form with "Sign all" (batch_sign=1).
 *
 * Listeners can perform batch signing (e.g. draw/upload for each box, or one PKI sign
 * for all boxes), then redirect or set a response. The bundle does not perform the
 * actual signing; your listener calls your signing service or handles the UI flow.
 *
 * @see PdfSignableEvents::BATCH_SIGN_REQUESTED
 */
final class BatchSignRequestedEvent extends Event
{
    /**
     * @param SignatureCoordinatesModel   $coordinates The submitted coordinates (boxes to sign)
     * @param Request                     $request     The HTTP request (POST with form data)
     * @param list<int>|list<string>|null $boxTarget   null = all boxes, or list of 0-based indices or box names to sign
     */
    public function __construct(
        private readonly SignatureCoordinatesModel $coordinates,
        private readonly Request $request,
        private readonly ?array $boxTarget = null,
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
     * null = sign all boxes; otherwise list of indices or box names to sign.
     *
     * @return list<int>|list<string>|null
     */
    public function getBoxTarget(): ?array
    {
        return $this->boxTarget;
    }
}
