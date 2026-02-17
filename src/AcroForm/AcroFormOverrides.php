<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\AcroForm;

use function is_array;

/**
 * DTO for full AcroForm overrides (storage).
 *
 * Map of fieldId → override data (defaultValue, label, controlType, rect, options, page).
 * Optionally stores the list of fields from the PDF (id, rect, fieldType, page, value) so GET can return fields + config.
 */
final class AcroFormOverrides
{
    /**
     * @param array<string, array<string, mixed>> $overrides Map fieldId → override data
     * @param array<int, array<string, mixed>>|null $fields Optional list of PDF field definitions (id, rect, fieldType, page, value?)
     */
    public function __construct(
        public readonly array $overrides,
        public readonly ?string $documentKey = null,
        public readonly ?array $fields = null,
    ) {
    }

    /**
     * @param array{overrides?: array<string, array>, document_key?: string|null, fields?: array} $data
     */
    public static function fromArray(array $data): self
    {
        $overrides = $data['overrides'] ?? [];
        if (!is_array($overrides)) {
            $overrides = [];
        }
        $documentKey = isset($data['document_key']) ? (string) $data['document_key'] : null;
        if ($documentKey === '') {
            $documentKey = null;
        }
        $fields = $data['fields'] ?? null;
        if ($fields !== null && !is_array($fields)) {
            $fields = null;
        }

        return new self($overrides, $documentKey, $fields);
    }

    /**
     * @return array{overrides: array<string, array>, document_key?: string|null, fields?: array}
     */
    public function toArray(): array
    {
        $out = ['overrides' => $this->overrides];
        if ($this->documentKey !== null) {
            $out['document_key'] = $this->documentKey;
        }
        if ($this->fields !== null && $this->fields !== []) {
            $out['fields'] = $this->fields;
        }

        return $out;
    }
}
