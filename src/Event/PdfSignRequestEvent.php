<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use LogicException;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Nowo\PdfSignableBundle\Submission\PdfRequestContext;
use Nowo\PdfSignableBundle\Submission\PdfRequestContextFactory;
use Nowo\PdfSignableBundle\Submission\SignatureSubmission;
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
 * 3.10 (REQ-ARCH-001 rules 1 and 3): the event can be created from a {@see SignatureSubmission} without a request
 * (for example from a command handler or a Messenger message), and a listener reports the outcome with
 * {@see setResult()} instead of an HttpFoundation `Response`.
 *
 * @see PdfSignableEvents::PDF_SIGN_REQUEST
 */
final class PdfSignRequestEvent extends Event
{
    /** If set by a listener, return this instead of the default flow. */
    private ?Response $response = null;

    /** Signed PDF bytes or a storage reference, set by a listener (3.10). */
    private ?string $result = null;

    private readonly SignatureCoordinatesModel $coordinates;

    /**
     * @param SignatureCoordinatesModel|SignatureSubmission $coordinates The coordinates (PDF URL, boxes); since 3.10 also an immutable submission
     * @param Request|null $request The current request; since 3.10 optional (null outside HTTP)
     * @param array<string, mixed> $options optional: signing_profile, box_indices, etc
     */
    public function __construct(
        SignatureCoordinatesModel|SignatureSubmission $coordinates,
        private readonly ?Request $request = null,
        private readonly array $options = [],
    ) {
        $this->coordinates = $coordinates instanceof SignatureSubmission ? $coordinates->toModel() : $coordinates;
    }

    /**
     * The coordinates as an immutable value object: a snapshot when called (3.10).
     */
    public function getSubmission(): SignatureSubmission
    {
        return SignatureSubmission::fromModel($this->coordinates);
    }

    /**
     * Method, path, locale, client IP and user agent of the request, null when the event was created without one (3.10).
     */
    public function getRequestContext(): ?PdfRequestContext
    {
        return $this->request instanceof Request ? PdfRequestContextFactory::fromRequest($this->request) : null;
    }

    /**
     * @deprecated since 3.10, use {@see getSubmission()}; removed in 4.0
     */
    public function getCoordinates(): SignatureCoordinatesModel
    {
        return $this->coordinates;
    }

    /**
     * @throws LogicException when the event was created without a request (3.10); check {@see hasRequest()}
     *
     * @deprecated since 3.10, use {@see getRequestContext()}; removed in 4.0
     */
    public function getRequest(): Request
    {
        if (!$this->request instanceof Request) {
            throw new LogicException('This PdfSignRequestEvent was created without a request; use getRequestContext().');
        }

        return $this->request;
    }

    /** True when the event was created with a request (3.10). */
    public function hasRequest(): bool
    {
        return $this->request instanceof Request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Records the signing outcome (signed PDF bytes or a storage reference) for the code that dispatched the event (3.10).
     */
    public function setResult(?string $result): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->result = $result;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    /**
     * Set a response to return (e.g. redirect or JSON) and stop propagation.
     *
     * @deprecated since 3.10, use {@see setResult()} and build the response in your controller; removed in 4.0
     */
    public function setResponse(?Response $response): void
    {
        // @igor-ignore - Request-scoped Event DTO; not a shared worker service.
        $this->response = $response;
    }

    /**
     * @deprecated since 3.10, use {@see getResult()}; removed in 4.0
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
