<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Form type for signature coordinates: PDF URL, unit, origin and a collection of signature boxes.
 *
 * Renders a full widget (PDF viewer + boxes). Options control URL field visibility/mode,
 * unit and origin choices, and min/max entries for the boxes collection.
 */
final class SignatureCoordinatesType extends AbstractType
{
    /**
     * @param string                $examplePdfUrl Fallback PDF URL when pdf_url option is not set
     * @param array<string, array>  $namedConfigs   Named configs from nowo_pdf_signable.configs (option keys => values)
     */
    public function __construct(
        private readonly string $examplePdfUrl = '',
        private readonly array $namedConfigs = [],
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
     * @param array<string, mixed> $options Resolved options (pdf_url, url_field, units, etc.). Merged with named config when option "config" is set.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $options = $this->mergeNamedConfig($options);
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
        if (isset($options['allowed_pages'])) {
            $entryOptions['allowed_pages'] = $options['allowed_pages'];
        }
        $entryOptions['angle_enabled'] = $options['enable_rotation'];
        $boxConstraints = $options['box_constraints'] ?? [];
        if ($boxConstraints !== []) {
            $entryOptions['constraints'] = array_merge($entryOptions['constraints'] ?? [], $boxConstraints);
        }
        if ($options['sort_boxes']) {
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
                $data = $event->getData();
                if (!is_array($data) || !isset($data['signatureBoxes']) || !is_array($data['signatureBoxes'])) {
                    return;
                }
                $boxes = $data['signatureBoxes'];
                usort($boxes, static function (mixed $a, mixed $b): int {
                    $pageA = is_array($a) ? (int) ($a['page'] ?? 0) : ($a instanceof SignatureBoxModel ? $a->getPage() : 0);
                    $pageB = is_array($b) ? (int) ($b['page'] ?? 0) : ($b instanceof SignatureBoxModel ? $b->getPage() : 0);
                    if ($pageA !== $pageB) {
                        return $pageA <=> $pageB;
                    }
                    $yA = is_array($a) ? (float) ($a['y'] ?? 0) : ($a instanceof SignatureBoxModel ? $a->getY() : 0.0);
                    $yB = is_array($b) ? (float) ($b['y'] ?? 0) : ($b instanceof SignatureBoxModel ? $b->getY() : 0.0);
                    if (abs($yA - $yB) > 0.0001) {
                        return $yA <=> $yB;
                    }
                    $xA = is_array($a) ? (float) ($a['x'] ?? 0) : ($a instanceof SignatureBoxModel ? $a->getX() : 0.0);
                    $xB = is_array($b) ? (float) ($b['x'] ?? 0) : ($b instanceof SignatureBoxModel ? $b->getX() : 0.0);
                    return $xA <=> $xB;
                });
                $data['signatureBoxes'] = array_values($boxes);
                $event->setData($data);
            });
        }
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
        $collectionConstraints = [];
        if ($maxEntries !== null || $options['min_entries'] > 0) {
            $collectionConstraints[] = new Count(
                min: $options['min_entries'],
                max: $maxEntries,
                minMessage: 'signature_coordinates_type.signature_boxes.min_message',
                maxMessage: 'signature_coordinates_type.signature_boxes.max_message',
            );
        }
        $uniqueNamesOpt = $options['unique_box_names'];
        if ($uniqueNamesOpt === true || (is_array($uniqueNamesOpt) && $uniqueNamesOpt !== [])) {
            $namesToEnforce = $uniqueNamesOpt === true ? null : array_fill_keys(array_map('trim', $uniqueNamesOpt), true);
            $collectionConstraints[] = new Callback(function (mixed $boxes, ExecutionContextInterface $context) use ($namesToEnforce): void {
                if (!is_array($boxes)) {
                    return;
                }
                $seen = [];
                foreach ($boxes as $index => $box) {
                    if (!$box instanceof SignatureBoxModel) {
                        continue;
                    }
                    $name = trim($box->getName());
                    if ($name === '') {
                        continue;
                    }
                    if ($namesToEnforce !== null && !isset($namesToEnforce[$name])) {
                        continue;
                    }
                    if (isset($seen[$name])) {
                        $context->buildViolation('signature_coordinates_type.signature_boxes.unique_names_message')
                            ->atPath('[' . $index . '].name')
                            ->addViolation();
                    } else {
                        $seen[$name] = $index;
                    }
                }
            });
        }
        if ($options['prevent_box_overlap']) {
            $collectionConstraints[] = new Callback(function (mixed $boxes, ExecutionContextInterface $context): void {
                if (!is_array($boxes)) {
                    return;
                }
                $list = [];
                foreach ($boxes as $index => $box) {
                    $model = $box instanceof SignatureBoxModel
                        ? $box
                        : self::boxFromArray(is_array($box) ? $box : []);
                    if ($model === null) {
                        continue;
                    }
                    $list[] = ['index' => $index, 'box' => $model];
                }
                for ($i = 0; $i < count($list); $i++) {
                    for ($j = $i + 1; $j < count($list); $j++) {
                        $a = $list[$i]['box'];
                        $b = $list[$j]['box'];
                        if ($a->getPage() !== $b->getPage()) {
                            continue;
                        }
                        if (self::boxesOverlap($a, $b)) {
                            $context->buildViolation('signature_coordinates_type.signature_boxes.no_overlap_message')
                                ->atPath('[' . $list[$j]['index'] . ']')
                                ->addViolation();
                        }
                    }
                }
            });
        }
        $collectionOptions['constraints'] = array_merge(
            $collectionConstraints,
            $options['collection_constraints'] ?? []
        );
        $builder->add('signatureBoxes', CollectionType::class, $collectionOptions);
    }

    /**
     * Passes signature_coordinates_options to the view for the PDF viewer and box logic.
     *
     * @param FormView       $view    The form view
     * @param FormInterface  $form    The form
     * @param array<string, mixed> $options Resolved form options
     *
     * @return void
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $options = $this->mergeNamedConfig($options);
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
            'allowed_pages' => $options['allowed_pages'] ?? null,
            'prevent_box_overlap' => $options['prevent_box_overlap'],
            'box_defaults_by_name' => $options['box_defaults_by_name'] ?? [],
            'enable_rotation' => $options['enable_rotation'],
        ];
    }

    /**
     * Configures default options and allowed types/values for URL, unit, origin and boxes.
     *
     * @param OptionsResolver $resolver The options resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SignatureCoordinatesModel::class,
            'translation_domain' => 'nowo_pdf_signable',

            // Named config from nowo_pdf_signable.configs (options merged; passed options override)
            'config' => null,

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
            'unique_box_names' => false,
            'signature_box_options' => [],
            /** @see ROADMAP.md "Page restriction" — limit which pages boxes can be placed on */
            'allowed_pages' => null,
            /** @see ROADMAP.md "Box order" — sort boxes by page, then Y, then X on submit */
            'sort_boxes' => false,
            /** Prevent overlapping boxes on the same page (validated on submit and enforced in frontend) */
            'prevent_box_overlap' => true,
            /** @see ROADMAP.md "Customisable constraints" — additional constraints on the collection */
            'collection_constraints' => [],
            /** @see ROADMAP.md "Customisable constraints" — additional constraints on each box (SignatureBoxModel) */
            'box_constraints' => [],
            /** @see ROADMAP.md "Default values per box name" — default width, height, x, y, angle per name */
            'box_defaults_by_name' => [],
            /** When true, each box has a rotation angle field and the viewer shows a rotate handle. When false, angle is not shown and defaults to 0. */
            'enable_rotation' => false,
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
        $resolver->setAllowedTypes('unique_box_names', ['bool', 'array']);
        $resolver->setAllowedValues('unique_box_names', static function ($value): bool {
            if (is_bool($value)) {
                return true;
            }
            return is_array($value) && array_reduce($value, static fn ($carry, $v) => $carry && is_string($v), true);
        });
        $resolver->setAllowedTypes('signature_box_options', 'array');
        $resolver->setAllowedTypes('config', ['string', 'null']);
        $resolver->setAllowedTypes('allowed_pages', ['array', 'null']);
        $resolver->setAllowedValues('allowed_pages', static function ($value): bool {
            if ($value === null) {
                return true;
            }
            if (!is_array($value)) {
                return false;
            }
            foreach ($value as $v) {
                $p = is_int($v) ? $v : (is_string($v) && is_numeric($v) ? (int) $v : -1);
                if ($p < 1) {
                    return false;
                }
            }
            return true;
        });
        $resolver->setAllowedTypes('sort_boxes', 'bool');
        $resolver->setAllowedTypes('prevent_box_overlap', 'bool');
        $resolver->setAllowedTypes('collection_constraints', 'array');
        $resolver->setAllowedTypes('box_constraints', 'array');
        $resolver->setAllowedTypes('box_defaults_by_name', 'array');
        $resolver->setAllowedTypes('enable_rotation', 'bool');
    }

    /**
     * Builds a SignatureBoxModel from a raw array (e.g. submitted form data) for overlap validation.
     * Returns null if the array does not contain the required keys.
     *
     * @param array<string, mixed> $arr
     */
    private static function boxFromArray(array $arr): ?SignatureBoxModel
    {
        if (!isset($arr['page'], $arr['x'], $arr['y'], $arr['width'], $arr['height'])) {
            return null;
        }
        $box = new SignatureBoxModel();
        $box->setPage((int) $arr['page']);
        $box->setX((float) $arr['x']);
        $box->setY((float) $arr['y']);
        $box->setWidth((float) $arr['width']);
        $box->setHeight((float) $arr['height']);
        if (isset($arr['name'])) {
            $box->setName((string) $arr['name']);
        }
        if (array_key_exists('angle', $arr)) {
            $box->setAngle((float) $arr['angle']);
        }
        return $box;
    }

    /**
     * Returns true if two boxes on the same page have overlapping rectangles (in coordinate space).
     *
     * @param SignatureBoxModel $a First box
     * @param SignatureBoxModel $b Second box
     *
     * @return bool True if on the same page and rectangles intersect
     */
    public static function boxesOverlap(SignatureBoxModel $a, SignatureBoxModel $b): bool
    {
        if ($a->getPage() !== $b->getPage()) {
            return false;
        }
        $ax2 = $a->getX() + $a->getWidth();
        $bx2 = $b->getX() + $b->getWidth();
        $ay2 = $a->getY() + $a->getHeight();
        $by2 = $b->getY() + $b->getHeight();
        return $a->getX() < $bx2 && $b->getX() < $ax2 && $a->getY() < $by2 && $b->getY() < $ay2;
    }

    /**
     * Merges options with the named config when option "config" is set.
     * Named config is the base; options passed to the type override (same keys in $options win).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function mergeNamedConfig(array $options): array
    {
        $name = $options['config'] ?? null;
        if ($name === null || $name === '' || !isset($this->namedConfigs[$name]) || !is_array($this->namedConfigs[$name])) {
            return $options;
        }
        $merged = array_merge($this->namedConfigs[$name], $options);
        unset($merged['config']);
        return $merged;
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
