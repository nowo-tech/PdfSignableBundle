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
 * Dispatched when the signature coordinates form is submitted and valid.
 *
 * Subscribers can persist the submission, send coordinates to a signing service,
 * or attach data for a custom response.
 *
 * 3.10 (REQ-ARCH-001 rules 1 and 3): {@see getSubmission()} (immutable {@see SignatureSubmission}) and
 * {@see getRequestContext()} (framework-free {@see PdfRequestContext}) replace the mutable model and the HttpFoundation
 * request for listeners that keep framework types out of their domain.
 */
final class SignatureCoordinatesSubmittedEvent extends Event
{
    /**
     * @param SignatureCoordinatesModel $coordinates The submitted coordinates (PDF URL, unit, origin, boxes)
     * @param Request $request The HTTP request (POST with form data)
     */
    public function __construct(
        private readonly SignatureCoordinatesModel $coordinates,
        private readonly Request $request,
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
     * Returns the submitted signature coordinates model.
     *
     * @return SignatureCoordinatesModel The validated form data (PDF URL, unit, origin, boxes)
     *
     * @deprecated since 3.10, use {@see getSubmission()}; removed in 4.0
     */
    public function getCoordinates(): SignatureCoordinatesModel
    {
        return $this->coordinates;
    }

    /**
     * Returns the HTTP request that submitted the form.
     *
     * @return Request The POST request with form data
     *
     * @deprecated since 3.10, use {@see getRequestContext()}; removed in 4.0
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
