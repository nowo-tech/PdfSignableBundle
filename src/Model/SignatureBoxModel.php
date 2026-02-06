<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Model;

/**
 * Single signature box: page number, name and coordinates (x, y, width, height).
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
}
