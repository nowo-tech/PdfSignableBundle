<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\AcroForm;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldPatch;
use PHPUnit\Framework\TestCase;

final class AcroFormFieldPatchTest extends TestCase
{
    public function testFromArrayRequiresFieldId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fieldId');
        AcroFormFieldPatch::fromArray([]);
    }

    public function testFromArrayRejectsEmptyFieldId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fieldId');
        AcroFormFieldPatch::fromArray(['fieldId' => '']);
    }

    public function testFromArrayAcceptsFieldIdOrFieldIdSnake(): void
    {
        $p = AcroFormFieldPatch::fromArray(['fieldId' => 'f1']);
        self::assertSame('f1', $p->fieldId);

        $p2 = AcroFormFieldPatch::fromArray(['field_id' => 'f2']);
        self::assertSame('f2', $p2->fieldId);
    }

    public function testFromArrayAndToArrayRoundtrip(): void
    {
        $data = [
            'fieldId' => 'tx_1',
            'rect' => [0, 0, 100, 20],
            'defaultValue' => 'Hello',
            'label' => 'Name',
            'controlType' => 'text',
            'page' => 1,
        ];
        $p = AcroFormFieldPatch::fromArray($data);
        self::assertSame('tx_1', $p->fieldId);
        self::assertSame([0, 0, 100, 20], $p->rect);
        self::assertSame('Hello', $p->defaultValue);
        self::assertSame('Name', $p->label);
        self::assertSame('text', $p->controlType);
        self::assertSame(1, $p->page);

        $out = $p->toArray();
        self::assertSame($data['fieldId'], $out['fieldId']);
        self::assertSame($data['rect'], $out['rect']);
        self::assertSame($data['defaultValue'], $out['defaultValue']);
        self::assertSame($data['label'], $out['label']);
        self::assertSame($data['controlType'], $out['controlType']);
        self::assertSame($data['page'], $out['page']);
    }

    public function testHiddenOverride(): void
    {
        $p = AcroFormFieldPatch::fromArray(['fieldId' => 'f1', 'hidden' => true]);
        self::assertTrue($p->hidden);
        self::assertSame(['fieldId' => 'f1', 'hidden' => true], $p->toArray());
    }

    public function testToArrayWithOptions(): void
    {
        $p = AcroFormFieldPatch::fromArray([
            'fieldId' => 'f1',
            'options' => [['value' => 'a'], ['value' => 'b', 'label' => 'B']],
        ]);
        self::assertSame([['value' => 'a'], ['value' => 'b', 'label' => 'B']], $p->options);
        $out = $p->toArray();
        self::assertArrayHasKey('options', $out);
        self::assertSame([['value' => 'a'], ['value' => 'b', 'label' => 'B']], $out['options']);
    }

    public function testFromArrayWithDefaultValueSnakeCase(): void
    {
        $p = AcroFormFieldPatch::fromArray(['field_id' => 'f1', 'default_value' => 'x']);
        self::assertSame('x', $p->defaultValue);
    }

    public function testFromArrayWithFieldNameMaxLenFontSizeFontFamily(): void
    {
        $p = AcroFormFieldPatch::fromArray([
            'fieldId' => 'f1',
            'fieldName' => 'CustomerName',
            'maxLen' => 50,
            'fontSize' => 10.5,
            'fontFamily' => 'Times New Roman',
        ]);
        self::assertSame('CustomerName', $p->fieldName);
        self::assertSame(50, $p->maxLen);
        self::assertSame(10.5, $p->fontSize);
        self::assertSame('Times New Roman', $p->fontFamily);
        $out = $p->toArray();
        self::assertArrayHasKey('fieldName', $out);
        self::assertArrayHasKey('maxLen', $out);
        self::assertArrayHasKey('fontSize', $out);
        self::assertArrayHasKey('fontFamily', $out);
        self::assertSame('Times New Roman', $out['fontFamily']);
    }

    public function testFromArraySnakeCaseFieldTypeControlType(): void
    {
        $p = AcroFormFieldPatch::fromArray(['field_id' => 'f1', 'field_type' => 'Tx', 'control_type' => 'text']);
        self::assertSame('Tx', $p->fieldType);
        self::assertSame('text', $p->controlType);
    }

    public function testToArrayOmitsNullOptionalFields(): void
    {
        $p = AcroFormFieldPatch::fromArray(['fieldId' => 'f1']);
        $out = $p->toArray();
        self::assertSame(['fieldId' => 'f1'], $out);
    }

    public function testConstructorWithAllParams(): void
    {
        $p = new AcroFormFieldPatch(
            'f1',
            [0, 0, 100, 20],
            'default',
            'Tx',
            'Label',
            'text',
            [['value' => 'a']],
            1,
            false,
            'FieldName',
            50,
            10.5,
            'Helvetica',
        );
        self::assertSame('f1', $p->fieldId);
        self::assertSame([0, 0, 100, 20], $p->rect);
        self::assertSame('default', $p->defaultValue);
        self::assertSame('Tx', $p->fieldType);
        self::assertSame('Label', $p->label);
        self::assertSame('text', $p->controlType);
        self::assertSame([['value' => 'a']], $p->options);
        self::assertSame(1, $p->page);
        self::assertFalse($p->hidden);
        self::assertSame('FieldName', $p->fieldName);
        self::assertSame(50, $p->maxLen);
        self::assertSame(10.5, $p->fontSize);
        self::assertSame('Helvetica', $p->fontFamily);
        $out = $p->toArray();
        self::assertArrayHasKey('rect', $out);
        self::assertArrayHasKey('defaultValue', $out);
    }

    public function testToArrayOmitsEmptyFontFamily(): void
    {
        $p = AcroFormFieldPatch::fromArray(['fieldId' => 'f1', 'fontFamily' => '']);
        $out = $p->toArray();
        self::assertArrayNotHasKey('fontFamily', $out);
    }
}
