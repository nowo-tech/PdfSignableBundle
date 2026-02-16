<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\AcroForm;

/**
 * Model for the AcroForm field edit form (modal).
 *
 * Used as the data class for AcroFormFieldEditType. The form is rendered once (empty or with defaults)
 * and filled by JavaScript when the user opens the edit modal; values are read by JS on save.
 * This DTO defines the structure and can be used for validation or server-side handling if needed.
 */
final class AcroFormFieldEdit
{
    public function __construct(
        public string $fieldId = '',
        public ?int $page = null,
        public string $label = '',
        public string $controlType = 'text',
        /** Rect as string "llx, lly, urx, ury" or "x, y, width, height" for the form input. */
        public string $rect = '',
        /** PDF field name (/T); used for matching and when creating new fields. */
        public string $fieldName = '',
        /** Options for select/choice: one per line, optional "value|label". Form may submit null when empty. */
        public ?string $options = null,
        public string $defaultValue = '',
        public bool $defaultChecked = false,
        public string $checkboxValueOn = '1',
        public string $checkboxValueOff = '0',
        public string $checkboxIcon = 'check',
        public ?int $fontSize = null,
        public string $fontFamily = 'sans-serif',
        public bool $fontAutoSize = false,
        /** Max length for text/textarea (/MaxLen). */
        public ?int $maxLen = null,
        /** When true, patch removes the widget from the PDF. */
        public bool $hidden = false,
        /** When true and patch not matched, create a new Widget at rect (Python apply_acroform_patches). */
        public bool $createIfMissing = false,
    ) {
    }

    /**
     * Create from overlay/patch data (e.g. when populating the form from draft overrides).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rect = null;
        if (isset($data['rect']) && \is_array($data['rect'])) {
            $r = $data['rect'];
            if (\count($r) >= 4) {
                $rect = sprintf('%.1f, %.1f, %.1f, %.1f', (float) $r[0], (float) $r[1], (float) $r[2], (float) $r[3]);
            }
        }
        $opts = '';
        if (isset($data['options']) && \is_array($data['options'])) {
            $lines = [];
            foreach ($data['options'] as $o) {
                if (\is_array($o) && isset($o['value'])) {
                    $lines[] = isset($o['label']) && $o['label'] !== $o['value'] ? $o['value'] . '|' . $o['label'] : $o['value'];
                } elseif (\is_string($o)) {
                    $lines[] = $o;
                }
            }
            $opts = implode("\n", $lines);
        }

        return new self(
            (string) ($data['fieldId'] ?? $data['field_id'] ?? ''),
            isset($data['page']) ? (int) $data['page'] : null,
            (string) ($data['label'] ?? ''),
            (string) ($data['controlType'] ?? $data['control_type'] ?? 'text'),
            $rect ?? '',
            (string) ($data['fieldName'] ?? $data['field_name'] ?? ''),
            $opts,
            (string) ($data['defaultValue'] ?? $data['default_value'] ?? ''),
            (bool) ($data['defaultChecked'] ?? false),
            (string) ($data['checkboxValueOn'] ?? '1'),
            (string) ($data['checkboxValueOff'] ?? '0'),
            (string) ($data['checkboxIcon'] ?? 'check'),
            isset($data['fontSize']) ? (int) $data['fontSize'] : null,
            (string) ($data['fontFamily'] ?? 'sans-serif'),
            (bool) ($data['fontAutoSize'] ?? false),
            isset($data['maxLen']) ? (int) $data['maxLen'] : null,
            (bool) ($data['hidden'] ?? false),
            (bool) ($data['createIfMissing'] ?? false),
        );
    }
}
