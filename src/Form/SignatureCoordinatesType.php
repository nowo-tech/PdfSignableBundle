<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Form type for signature coordinates: PDF URL, unit, origin and a collection of signature boxes.
 *
 * Renders a full widget (PDF viewer + boxes). Options control URL field visibility/mode,
 * unit and origin choices, and min/max entries for the boxes collection.
 */
final class SignatureCoordinatesType extends AbstractType
{
    private const PREFIX_TRANS = 'signature_coordinates_type';

    /**
     * @param string $examplePdfUrl Fallback PDF URL when pdf_url option is not set
     */
    public function __construct(
        private readonly string $examplePdfUrl = '',
    ) {
    }

    /** URL is a free-text input field. */
    public const URL_MODE_INPUT = 'input';

    /** URL is chosen from a dropdown (url_choices). */
    public const URL_MODE_CHOICE = 'choice';

    /** Unit is a choice list. */
    public const UNIT_MODE_CHOICE = 'choice';

    /** Unit is a text input (validated against allowed units). */
    public const UNIT_MODE_INPUT = 'input';

    /** Origin is a choice list. */
    public const ORIGIN_MODE_CHOICE = 'choice';

    /** Origin is a text input (validated against allowed origins). */
    public const ORIGIN_MODE_INPUT = 'input';

    /**
     * Returns all supported unit values (pt, mm, cm, px, in).
     *
     * @return list<string>
     */
    public static function getAllUnits(): array
    {
        return [
            SignatureCoordinatesModel::UNIT_PT,
            SignatureCoordinatesModel::UNIT_MM,
            SignatureCoordinatesModel::UNIT_CM,
            SignatureCoordinatesModel::UNIT_PX,
            SignatureCoordinatesModel::UNIT_IN,
        ];
    }

    /**
     * Returns all supported origin values (top_left, bottom_left, top_right, bottom_right).
     *
     * @return list<string>
     */
    public static function getAllOrigins(): array
    {
        return [
            SignatureCoordinatesModel::ORIGIN_TOP_LEFT,
            SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            SignatureCoordinatesModel::ORIGIN_TOP_RIGHT,
            SignatureCoordinatesModel::ORIGIN_BOTTOM_RIGHT,
        ];
    }

    /**
     * Builds the form: pdfUrl, unit, origin and signatureBoxes collection.
     *
     * @param array<string, mixed> $options Resolved options (pdf_url, url_field, units, etc.)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $units = $options['units'] ?? self::getAllUnits();
        $origins = $options['origins'] ?? self::getAllOrigins();

        $unitChoices = $this->buildUnitChoices($units);
        $originChoices = $this->buildOriginChoices($origins);

        // Resolve pdf_url: use option if set, otherwise fallback to bundle example
        $pdfUrl = $options['pdf_url'] ?? null;
        if (($pdfUrl === null || $pdfUrl === '') && $this->examplePdfUrl !== '') {
            $pdfUrl = $this->examplePdfUrl;
        }

        // Pre-populate model with pdf_url when empty, so the PDF can auto-load in the viewer
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($pdfUrl): void {
            $model = $event->getData();
            if ($model instanceof SignatureCoordinatesModel
                && ($model->getPdfUrl() === null || $model->getPdfUrl() === '')
                && $pdfUrl !== null
                && $pdfUrl !== ''
            ) {
                $model->setPdfUrl($pdfUrl);
            }
        });

        // --- PDF URL ---
        if ($options['url_field'] === false && $pdfUrl !== null && $pdfUrl !== '') {
            $builder->add('pdfUrl', HiddenType::class, [
                'data' => $pdfUrl,
                'empty_data' => $pdfUrl,
                'attr' => ['class' => 'pdf-url-input'],
            ]);
        } else {
            if ($options['url_mode'] === self::URL_MODE_CHOICE && $options['url_choices'] !== []) {
                $builder->add('pdfUrl', ChoiceType::class, [
                    'label' => $options['url_label'],
                    'choices' => $options['url_choices'],
                    'required' => true,
                    'attr' => ['class' => 'pdf-url-input form-control form-select'],
                    'placeholder' => $options['url_placeholder'],
                ]);
            } else {
                $builder->add('pdfUrl', UrlType::class, [
                    'label' => $options['url_label'],
                    'required' => true,
                    'data' => $pdfUrl,
                    'attr' => [
                        'placeholder' => $options['url_placeholder'],
                        'class' => 'pdf-url-input form-control',
                    ],
                ]);
            }
        }

        // --- Unit ---
        if ($options['unit_mode'] === self::UNIT_MODE_INPUT) {
            $builder->add('unit', TextType::class, [
                'label' => $options['unit_label'],
                'attr' => ['class' => 'unit-selector form-control form-control-sm'],
                'data' => $options['unit_default'],
                'constraints' => [new Choice(['choices' => $units])],
            ]);
        } else {
            $builder->add('unit', ChoiceType::class, [
                'label' => $options['unit_label'],
                'choices' => $unitChoices,
                'data' => $options['unit_default'],
                'attr' => ['class' => 'unit-selector form-select form-select-sm'],
            ]);
        }

        // --- Origin ---
        if ($options['origin_mode'] === self::ORIGIN_MODE_INPUT) {
            $builder->add('origin', TextType::class, [
                'label' => $options['origin_label'],
                'attr' => ['class' => 'origin-selector form-control form-control-sm'],
                'data' => $options['origin_default'],
                'constraints' => [new Choice(['choices' => $origins])],
            ]);
        } else {
            $builder->add('origin', ChoiceType::class, [
                'label' => $options['origin_label'],
                'choices' => $originChoices,
                'data' => $options['origin_default'],
                'attr' => ['class' => 'origin-selector form-select form-select-sm'],
            ]);
        }

        $entryOptions = array_merge(
            ['label' => false],
            $options['signature_box_options'] ?? []
        );
        $collectionOptions = [
            'entry_type' => SignatureBoxType::class,
            'entry_options' => $entryOptions,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => 'signature_coordinates_type.signature_boxes.label',
            'attr' => ['class' => 'signature-boxes-collection'],
        ];
        $maxEntries = $options['max_entries'];
        if ($maxEntries !== null || $options['min_entries'] > 0) {
            $collectionOptions['constraints'] = [
                new Count(
                    min: $options['min_entries'],
                    max: $maxEntries,
                    minMessage: 'signature_coordinates_type.signature_boxes.min_message',
                    maxMessage: 'signature_coordinates_type.signature_boxes.max_message',
                ),
            ];
        }
        $builder->add('signatureBoxes', CollectionType::class, $collectionOptions);
    }

    /**
     * Passes signature_coordinates_options to the view for the PDF viewer and box logic.
     *
     * @param array<string, mixed> $options Resolved form options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $pdfUrl = $options['pdf_url'] ?? null;
        if (($pdfUrl === null || $pdfUrl === '') && $this->examplePdfUrl !== '') {
            $pdfUrl = $this->examplePdfUrl;
        }
        $view->vars['signature_coordinates_options'] = [
            'pdf_url' => $pdfUrl,
            'url_field' => $options['url_field'],
            'url_mode' => $options['url_mode'],
            'unit_default' => $options['unit_default'],
            'origin_default' => $options['origin_default'],
            'min_entries' => $options['min_entries'],
            'max_entries' => $options['max_entries'],
        ];
    }

    /**
     * Configures default options and allowed types/values for URL, unit, origin and boxes.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SignatureCoordinatesModel::class,
            'translation_domain' => 'nowo_pdf_signable',

            // URL (null = use bundle example_pdf_url when set)
            'pdf_url' => null,
            'url_field' => true,
            'url_mode' => self::URL_MODE_INPUT,
            'url_choices' => [],
            'url_label' => 'signature_coordinates_type.pdf_url.label',
            'url_placeholder' => 'signature_coordinates_type.pdf_url.placeholder',

            // Unit
            'units' => null,
            'unit_default' => SignatureCoordinatesModel::UNIT_MM,
            'unit_mode' => self::UNIT_MODE_CHOICE,
            'unit_label' => 'signature_coordinates_type.unit.label',

            // Origin
            'origins' => null,
            'origin_default' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'origin_mode' => self::ORIGIN_MODE_CHOICE,
            'origin_label' => 'signature_coordinates_type.origin.label',

            // Signature boxes collection
            'min_entries' => 0,
            'max_entries' => null,
            'signature_box_options' => [],
        ]);

        $resolver->setAllowedTypes('pdf_url', ['string', 'null']);
        $resolver->setAllowedTypes('url_field', 'bool');
        $resolver->setAllowedValues('url_mode', [self::URL_MODE_INPUT, self::URL_MODE_CHOICE]);
        $resolver->setAllowedTypes('url_choices', 'array');
        $resolver->setAllowedTypes('units', ['array', 'null']);
        $resolver->setAllowedTypes('origins', ['array', 'null']);
        $resolver->setAllowedValues('unit_mode', [self::UNIT_MODE_CHOICE, self::UNIT_MODE_INPUT]);
        $resolver->setAllowedValues('origin_mode', [self::ORIGIN_MODE_CHOICE, self::ORIGIN_MODE_INPUT]);
        $resolver->setAllowedTypes('min_entries', 'int');
        $resolver->setAllowedTypes('max_entries', ['int', 'null']);
        $resolver->setAllowedTypes('signature_box_options', 'array');
    }

    /**
     * Builds choice array for unit field (label => value).
     *
     * @param list<string> $units
     * @return array<string, string>
     */
    private function buildUnitChoices(array $units): array
    {
        $labels = [
            SignatureCoordinatesModel::UNIT_PT => 'signature_coordinates_type.unit.option.pt',
            SignatureCoordinatesModel::UNIT_MM => 'signature_coordinates_type.unit.option.mm',
            SignatureCoordinatesModel::UNIT_CM => 'signature_coordinates_type.unit.option.cm',
            SignatureCoordinatesModel::UNIT_PX => 'signature_coordinates_type.unit.option.px',
            SignatureCoordinatesModel::UNIT_IN => 'signature_coordinates_type.unit.option.in',
        ];
        $result = [];
        foreach ($units as $u) {
            $result[$labels[$u] ?? $u] = $u;
        }
        return $result;
    }

    /**
     * Builds choice array for origin field (label => value).
     *
     * @param list<string> $origins
     * @return array<string, string>
     */
    private function buildOriginChoices(array $origins): array
    {
        $labels = [
            SignatureCoordinatesModel::ORIGIN_TOP_LEFT => 'signature_coordinates_type.origin.option.top_left',
            SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT => 'signature_coordinates_type.origin.option.bottom_left',
            SignatureCoordinatesModel::ORIGIN_TOP_RIGHT => 'signature_coordinates_type.origin.option.top_right',
            SignatureCoordinatesModel::ORIGIN_BOTTOM_RIGHT => 'signature_coordinates_type.origin.option.bottom_right',
        ];
        $result = [];
        foreach ($origins as $o) {
            $result[$labels[$o] ?? $o] = $o;
        }
        return $result;
    }
}
