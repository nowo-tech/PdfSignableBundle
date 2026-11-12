<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Nowo\PdfSignableBundle\Submission\PdfRequestContext;
use Nowo\PdfSignableBundle\Submission\PdfRequestContextFactory;
use Nowo\PdfSignableBundle\Submission\SignatureSubmission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when the user submits the form with "Sign all" (batch_sign=1).
 *
 * Listeners can perform batch signing (e.g. draw/upload for each box, or one PKI sign
 * for all boxes), then redirect or set a response. The bundle does not perform the
 * actual signing; your listener calls your signing service or handles the UI flow.
 *
 * 3.10 (REQ-ARCH-001 rules 1 and 3): {@see getSubmission()} and {@see getRequestContext()} carry no framework type.
 *
 * @see PdfSignableEvents::BATCH_SIGN_REQUESTED
 */
final class BatchSignRequestedEvent extends Event
{
    /**
     * @param SignatureCoordinatesModel $coordinates The submitted coordinates (boxes to sign)
     * @param Request $request The HTTP request (POST with form data)
     * @param list<int>|list<string>|null $boxTarget null = all boxes, or list of 0-based indices or box names to sign
     */
    public function __construct(
        private readonly SignatureCoordinatesModel $coordinates,
        private readonly Request $request,
        private readonly ?array $boxTarget = null,
    ) {
    }

    /**
     * The submitted coordinates as an immutable value object: a snapshot of the model when called (3.10).
     */
    public function getSubmission(): SignatureSubmission
    {
        return SignatureSubmission::fromModel($this->coordinates);
    }

    /**
     * Method, path, locale, client IP and user agent of the submitting request (3.10).
     */
    public function getRequestContext(): PdfRequestContext
    {
        return PdfRequestContextFactory::fromRequest($this->request);
    }

    /**
     * @deprecated since 3.10, use {@see getSubmission()}; removed in 4.0
     */
    public function getCoordinates(): SignatureCoordinatesModel
    {
        return $this->coordinates;
    }

    /**
     * @deprecated since 3.10, use {@see getRequestContext()}; removed in 4.0
     */
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
