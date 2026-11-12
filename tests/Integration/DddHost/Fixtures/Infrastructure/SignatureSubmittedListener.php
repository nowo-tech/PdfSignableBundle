<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Infrastructure;

use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Nowo\PdfSignableBundle\Model\AuditMetadata;
use Nowo\PdfSignableBundle\Submission\SignatureBox;
use Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Domain\SignatureRequestLog;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use function array_map;
use function array_values;

/**
 * The DDD host's adapter: reads only the framework-free accessors (submission VO + request context) and calls its own
 * domain service. No HttpFoundation request, no mutable model.
 */
#[AsEventListener(event: PdfSignableEvents::SIGNATURE_COORDINATES_SUBMITTED)]
final readonly class SignatureSubmittedListener
{
    public function __construct(private SignatureRequestLog $log)
    {
    }

    public function __invoke(SignatureCoordinatesSubmittedEvent $event): void
    {
        $submission = $event->getSubmission();
        $context    = $event->getRequestContext();

        $this->log->record(
            $submission->pdfUrl,
            array_values(array_map(static fn (SignatureBox $box): string => $box->name, $submission->boxes)),
            array_values(array_map(static fn (SignatureBox $box): int => $box->page, $submission->boxes)),
            $submission->signingConsent,
            $context->getClientIp(),
            $submission->getAuditValue(AuditMetadata::IP),
            $context->locale,
        );
    }
}
