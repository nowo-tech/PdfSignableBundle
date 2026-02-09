<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Model;

/**
 * Main model for signature coordinates: PDF URL, unit, origin and signature boxes.
 *
 * Used as data_class for SignatureCoordinatesType. Unit and origin values
 * are defined as class constants (UNIT_*, ORIGIN_*).
 */
class SignatureCoordinatesModel
{
    /** Points (1/72 inch). */
    public const UNIT_PT = 'pt';
    /** Millimeters. */
    public const UNIT_MM = 'mm';
    /** Centimeters. */
    public const UNIT_CM = 'cm';
    /** Pixels (96 per inch). */
    public const UNIT_PX = 'px';
    /** Inches. */
    public const UNIT_IN = 'in';

    /** Origin at top-left corner. */
    public const ORIGIN_TOP_LEFT = 'top_left';
    /** Origin at bottom-left corner. */
    public const ORIGIN_BOTTOM_LEFT = 'bottom_left';
    /** Origin at top-right corner. */
    public const ORIGIN_TOP_RIGHT = 'top_right';
    /** Origin at bottom-right corner. */
    public const ORIGIN_BOTTOM_RIGHT = 'bottom_right';

    /** @var string|null PDF document URL (null if not set). */
    private ?string $pdfUrl = null;

    /** @var string Measurement unit (one of UNIT_* constants). */
    private string $unit = self::UNIT_MM;

    /** @var string Coordinate origin (one of ORIGIN_* constants). */
    private string $origin = self::ORIGIN_BOTTOM_LEFT;

    /** @var SignatureBoxModel[] List of signature boxes. */
    private array $signatureBoxes = [];

    /** @var bool Whether the user accepted the legal effect of the signature (consent checkbox). */
    private bool $signingConsent = false;

    /**
     * Optional audit metadata (e.g. signed_at, ip, user_agent) set by the app on submit.
     * Exported in toArray for evidence; not part of the form.
     *
     * @var array<string, mixed>
     */
    private array $auditMetadata = [];

    /**
     * Gets the PDF document URL.
     *
     * @return string|null PDF URL or null if not set
     */
    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    /**
     * Sets the PDF document URL.
     *
     * @param string|null $pdfUrl PDF URL or null to clear
     *
     * @return $this
     */
    public function setPdfUrl(?string $pdfUrl): self
    {
        $this->pdfUrl = $pdfUrl;

        return $this;
    }

    /**
     * Gets the measurement unit (e.g. mm, pt).
     *
     * @return string One of UNIT_* constants
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * Sets the measurement unit.
     *
     * @param string $unit One of UNIT_* constants (pt, mm, cm, px, in)
     *
     * @return $this
     */
    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * Gets the coordinate origin (e.g. bottom_left).
     *
     * @return string One of ORIGIN_* constants
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * Sets the coordinate origin.
     *
     * @param string $origin One of ORIGIN_* constants (top_left, bottom_left, top_right, bottom_right)
     *
     * @return $this
     */
    public function setOrigin(string $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    /**
     * Gets the list of signature boxes.
     *
     * @return SignatureBoxModel[]
     */
    public function getSignatureBoxes(): array
    {
        return $this->signatureBoxes;
    }

    /**
     * Sets the list of signature boxes.
     *
     * @param SignatureBoxModel[] $signatureBoxes Array of box models
     *
     * @return $this
     */
    public function setSignatureBoxes(array $signatureBoxes): self
    {
        $this->signatureBoxes = $signatureBoxes;

        return $this;
    }

    /**
     * Appends a signature box to the collection.
     *
     * @param SignatureBoxModel $box Box to add
     *
     * @return $this
     */
    public function addSignatureBox(SignatureBoxModel $box): self
    {
        $this->signatureBoxes[] = $box;

        return $this;
    }

    /**
     * Gets whether the user gave explicit consent (e.g. "I accept the legal effect").
     *
     * @return bool
     */
    public function getSigningConsent(): bool
    {
        return $this->signingConsent;
    }

    /**
     * Sets the user's explicit consent to the legal effect of the signature.
     *
     * @param bool $signingConsent
     *
     * @return $this
     */
    public function setSigningConsent(bool $signingConsent): self
    {
        $this->signingConsent = $signingConsent;

        return $this;
    }

    /**
     * Gets optional audit metadata (e.g. signed_at, ip, user_agent) for evidence.
     *
     * @return array<string, mixed>
     */
    public function getAuditMetadata(): array
    {
        return $this->auditMetadata;
    }

    /**
     * Sets audit metadata (e.g. from Request on submit: signed_at, ip, user_agent).
     *
     * @param array<string, mixed> $auditMetadata
     *
     * @return $this
     */
    public function setAuditMetadata(array $auditMetadata): self
    {
        $this->auditMetadata = $auditMetadata;

        return $this;
    }

    /**
     * Exports the coordinates model to an array (e.g. for JSON/YAML export).
     *
     * @return array{pdf_url: string|null, unit: string, origin: string, signature_boxes: array, signing_consent?: bool, audit_metadata?: array}
     */
    public function toArray(): array
    {
        $out = [
            'pdf_url' => $this->pdfUrl,
            'unit' => $this->unit,
            'origin' => $this->origin,
            'signature_boxes' => array_map(static fn (SignatureBoxModel $box) => $box->toArray(), $this->signatureBoxes),
        ];
        if ($this->signingConsent) {
            $out['signing_consent'] = true;
        }
        if ([] !== $this->auditMetadata) {
            $out['audit_metadata'] = $this->auditMetadata;
        }

        return $out;
    }

    /**
     * Creates a coordinates model from an array (e.g. from JSON/YAML import).
     *
     * @param array{pdf_url?: string|null, unit?: string, origin?: string, signature_boxes?: array<int, array>, signing_consent?: bool, audit_metadata?: array} $data Raw data; defaults applied for missing values
     *
     * @return self New instance with data applied
     */
    public static function fromArray(array $data): self
    {
        $model = new self();
        $model->setPdfUrl($data['pdf_url'] ?? null);
        $model->setUnit((string) ($data['unit'] ?? self::UNIT_MM));
        $model->setOrigin((string) ($data['origin'] ?? self::ORIGIN_BOTTOM_LEFT));
        $boxes = $data['signature_boxes'] ?? [];
        foreach ($boxes as $boxData) {
            if (is_array($boxData)) {
                $model->addSignatureBox(SignatureBoxModel::fromArray($boxData));
            }
        }
        $model->setSigningConsent(!empty($data['signing_consent']));
        if (isset($data['audit_metadata']) && is_array($data['audit_metadata'])) {
            $model->setAuditMetadata($data['audit_metadata']);
        }

        return $model;
    }
}
