<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use Nowo\PdfSignableBundle\Form\AcroFormEditorPanelType;
use Nowo\PdfSignableBundle\Form\AcroFormEditorType;
use Nowo\PdfSignableBundle\Form\AcroFormFieldEditType;
use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\PreloadedExtension;

/**
 * FormKit wiring for form tests without a kernel: the bundle form types are FormKit Types
 * (#[FormKitConfig] + FormOptionsTrait) and need the FormOptionsMerger the FormKit compiler pass injects.
 */
final class FormKitTestSupport
{
    public static function merger(): FormOptionsMerger
    {
        $profile = PdfSignableExtension::formKitProfile();
        unset($profile['alias']);

        /** @var array<string, array{translation_domain: string, defaults: array{attr: array<string, mixed>, row_attr: array<string, mixed>}, field_types: array<string, array<string, mixed>>}> $profiles */
        $profiles = [NowoPdfSignableBundle::FORM_KIT_PROFILE => $profile + ['by_form' => []]];

        return new FormOptionsMerger(
            $profiles,
            NowoPdfSignableBundle::FORM_KIT_PROFILE,
            new ConstraintDefinitionFactory(),
        );
    }

    /**
     * @template T of FormTypeInterface
     *
     * @param T $type
     *
     * @return T
     */
    public static function withMerger(FormTypeInterface $type): FormTypeInterface
    {
        if (method_exists($type, 'setFormOptionsMerger')) {
            $type->setFormOptionsMerger(self::merger());
        }

        return $type;
    }

    /**
     * Every bundle form type with its default constructor arguments, wired with the merger. Types passed in
     * $types replace the default instance of the same class.
     *
     * @param list<FormTypeInterface<mixed>> $types
     */
    public static function extension(array $types = []): PreloadedExtension
    {
        $byClass = [];
        foreach ([
            new AcroFormEditorPanelType(),
            new AcroFormEditorType(),
            new AcroFormFieldEditType(),
            new SignatureBoxType(),
            new SignatureCoordinatesType('', []),
            ...$types,
        ] as $type) {
            $byClass[$type::class] = self::withMerger($type);
        }

        return new PreloadedExtension(array_values($byClass), []);
    }
}
