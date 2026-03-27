<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\AcroForm;

use Nowo\PdfSignableBundle\AcroForm\AcroFormOverrides;
use PHPUnit\Framework\TestCase;

final class AcroFormOverridesTest extends TestCase
{
    public function testFromArrayAndToArrayRoundtrip(): void
    {
        $data = [
            'overrides' => [
                'f1' => ['defaultValue' => 'x', 'label' => 'F1'],
                'f2' => ['rect' => [0, 0, 50, 20]],
            ],
            'document_key' => 'doc-123',
        ];
        $o = AcroFormOverrides::fromArray($data);
        self::assertSame($data['overrides'], $o->overrides);
        self::assertSame('doc-123', $o->documentKey);

        $out = $o->toArray();
        self::assertSame($data['overrides'], $out['overrides']);
        self::assertSame('doc-123', $out['document_key']);
    }

    public function testFromArrayEmptyDocumentKeyBecomesNull(): void
    {
        $o = AcroFormOverrides::fromArray(['overrides' => [], 'document_key' => '']);
        self::assertNull($o->documentKey);
    }

    public function testConstructorWithFields(): void
    {
        $fields = [
            ['id' => 'f1', 'rect' => [0, 0, 100, 20], 'fieldType' => 'text'],
        ];
        $o = new AcroFormOverrides(['f1' => ['label' => 'x']], 'doc1', $fields);
        self::assertSame($fields, $o->fields);
    }

    public function testToArrayIncludesFieldsWhenNonEmpty(): void
    {
        $fields = [['id' => 'f1', 'rect' => [0, 0, 100, 20]]];
        $o      = new AcroFormOverrides([], 'doc1', $fields);
        $out    = $o->toArray();
        self::assertArrayHasKey('fields', $out);
        self::assertSame($fields, $out['fields']);
    }

    public function testToArrayExcludesFieldsWhenNull(): void
    {
        $o   = new AcroFormOverrides([], 'doc1', null);
        $out = $o->toArray();
        self::assertArrayNotHasKey('fields', $out);
    }

    public function testFromArrayWithFields(): void
    {
        $data = [
            'overrides'    => ['f1' => ['defaultValue' => 'x']],
            'document_key' => 'doc1',
            'fields'       => [['id' => 'f1', 'rect' => [0, 0, 50, 20]]],
        ];
        $o = AcroFormOverrides::fromArray($data);
        self::assertSame($data['fields'], $o->fields);
        $out = $o->toArray();
        self::assertArrayHasKey('fields', $out);
    }

    public function testFromArrayWithNonArrayOverridesTreatsAsEmpty(): void
    {
        $o = AcroFormOverrides::fromArray(['overrides' => 'invalid', 'document_key' => 'd1']);
        self::assertSame([], $o->overrides);
    }

    public function testFromArrayWithNonArrayFieldsTreatsAsNull(): void
    {
        $o = AcroFormOverrides::fromArray(['overrides' => [], 'document_key' => 'd1', 'fields' => 'invalid']);
        self::assertNull($o->fields);
    }

    public function testToArrayExcludesFieldsWhenEmptyArray(): void
    {
        $o   = new AcroFormOverrides([], 'doc1', []);
        $out = $o->toArray();
        self::assertArrayNotHasKey('fields', $out);
    }

    public function testFromArrayWithoutDocumentKeyKeyUsesNull(): void
    {
        $o = AcroFormOverrides::fromArray(['overrides' => ['f1' => []]]);
        self::assertNull($o->documentKey);
        self::assertSame(['f1' => []], $o->overrides);
    }
}
