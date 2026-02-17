<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\AcroForm;

use InvalidArgumentException;

use function is_array;

/**
 * DTO for a single AcroForm field patch (frontend to backend).
 *
 * Used when saving overrides and when applying patches to a PDF. The apply script matches
 * patches by fieldId (e.g. "p1-0"), by fieldName (PDF /T, e.g. "NOMBRE Y APELLIDOS"), or by
 * fieldId when it equals the annotation id from the extractor/PDF.js.
 */
final class AcroFormFieldPatch
{
    public function __construct(
        /** Identifier: PDF.js annotation id or PDF field name */
        public readonly string $fieldId,
        /** Rect in PDF points [llx, lly, urx, ury] — optional; for move/resize */
        public readonly ?array $rect = null,
        /** Default value (PDF /DV or our override) */
        public readonly ?string $defaultValue = null,
        /** PDF field type: Tx, Btn, Ch (optional; type change is advanced) */
        public readonly ?string $fieldType = null,
        /** Display label (overrides only) */
        public readonly ?string $label = null,
        /** UI control type: text, textarea, checkbox, select (overrides only) */
        public readonly ?string $controlType = null,
        /** Options for select (overrides only); array of { value, label? } */
        public readonly ?array $options = null,
        /** Page 1-based (for rect or field identification) */
        public readonly ?int $page = null,
        /** When true, hide field in our view (overrides only; apply may exclude from PDF) */
        public readonly ?bool $hidden = null,
        /** PDF field name (/T) — optional; for apply script */
        public readonly ?string $fieldName = null,
        /** Max length for text fields (/MaxLen) — optional; for apply script */
        public readonly ?int $maxLen = null,
        /** Font size in points — optional; for apply script /DA */
        public readonly ?float $fontSize = null,
        /** Font family name (e.g. Helvetica, Arial) — optional; for apply script /DA */
        public readonly ?string $fontFamily = null,
    ) {
    }

    /**
     * Build a patch from a request array (e.g. from JSON body).
     *
     * Supports both camelCase (fieldId, defaultValue, …) and snake_case (field_id, default_value, …) keys.
     *
     * @param array<string, mixed> $data Associative array with at least fieldId (or field_id)
     *
     * @throws InvalidArgumentException If fieldId is missing or empty
     */
    public static function fromArray(array $data): self
    {
        $fieldId = $data['fieldId'] ?? $data['field_id'] ?? '';
        if ($fieldId === '') {
            throw new InvalidArgumentException('fieldId is required.');
        }

        return new self(
            $fieldId,
            isset($data['rect']) && is_array($data['rect']) ? $data['rect'] : null,
            isset($data['defaultValue']) ? (string) $data['defaultValue'] : (isset($data['default_value']) ? (string) $data['default_value'] : null),
            isset($data['fieldType']) ? (string) $data['fieldType'] : (isset($data['field_type']) ? (string) $data['field_type'] : null),
            isset($data['label']) ? (string) $data['label'] : null,
            isset($data['controlType']) ? (string) $data['controlType'] : (isset($data['control_type']) ? (string) $data['control_type'] : null),
            isset($data['options']) && is_array($data['options']) ? $data['options'] : null,
            isset($data['page']) ? (int) $data['page'] : null,
            isset($data['hidden']) ? (bool) $data['hidden'] : null,
            isset($data['fieldName']) ? (string) $data['fieldName'] : (isset($data['field_name']) ? (string) $data['field_name'] : null),
            isset($data['maxLen']) ? (int) $data['maxLen'] : (isset($data['max_len']) ? (int) $data['max_len'] : null),
            isset($data['fontSize']) ? (float) $data['fontSize'] : (isset($data['font_size']) ? (float) $data['font_size'] : null),
            isset($data['fontFamily']) ? (string) $data['fontFamily'] : (isset($data['font_family']) ? (string) $data['font_family'] : null),
        );
    }

    /**
     * Export patch to an array for JSON (e.g. for the apply script).
     *
     * Only includes keys that are set (non-null). fieldId is always present.
     *
     * @return array<string, mixed> Associative array suitable for JSON encoding
     */
    public function toArray(): array
    {
        $out = ['fieldId' => $this->fieldId];
        if ($this->rect !== null) {
            $out['rect'] = $this->rect;
        }
        if ($this->defaultValue !== null) {
            $out['defaultValue'] = $this->defaultValue;
        }
        if ($this->fieldType !== null) {
            $out['fieldType'] = $this->fieldType;
        }
        if ($this->label !== null) {
            $out['label'] = $this->label;
        }
        if ($this->controlType !== null) {
            $out['controlType'] = $this->controlType;
        }
        if ($this->options !== null) {
            $out['options'] = $this->options;
        }
        if ($this->page !== null) {
            $out['page'] = $this->page;
        }
        if ($this->hidden !== null) {
            $out['hidden'] = $this->hidden;
        }
        if ($this->fieldName !== null) {
            $out['fieldName'] = $this->fieldName;
        }
        if ($this->maxLen !== null) {
            $out['maxLen'] = $this->maxLen;
        }
        if ($this->fontSize !== null) {
            $out['fontSize'] = $this->fontSize;
        }
        if ($this->fontFamily !== null && $this->fontFamily !== '') {
            $out['fontFamily'] = $this->fontFamily;
        }

        return $out;
    }
}
