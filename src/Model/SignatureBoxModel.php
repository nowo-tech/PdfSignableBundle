<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Model;

/**
 * Single signature box: page number, name, coordinates (x, y, width, height) and optional rotation angle.
 *
 * Used as data_class for SignatureBoxType and as collection items in SignatureCoordinatesModel.
 * All coordinates and dimensions use the unit/origin defined on the parent SignatureCoordinatesModel.
 */
class SignatureBoxModel
{
    /** @var int 1-based PDF page number. */
    private int $page = 1;

    /** @var string Box label or role (e.g. signer_1, witness). */
    private string $name = '';

    /** @var float Horizontal position in the configured unit. */
    private float $x = 0.0;

    /** @var float Vertical position in the configured unit. */
    private float $y = 0.0;

    /** @var float Box width in the configured unit. */
    private float $width = 150.0;

    /** @var float Box height in the configured unit. */
    private float $height = 40.0;

    /** @var float Rotation angle in degrees (0 = no rotation). */
    private float $angle = 0.0;

    /**
     * Optional signature image as base64 data URL (e.g. from draw pad or upload).
     * When set, the viewer can show it inside the box overlay. Not a qualified/digital signature.
     */
    private ?string $signatureData = null;

    /**
     * Optional ISO 8601 timestamp when the signature was captured (client or server).
     * Improves evidential value; backend can overwrite with server time on submit.
     */
    private ?string $signedAt = null;

    /**
     * Gets the PDF page number (1-based).
     *
     * @return int Page number (1 or greater)
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Sets the PDF page number (1-based).
     *
     * @param int $page Page number (1 or greater)
     *
     * @return $this
     */
    public function setPage(int $page): self
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Gets the box label/identifier (e.g. signer role).
     *
     * @return string Box name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the box label/identifier.
     *
     * @param string $name Box name (e.g. signer_1, witness)
     *
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the horizontal position (x) in the configured unit.
     *
     * @return float X coordinate
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Sets the horizontal position (x).
     *
     * @param float $x X coordinate in the configured unit
     *
     * @return $this
     */
    public function setX(float $x): self
    {
        $this->x = $x;

        return $this;
    }

    /**
     * Gets the vertical position (y) in the configured unit.
     *
     * @return float Y coordinate
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Sets the vertical position (y).
     *
     * @param float $y Y coordinate in the configured unit
     *
     * @return $this
     */
    public function setY(float $y): self
    {
        $this->y = $y;

        return $this;
    }

    /**
     * Gets the box width in the configured unit.
     *
     * @return float Width
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * Sets the box width.
     *
     * @param float $width Width in the configured unit
     *
     * @return $this
     */
    public function setWidth(float $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Gets the box height in the configured unit.
     *
     * @return float Height
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * Sets the box height.
     *
     * @param float $height Height in the configured unit
     *
     * @return $this
     */
    public function setHeight(float $height): self
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Gets the rotation angle in degrees (0 = no rotation).
     *
     * @return float Angle in degrees
     */
    public function getAngle(): float
    {
        return $this->angle;
    }

    /**
     * Sets the rotation angle in degrees.
     *
     * @param float $angle Angle in degrees (e.g. -180 to 180)
     *
     * @return $this
     */
    public function setAngle(float $angle): self
    {
        $this->angle = $angle;

        return $this;
    }

    /**
     * Gets the optional signature image (base64 data URL), or null if not set.
     */
    public function getSignatureData(): ?string
    {
        return $this->signatureData;
    }

    /**
     * Sets the signature image (base64 data URL from draw pad or upload).
     *
     * @param string|null $signatureData Data URL (e.g. data:image/png;base64,...) or null to clear
     *
     * @return $this
     */
    public function setSignatureData(?string $signatureData): self
    {
        $this->signatureData = $signatureData;

        return $this;
    }

    /**
     * Gets the optional timestamp when the signature was captured (ISO 8601).
     */
    public function getSignedAt(): ?string
    {
        return $this->signedAt;
    }

    /**
     * Sets the timestamp when the signature was captured (ISO 8601; e.g. from client or server).
     *
     * @param string|null $signedAt ISO 8601 datetime or null to clear
     *
     * @return $this
     */
    public function setSignedAt(?string $signedAt): self
    {
        $this->signedAt = $signedAt;

        return $this;
    }

    /**
     * Exports the box to an array (e.g. for JSON/YAML export).
     *
     * @return array{name: string, page: int, x: float, y: float, width: float, height: float, angle: float, signature_data?: string, signed_at?: string}
     */
    public function toArray(): array
    {
        $out = [
            'name'   => $this->name,
            'page'   => $this->page,
            'x'      => $this->x,
            'y'      => $this->y,
            'width'  => $this->width,
            'height' => $this->height,
            'angle'  => $this->angle,
        ];
        if ($this->signatureData !== null && $this->signatureData !== '') {
            $out['signature_data'] = $this->signatureData;
        }
        if ($this->signedAt !== null && $this->signedAt !== '') {
            $out['signed_at'] = $this->signedAt;
        }

        return $out;
    }

    /**
     * Creates a box from an array (e.g. from JSON/YAML import).
     *
     * @param array{name?: string, page?: int, x?: float, y?: float, width?: float, height?: float, angle?: float, signature_data?: string|null, signed_at?: string|null} $data Raw data with optional keys; defaults applied for missing values
     *
     * @return self New instance with data applied
     */
    public static function fromArray(array $data): self
    {
        $box = new self();
        $box->setName((string) ($data['name'] ?? ''));
        $box->setPage((int) ($data['page'] ?? 1));
        $box->setX((float) ($data['x'] ?? 0));
        $box->setY((float) ($data['y'] ?? 0));
        $box->setWidth((float) ($data['width'] ?? 150));
        $box->setHeight((float) ($data['height'] ?? 40));
        $box->setAngle((float) ($data['angle'] ?? 0));
        $box->setSignatureData(isset($data['signature_data']) && $data['signature_data'] !== '' ? (string) $data['signature_data'] : null);
        $box->setSignedAt(isset($data['signed_at']) && $data['signed_at'] !== '' ? (string) $data['signed_at'] : null);

        return $box;
    }
}
