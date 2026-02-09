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

    private ?string $pdfUrl = null;
    private string $unit = self::UNIT_MM;
    private string $origin = self::ORIGIN_BOTTOM_LEFT;

    /** @var SignatureBoxModel[] */
    private array $signatureBoxes = [];

    /**
     * Gets the PDF document URL.
     */
    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    /**
     * Sets the PDF document URL.
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
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * Sets the measurement unit.
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
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * Sets the coordinate origin.
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
     * @param SignatureBoxModel[] $signatureBoxes
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
     * @return $this
     */
    public function addSignatureBox(SignatureBoxModel $box): self
    {
        $this->signatureBoxes[] = $box;

        return $this;
    }

    /**
     * Exports the coordinates model to an array (e.g. for JSON/YAML export).
     *
     * @return array{pdf_url: string|null, unit: string, origin: string, signature_boxes: array<int, array{name: string, page: int, x: float, y: float, width: float, height: float, angle: float}>}
     */
    public function toArray(): array
    {
        return [
            'pdf_url' => $this->pdfUrl,
            'unit' => $this->unit,
            'origin' => $this->origin,
            'signature_boxes' => array_map(static fn (SignatureBoxModel $box) => $box->toArray(), $this->signatureBoxes),
        ];
    }

    /**
     * Creates a coordinates model from an array (e.g. from JSON/YAML import).
     *
     * @param array{pdf_url?: string|null, unit?: string, origin?: string, signature_boxes?: array<int, array>} $data
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

        return $model;
    }
}
