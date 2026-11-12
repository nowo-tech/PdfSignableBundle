<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Submission;

use Nowo\PdfSignableBundle\Model\SignatureBoxModel;

use function is_string;

/**
 * One signature box of a {@see SignatureSubmission} (3.10, REQ-ARCH-001 rules 1 and 3): an immutable copy of a
 * {@see SignatureBoxModel}, with no framework type.
 *
 * The signature image ({@see getSignatureData()}, a data URL of the drawn or uploaded signature) is kept outside the
 * object's properties, so a dump, `var_export()` or `(array)` cast of the box does not show it. The box cannot be
 * cloned or serialized; use {@see toArray()} to export it on purpose.
 */
final readonly class SignatureBox
{
    /**
     * @param string $name box name (role / signer id)
     * @param int $page 1-based page number
     * @param string|null $signedAt when the box was signed (as submitted), null when not signed
     * @param string|null $signatureData signature image (data URL), null when none
     */
    public function __construct(
        public string $name,
        public int $page,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public float $angle = 0.0,
        public ?string $signedAt = null,
        ?string $signatureData = null,
    ) {
        SensitiveValues::put($this, ['signatureData' => $signatureData]);
    }

    public static function fromModel(SignatureBoxModel $box): self
    {
        return new self(
            $box->getName(),
            $box->getPage(),
            $box->getX(),
            $box->getY(),
            $box->getWidth(),
            $box->getHeight(),
            $box->getAngle(),
            $box->getSignedAt(),
            $box->getSignatureData(),
        );
    }

    /** The signature image (data URL) as submitted, null when the box has none. */
    public function getSignatureData(): ?string
    {
        $data = SensitiveValues::get($this, 'signatureData');

        return is_string($data) ? $data : null;
    }

    public function hasSignatureData(): bool
    {
        return ($this->getSignatureData() ?? '') !== '';
    }

    /**
     * The same array as {@see SignatureBoxModel::toArray()}.
     *
     * @return array{name: string, page: int, x: float, y: float, width: float, height: float, angle: float, signature_data?: string, signed_at?: string}
     */
    public function toArray(): array
    {
        return $this->toModel()->toArray();
    }

    /** A new mutable model with the same values (for code written against the model). */
    public function toModel(): SignatureBoxModel
    {
        return (new SignatureBoxModel())
            ->setName($this->name)
            ->setPage($this->page)
            ->setX($this->x)
            ->setY($this->y)
            ->setWidth($this->width)
            ->setHeight($this->height)
            ->setAngle($this->angle)
            ->setSignatureData($this->getSignatureData())
            ->setSignedAt($this->signedAt);
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
     * Not cloneable: a copy would carry no signature data (`clone` fails before this body runs).
     *
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }
}
