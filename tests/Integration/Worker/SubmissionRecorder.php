<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\Worker;

use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Nowo\PdfSignableBundle\Model\AuditMetadata;
use Nowo\PdfSignableBundle\Submission\SignatureBox;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_map;
use function array_values;

/**
 * Host listener of the worker test: records what each submission event carried. The test empties it after every
 * request (it is test state, not bundle state).
 */
final class SubmissionRecorder implements EventSubscriberInterface
{
    /** @var list<array{pdfUrl: string|null, signers: list<string>, ip: string|null, auditIp: mixed, userAgent: string|null}> */
    public array $seen = [];

    public static function getSubscribedEvents(): array
    {
        return [PdfSignableEvents::SIGNATURE_COORDINATES_SUBMITTED => 'onSubmitted'];
    }

    public function onSubmitted(SignatureCoordinatesSubmittedEvent $event): void
    {
        $submission   = $event->getSubmission();
        $context      = $event->getRequestContext();
        $this->seen[] = [
            'pdfUrl'    => $submission->pdfUrl,
            'signers'   => array_values(array_map(static fn (SignatureBox $box): string => $box->name, $submission->boxes)),
            'ip'        => $context->getClientIp(),
            'auditIp'   => $submission->getAuditValue(AuditMetadata::IP),
            'userAgent' => $context->getUserAgent(),
        ];
    }
}
