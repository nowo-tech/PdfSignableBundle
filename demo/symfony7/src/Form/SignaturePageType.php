<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo form type that embeds SignatureCoordinatesType.
 *
 * Accepts optional "signature_options" to customize the signature block per page
 * (e.g. fixed URL, URL as choice, limited boxes, predefined boxes).
 */
final class SignaturePageType extends AbstractType
{
    /**
     * @param string|null $examplePdfUrl Default PDF URL from config
     */
    public function __construct(
        private readonly ?string $examplePdfUrl = null,
    ) {
    }

    /**
     * Adds the signatureCoordinates child (SignatureCoordinatesType) with merged base and signature_options.
     *
     * @param array<string, mixed> $options Form options (signature_options)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $baseOptions = [
            'label' => false,
            'units' => ['mm', 'cm', 'pt'],
            'unit_default' => 'mm',
            'origin_default' => 'bottom_left',
        ];
        if ($this->examplePdfUrl !== null && $this->examplePdfUrl !== '') {
            $baseOptions['pdf_url'] = $this->examplePdfUrl;
        }
        $signatureOptions = array_merge($baseOptions, $options['signature_options'] ?? []);
        $builder->add('signatureCoordinates', SignatureCoordinatesType::class, $signatureOptions);
    }

    /**
     * Sets data_class and signature_options default.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Model\SignaturePageModel::class,
            'signature_options' => [],
        ]);
        $resolver->setAllowedTypes('signature_options', 'array');
    }
}
