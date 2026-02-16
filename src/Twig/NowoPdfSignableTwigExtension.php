<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for PdfSignableBundle.
 *
 * Exposes:
 * - nowo_pdf_signable_include_assets() — include CSS/JS only once per request
 * - nowo_pdf_signable_acroform_strings() — translated strings for AcroForm editor (JSON)
 * - nowo_pdf_signable_acroform_editor_config() — AcroForm editor config from bundle
 *
 * @see src/Resources/views/form/theme.html.twig Uses this function in signature_coordinates_widget
 * @see src/Resources/views/acroform/editor_root.html.twig Uses acroform strings and config
 */
final class NowoPdfSignableTwigExtension extends AbstractExtension
{
    private const REQUEST_ATTR = '_nowo_pdf_signable_assets_included';

    /** Keys for AcroForm editor UI. Translations come from nowo_pdf_signable (acroform_editor.*) and are injected via Twig into #acroform-editor-strings. */
    private const ACROFORM_STRING_KEYS = [
        'msg_draft_updated', 'msg_enter_document_key', 'msg_load_pdf_first_load', 'msg_draft_loaded',
        'msg_no_data_server', 'msg_draft_saved', 'msg_draft_cleared', 'msg_invalid_json', 'msg_apply_pdf_first',
        'msg_pdf_modified_received', 'msg_process_first', 'msg_process_success', 'msg_processed',
        'msg_refresh_load_first', 'msg_field_name_required', 'modal_edit_title', 'modal_label', 'modal_label_placeholder',
        'modal_field_name', 'modal_field_name_placeholder', 'modal_control_type', 'modal_rect_label',
        'modal_rect_placeholder', 'modal_max_len', 'modal_max_len_placeholder', 'modal_hidden', 'modal_create_if_missing',
        'modal_options_label', 'modal_options_placeholder', 'modal_default_value', 'modal_default_value_placeholder',
        'modal_default_checked', 'modal_cancel', 'modal_save', 'modal_font_label', 'modal_font_size',
        'modal_font_size_placeholder', 'modal_font_family', 'modal_font_auto_size', 'modal_checkbox_value_on',
        'modal_checkbox_value_off', 'modal_checkbox_icon', 'modal_checkbox_icon_check', 'modal_checkbox_icon_cross',
        'modal_checkbox_icon_dot', 'btn_apply_pdf', 'btn_apply_pdf_title', 'btn_process', 'btn_process_title',
        'list_id', 'list_type', 'list_page', 'list_label', 'list_field_name', 'list_current_value', 'list_no_fields', 'list_hidden_suffix',
        'btn_restore_title', 'btn_hide_title', 'btn_edit_title', 'btn_move_resize_title', 'btn_add_field',
        'btn_add_field_title', 'btn_edit_mode', 'btn_edit_mode_title', 'msg_edit_mode_hint', 'msg_click_pdf_to_place',
        'row_click_highlight', 'download_filename', 'error_prefix', 'new_field_name_pattern',
        'add_field_mode_btn', 'add_field_mode_btn_done', 'add_field_mode_title',
    ];

    /**
     * @param RequestStack       $requestStack Request stack for asset inclusion
     * @param TranslatorInterface $translator   Translator for AcroForm strings
     * @param string             $labelMode    acroform.label_mode (deprecated)
     * @param array<int, string> $labelChoices acroform.label_choices (deprecated)
     * @param string             $labelOtherText acroform.label_other_text (deprecated)
     * @param string             $fieldNameMode acroform.field_name_mode
     * @param array<int, string> $fieldNameChoices acroform.field_name_choices
     * @param string             $fieldNameOtherText acroform.field_name_other_text
     * @param bool               $showFieldRect acroform.show_field_rect
     * @param array<int, int>    $fontSizes    acroform.font_sizes
     * @param array<int, string> $fontFamilies acroform.font_families
     * @param float              $minFieldWidth acroform.min_field_width
     * @param float              $minFieldHeight acroform.min_field_height
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'nowo_pdf_signable.acroform.label_mode')]
        private readonly string $labelMode = 'input',
        #[Autowire(param: 'nowo_pdf_signable.acroform.label_choices')]
        private readonly array $labelChoices = [],
        #[Autowire(param: 'nowo_pdf_signable.acroform.label_other_text')]
        private readonly string $labelOtherText = '',
        #[Autowire(param: 'nowo_pdf_signable.acroform.field_name_mode')]
        private readonly string $fieldNameMode = 'input',
        #[Autowire(param: 'nowo_pdf_signable.acroform.field_name_choices')]
        private readonly array $fieldNameChoices = [],
        #[Autowire(param: 'nowo_pdf_signable.acroform.field_name_other_text')]
        private readonly string $fieldNameOtherText = '',
        #[Autowire(param: 'nowo_pdf_signable.acroform.show_field_rect')]
        private readonly bool $showFieldRect = true,
        #[Autowire(param: 'nowo_pdf_signable.acroform.font_sizes')]
        private readonly array $fontSizes = [],
        #[Autowire(param: 'nowo_pdf_signable.acroform.font_families')]
        private readonly array $fontFamilies = [],
        #[Autowire(param: 'nowo_pdf_signable.acroform.min_field_width')]
        private readonly float $minFieldWidth = 12.0,
        #[Autowire(param: 'nowo_pdf_signable.acroform.min_field_height')]
        private readonly float $minFieldHeight = 12.0,
    ) {
    }

    /**
     * Registers Twig functions.
     *
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_pdf_signable_include_assets', [$this, 'shouldIncludeAssets']),
            new TwigFunction('nowo_pdf_signable_acroform_strings', [$this, 'getAcroformStrings']),
            new TwigFunction('nowo_pdf_signable_acroform_editor_config', [$this, 'getAcroformEditorConfig']),
        ];
    }

    /**
     * Returns all AcroForm editor strings translated for the current locale.
     *
     * @return array<string, string>
     */
    public function getAcroformStrings(): array
    {
        $domain = 'nowo_pdf_signable';
        $strings = [];
        foreach (self::ACROFORM_STRING_KEYS as $key) {
            $strings[$key] = $this->translator->trans('acroform_editor.'.$key, [], $domain);
        }

        return $strings;
    }

    /**
     * Returns AcroForm editor config from bundle parameters (field_name_mode, field_name_choices, etc.).
     *
     * @return array{label_mode: string, label_choices: array, label_other_text: string, field_name_mode: string, field_name_choices: array, field_name_other_text: string, show_field_rect: bool, font_sizes: array, font_families: array, min_field_width: float, min_field_height: float}
     */
    public function getAcroformEditorConfig(): array
    {
        return [
            'label_mode' => $this->labelMode,
            'label_choices' => $this->labelChoices,
            'label_other_text' => $this->labelOtherText,
            'field_name_mode' => $this->fieldNameMode,
            'field_name_choices' => $this->fieldNameChoices,
            'field_name_other_text' => $this->fieldNameOtherText,
            'show_field_rect' => $this->showFieldRect,
            'font_sizes' => $this->fontSizes,
            'font_families' => $this->fontFamilies,
            'min_field_width' => $this->minFieldWidth,
            'min_field_height' => $this->minFieldHeight,
        ];
    }

    /**
     * Returns true only the first time per request; then false.
     *
     * Use in the form theme to output the CSS link and scripts only once.
     *
     * @return bool True if assets should be included (first call in this request), false otherwise
     */
    public function shouldIncludeAssets(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return true;
        }
        if ($request->attributes->get(self::REQUEST_ATTR)) {
            return false;
        }
        $request->attributes->set(self::REQUEST_ATTR, true);

        return true;
    }
}
