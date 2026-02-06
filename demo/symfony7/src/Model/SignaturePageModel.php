<?php

declare(strict_types=1);

namespace App\Model;

use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;

/**
 * Demo page model: holds the signature coordinates block (SignatureCoordinatesType / SignatureBoxType).
 */
class SignaturePageModel
{
    private ?SignatureCoordinatesModel $signatureCoordinates = null;

    public function __construct()
    {
        $this->signatureCoordinates = new SignatureCoordinatesModel();
    }

    /**
     * Gets the signature coordinates model (PDF URL, unit, origin, boxes).
     */
    public function getSignatureCoordinates(): SignatureCoordinatesModel
    {
        return $this->signatureCoordinates ?? new SignatureCoordinatesModel();
    }

    /**
     * Sets the signature coordinates model.
     *
     * @return $this
     */
    public function setSignatureCoordinates(?SignatureCoordinatesModel $signatureCoordinates): self
    {
        $this->signatureCoordinates = $signatureCoordinates;
        return $this;
    }
}
