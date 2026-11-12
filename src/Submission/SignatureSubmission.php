<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Submission;

use Nowo\PdfSignableBundle\Event\BatchSignRequestedEvent;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Nowo\PdfSignableBundle\Model\AuditMetadata;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;

use function array_map;
use function is_array;

/**
 * The submitted signature coordinates as an immutable value object (3.10, REQ-ARCH-001 rules 1 and 3): PDF URL, unit,
 * origin, boxes ({@see SignatureBox}), signing consent and audit metadata, with no framework type and no mutable model.
 *
 * Read it from {@see SignatureCoordinatesSubmittedEvent::getSubmission()} and
 * {@see BatchSignRequestedEvent::getSubmission()}, or build it in your own controller
 * with {@see fromModel()} from the form data.
 *
 * The audit metadata (IP, user agent, user / session id, TSA token…) is kept outside the object's properties, like the
 * boxes' signature images, so a dump, `var_export()` or `(array)` cast shows neither. It cannot be cloned or
 * serialized; use {@see toArray()} (the same array as {@see SignatureCoordinatesModel::toArray()}) to export it.
 */
final readonly class SignatureSubmission
{
    /**
     * @param string $unit one of the SignatureCoordinatesModel::UNIT_* values
     * @param string $origin one of the SignatureCoordinatesModel::ORIGIN_* values
     * @param array<int, SignatureBox> $boxes keyed as in the submitted form collection
     * @param array<string, mixed> $auditMetadata keys of {@see AuditMetadata} and your own
     */
    public function __construct(
        public ?string $pdfUrl,
        public string $unit,
        public string $origin,
        public array $boxes,
        public bool $signingConsent = false,
        array $auditMetadata = [],
    ) {
        SensitiveValues::put($this, ['auditMetadata' => $auditMetadata]);
    }

    /** A snapshot of the model's current values. */
    public static function fromModel(SignatureCoordinatesModel $model): self
    {
        return new self(
            $model->getPdfUrl(),
            $model->getUnit(),
            $model->getOrigin(),
            array_map(SignatureBox::fromModel(...), $model->getSignatureBoxes()),
            $model->getSigningConsent(),
            $model->getAuditMetadata(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuditMetadata(): array
    {
        $audit = SensitiveValues::get($this, 'auditMetadata');

        return is_array($audit) ? $audit : [];
    }

    /** One audit metadata value (e.g. AuditMetadata::IP), null when absent. */
    public function getAuditValue(string $key): mixed
    {
        return $this->getAuditMetadata()[$key] ?? null;
    }

    /**
     * The same array as {@see SignatureCoordinatesModel::toArray()}.
     *
     * @return array{pdf_url: string|null, unit: string, origin: string, signature_boxes: array<int, array<string, mixed>>, signing_consent?: bool, audit_metadata?: array<string, mixed>}
     */
    public function toArray(): array
    {
        return $this->toModel()->toArray();
    }

    /** A new mutable model with the same values (for code written against the model). */
    public function toModel(): SignatureCoordinatesModel
    {
        return (new SignatureCoordinatesModel())
            ->setPdfUrl($this->pdfUrl)
            ->setUnit($this->unit)
            ->setOrigin($this->origin)
            ->setSignatureBoxes(array_map(static fn (SignatureBox $box): SignatureBoxModel => $box->toModel(), $this->boxes))
            ->setSigningConsent($this->signingConsent)
            ->setAuditMetadata($this->getAuditMetadata());
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw SensitiveValues::refuseSerialization($this);
    }

    /** @param array<array-key, mixed> $data */
    public function __unserialize(array $data): void
    {
        throw SensitiveValues::refuseSerialization($this);
    }

    /**
     * Not cloneable: a copy would carry no audit data (`clone` fails before this body runs).
     *
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }
}
