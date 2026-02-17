<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form\Extension;

use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Injects default box dimension options from bundle config (nowo_pdf_signable)
 * so YAML defaults apply unless overridden when creating the form.
 */
final class SignatureCoordinatesTypeExtension extends AbstractTypeExtension
{
    /**
     * @param float|null $defaultBoxWidth Default width for new signature boxes (from signature config)
     * @param float|null $defaultBoxHeight Default height for new signature boxes (from signature config)
     * @param bool $lockBoxWidth Whether to lock width and hide the field (from signature config)
     * @param bool $lockBoxHeight Whether to lock height and hide the field (from signature config)
     */
    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.signature.default_box_width')]
        private readonly ?float $defaultBoxWidth = null,
        #[Autowire(param: 'nowo_pdf_signable.signature.default_box_height')]
        private readonly ?float $defaultBoxHeight = null,
        #[Autowire(param: 'nowo_pdf_signable.signature.lock_box_width')]
        private readonly bool $lockBoxWidth = false,
        #[Autowire(param: 'nowo_pdf_signable.signature.lock_box_height')]
        private readonly bool $lockBoxHeight = false,
        #[Autowire(param: 'nowo_pdf_signable.signature.min_box_width')]
        private readonly ?float $minBoxWidth = null,
        #[Autowire(param: 'nowo_pdf_signable.signature.min_box_height')]
        private readonly ?float $minBoxHeight = null,
    ) {
    }

    /**
     * @return iterable<int, class-string<SignatureCoordinatesType>>
     */
    public static function getExtendedTypes(): iterable
    {
        return [SignatureCoordinatesType::class];
    }

    /**
     * Sets default_box_width, default_box_height, lock_box_width, lock_box_height, min_box_width, min_box_height from bundle config.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'default_box_width'  => $this->defaultBoxWidth,
            'default_box_height' => $this->defaultBoxHeight,
            'lock_box_width'     => $this->lockBoxWidth,
            'lock_box_height'    => $this->lockBoxHeight,
            'min_box_width'      => $this->minBoxWidth,
            'min_box_height'     => $this->minBoxHeight,
        ]);
    }
}
