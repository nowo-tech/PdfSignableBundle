<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Model;

/**
 * Single signature box: page number, name, coordinates (x, y, width, height) and optional rotation angle.
 *
 * Used as data_class for SignatureBoxType and as collection items in SignatureCoordinatesModel.
 */
class SignatureBoxModel
{
    private int $page = 1;
    private string $name = '';
    private float $x = 0.0;
    private float $y = 0.0;
    private float $width = 150.0;
    private float $height = 40.0;

    /** Rotation angle in degrees (0 = no rotation). */
    private float $angle = 0.0;

    /**
     * Gets the PDF page number (1-based).
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Sets the PDF page number (1-based).
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
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the box label/identifier.
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
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Sets the horizontal position (x).
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
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Sets the vertical position (y).
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
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * Sets the box width.
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
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * Sets the box height.
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
     */
    public function getAngle(): float
    {
        return $this->angle;
    }

    /**
     * Sets the rotation angle in degrees.
     *
     * @return $this
     */
    public function setAngle(float $angle): self
    {
        $this->angle = $angle;
        return $this;
    }

    /**
     * Exports the box to an array (e.g. for JSON/YAML export).
     *
     * @return array{name: string, page: int, x: float, y: float, width: float, height: float, angle: float}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'page' => $this->page,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'angle' => $this->angle,
        ];
    }

    /**
     * Creates a box from an array (e.g. from JSON/YAML import).
     *
     * @param array{name?: string, page?: int, x?: float, y?: float, width?: float, height?: float, angle?: float} $data
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
        return $box;
    }
}
