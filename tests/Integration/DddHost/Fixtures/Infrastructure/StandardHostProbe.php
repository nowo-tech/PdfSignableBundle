<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Infrastructure;

use Nowo\PdfSignableBundle\Event\BatchSignRequestedEvent;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A standard host listener (deprecated accessors) next to the new ones, recording both views of the same dispatch.
 */
final class StandardHostProbe implements EventSubscriberInterface
{
    /** @var list<array<string, mixed>> */
    public array $seen = [];

    public static function getSubscribedEvents(): array
    {
        return [
            PdfSignableEvents::SIGNATURE_COORDINATES_SUBMITTED => 'record',
            PdfSignableEvents::BATCH_SIGN_REQUESTED            => 'record',
        ];
    }

    public function record(SignatureCoordinatesSubmittedEvent|BatchSignRequestedEvent $event): void
    {
        $request      = $event->getRequest();
        $context      = $event->getRequestContext();
        $this->seen[] = [
            'event'      => $event::class,
            'model'      => $event->getCoordinates()->toArray(),
            'submission' => $event->getSubmission()->toArray(),
            'request'    => [$request->getMethod(), $request->getPathInfo(), $request->getLocale(), $request->getClientIp(), $request->headers->get('User-Agent')],
            'context'    => [$context->method, $context->path, $context->locale, $context->getClientIp(), $context->getUserAgent()],
        ];
    }
}
